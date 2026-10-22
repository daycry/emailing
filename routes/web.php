<?php

use App\Livewire\ContactImports;
use App\Livewire\Contacts;
use App\Livewire\MailSettings;
use App\Livewire\Team;
use App\Services\AccountContext;
use App\Services\ContactImportService;
use App\Services\MailSettingsService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::middleware('auth')->group(function () {
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
