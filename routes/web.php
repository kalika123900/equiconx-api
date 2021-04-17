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

use Illuminate\Support\Facades\Artisan;

Route::get('/call-artisan',function(){
    $exitCode = Artisan::call('storage:link', [] );
    echo $exitCode;
});

Route::get('/oauth/callback',function(){
   print_r($_REQUEST); die();
});


Route::get('/', function () {
    return redirect('https://www.equiconx.com');
})->name('/');

Route::get('/verify-email/{link}', 'UserController@verify_email');
Route::get('/tax-rates', 'PlanController@tax_rates');
Route::get('/foo', function () {
     Artisan::call('storage:link');
});
