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
Route::post('register', 'UsersController@register');

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('user/lastReports', 'ReportsController@lastUserReports');
    Route::get('user/notifications', 'UsersController@notifications');
    Route::post('user/notifications/{notification}', 'UserController@notificationVisited');

    Route::post('report/search', 'ReportsController@search');
    Route::get('report/popular', 'ReportsController@popularReports');
    Route::post('report/prepare', 'ReportsController@prepare');
    Route::get('report/progress', 'ReportsController@progress');
    Route::get('report/queue', 'ReportsController@queue');
    Route::get('report/{report}', 'ReportsController@report');
});

Route::get('rate-limit', 'ReportsController@rateLimit');
