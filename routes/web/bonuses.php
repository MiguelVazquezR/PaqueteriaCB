<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BonusController;

Route::resource('bonuses', BonusController::class);