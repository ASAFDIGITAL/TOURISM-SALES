<?php

namespace App\Services;

use App\Models\Hotel;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class HotelSpreadsheetService
{
    /**
     * Canonical column order used in the template and positional fallback.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'name',
        'destination',
        'stars',
        'phone',
        'email',
        'address',
        'default_supplier',
        'notes',
    ];

    /**
     * Localized column headers for Excel/CSV templates.
     *
     * @return list<string>
     */
    public function getLocalizedHeaders(string $locale = 'he'): array
    {
        return match ($locale) {
            'ar' => [
                'اسم الفندق',
                'الوجهة / المدينة',
                'النجوم (1-5)',
                'الهاتف',
                'البريد الإلكتروني',
                'العنوان',
                'المزوّد الافتراضي',
                'ملاحظات',
            ],
            'en' => [
                'Hotel Name',
                'Destination / City',
                'Stars (1-5)',
                'Phone',
                'Email',
                'Address',
                'Default Supplier',
                'Notes',
            ],
            default => [
                'שם המלון',
                'יעד / עיר',
                'כוכבים (1-5)',
                'טלפון',
                'אימייל',
                'כתובת',
                'ספק ברירת מחדל',
                'הערות',
            ],
        };
    }

    /**
     * Sample rows included in the downloadable template so agencies see the expected format.
     *
     * @return list<list<string|int>>
     */
    public function getSampleRows(string $locale = 'he'): array
    {
        return match ($locale) {
            'ar' => [
                ['Hilton Athens', 'أثينا، اليونان', 5, '+30-210-7281000', 'reservations@hilton-athens.example', '46 Vassilissis Sofias Ave', 'Booking.com', 'يشمل الإفطار وتوصيل المطار'],
                ['Vienna Garden Hotel', 'فيينا، النمسا', 4, '+43-1-5550199', 'desk@viennagarden.example', 'Ringstrasse 12, Vienna', 'Direct Contract', 'كود الوكالة: VIP2026'],
            ],
            'en' => [
                ['Hilton Athens', 'Athens, Greece', 5, '+30-210-7281000', 'reservations@hilton-athens.example', '46 Vassilissis Sofias Ave', 'Booking.com', 'Breakfast & airport transfer included'],
                ['Vienna Garden Hotel', 'Vienna, Austria', 4, '+43-1-5550199', 'desk@viennagarden.example', 'Ringstrasse 12, Vienna', 'Direct Contract', 'Agency code: VIP2026'],
            ],
            default => [
                ['Hilton Athens', 'אתונה, יוון', 5, '+30-210-7281000', 'reservations@hilton-athens.example', '46 Vassilissis Sofias Ave', 'Booking.com', 'כולל ארוחת בוקר והעברות'],
                ['Vienna Garden Hotel', 'וינה, אוסטריה', 4, '+43-1-5550199', 'desk@viennagarden.example', 'Ringstrasse 12, Vienna', 'Direct Contract', 'קוד סוכן: VIP2026'],
            ],
        };
    }

    /**
     * Generate a genuine styled .xlsx Excel workbook (UTF-8, RTL-aware) with zero external packages.
     */
    public function generateTemplateXlsx(string $locale = 'he'): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for XLSX export.');
        }

        $isRtl = $locale !== 'en';
        $sheetTitle = match ($locale) {
            'ar' => 'الفنادق',
            'en' => 'Hotels',
            default => 'בתי מלון',
        };

        $rows = array_merge([$this->getLocalizedHeaders($locale)], $this->getSampleRows($locale));

        $tmpFile = tempnam(sys_get_temp_dir(), 'hotels_tpl_');
        if ($tmpFile === false) {
            throw new RuntimeException('Could not create temporary file for XLSX template.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new RuntimeException('Could not open temporary XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml($sheetTitle));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildWorksheetXml($rows, $isRtl));
        $zip->close();

        $binary = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $binary === false ? '' : $binary;
    }

    /**
     * Fallback UTF-8 BOM CSV template.
     */
    public function generateTemplateCsv(string $locale = 'he'): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->getLocalizedHeaders($locale));
        foreach ($this->getSampleRows($locale) as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Import hotels from an uploaded .xlsx or .csv file into a specific tenant.
     *
     * @return array{created: int, updated: int, skipped: int, total_rows: int}
     */
    public function importFromPath(string $filePath, int $tenantId, bool $updateExisting = true): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Uploaded spreadsheet file could not be read.');
        }

        $rows = $this->isXlsxFile($filePath)
            ? $this->parseXlsx($filePath)
            : $this->parseCsv($filePath);

        if (empty($rows)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total_rows' => 0];
        }

        $headerMap = $this->detectHeaderMap($rows[0]);
        $startIndex = $headerMap !== null ? 1 : 0;

        if ($headerMap === null) {
            $headerMap = [];
            foreach (self::COLUMNS as $idx => $field) {
                $headerMap[$idx] = $field;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $totalRows = 0;

        for ($i = $startIndex; $i < count($rows); $i++) {
            $rawRow = $rows[$i];
            if (! is_array($rawRow)) {
                continue;
            }

            $mapped = [];
            foreach ($headerMap as $colIdx => $field) {
                $mapped[$field] = isset($rawRow[$colIdx]) ? trim((string) $rawRow[$colIdx]) : '';
            }

            $name = $mapped['name'] ?? '';
            if ($name === '') {
                continue;
            }

            $totalRows++;

            $payload = [
                'destination' => $this->nullIfBlank($mapped['destination'] ?? null, 255),
                'stars' => $this->parseStars($mapped['stars'] ?? null),
                'phone' => $this->nullIfBlank($mapped['phone'] ?? null, 64),
                'email' => $this->parseEmail($mapped['email'] ?? null),
                'address' => $this->nullIfBlank($mapped['address'] ?? null, 255),
                'default_supplier' => $this->nullIfBlank($mapped['default_supplier'] ?? null, 160),
                'notes' => $this->nullIfBlank($mapped['notes'] ?? null, 2000),
                'is_active' => true,
            ];

            $existing = Hotel::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing) {
                if (! $updateExisting) {
                    $skipped++;
                    continue;
                }

                $updates = ['is_active' => true];
                foreach (['destination', 'stars', 'phone', 'email', 'address', 'default_supplier', 'notes'] as $field) {
                    if ($payload[$field] !== null) {
                        $updates[$field] = $payload[$field];
                    }
                }

                $existing->update($updates);
                $updated++;
            } else {
                Hotel::withoutGlobalScopes()->create(array_merge([
                    'tenant_id' => $tenantId,
                    'name' => mb_substr($name, 0, 255),
                ], $payload));
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_rows' => $totalRows,
        ];
    }

    private function isXlsxFile(string $filePath): bool
    {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'xlsx') {
            return true;
        }

        $header = @file_get_contents($filePath, false, null, 0, 4);

        return $header === "PK\x03\x04";
    }

    /**
     * Parse the first worksheet of an .xlsx archive into a 2D array of cell strings.
     *
     * @return list<array<int, string>>
     */
    private function parseXlsx(string $filePath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required to read XLSX files.');
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Invalid Excel (.xlsx) file.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            $sheetXmlStr = $zip->getFromName($sheetPath);

            if ($sheetXmlStr === false) {
                return [];
            }

            $xml = @simplexml_load_string($sheetXmlStr);
            if (! $xml instanceof SimpleXMLElement) {
                return [];
            }

            $ns = $xml->getNamespaces(true);
            $mainNs = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $sheet = $xml->children($mainNs);

            if (! isset($sheet->sheetData)) {
                return [];
            }

            $rows = [];
            foreach ($sheet->sheetData->row as $rowNode) {
                $rowValues = [];
                $colCursor = 0;

                foreach ($rowNode->c as $cell) {
                    $attrs = $cell->attributes();
                    $ref = isset($attrs['r']) ? (string) $attrs['r'] : '';
                    $type = isset($attrs['t']) ? (string) $attrs['t'] : '';

                    if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $m)) {
                        $colIdx = $this->columnLettersToIndex($m[1]);
                    } else {
                        $colIdx = $colCursor;
                    }
                    $colCursor = $colIdx + 1;

                    $value = '';
                    if ($type === 's') {
                        $idx = (int) ((string) $cell->v);
                        $value = $sharedStrings[$idx] ?? '';
                    } elseif ($type === 'inlineStr') {
                        if (isset($cell->is->t)) {
                            $value = (string) $cell->is->t;
                        } elseif (isset($cell->is->r)) {
                            $parts = [];
                            foreach ($cell->is->r as $run) {
                                $parts[] = (string) $run->t;
                            }
                            $value = implode('', $parts);
                        }
                    } elseif (isset($cell->v)) {
                        $value = (string) $cell->v;
                    }

                    $rowValues[$colIdx] = trim($value);
                }

                if (! empty(array_filter($rowValues, fn ($v) => $v !== ''))) {
                    $rows[] = $rowValues;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xmlStr = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlStr === false) {
            return [];
        }

        $xml = @simplexml_load_string($xmlStr);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $ns = $xml->getNamespaces(true);
        $mainNs = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $root = $xml->children($mainNs);

        $strings = [];
        foreach ($root->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                $parts = [];
                foreach ($si->r as $run) {
                    $parts[] = (string) $run->t;
                }
                $strings[] = implode('', $parts);
            }
        }

        return $strings;
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): string
    {
        $workbookStr = $zip->getFromName('xl/workbook.xml');
        $relsStr = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookStr !== false && $relsStr !== false) {
            $wb = @simplexml_load_string($workbookStr);
            $rels = @simplexml_load_string($relsStr);

            if ($wb instanceof SimpleXMLElement && $rels instanceof SimpleXMLElement) {
                $wbNs = $wb->getNamespaces(true);
                $mainNs = $wbNs[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                $rNs = $wbNs['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

                $firstSheet = $wb->children($mainNs)->sheets?->sheet[0] ?? null;
                if ($firstSheet) {
                    $relId = (string) ($firstSheet->attributes($rNs)['id'] ?? '');
                    foreach ($rels->Relationship as $rel) {
                        if ((string) $rel['Id'] === $relId) {
                            $target = ltrim((string) $rel['Target'], '/');

                            return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                        }
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return list<array<int, string>>
     */
    private function parseCsv(string $filePath): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        // Strip UTF-8 BOM if present
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $firstLine = strtok($raw, "\r\n") ?: '';
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $cleaned = array_map(fn ($cell) => trim((string) $cell), $row);
            if (! empty(array_filter($cleaned, fn ($v) => $v !== ''))) {
                $rows[] = $cleaned;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Detect if the first row contains known Hebrew/Arabic/English headers.
     *
     * @param  array<int, string>  $row
     * @return array<int, string>|null
     */
    private function detectHeaderMap(array $row): ?array
    {
        $aliases = [
            'name' => ['name', 'hotel', 'hotel name', 'hotel_name', 'שם', 'שם המלון', 'שם מלון', 'מלון', 'اسم الفندق', 'الفندق', 'الاسم'],
            'destination' => ['destination', 'city', 'location', 'destination / city', 'יעד', 'עיר', 'יעד / עיר', 'מיקום', 'الوجهة', 'المدينة', 'الوجهة / المدينة'],
            'stars' => ['stars', 'star', 'rating', 'stars (1-5)', 'כוכבים', 'דירוג', 'דירוג כוכבים', 'כוכבים (1-5)', 'النجوم', 'تصنيف النجوم', 'النجوم (1-5)', 'نجوم'],
            'phone' => ['phone', 'tel', 'telephone', 'mobile', 'טלפון', 'נייד', 'الهاتف', 'رقم الهاتف', 'جوال'],
            'email' => ['email', 'e-mail', 'mail', 'אימייל', 'דוא"ל', 'מייל', 'البريد الإلكتروني', 'البريد', 'ايميل'],
            'address' => ['address', 'street', 'כתובת', 'العنوان'],
            'default_supplier' => ['supplier', 'default supplier', 'default_supplier', 'ספק', 'ספק ברירת מחדל', 'שם ספק', 'المزود', 'المزوّد', 'المزود الافتراضي', 'المزوّد الافتراضي'],
            'notes' => ['notes', 'note', 'comments', 'remarks', 'הערות', 'הערה', 'ملاحظات', 'ملاحظة'],
        ];

        $map = [];
        foreach ($row as $colIdx => $cell) {
            $normalized = mb_strtolower(trim($cell));
            if ($normalized === '') {
                continue;
            }

            foreach ($aliases as $field => $candidates) {
                if (in_array($normalized, $candidates, true)) {
                    $map[$colIdx] = $field;
                    break;
                }
            }
        }

        return isset($map) && in_array('name', $map, true) ? $map : null;
    }

    private function parseStars(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);
        if (str_contains($trimmed, '★')) {
            $count = mb_substr_count($trimmed, '★');

            return max(1, min(5, $count));
        }

        if (preg_match('/([1-5])/', $trimmed, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseEmail(?string $raw): ?string
    {
        $val = $this->nullIfBlank($raw, 255);
        if ($val === null) {
            return null;
        }

        return filter_var($val, FILTER_VALIDATE_EMAIL) ? $val : null;
    }

    private function nullIfBlank(?string $raw, int $maxLength): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '-') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function indexToColumnLetters(int $index): string
    {
        $letters = '';
        $num = $index + 1;
        while ($num > 0) {
            $mod = ($num - 1) % 26;
            $letters = chr(ord('A') + $mod) . $letters;
            $num = intdiv($num - 1, 26);
        }

        return $letters;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function buildContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbookXml(string $sheetTitle): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetTitle) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><color rgb="FF0F172A"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function buildWorksheetXml(array $rows, bool $isRtl): string
    {
        $widths = [28, 24, 14, 18, 28, 28, 22, 34];
        $colsXml = '<cols>';
        foreach ($widths as $idx => $w) {
            $colNum = $idx + 1;
            $colsXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetDataXml = '<sheetData>';
        foreach ($rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $styleId = $rowIdx === 0 ? 1 : 0;
            $heightAttr = $rowIdx === 0 ? ' ht="26" customHeight="1"' : ' ht="20" customHeight="1"';
            $sheetDataXml .= '<row r="' . $rowNum . '"' . $heightAttr . '>';

            foreach ($row as $colIdx => $val) {
                $cellRef = $this->indexToColumnLetters($colIdx) . $rowNum;
                if (is_int($val)) {
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="' . $styleId . '"><v>' . $val . '</v></c>';
                } else {
                    $sheetDataXml .= '<c r="' . $cellRef . '" t="inlineStr" s="' . $styleId . '"><is><t>' . $this->xmlEscape((string) $val) . '</t></is></c>';
                }
            }

            $sheetDataXml .= '</row>';
        }
        $sheetDataXml .= '</sheetData>';

        $rtlAttr = $isRtl ? ' rightToLeft="1"' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView' . $rtlAttr . ' workbookViewId="0"/></sheetViews>'
            . $colsXml
            . $sheetDataXml
            . '</worksheet>';
    }
}
