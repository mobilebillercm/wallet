<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::post('holders', 'ApiController@createHolder')->middleware('rabbitmq.client');

Route::post('payements-from-mobile-biller-credit-account', 'ApiController@makePayementFromMobileBillerCreditAccount')->middleware('rabbitmq.client');

Route::post('mobilebillercreditaccounttransactions', 'ApiController@makeOperation')->middleware('token.verification');

Route::post('mobilebillercreditaccounts/{id}/photo', 'ApiController@changePhoto')->middleware('token.verification');

Route::get('mobilebillercreditaccounts/{id}', 'ApiController@getInfos')->middleware('token.verification');

Route::get('paymentmethodtypes', 'ApiController@getPaymentmethodTypes');

Route::post('topups', 'ApiController@makeTopup')->middleware('token.verification');

Route::post('cash-topups', 'ApiController@makeCashTopup')->middleware('token.verification');

Route::post('transferts', 'ApiController@makeTransfert')->middleware('token.verification');

Route::get('transactions/{userid}', 'ApiController@getTransactions')->middleware('token.verification');

Route::get('transactions/details/{transactionid}', 'ApiController@getTransactionDetails')->middleware('token.verification');






















Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
