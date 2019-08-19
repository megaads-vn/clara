<?php

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
Route::group(['prefix' => '{{MODULE_NAMESPACE}}', 'middleware' => 'example'], function () {
    Route::get('/', [
        'as' => '{{MODULE_NAMESPACE}}::home',
        'uses' => 'HomeController@index',
    ]);
});
