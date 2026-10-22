<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ContactCsv
{
    public const HEADERS = ['email', 'name', 'consent_status', 'consent_source', 'consented_at'];

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    public function parse(string $csv, int $maxRows): array
    {
        if (! mb_check_encoding($csv, 'UTF-8') || str_contains($csv, "\0")) {
            $this->invalid('El CSV debe usar UTF-8 y no contener bytes nulos.');
        }
        $csv = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
        $this->checkQuoting($csv);
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, $csv);
            rewind($stream);
            $headers = fgetcsv($stream, null, ',', '"', '');
            if (! $headers || in_array(null, $headers, true)) {
                $this->invalid('El archivo necesita encabezados y la columna email.');
            }
            $headers = array_map('trim', $headers);
            if (! in_array('email', $headers, true) || count(array_unique($headers)) !== count($headers) || array_diff($headers, self::HEADERS)) {
                $this->invalid('Encabezados admitidos, sin repetir: '.implode(', ', self::HEADERS).'. email es obligatorio.');
            }
            $rows = [];
            while (($fields = fgetcsv($stream, null, ',', '"', '')) !== false) {
                // An empty physical line is not a data record; quoted empty values still are.
                if ($fields === [null]) {
                    continue;
                }
                if (count($fields) !== count($headers)) {
                    $this->invalid('El número de columnas no coincide con los encabezados. No se ha importado ninguna fila.');
                }
                if (count($rows) >= $maxRows) {
                    $this->invalid('El CSV supera el límite de '.$maxRows.' registros de datos.');
                }
                $rows[] = array_combine($headers, $fields);
            }
            if ($rows === []) {
                $this->invalid('El CSV no contiene registros de datos.');
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function checkQuoting(string $csv): void
    {
        // Validate RFC 4180 lexical structure before PHP's permissive CSV reader.
        $state = 'start';
        $length = strlen($csv);
        for ($i = 0; $i < $length; $i++) {
            $char = $csv[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    if ($i + 1 < $length && $csv[$i + 1] === '"') {
                        $i++;
                    } else {
                        $state = 'after';
                    }
                }

                continue;
            }
            if ($char === "\r" || $char === "\n") {
                if ($char === "\r" && ($i + 1 >= $length || $csv[$i + 1] !== "\n")) {
                    $this->invalid('Usa saltos de línea LF o CRLF en el CSV.');
                }
                if ($char === "\r") {
                    $i++;
                }
                $state = 'start';
            } elseif ($char === ',') {
                $state = 'start';
            } elseif ($char === '"' && $state === 'start') {
                $state = 'quoted';
            } elseif ($char === '"' || $state === 'after') {
                $this->invalid('El CSV contiene comillas o separadores mal formados.');
            } else {
                $state = 'plain';
            }
        }
        if ($state === 'quoted') {
            $this->invalid('El CSV contiene un campo entre comillas sin cerrar.');
        }
    }
}
