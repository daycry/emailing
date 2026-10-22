<?php

namespace App\Support;

use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TemplateRenderer
{
    public const VARIABLES = ['contact.name', 'contact.email', 'account.name'];

    public function render(string $source, array $values, string $field, bool $html = false): string
    {
        if (! mb_check_encoding($source, 'UTF-8') || strlen($source) > 100000) {
            $this->invalid($field, 'El contenido debe ser UTF-8 y ocupar como máximo 100.000 bytes.');
        }
        foreach (self::VARIABLES as $key) {
            if (isset($values[$key]) && (! is_string($values[$key]) || ! mb_check_encoding($values[$key], 'UTF-8') || mb_strlen($values[$key]) > 512)) {
                $this->invalid('values', 'Los valores deben ser textos de hasta 512 caracteres.');
            }
        }
        preg_match_all('/\{\{(.*?)\}\}/s', $source, $matches);
        if (count($matches[0]) > 200) {
            $this->invalid($field, 'Máximo 200 variables por campo.');
        }
        foreach ($matches[1] as $key) {
            if (! in_array(trim($key), self::VARIABLES, true)) {
                $this->invalid($field, 'Variable no admitida. Usa contact.name, contact.email o account.name.');
            }
        }
        $withoutTokens = preg_replace('/\{\{(.*?)\}\}/s', '', $source);
        if (str_contains($withoutTokens, '{{') || str_contains($withoutTokens, '}}')) {
            $this->invalid($field, 'Una variable tiene delimitadores sin cerrar o mal formados.');
        }
        if ($html && $matches[0] !== []) {
            $document = HtmlDocument::parse($source);
            $eligible = 0;
            $walk = function (DOMNode $node, bool $textContext = true) use (&$walk, &$eligible): void {
                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['script', 'style', 'textarea', 'title', 'head'], true)) {
                    $textContext = false;
                }
                if ($node instanceof DOMText && $textContext) {
                    $eligible += preg_match_all('/\{\{(.*?)\}\}/s', $node->nodeValue);
                }
                foreach ($node->childNodes as $child) {
                    $walk($child, $textContext);
                }
            };
            $walk($document);
            if ($eligible !== count($matches[0])) {
                $this->invalid($field, 'Las variables HTML solo se admiten en texto del cuerpo, no en atributos, CSS, scripts ni comentarios.');
            }
        }
        $rendered = preg_replace_callback('/\{\{(.*?)\}\}/s', function ($match) use ($values, $html) {
            $value = $values[trim($match[1])] ?? '';

            return $html ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $source);
        if (strlen($rendered) > 200000) {
            $this->invalid($field, 'El resultado supera 200.000 bytes.');
        }
        if ($field === 'subject') {
            Validator::make(['subject' => $rendered], ['subject' => ['bail', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/']])->validate();
        }

        return $rendered;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
