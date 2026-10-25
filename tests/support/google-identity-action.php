<?php

use App\Models\User;
use App\Services\Auth\GoogleIdentityResolver;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 12;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Identity barrier timed out.');
    }
    usleep(10000);
}
try {
    $actor = User::findOrFail($input['user_id']);
    $resolver = app(GoogleIdentityResolver::class);
    if ($input['operation'] === 'unlink') {
        $resolver->unlink($actor, $input['identity_id']);
    } else {
        $resolver->link($actor, ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => $input['sub']]);
    }
    echo 'accepted';
} catch (ValidationException) {
    echo 'rejected';
}
