<?php

use App\Http\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/integrations/{type}/results', [IntegrationController::class, 'receive'])->middleware('throttle:60,1')->name('integrations.receive');
