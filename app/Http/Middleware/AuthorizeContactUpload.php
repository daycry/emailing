<?php

namespace App\Http\Middleware;

use App\Services\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AuthorizeContactUpload
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(AccountContext::class);
        $member = $context->authorize('contacts.write');
        $context->authorize('contacts.read');
        $response = $next($request);
        if ($response->isSuccessful() && method_exists($response, 'getData')) {
            $uploads = array_filter(session('contact_import_uploads', []), fn ($entry) => $entry['at'] >= time() - 3600);
            foreach ($response->getData(true)['paths'] ?? [] as $signed) {
                $path = TemporaryUploadedFile::extractPathFromSignedPath($signed);
                abort_unless($path !== false, 400);
                $uploads[hash('sha256', basename($path))] = ['account_id' => $member->account_id, 'user_id' => auth()->id(), 'at' => time()];
            }
            session(['contact_import_uploads' => array_slice($uploads, -100, null, true)]);
        }

        return $response;
    }
}
