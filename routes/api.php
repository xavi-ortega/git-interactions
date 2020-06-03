<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('login', 'UsersController@login');

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('user/lastReports', 'ReportsController@lastUserReports');
});

Route::get('rate-limit', 'ReportsController@rateLimit');
Route::get('report', 'ReportsController@report');
Route::get('report/dispatch', 'ReportsController@prepare');
Route::get('report/progress', 'ReportsController@progress');
Route::get('user/notifications', 'UsersController@notifications');
