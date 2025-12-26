<?php

namespace App\Helpers;

class PdfHelper
{
    /**
     * Fix RTL text for dompdf which doesn't support Bidi reordering.
     */
    public static function fixRtl(?string $text): string
    {
        if (!$text) return '';

        // If it doesn't contain RTL characters, return as is
        if (!preg_match('/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }

        // Split by HTML tags to preserve them
        $parts = preg_split('/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';

        foreach ($parts as $part) {
            if (str_starts_with($part, '<') && str_ends_with($part, '>')) {
                // It's a tag, keep it as is
                $result .= $part;
            } else {
                // It's text, fix RTL
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
            if (preg_match('/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}]/u', $word)) {
                preg_match_all('/./us', $word, $ar);
                $fixedWords[] = join('', array_reverse($ar[0]));
            } else {
                $fixedWords[] = $word;
            }
        }

        return join(' ', array_reverse($fixedWords));
    }
}
