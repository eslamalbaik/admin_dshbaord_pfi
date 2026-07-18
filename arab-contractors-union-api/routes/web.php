<?php

use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/up', fn() => response()->json(['status' => 'ok', 'app' => 'Arab Contractors Union API']));
