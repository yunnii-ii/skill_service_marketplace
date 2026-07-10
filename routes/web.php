<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mail', function () {
    Mail::raw('This is test email.', function ($message) {
        $message->to('kmyozaw.dev@gmail.com')
                ->subject('Test Email');
    });

    return 'Mail sent';
});
