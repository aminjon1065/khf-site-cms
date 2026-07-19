<?php

use App\Http\Controllers\Cms\ActivityController;
use App\Http\Controllers\Cms\AlertController;
use App\Http\Controllers\Cms\ApprovalController;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\LocaleController;
use App\Http\Controllers\Cms\NotificationController;
use App\Http\Controllers\Cms\SectionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect('/dashboard'))->name('home');

// Interface language toggle (available to guests on the login screen).
Route::post('locale', LocaleController::class)->name('locale');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Alerts — the reference module.
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('alerts/create', [AlertController::class, 'create'])->name('alerts.create');
    Route::post('alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('alerts/{alert}/edit', [AlertController::class, 'edit'])->name('alerts.edit');
    Route::put('alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
    Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::post('alerts/{alert}/duplicate', [AlertController::class, 'duplicate'])->name('alerts.duplicate');
    Route::post('alerts/{alert}/publish', [AlertController::class, 'publish'])->name('alerts.publish');
    Route::post('alerts/{alert}/unpublish', [AlertController::class, 'unpublish'])->name('alerts.unpublish');

    // Approval center
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::post('approvals/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/return', [ApprovalController::class, 'returnToAuthor'])->name('approvals.return');

    // Activity log
    Route::get('activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('activity/export', [ActivityController::class, 'export'])->name('activity.export');

    // Notifications
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Sections not yet built out — render the generic "Раздел в структуре" screen.
    $stubs = [
        'control' => 'Центр контроля',
        'news' => 'Новости',
        'instructions' => 'Инструкции населению',
        'documents' => 'Документы',
        'media' => 'Медиабиблиотека',
        'home-blocks' => 'Главная страница',
        'users' => 'Пользователи',
        'roles' => 'Роли и права',
        'settings' => 'Настройки',
        'profile' => 'Профиль пользователя',
    ];
    foreach ($stubs as $path => $title) {
        Route::get($path, fn () => Inertia::render('section', ['sectionKey' => $path, 'title' => $title]))
            ->name(str_replace('-', '_', $path));
    }

    Route::get('section/{key}', SectionController::class)->name('section');
});
