<?php

use Illuminate\Support\Facades\Route;

Route::get('payment/{id}', 'StripePaymentController@show')->name('payment');
Route::post('webhook', 'StripeWebhookController@handleWebhook')->name('webhook');

Route::get('vat', 'VatController@show')->name('vat.show');
Route::post('vat', 'VatController@store')->name('vat.store');
Route::post('vat/validate', 'VatController@validate')->name('vat.validate');
