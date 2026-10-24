<?php

use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\ApiKeyController;
use App\Livewire\Activity;
use App\Livewire\Campaigns;
use App\Livewire\ContactImports;
use App\Livewire\Contacts;
use App\Livewire\MailSettings;
use App\Livewire\Suppressions;
use App\Livewire\Team;
use App\Livewire\Templates;
use App\Services\AccountContext;
use App\Services\ActivityService;
use App\Services\ContactImportService;
use App\Services\MailSettingsService;
use App\Services\SuppressionService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/unsubscribe/{token}', UnsubscribeController::class)->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('unsubscribe.show');
Route::post('/unsubscribe/{token}', UnsubscribeController::class)->where('token', '[a-f0-9]{64}')->middleware('throttle:15,1')->name('unsubscribe.confirm');

Route::middleware('auth')->group(function () {
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/api-keys/{keyId}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
    Route::get('/activity', Activity::class)->name('activity');
    Route::get('/activity/export', fn (Request $request, ActivityService $activity) => $activity->export($request->query()))->name('activity.export');
    Route::get('/campaigns', Campaigns::class)->name('campaigns');
    Route::get('/suppressions', Suppressions::class)->name('suppressions');
    Route::post('/suppressions/links', function (Request $request, AccountContext $context, SuppressionService $service) {
        $context->authorize('suppressions.read');
        $url = $service->issue($context->account(), $request->user()->id, $request->only('email'));

        return response($url."\n")->header('Content-Type', 'text/plain; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="enlace-baja.txt"')->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    })->middleware('throttle:15,1')->name('suppressions.issue');
    Route::get('/templates', Templates::class)->name('templates');
    Route::get('/settings/mail', MailSettings::class)->name('mail-settings');
    Route::get('/mail-artifacts/{id}/download', fn (int $id, MailSettingsService $settings) => $settings->download($id))->whereNumber('id')->name('mail-artifacts.download');
    Route::get('/contacts/imports', ContactImports::class)->name('contact-imports');
    Route::get('/contacts/imports/{id}/download', fn (int $id, ContactImportService $imports) => $imports->download($id))->whereNumber('id')->name('contact-imports.download');
    Route::get('/contacts', Contacts::class)->name('contacts');
    Route::get('/team', Team::class)->name('team');
    Route::get('/invitations/{token}', function (string $token, TeamService $teams) {
        $invitation = $teams->pending($token);
        abort_unless(hash_equals($invitation->email, mb_strtolower(auth()->user()->email)), 403, 'Accede con la dirección que recibió la invitación.');

        return view('invitation', compact('invitation', 'token'));
    })->middleware('throttle:30,1')->name('invitations.show');
    Route::post('/invitations/{token}', function (string $token, TeamService $teams) {
        $member = $teams->accept($token, auth()->user());
        session(['account_id' => $member->account_id]);

        return redirect()->route('team')->with('success', 'Ya formas parte del equipo.');
    })->middleware('throttle:15,1')->name('invitations.accept');
    Route::get('/dashboard', function (AccountContext $context) {
        return view('dashboard', ['account' => $context->account(), 'membership' => $context->membership()]);
    })->name('dashboard');
    Route::post('/accounts/switch', function (Request $request) {
        $data = $request->validate(['account_id' => ['required', 'integer']]);
        abort_unless($request->user()->memberships()->where('account_id', $data['account_id'])->exists(), 403);
        $request->session()->put('account_id', (int) $data['account_id']);

        return redirect()->route('dashboard');
    })->name('accounts.switch');
    Route::get('/accounts', fn () => view('accounts'))->name('accounts');
});
