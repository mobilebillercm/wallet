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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('holders', 'ApiController@createHolder');

Route::post('mobilebillercreditaccounttransactions', 'ApiController@makeOperation');

Route::post('mobilebillercreditaccounts/{id}/photo', 'ApiController@changePhoto');

Route::get('mobilebillercreditaccounts/{id}', 'ApiController@getInfos');

Route::get('paymentmethodtypes', 'ApiController@getPaymentmethodTypes');

Route::post('topups', 'ApiController@makeTopup');

Route::post('transferts', 'ApiController@makeTransfert');

Route::get('transactions/{userid}', 'ApiController@getTransactions');

Route::get('transactions/details/{transactionid}', 'ApiController@getTransactionDetails');


