<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'application' => 'Electrical Shop API',
        'health_url' => url('/api/health'),
    ]);
});
