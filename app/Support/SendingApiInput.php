<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class SendingApiInput
{
    public const SCOPES = ['mail.send', 'templates.read', 'activity.read', 'api-keys.read', 'api-keys.write'];

    public static function object(mixed $value, array $allowed, array $required): array
    {
        ApiError::require($value instanceof \stdClass);
        $data = get_object_vars($value);
        ApiError::require(array_diff(array_keys($data), $allowed) === [] && array_diff($required, array_keys($data)) === []);

        return $data;
    }

    public static function header(string $value, int $max, bool $nonblank = false): string
    {
        ApiError::require(mb_strlen($value) <= $max && ! preg_match('/[\x00-\x1f\x7f]/', $value) && (! $nonblank || preg_match('/\S/u', $value)));

        return $value;
    }

    public static function integer(mixed $value): int
    {
        if ($value instanceof ApiJsonNumber) {
            $exact = $value->safePositiveInteger();
            ApiError::require($exact !== null);

            return $exact;
        }
        ApiError::require((is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 1 && $value <= 9007199254740991 && floor($value) == $value);

        return (int) $value;
    }

    public static function send(mixed $value): array
    {
        $data = self::object($value, ['sender_id', 'recipients', 'content'], ['sender_id', 'recipients', 'content']);
        $data['sender_id'] = self::integer($data['sender_id']);
        ApiError::require(is_array($data['recipients']) && count($data['recipients']) >= 1 && count($data['recipients']) <= 100);
        $seen = [];
        foreach ($data['recipients'] as &$recipient) {
            $recipient = self::object($recipient, ['email', 'name'], ['email']);
            ApiError::require(is_string($recipient['email']));
            ApiError::require(! preg_match('/[\x00-\x08\x0a-\x1f\x7f]|(?<=\S)\t(?=\S)/u', $recipient['email']));
            $email = mb_strtolower(trim($recipient['email'], " \t"));
            ApiError::require(! preg_match('/[\x00-\x20\x7f]/u', $email) && mb_strlen($email) <= 254 && Validator::make(['email' => $email], ['email' => MailInput::emailRules()])->passes());
            ApiError::require(! isset($seen[$email]));
            $seen[$email] = true;
            $name = $recipient['name'] ?? '';
            ApiError::require(is_string($name) && (! array_key_exists('name', $recipient) || $recipient['name'] !== null));
            $recipient = ['email' => $email, 'name' => self::header($name, 150)];
        }
        unset($recipient);
        $content = self::object($data['content'], ['type', 'subject', 'html', 'text', 'template_id'], ['type']);
        if ($content['type'] === 'inline') {
            ApiError::require(array_diff(array_keys($content), ['type', 'subject', 'html', 'text']) === [] && isset($content['subject']) && is_string($content['subject']));
            $content['subject'] = self::header($content['subject'], 200, true);
            foreach (['html', 'text'] as $field) {
                if (! array_key_exists($field, $content)) {
                    $content[$field] = '';
                }
                ApiError::require(is_string($content[$field]) && mb_strlen($content[$field]) <= 200000);
            }
            ApiError::require((bool) preg_match('/\S/u', $content['html'].$content['text']));
        } else {
            ApiError::require($content['type'] === 'template' && array_diff(array_keys($content), ['type', 'template_id']) === [] && isset($content['template_id']));
            $content['template_id'] = self::integer($content['template_id']);
        }
        $data['content'] = $content;

        return $data;
    }

    public static function key(mixed $value): array
    {
        $data = self::object($value, ['name', 'scopes', 'expires_at'], ['name', 'scopes']);
        ApiError::require(is_string($data['name']));
        self::header($data['name'], 150, true);
        ApiError::require(is_array($data['scopes']) && count($data['scopes']) >= 1 && count($data['scopes']) <= 5);
        foreach ($data['scopes'] as $scope) {
            ApiError::require(is_string($scope) && in_array($scope, self::SCOPES, true));
        }
        ApiError::require(count(array_unique($data['scopes'])) === count($data['scopes']));
        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== null) {
            ApiError::require(is_string($data['expires_at']));
            $data['expires_at'] = self::timestamp($data['expires_at']);
        }

        return $data;
    }

    public static function timestamp(string $value): string
    {
        ApiError::require((bool) preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?Z$/D', $value));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', str_contains($value, '.') ? $value : substr($value, 0, -1).'.000000Z', new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        ApiError::require($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)));

        return $date->format('Y-m-d H:i:s.u');
    }

    public static function query(Request $request, bool $listing): array
    {
        $result = [];
        $raw = $request->server('QUERY_STRING', '');
        foreach ($raw === '' ? [] : explode('&', $raw) as $part) {
            ApiError::require($part !== '' && ! preg_match('/%(?![a-fA-F0-9]{2})/', $part), 400);
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            ApiError::require(mb_check_encoding($key.$value, 'UTF-8') && ! array_key_exists($key, $result), 400);
            $result[$key] = $value;
        }
        ApiError::require($result === [] || ($listing && array_diff(array_keys($result), ['limit', 'cursor']) === []));
        if (isset($result['limit'])) {
            ApiError::require((bool) preg_match('/^[0-9]+$/D', $result['limit']) && (int) $result['limit'] >= 1 && (int) $result['limit'] <= 100);
            $result['limit'] = (int) $result['limit'];
        }
        if (isset($result['cursor'])) {
            ApiError::require(strlen($result['cursor']) <= 512, 400);
        }

        return $result;
    }

    public static function body(Request $request): mixed
    {
        ApiError::require(strlen($request->getContent()) <= 2097152, 413);
        ApiError::require((bool) preg_match('/^application\/json(?:\s*;\s*charset\s*=\s*(?:utf-8|"utf-8"))?\s*$/iD', $request->header('Content-Type', '')), 415);
        ApiError::require(in_array(strtolower($request->header('Content-Encoding', 'identity')), ['identity', ''], true), 415);

        return StrictApiJson::decode($request->getContent());
    }

    public static function fingerprint(array $data): string
    {
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', 'sending-api-v1:'.json_encode($canonical($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
