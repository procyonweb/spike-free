<?php

use Illuminate\Support\Facades\Route;
use Opcodes\Spike\Http\Controllers\MollieController;

Route::post('webhook', [MollieController::class, 'webhook'])->name('webhook');

Route::get('checkout/success/{cart}', [MollieController::class, 'checkoutSuccess'])->name('checkout.success');
