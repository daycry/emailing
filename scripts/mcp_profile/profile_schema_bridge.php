<?php

// Private CLI adapter. Never bootstrap Laravel, read environment files or echo inputs.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ob_start();
set_error_handler(static function (): never {
    throw new RuntimeException('Schema validation unavailable');
});

$available = false;
$valid = false;
try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI required');
    }
    $input = stream_get_contents(STDIN, 8388609);
    if (! is_string($input) || strlen($input) > 8388608) {
        throw new RuntimeException('Invalid input');
    }
    $document = json_decode($input, false, 512, JSON_THROW_ON_ERROR);
    if (! $document instanceof stdClass
        || count(get_object_vars($document)) !== 2
        || ! isset($document->operation)
        || ! is_string($document->operation)
        || ! property_exists($document, 'value')
        || ! $document->value instanceof stdClass) {
        throw new RuntimeException('Invalid input');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $operation = App\Services\Mcp\ContractArguments::operation($document->operation);
    $valid = App\Services\Mcp\ContractArguments::valid($document->value, $operation['outputSchema']);
    $available = true;
} catch (Throwable) {
    $available = false;
    $valid = false;
}
ob_end_clean();
restore_error_handler();
echo json_encode(['available' => $available, 'valid' => $valid], JSON_THROW_ON_ERROR);
exit($available ? 0 : 2);
