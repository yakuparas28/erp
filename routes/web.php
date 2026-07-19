<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('central.web.login'))->name('home');
