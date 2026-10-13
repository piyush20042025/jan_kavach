<?php
namespace JanKavach\Libraries;

use JanKavach\Core\Logger;

/**
 * Dependency-free XLSX / CSV reader.
 *
 * Built on ZipArchive + SimpleXML so the application needs no Composer
 * packages.  It understands the parts of the format the scheme import
 * actually relies on:
 *
 *   - shared strings and inline strings
 *   - merged cells (the supplied workbook merges a scheme's columns down
 *     several rows when it carries more than one Beneficiary Type), which
 *     are expanded so every row of a merge carries the anchor value
 *   - sparse rows and skipped columns
 *   - date-formatted numbers, converted back to Y-m-d
 */
class XlsxReader
{
    private string $path;
    private ?\ZipArchive $zip = null;
    private array $sharedStrings = [];
    private array $sheets = [];          // name => internal path
    private array $dateStyles = [];      // style index => is a date format
    private array $merges = [];

    /** Formats that mean "this number is really a date". */
    private const DATE_FORMAT_IDS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36,
                                     45, 46, 47, 50, 57];

    public function __construct(string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Spreadsheet not found or not readable: ' . basename($path));
        }
        $this->path = $path;
    }

    public function isCsv(): bool
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'csv';
    }

    /** Sheet names in workbook order. */
    public function sheetNames(): array
    {
        if ($this->isCsv()) {
            return ['CSV'];
        }
        $this->open();
        return array_keys($this->sheets);
    }

    /**
     * Read a sheet into a plain array of rows (each row an array of strings).
     *
     * @param string|null $sheet     sheet name; null = first sheet
     * @param int         $maxRows   safety limit
     * @param bool        $expandMerges fill every cell of a merged range
     */
    public function rows(?string $sheet = null, int $maxRows = 20000, bool $expandMerges = true): array
    {
        if ($this->isCsv()) {
            return $this->readCsv($maxRows);
        }

        $this->open();

        $names = array_keys($this->sheets);
        $target = $sheet !== null && isset($this->sheets[$sheet]) ? $sheet : ($names[0] ?? null);
        if ($target === null) {
            throw new \RuntimeException('The workbook contains no readable sheets.');
        }

        $xml = $this->zip->getFromName($this->sheets[$target]);
        if ($xml === false) {
            throw new \RuntimeException('Could not read sheet "' . $target . '".');
        }

        $sheetXml = $this->parseXml($xml);
        $this->merges = $this->readMerges($sheetXml);

        $rows = [];
        $maxCol = 0;

        foreach ($sheetXml->sheetData->row ?? [] as $rowXml) {
            $rowIndex = (int)($rowXml['r'] ?? (count($rows) + 1));
            if ($rowIndex > $maxRows) {
                break;
            }
            $row = [];
            foreach ($rowXml->c ?? [] as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $colIndex = $ref !== '' ? $this->columnIndex($ref) : count($row);
                $row[$colIndex] = $this->cellValue($cell);
                $maxCol = max($maxCol, $colIndex);
            }
            $rows[$rowIndex] = $row;
        }

        // Normalise into a dense 0-based grid.
        $lastRow = $rows ? max(array_keys($rows)) : 0;
        $grid = [];
        for ($r = 1; $r <= $lastRow; $r++) {
            $line = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $line[$c] = $rows[$r][$c] ?? null;
            }
            $grid[$r] = $line;
        }

        if ($expandMerges) {
            $grid = $this->applyMerges($grid);
        }

        return array_values($grid);
    }

    /**
     * Rows keyed by the header row's labels.
     *
     * @param int $headerRow 1-based index of the header row
     */
    public function associative(?string $sheet = null, int $headerRow = 1, int $maxRows = 20000): array
    {
        $rows = $this->rows($sheet, $maxRows);
        if (count($rows) < $headerRow) {
            return [];
        }

        $headers = [];
        foreach ($rows[$headerRow - 1] as $i => $label) {
            $label = trim((string)$label);
            $headers[$i] = $label !== '' ? $label : ('Column ' . ($i + 1));
        }

        $out = [];
        foreach (array_slice($rows, $headerRow) as $offset => $row) {
            $record = [];
            foreach ($headers as $i => $label) {
                $record[$label] = $row[$i] ?? null;
            }
            $record['__row'] = $headerRow + $offset + 1;
            $out[] = $record;
        }
        return $out;
    }

    /** The header labels, for the column-mapping screen. */
    public function headers(?string $sheet = null, int $headerRow = 1): array
    {
        $rows = $this->rows($sheet, $headerRow + 1);
        if (count($rows) < $headerRow) {
            return [];
        }
        return array_map(fn($v) => trim((string)$v), $rows[$headerRow - 1]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function open(): void
    {
        if ($this->zip !== null) {
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new \RuntimeException('The file could not be opened as an Excel workbook. Please save it as .xlsx and try again.');
        }
        $this->zip = $zip;

        $this->readSharedStrings();
        $this->readStyles();
        $this->readWorkbook();
    }

    private function readWorkbook(): void
    {
        $workbookXml = $this->zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            throw new \RuntimeException('The workbook is missing its xl/workbook.xml part.');
        }
        $workbook = $this->parseXml($workbookXml);

        // sheetId -> file, resolved through the relationship ids.
        $relMap = [];
        $relsXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            foreach ($this->parseXml($relsXml)->Relationship ?? [] as $rel) {
                $target = (string)$rel['Target'];
                $target = ltrim(str_replace('/xl/', '', $target), '/');
                if (!str_starts_with($target, 'xl/')) {
                    $target = 'xl/' . $target;
                }
                $relMap[(string)$rel['Id']] = $target;
            }
        }

        $index = 1;
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $name = (string)$sheet['name'];
            $rid  = '';
            foreach ($sheet->attributes('r', true) as $key => $value) {
                if ($key === 'id') {
                    $rid = (string)$value;
                }
            }
            $file = $relMap[$rid] ?? ('xl/worksheets/sheet' . $index . '.xml');
            if ($this->zip->locateName($file) === false) {
                $file = 'xl/worksheets/sheet' . $index . '.xml';
            }
            $this->sheets[$name] = $file;
            $index++;
        }

        if ($this->sheets === []) {
            $this->sheets['Sheet1'] = 'xl/worksheets/sheet1.xml';
        }
    }

    private function readSharedStrings(): void
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        foreach ($this->parseXml($xml)->si ?? [] as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
                continue;
            }
            // Rich text: concatenate the runs.
            $text = '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string)$run->t;
            }
            $this->sharedStrings[] = $text;
        }
    }

    private function readStyles(): void
    {
        $xml = $this->zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $styles = $this->parseXml($xml);

        $customDateFormats = [];
        foreach ($styles->numFmts->numFmt ?? [] as $fmt) {
            $code = (string)$fmt['formatCode'];
            if (preg_match('/[dmyhs]/i', $code) && !preg_match('/^[#0.,%\s"]+$/', $code)) {
                $customDateFormats[(int)$fmt['numFmtId']] = true;
            }
        }

        $index = 0;
        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $numFmtId = (int)($xf['numFmtId'] ?? 0);
            $this->dateStyles[$index] = in_array($numFmtId, self::DATE_FORMAT_IDS, true)
                                        || isset($customDateFormats[$numFmtId]);
            $index++;
        }
    }

    private function readMerges(\SimpleXMLElement $sheetXml): array
    {
        $out = [];
        foreach ($sheetXml->mergeCells->mergeCell ?? [] as $merge) {
            $ref = (string)$merge['ref'];
            if (!str_contains($ref, ':')) {
                continue;
            }
            [$from, $to] = explode(':', $ref, 2);
            $out[] = [
                'r1' => $this->rowIndex($from), 'c1' => $this->columnIndex($from),
                'r2' => $this->rowIndex($to),   'c2' => $this->columnIndex($to),
            ];
        }
        return $out;
    }

    /**
     * Copy each merged range's anchor value into every cell of the range.
     * Without this a scheme spanning several rows loses its name, State and
     * theme on all but the first row.
     */
    private function applyMerges(array $grid): array
    {
        foreach ($this->merges as $m) {
            $value = $grid[$m['r1']][$m['c1']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            for ($r = $m['r1']; $r <= $m['r2']; $r++) {
                for ($c = $m['c1']; $c <= $m['c2']; $c++) {
                    if (isset($grid[$r])) {
                        $grid[$r][$c] = $value;
                    }
                }
            }
        }
        return $grid;
    }

    private function cellValue(\SimpleXMLElement $cell)
    {
        $type = (string)($cell['t'] ?? 'n');

        if ($type === 'inlineStr') {
            $text = '';
            if (isset($cell->is->t)) {
                $text = (string)$cell->is->t;
            } else {
                foreach ($cell->is->r ?? [] as $run) {
                    $text .= (string)$run->t;
                }
            }
            return $this->clean($text);
        }

        if (!isset($cell->v)) {
            return null;
        }
        $raw = (string)$cell->v;

        if ($type === 's') {
            $index = (int)$raw;
            return $this->clean($this->sharedStrings[$index] ?? '');
        }
        if ($type === 'b') {
            return $raw === '1' ? 'Yes' : 'No';
        }
        if ($type === 'e') {
            return null;   // formula error
        }
        if ($type === 'str') {
            return $this->clean($raw);
        }

        // Numeric: may actually be a date, depending on the style.
        $styleIndex = isset($cell['s']) ? (int)$cell['s'] : 0;
        if (!empty($this->dateStyles[$styleIndex]) && is_numeric($raw) && (float)$raw > 0) {
            return $this->excelDate((float)$raw);
        }
        return $this->clean($raw);
    }

    /** Excel serial date (1900 system) to Y-m-d. */
    private function excelDate(float $serial): string
    {
        // Excel wrongly treats 1900 as a leap year; offset 25569 handles the
        // 1970 epoch difference for every date after 1900-03-01.
        $timestamp = ($serial - 25569) * 86400;
        return gmdate('Y-m-d', (int)round($timestamp));
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = str_replace("\xC2\xA0", ' ', $value);    // non-breaking space
        return trim($value);
    }

    /** "C12" -> 2 (0-based column) */
    private function columnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/i', $ref, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0, $n = strlen($letters); $i < $n; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    /** "C12" -> 12 (1-based row) */
    private function rowIndex(string $ref): int
    {
        return preg_match('/(\d+)$/', $ref, $m) ? (int)$m[1] : 1;
    }

    private function parseXml(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        // XXE protection: no external entities, ever.
        $element = simplexml_load_string($xml, \SimpleXMLElement::class,
                                         LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($element === false) {
            throw new \RuntimeException('The workbook contains XML this reader could not parse.');
        }
        return $element;
    }

    private function readCsv(int $maxRows): array
    {
        $rows = [];
        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('The CSV file could not be opened.');
        }
        // Skip a UTF-8 BOM if present.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            $rows[] = array_map(fn($v) => $v === null ? null : $this->clean((string)$v), $row);
            if (count($rows) >= $maxRows) {
                break;
            }
        }
        fclose($handle);

        // Pad every row to the widest, so column access is predictable.
        $width = 0;
        foreach ($rows as $row) {
            $width = max($width, count($row));
        }
        foreach ($rows as $i => $row) {
            $rows[$i] = array_pad($row, $width, null);
        }
        return $rows;
    }

    public function __destruct()
    {
        if ($this->zip !== null) {
            @$this->zip->close();
        }
    }
}
