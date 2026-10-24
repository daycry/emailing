<?php

namespace App\Support;

use JsonException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchedulerTriggerInput
{
    private function __construct(public string $action) {}

    public static function fromJson(string $body, string $contentType): self
    {
        if (strlen($body) > 1024) {
            throw new HttpException(413, 'body_too_large');
        }
        if (strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new HttpException(415, 'json_required');
        }
        try {
            $input = json_decode($body, false, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'invalid_json');
        }
        if (! $input instanceof stdClass || array_keys(get_object_vars($input)) !== ['action'] || ! is_string($input->action) || ! in_array($input->action, ['materialize', 'dispatch'], true)) {
            throw new HttpException(422, 'invalid_action');
        }

        return new self($input->action);
    }
}
