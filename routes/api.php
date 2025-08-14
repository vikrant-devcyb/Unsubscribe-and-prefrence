<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;

// Tag update endpoint
Route::post('update-preference', [CustomerController::class, 'updateCustomerTags']);
Route::get('/customer-tags', [CustomerController::class, 'getCustomerTags']);

// Preflight OPTIONS handler for CORS
Route::options('/update-preference', function (Request $request) {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', $request->headers->get('Origin') ?? '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->header('Vary', 'Origin');
});
