<?php

use EmplifySoftware\StatamicCookieDialog\Http\Controllers\CookieDialogController;
use Illuminate\Support\Facades\Route;

Route::prefix('cookie-dialog')->name('cookie-dialog.')->group(function () {
    Route::get('/', [CookieDialogController::class, 'edit'])->name('edit');
    Route::post('/', [CookieDialogController::class, 'update'])->name('update');
});
