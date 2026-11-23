<?php

namespace App\Helpers;

class PdfHelper
{
    /**
     * Unicode Arabic Presentation Forms-B map:
     * char => [isolated, final, initial, medial]
     *
     * @var array<string, array{0: string, 1: ?string, 2: ?string, 3: ?string}>
     */
    private const ARABIC_FORMS = [
        "\u{0621}" => ["\u{FE80}", null, null, null],
        "\u{0622}" => ["\u{FE81}", "\u{FE82}", null, null],
        "\u{0623}" => ["\u{FE83}", "\u{FE84}", null, null],
        "\u{0624}" => ["\u{FE85}", "\u{FE86}", null, null],
        "\u{0625}" => ["\u{FE87}", "\u{FE88}", null, null],
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}", null, null],
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        "\u{0629}" => ["\u{FE93}", "\u{FE94}", null, null],
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}", null, null],
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}", null, null],
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}", null, null],
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}", null, null],
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        "\u{0640}" => ["\u{0640}", "\u{0640}", "\u{0640}", "\u{0640}"],
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}", null, null],
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}", null, null],
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
    ];

    /**
     * Lam-Alef ligatures: second char => [isolated, final]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const LAM_ALEF_LIGATURES = [
        "\u{0622}" => ["\u{FEF5}", "\u{FEF6}"],
        "\u{0623}" => ["\u{FEF7}", "\u{FEF8}"],
        "\u{0625}" => ["\u{FEF9}", "\u{FEFA}"],
        "\u{0627}" => ["\u{FEFB}", "\u{FEFC}"],
    ];

    /**
     * Fix RTL text (Hebrew & Arabic with contextual glyph shaping) for DomPDF.
     */
    public static function fixRtl(?string $text): string
    {
        if (! $text) {
            return '';
        }

        // If it doesn't contain RTL characters, return as is
        if (! preg_match('/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }

        // Split by HTML tags to preserve them
        $parts = preg_split('/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';

        foreach ($parts as $part) {
            if (str_starts_with($part, '<') && str_ends_with($part, '>')) {
                $result .= $part;
            } else {
                $result .= self::reverseText($part);
            }
        }

        return $result;
    }

    private static function reverseText(string $text): string
    {
        $words = explode(' ', $text);
        $fixedWords = [];

        foreach ($words as $word) {
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $word)) {
                $shaped = self::shapeArabicWord($word);
                preg_match_all('/./us', $shaped, $ar);
                $fixedWords[] = implode('', array_reverse($ar[0]));
            } elseif (preg_match('/[\x{0590}-\x{05FF}]/u', $word)) {
                preg_match_all('/./us', $word, $ar);
                $fixedWords[] = implode('', array_reverse($ar[0]));
            } else {
                $fixedWords[] = $word;
            }
        }

        return implode(' ', array_reverse($fixedWords));
    }

    private static function shapeArabicWord(string $word): string
    {
        // Strip Arabic combining tashkeel diacritics so DomPDF renders clean joined glyphs
        $clean = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $word) ?? $word;
        preg_match_all('/./us', $clean, $matches);
        $chars = $matches[0];
        $count = count($chars);
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];

            if (! isset(self::ARABIC_FORMS[$ch])) {
                $output .= $ch;
                continue;
            }

            $prev = $i > 0 ? $chars[$i - 1] : null;
            $next = $i + 1 < $count ? $chars[$i + 1] : null;

            $prevConnectsAfter = $prev !== null
                && isset(self::ARABIC_FORMS[$prev])
                && self::ARABIC_FORMS[$prev][2] !== null;

            // Handle Lam-Alef ligatures
            if ($ch === "\u{0644}" && $next !== null && isset(self::LAM_ALEF_LIGATURES[$next])) {
                $output .= $prevConnectsAfter
                    ? self::LAM_ALEF_LIGATURES[$next][1]
                    : self::LAM_ALEF_LIGATURES[$next][0];
                $i++;
                continue;
            }

            $nextConnectsBefore = $next !== null
                && isset(self::ARABIC_FORMS[$next])
                && self::ARABIC_FORMS[$next][1] !== null;

            $forms = self::ARABIC_FORMS[$ch];
            $canConnectNext = $forms[2] !== null && $nextConnectsBefore;
            $canConnectPrev = $forms[1] !== null && $prevConnectsAfter;

            if ($canConnectPrev && $canConnectNext && $forms[3] !== null) {
                $output .= $forms[3]; // medial
            } elseif ($canConnectPrev && $forms[1] !== null) {
                $output .= $forms[1]; // final
            } elseif ($canConnectNext && $forms[2] !== null) {
                $output .= $forms[2]; // initial
            } else {
                $output .= $forms[0]; // isolated
            }
        }

        return $output;
    }
}
