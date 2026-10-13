<?php
namespace JanKavach\Libraries;

/**
 * Minimal, dependency-free XLSX writer.
 *
 * Produces a single-sheet workbook with a styled header row, frozen header,
 * auto-filter and sensible column widths - which is all the export screens
 * need.  Values are written as inline strings except for genuine numbers and
 * dates, so Excel shows exactly what the application shows.
 */
class XlsxWriter
{
    private array $headers = [];
    private array $rows = [];
    private string $sheetName = 'Sheet1';
    private array $meta = [];

    public function __construct(array $headers = [], array $rows = [], string $sheetName = 'Sheet1')
    {
        $this->headers = array_values($headers);
        $this->rows = $rows;
        $this->setSheetName($sheetName);
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = array_values($headers);
        return $this;
    }

    public function setRows(array $rows): self
    {
        $this->rows = $rows;
        return $this;
    }

    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    public function setSheetName(string $name): self
    {
        // Excel rejects these characters and anything over 31 chars.
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $name);
        $this->sheetName = mb_substr(trim($name) ?: 'Sheet1', 0, 31);
        return $this;
    }

    /** Title / filter lines written above the header row. */
    public function setMeta(array $lines): self
    {
        $this->meta = $lines;
        return $this;
    }

    /** Build the workbook and send it as a download. */
    public function download(string $filename): void
    {
        $path = $this->save();
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        @unlink($path);
        exit;
    }

    /** Write the workbook to a temporary file and return its path. */
    public function save(?string $path = null): string
    {
        $path = $path ?: (defined('STORAGE_PATH')
            ? STORAGE_PATH . '/temp/export_' . bin2hex(random_bytes(8)) . '.xlsx'
            : tempnam(sys_get_temp_dir(), 'jkx'));

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the Excel file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('docProps/app.xml', $this->appProps());
        $zip->addFromString('docProps/core.xml', $this->coreProps());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        return $path;
    }

    // =================================================================
    // XML parts
    // =================================================================

    private function sheet(): string
    {
        $colCount = max(1, count($this->headers));
        $metaCount = count($this->meta);
        $headerRow = $metaCount + ($metaCount > 0 ? 2 : 1);   // blank line after the meta block

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Column widths, sized from the header text with a sane ceiling.
        $xml .= '<cols>';
        foreach ($this->headers as $i => $header) {
            $width = min(60, max(12, mb_strlen((string)$header) + 6));
            $xml .= sprintf('<col min="%d" max="%d" width="%.1f" customWidth="1"/>', $i + 1, $i + 1, $width);
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        $rowNumber = 1;
        foreach ($this->meta as $line) {
            $xml .= '<row r="' . $rowNumber . '">';
            $xml .= $this->cell($this->ref(0, $rowNumber), $line, 3);
            $xml .= '</row>';
            $rowNumber++;
        }
        if ($metaCount > 0) {
            $rowNumber++;   // leave a blank row
        }

        // Header
        $xml .= '<row r="' . $rowNumber . '" ht="22" customHeight="1">';
        foreach ($this->headers as $i => $header) {
            $xml .= $this->cell($this->ref($i, $rowNumber), $header, 1);
        }
        $xml .= '</row>';
        $rowNumber++;

        // Data
        foreach ($this->rows as $row) {
            $row = array_values(is_array($row) ? $row : [$row]);
            $xml .= '<row r="' . $rowNumber . '">';
            for ($c = 0; $c < $colCount; $c++) {
                $value = $row[$c] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $xml .= $this->cell($this->ref($c, $rowNumber), $value, 0);
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        $xml .= '</sheetData>';

        $lastCol = $this->columnLetter($colCount - 1);
        $xml .= sprintf('<autoFilter ref="A%d:%s%d"/>', $headerRow, $lastCol, max($headerRow, $rowNumber - 1));
        $xml .= '</worksheet>';

        // Freeze everything above and including the header row.
        $pane = sprintf(
            '<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
            . '<pane ySplit="%d" topLeftCell="A%d" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>',
            $headerRow, $headerRow + 1);

        return str_replace('<sheetData>', $pane . '<sheetData>', $xml);
    }

    /**
     * One cell.  Numbers are written as numbers, dates as ISO text (Excel
     * still sorts them correctly and there is no timezone ambiguity),
     * everything else as an inline string.
     */
    private function cell(string $ref, $value, int $style): string
    {
        $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';

        if (is_int($value) || is_float($value)) {
            return sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $styleAttr, $value);
        }

        $text = (string)$value;

        // A plain numeric string stays numeric, unless it is an identifier
        // that must keep its leading zeros (mobile numbers, codes, PIN codes).
        if (preg_match('/^-?\d{1,12}(\.\d+)?$/', $text)
            && !str_starts_with($text, '0')
            && strlen($text) <= 12) {
            return sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $styleAttr, $text);
        }

        return sprintf('<c r="%s"%s t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
            $ref, $styleAttr, $this->escape($text));
    }

    private function escape(string $text): string
    {
        // Strip control characters Excel refuses to open.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function ref(int $col, int $row): string
    {
        return $this->columnLetter($col) . $row;
    }

    private function columnLetter(int $index): string
    {
        $index = max(0, $index);
        $letters = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letters = chr(65 + $rem) . $letters;
            $index = (int)(($index - $rem - 1) / 26);
        }
        return $letters;
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="13"/><color rgb="FF0F4C81"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F4C81"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD0D7DE"/></left><right style="thin"><color rgb="FFD0D7DE"/></right>'
            . '<top style="thin"><color rgb="FFD0D7DE"/></top><bottom style="thin"><color rgb="FFD0D7DE"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->escape($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function coreProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $this->escape($this->sheetName) . '</dc:title>'
            . '<dc:creator>Jan Kavach</dc:creator>'
            . '<cp:lastModifiedBy>Jan Kavach</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private function appProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Jan Kavach</Application></Properties>';
    }
}
