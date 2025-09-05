<?php

use Illuminate\Support\Facades\Route;

Route::get('payment/{id}', 'StripePaymentController@show')->name('payment');
Route::post('webhook', 'StripeWebhookController@handleWebhook')->name('webhook');
