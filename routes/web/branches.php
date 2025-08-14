<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BranchController;

Route::resource('branches', BranchController::class);