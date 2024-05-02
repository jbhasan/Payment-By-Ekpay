<?php

use Illuminate\Support\Facades\Route;
use Sayeed\PaymentByEkpay\Http\Controllers\EkpayController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Ekpay GW Start
Route::post('/ekpay/pay', [EkpayController::class, 'index']);
Route::get('/ekapy/payment-response/{response_type}', [EkpayController::class, 'paymentResponse']);
Route::post('/ekapy/ipn', [EkpayController::class, 'ipn']);
// Ekpay GW END
