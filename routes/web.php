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

Route::any('/', 'TranslateController@homepage')->name('homepage');
Route::get('file/{file}', 'TranslateController@filePage')->name('translate.file');
Route::get('translate', 'TranslateController@index')->name('translate');
Route::get('translate-google', 'TranslateController@google')->name('translate.google');
Route::get('translate-reverso', 'TranslateController@reverso')->name('translate.google');
Route::any('export-all', 'TranslateController@export')->name('translate.export');
Route::any('save-approved', 'TranslateController@saveApproved')->name('translate.save');
Route::any('updateWorklog', 'TranslateController@updateWorklog')->name('translate.updateWorklog');
Route::any('setUserWorkingActivityStatus', 'TranslateController@setUserWorkingActivityStatus')->name('translate.setUserWorkingActivityStatus');
Route::any('test', 'TranslateController@test')->name('translate.test');
