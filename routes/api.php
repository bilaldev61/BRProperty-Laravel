<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Middleware\AuthenticateAutomation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phone agent API (/api/v1)
|--------------------------------------------------------------------------
|
| Called by the Retell voice agent (during a call) and by n8n (after it).
| One shared token, set in AUTOMATION_API_TOKEN; closed until it is set.
| Every in-call endpoint accepts GET or POST, so a Retell custom function
| (always POST, {name, call, args}) and an n8n HTTP node both fit.
| Full reference: docs/phone-agent.md
|
*/

Route::prefix('v1')
    ->middleware([AuthenticateAutomation::class, 'throttle:120,1'])
    ->group(function () {
        // During the call
        Route::match(['get', 'post'], 'properties/search', [PropertyController::class, 'search'])->name('api.properties.search');
        Route::match(['get', 'post'], 'properties/details/{slug?}', [PropertyController::class, 'show'])->name('api.properties.show');
        Route::match(['get', 'post'], 'leads/lookup', [LeadController::class, 'lookup'])->name('api.leads.lookup');
        Route::match(['get', 'post'], 'appointments/availability', [AppointmentController::class, 'availability'])->name('api.appointments.availability');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('api.appointments.store');

        // After the call
        Route::post('calls', [CallController::class, 'store'])->name('api.calls.store');
        Route::get('leads/{enquiry}/matches', [LeadController::class, 'matches'])->name('api.leads.matches');
    });
