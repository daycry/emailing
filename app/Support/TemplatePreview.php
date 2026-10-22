<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class TemplatePreview
{
    public const CSP = "default-src 'none'; script-src 'none'; connect-src 'none'; object-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'; img-src 'none'; style-src 'unsafe-inline'";

    private const TAGS = ['p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'colgroup', 'col', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'br', 'hr', 'blockquote', 'pre', 'code', 'sub', 'sup'];

    private const DROP = ['script', 'style', 'meta', 'base', 'link', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'option', 'video', 'audio', 'source', 'track', 'canvas', 'template', 'noscript', 'title'];

    private const STYLES = ['color', 'background-color', 'font-size', 'font-family', 'font-weight', 'font-style', 'text-align', 'text-decoration', 'border', 'border-collapse', 'border-spacing', 'border-color', 'border-width', 'border-style', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'width', 'height', 'max-width', 'line-height', 'display', 'vertical-align', 'letter-spacing'];

    public function document(string $html): string
    {
        $source = HtmlDocument::parse($html);
        $safe = new DOMDocument('1.0', 'UTF-8');
        $body = $safe->createElement('div');
        $safe->appendChild($body);
        $sourceBody = $source->getElementsByTagName('body')->item(0);
        if ($sourceBody) {
            foreach ($sourceBody->childNodes as $node) {
                $this->copy($node, $body, $safe);
            }
        }
        $fragment = '';
        foreach ($body->childNodes as $node) {
            $fragment .= $safe->saveHTML($node);
        }

        return '<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars(self::CSP, ENT_QUOTES, 'UTF-8').'"><meta charset="utf-8"><meta name="referrer" content="no-referrer"></head><body>'.$fragment.'</body></html>';
    }

    private function copy(DOMNode $source, DOMNode $parent, DOMDocument $document): void
    {
        if ($source instanceof DOMText) {
            $parent->appendChild($document->createTextNode($source->nodeValue));

            return;
        }
        if (! $source instanceof DOMElement) {
            return;
        }
        $tag = strtolower($source->tagName);
        if (in_array($tag, self::DROP, true)) {
            return;
        }
        if ($tag === 'img') {
            $image = $document->createElement('span');
            $image->appendChild($document->createTextNode('[Imagen omitida en preview]'));
            $parent->appendChild($image);

            return;
        }
        // Link destinations/targets are intentionally discarded; email delivery keeps the original HTML.
        $tag = $tag === 'a' ? 'span' : $tag;
        $target = $parent;
        if (in_array($tag, self::TAGS, true)) {
            $target = $document->createElement($tag);
            $parent->appendChild($target);
            $style = $this->style($source->getAttribute('style'));
            if ($style !== '') {
                $target->setAttribute('style', $style);
            }
            foreach (['width', 'height', 'cellpadding', 'cellspacing', 'border', 'colspan', 'rowspan'] as $attribute) {
                $value = $source->getAttribute($attribute);
                if (preg_match('/^\d{1,4}%?$/D', $value)) {
                    $target->setAttribute($attribute, $value);
                }
            }
        }
        foreach ($source->childNodes as $child) {
            $this->copy($child, $target, $document);
        }
    }

    private function style(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (in_array($property, self::STYLES, true) && strlen($value) <= 128 && preg_match('/^[#a-zA-Z0-9\s.,%\-]+$/D', $value)) {
                $safe[] = $property.':'.$value;
            }
        }

        return implode(';', $safe);
    }
}
