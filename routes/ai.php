<?php

use App\Http\Controllers\McpTransportController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST', 'DELETE', 'PUT', 'PATCH', 'OPTIONS'], '/mcp', McpTransportController::class)->name('mcp.transport');
