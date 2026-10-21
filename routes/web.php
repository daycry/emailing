<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (\App\Services\AccountContext $context) {
        return view('dashboard',['account'=>$context->account(),'membership'=>$context->membership()]);
    })->name('dashboard');
    Route::post('/accounts/switch', function (\Illuminate\Http\Request $request) {
        $data = $request->validate(['account_id'=>['required','integer']]);
        abort_unless($request->user()->memberships()->where('account_id',$data['account_id'])->exists(),403);
        $request->session()->put('account_id',(int) $data['account_id']);
        return redirect()->route('dashboard');
    })->name('accounts.switch');
    Route::get('/accounts', fn () => view('accounts'))->name('accounts');
});
