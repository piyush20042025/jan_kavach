<?php
namespace JanKavach\Libraries;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Response;
use JanKavach\Core\View;

/**
 * Export a result set as Excel, CSV or a print-ready page.
 *
 * "PDF export" is delivered as a print-optimised HTML view that opens the
 * browser's print dialogue with Save as PDF preselected.  That keeps the
 * application free of a PDF binary dependency while still giving users a
 * faithful, paginated PDF with working Unicode for Indian-language names -
 * something the lightweight PHP PDF libraries handle poorly.
 */
class Exporter
{
    /**
     * @param array  $columns  ['header' => callable|string] - a string is a
     *                         row key, a callable receives the row
     * @param array  $rows     the data
     * @param string $filename base name, without extension
     * @param array  $options  title, subtitle, filters[], module, orientation
     */
    public static function send(string $format, array $columns, array $rows, string $filename, array $options = []): void
    {
        $limit = (int)(Config::setting('export_row_limit') ?? Config::get('pagination.export_max_rows', 20000));
        $truncated = false;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $truncated = true;
        }

        $headers = array_keys($columns);
        $matrix  = self::toMatrix($columns, $rows);

        Audit::exported($options['module'] ?? 'Reports',
                        $options['title'] ?? $filename,
                        count($rows),
                        $options['filters'] ?? []);

        switch (strtolower($format)) {
            case 'csv':
                self::csv($headers, $matrix, $filename);
                break;
            case 'pdf':
            case 'print':
                self::printable($headers, $matrix, $options, $truncated);
                break;
            case 'excel':
            case 'xlsx':
            default:
                self::excel($headers, $matrix, $filename, $options, $truncated);
                break;
        }
    }

    /** Resolve the column definitions against each row. */
    private static function toMatrix(array $columns, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $definition) {
                if (is_callable($definition)) {
                    $line[] = $definition($row);
                } elseif (is_string($definition)) {
                    $line[] = $row[$definition] ?? null;
                } else {
                    $line[] = null;
                }
            }
            $out[] = $line;
        }
        return $out;
    }

    private static function excel(array $headers, array $rows, string $filename, array $options, bool $truncated): void
    {
        $writer = new XlsxWriter($headers, $rows, $options['sheet'] ?? ($options['title'] ?? 'Export'));
        $writer->setMeta(self::metaLines($options, count($rows), $truncated));
        $writer->download(self::stampName($filename) . '.xlsx');
    }

    private static function csv(array $headers, array $rows, string $filename): void
    {
        $handle = fopen('php://temp', 'r+');
        // BOM so Excel opens UTF-8 correctly on Windows.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($v) {
                $v = (string)$v;
                // Neutralise spreadsheet formula injection.
                if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $v = "'" . $v;
                }
                return $v;
            }, $row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Response::attachment($csv, self::stampName($filename) . '.csv', 'text/csv');
    }

    /** Print-optimised HTML that triggers the browser print dialogue. */
    private static function printable(array $headers, array $rows, array $options, bool $truncated): void
    {
        View::display('reports/print', [
            'headers'     => $headers,
            'rows'        => $rows,
            'title'       => $options['title'] ?? 'Jan Kavach Report',
            'subtitle'    => $options['subtitle'] ?? null,
            'filters'     => $options['filters'] ?? [],
            'orientation' => $options['orientation'] ?? 'portrait',
            'truncated'   => $truncated,
            'generatedBy' => Auth::user()['full_name'] ?? 'System',
            'generatedAt' => date(Config::get('app.datetime_format', 'd-m-Y h:i A')),
            'appName'     => Config::get('app.name'),
            'footerNote'  => Config::setting('footer_note', 'Generated from Jan Kavach - Social Protection MIS'),
            'autoPrint'   => true,
        ], null);
        exit;
    }

    private static function metaLines(array $options, int $rowCount, bool $truncated): array
    {
        $lines = [];
        $lines[] = Config::get('app.name') . ' - ' . ($options['title'] ?? 'Export');
        if (!empty($options['subtitle'])) {
            $lines[] = $options['subtitle'];
        }

        $filters = array_filter($options['filters'] ?? [], fn($v) => $v !== null && $v !== '');
        if ($filters) {
            $parts = [];
            foreach ($filters as $label => $value) {
                $parts[] = $label . ': ' . $value;
            }
            $lines[] = 'Filters - ' . implode('  |  ', $parts);
        }

        $lines[] = sprintf('Generated %s by %s   |   %s row%s%s',
            date(Config::get('app.datetime_format', 'd-m-Y h:i A')),
            Auth::user()['full_name'] ?? 'System',
            number_format($rowCount),
            $rowCount === 1 ? '' : 's',
            $truncated ? '  (truncated at the configured export limit)' : '');

        return $lines;
    }

    private static function stampName(string $filename): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $filename);
        return trim($clean, '-') . '_' . date('Ymd_His');
    }
}
