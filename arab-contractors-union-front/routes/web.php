<?php

use Illuminate\Support\Facades\Route;

// كل الطلبات تُعاد لـ Vue SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
