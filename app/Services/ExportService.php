<?php

declare(strict_types=1);

final class ExportService
{
    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public static function csv(string $filename, array $headers, array $rows): never
    {
        Auth::requireLogin();

        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename) ?: 'export.csv';
        if (!str_ends_with(strtolower($safe), '.csv')) {
            $safe .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            echo 'No se pudo generar el CSV.';
            exit;
        }

        // BOM UTF-8 para Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            $line = [];
            foreach ($row as $cell) {
                if (is_float($cell) || is_int($cell)) {
                    $line[] = (string) $cell;
                } else {
                    $line[] = (string) ($cell ?? '');
                }
            }
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit;
    }
}
