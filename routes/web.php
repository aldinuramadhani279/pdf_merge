<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [PDFController::class, 'index'])->name('home');
Route::get('/template', [PDFController::class, 'downloadTemplate'])->name('pdf.template');
Route::get('/download-result', [PDFController::class, 'downloadPdf'])->name('pdf.download.result');
Route::post('/merge', [PDFController::class, 'merge'])->name('pdf.merge');
