<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

Route::get('/login', function () {
    return view('pages.home');
})->name('login');

Route::post('/discuss', [DiscussionController::class, 'submit'])
    ->middleware('throttle:5,1');

Route::get('/d/{slug}', [DiscussionController::class, 'show']);

Route::get('/d/{slug}/comments', [CommentController::class, 'index']);

Route::post('/d/{slug}/comment', [CommentController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/d/{slug}/comments/{id}/vote', [VoteController::class, 'toggle']);

Route::post('/d/{slug}/comments/{id}/report', [ReportController::class, 'store']);

// Admin routes
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/comments/{id}/flag', [AdminController::class, 'flagComment'])->name('admin.comments.flag');
    Route::delete('/comments/{id}', [AdminController::class, 'deleteComment'])->name('admin.comments.delete');
});

