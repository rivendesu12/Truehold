<?php

namespace App\Support;

/**
 * Minimal .xlsx reader: enough to pull cell values out of one worksheet.
 *
 * An .xlsx is a zip of XML, and we only need plain values from a single tab, so
 * this avoids pulling in PhpSpreadsheet (~10MB) for one lookup table. Handles
 * shared strings, inline strings and numbers.
 */
class XlsxReader
{
    /**
     * Rows of the named sheet as arrays keyed by 1-based column number.
     *
     * @return array<int, array<int, string>>
     */
    public static function rows(string $path, string $sheetName): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        try {
            $sheetPath = self::resolveSheetPath($zip, $sheetName);
            if (! $sheetPath) {
                return [];
            }

            $shared = self::sharedStrings($zip);
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                return [];
            }

            $doc = @simplexml_load_string($xml);
            if (! $doc) {
                return [];
            }

            $out = [];

            foreach ($doc->sheetData->row as $row) {
                $cells = [];
                $rowNumber = (int) $row['r'];
                foreach ($row->c as $c) {
                    $col = self::columnNumber((string) $c['r']);
                    $type = (string) $c['t'];

                    if ($type === 's') {
                        $value = $shared[(int) $c->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = trim((string) $c->is->t);
                    } else {
                        $value = trim((string) $c->v);
                    }

                    if ($value !== '') {
                        $cells[$col] = $value;
                    }
                }
                if ($cells) {
                    // The sheet's own row number, to pair a row with its links.
                    $out[] = $cells + ['_row' => $rowNumber];
                }
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    /**
     * Cell links of the named sheet, keyed "row:col" (1-based): the target
     * of each hyperlink, which rows() cannot see.
     *
     * @return array<string, string>
     */
    public static function hyperlinks(string $path, string $sheetName): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        try {
            $sheetPath = self::resolveSheetPath($zip, $sheetName);
            $xml = $sheetPath ? $zip->getFromName($sheetPath) : false;
            $rels = $sheetPath ? $zip->getFromName(dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels') : false;
            if ($xml === false || $rels === false) {
                return [];
            }

            $targets = [];
            foreach ((@simplexml_load_string($rels) ?: [])->Relationship ?? [] as $rel) {
                $targets[(string) $rel['Id']] = html_entity_decode((string) $rel['Target']);
            }

            $out = [];
            // Attribute order varies between writers, so read each tag's own.
            preg_match_all('/<hyperlink\b[^>]*>/', $xml, $tags);
            foreach ($tags[0] as $tag) {
                if (preg_match('/\bref="([A-Z]+)(\d+)/', $tag, $ref) && preg_match('/\br:id="([^"]+)"/', $tag, $id)
                    && isset($targets[$id[1]])) {
                    $out[$ref[2] . ':' . self::columnNumber($ref[1])] = $targets[$id[1]];
                }
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    protected static function resolveSheetPath(\ZipArchive $zip, string $sheetName): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if ($workbook === false) {
            return null;
        }

        $wb = @simplexml_load_string($workbook);
        if (! $wb) {
            return null;
        }

        $targets = [];
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels !== false && ($relsXml = @simplexml_load_string($rels))) {
            foreach ($relsXml->Relationship as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
        }

        $wanted = strtolower(trim($sheetName));

        foreach ($wb->sheets->sheet as $sheet) {
            if (strtolower(trim((string) $sheet['name'])) !== $wanted) {
                continue;
            }
            foreach ($sheet->attributes('r', true) as $key => $value) {
                if ($key === 'id' && isset($targets[(string) $value])) {
                    $target = ltrim($targets[(string) $value], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    protected static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = @simplexml_load_string($xml);
        if (! $doc) {
            return [];
        }

        $out = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $out[] = trim((string) $si->t);
                continue;
            }
            $text = '';
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
            $out[] = trim($text);
        }

        return $out;
    }

    /** "B12" -> 2 */
    protected static function columnNumber(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return 0;
        }

        $n = 0;
        foreach (str_split($m[1]) as $char) {
            $n = $n * 26 + (ord($char) - 64);
        }

        return $n;
    }
}
