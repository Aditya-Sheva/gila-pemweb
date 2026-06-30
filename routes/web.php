<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\ReviewerController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChiefController;
use Illuminate\Support\Facades\Route;

// Halaman awal
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('landing');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

// Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Public download template
Route::get('/proposals/template', [ProposalController::class, 'downloadTemplate'])
    ->name('proposals.template');

// Protected routes
Route::middleware(['auth', 'check.active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Proposals
    Route::get('/proposals', [ProposalController::class, 'index'])
        ->name('proposals.index');

    Route::get('/proposals/create', [ProposalController::class, 'create'])
        ->name('proposals.create');

    Route::post('/proposals', [ProposalController::class, 'store'])
        ->name('proposals.store');

    Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])
        ->name('proposals.show');

    Route::get('/proposals/{proposal}/documents/{document}/download', [ProposalController::class, 'downloadDocument'])
        ->name('proposals.documents.download');

    Route::post('/proposals/{proposal}/upload', [ProposalController::class, 'uploadSupporting'])
        ->name('proposals.upload');

    Route::post('/proposals/{proposal}/resubmit', [ProposalController::class, 'resubmit'])
        ->name('proposals.resubmit');

    Route::post('/proposals/{proposal}/confirm-data', [ProposalController::class, 'confirmData'])
        ->name('proposals.confirm');

    Route::get('/proposals/{proposal}/certificate', [CertificateController::class, 'download'])
        ->name('proposals.certificate');

    // Sekretariat
    Route::middleware('role:sekretariat,admin')->group(function () {

        Route::post('/secretary/proposals/{proposal}/verify', [SecretaryController::class, 'verifyProposal'])
            ->name('secretary.verify');

        Route::post('/secretary/proposals/{proposal}/assign', [SecretaryController::class, 'assignReviewer'])
            ->name('secretary.assign');

        Route::post('/secretary/proposals/{proposal}/decision', [SecretaryController::class, 'makeDecision'])
            ->name('secretary.decision');
    });

    // Admin
    Route::middleware('role:admin')->group(function () {

        Route::get('/admin/pending-users', [AdminController::class, 'pendingUsers'])
            ->name('admin.pending-users');

        Route::post('/admin/users/{user}/activate', [AdminController::class, 'activateUser'])
            ->name('admin.activate');

        Route::get('/admin/incoming-proposals', [AdminController::class, 'incomingIndex'])
            ->name('admin.incoming.index');

        Route::get('/admin/incoming-proposals/create', [AdminController::class, 'incomingCreate'])
            ->name('admin.incoming.create');

        Route::post('/admin/incoming-proposals', [AdminController::class, 'incomingStore'])
            ->name('admin.incoming.store');

        Route::get('/admin/incoming-proposals/{proposal}/edit', [AdminController::class, 'incomingEdit'])
            ->name('admin.incoming.edit');

        Route::put('/admin/incoming-proposals/{proposal}', [AdminController::class, 'incomingUpdate'])
            ->name('admin.incoming.update');

        Route::delete('/admin/incoming-proposals/{proposal}', [AdminController::class, 'incomingDestroy'])
            ->name('admin.incoming.destroy');

        Route::get('/admin/templates', [AdminController::class, 'templates'])
            ->name('admin.templates');

        Route::post('/admin/templates', [AdminController::class, 'uploadTemplate'])
            ->name('admin.templates.upload');

        Route::post('/admin/proposals/{proposal}/assign-secretary', [AdminController::class, 'assignSecretary'])
            ->name('admin.assign-secretary');

        Route::get('/admin/monitoring', [AdminController::class, 'monitoring'])
            ->name('admin.monitoring');

        Route::get('/admin/ethics', [AdminController::class, 'ethicsQueue'])
            ->name('admin.ethics');

        Route::post('/admin/proposals/{proposal}/send-confirmation', [AdminController::class, 'sendForConfirmation'])
            ->name('admin.send-confirmation');

        Route::post('/admin/proposals/{proposal}/publish', [AdminController::class, 'publish'])
            ->name('admin.publish');
    });

    // Reviewer
    Route::middleware('role:reviewer')->group(function () {

        Route::get('/reviewer', [ReviewerController::class, 'index'])
            ->name('reviewer.index');

        Route::post('/reviewer/reviews/{review}/feedback', [ReviewerController::class, 'submitFeedback'])
            ->name('reviewer.feedback');
    });

    // Ketua
    Route::middleware('role:ketua')->group(function () {

        Route::get('/chief', [ChiefController::class, 'index'])
            ->name('chief.index');

        Route::post('/chief/proposals/{proposal}/signature', [ChiefController::class, 'uploadSignature'])
            ->name('chief.signature');
    });
});