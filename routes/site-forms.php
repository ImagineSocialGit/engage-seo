<?php

use App\Features\Forms\Http\Controllers\SiteFormSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:8,1'])
    ->post('/_site-forms/{formKey}', SiteFormSubmissionController::class)
    ->where('formKey', '[a-z][a-z0-9_]*')
    ->name('site.forms.store');