<?php

namespace App\Support;

use DOMDocument;

class HtmlDocument
{
    public static function parse(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            $document->validateOnParse = false;
            // No NOENT/DTDLOAD/DTDATTR: external entities and DTDs are never expanded.
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
