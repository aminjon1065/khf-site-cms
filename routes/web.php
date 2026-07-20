<?php

use App\Http\Controllers\Cms\ActivityController;
use App\Http\Controllers\Cms\AlertController;
use App\Http\Controllers\Cms\AnnouncementController;
use App\Http\Controllers\Cms\ApprovalController;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\DocumentController;
use App\Http\Controllers\Cms\HomeBlockController;
use App\Http\Controllers\Cms\InstructionController;
use App\Http\Controllers\Cms\LocaleController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\MenuController;
use App\Http\Controllers\Cms\NewsController;
use App\Http\Controllers\Cms\NotificationController;
use App\Http\Controllers\Cms\PageController;
use App\Http\Controllers\Cms\ProjectController;
use App\Http\Controllers\Cms\RegionController;
use App\Http\Controllers\Cms\RoleController;
use App\Http\Controllers\Cms\SectionController;
use App\Http\Controllers\Cms\SettingController;
use App\Http\Controllers\Cms\SubmissionController;
use App\Http\Controllers\Cms\TaxonomyController;
use App\Http\Controllers\Cms\UserController;
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

    // News & official statements — full editorial CRUD + workflow.
    Route::get('news', [NewsController::class, 'index'])->name('news.index');
    Route::get('news/create', [NewsController::class, 'create'])->name('news.create');
    Route::post('news', [NewsController::class, 'store'])->name('news.store');
    Route::get('news/{news}/edit', [NewsController::class, 'edit'])->name('news.edit');
    Route::put('news/{news}', [NewsController::class, 'update'])->name('news.update');
    Route::delete('news/{news}', [NewsController::class, 'destroy'])->name('news.destroy');
    Route::post('news/{news}/duplicate', [NewsController::class, 'duplicate'])->name('news.duplicate');
    Route::post('news/{news}/publish', [NewsController::class, 'publish'])->name('news.publish');
    Route::post('news/{news}/unpublish', [NewsController::class, 'unpublish'])->name('news.unpublish');

    // Instructions to the public — structured safety guides.
    Route::get('instructions', [InstructionController::class, 'index'])->name('instructions.index');
    Route::get('instructions/create', [InstructionController::class, 'create'])->name('instructions.create');
    Route::post('instructions', [InstructionController::class, 'store'])->name('instructions.store');
    Route::get('instructions/{instruction}/edit', [InstructionController::class, 'edit'])->name('instructions.edit');
    Route::put('instructions/{instruction}', [InstructionController::class, 'update'])->name('instructions.update');
    Route::delete('instructions/{instruction}', [InstructionController::class, 'destroy'])->name('instructions.destroy');
    Route::post('instructions/{instruction}/duplicate', [InstructionController::class, 'duplicate'])->name('instructions.duplicate');
    Route::post('instructions/{instruction}/publish', [InstructionController::class, 'publish'])->name('instructions.publish');
    Route::post('instructions/{instruction}/unpublish', [InstructionController::class, 'unpublish'])->name('instructions.unpublish');

    // Official documents — per-language file library.
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('documents/{document}/duplicate', [DocumentController::class, 'duplicate'])->name('documents.duplicate');
    Route::post('documents/{document}/publish', [DocumentController::class, 'publish'])->name('documents.publish');
    Route::post('documents/{document}/unpublish', [DocumentController::class, 'unpublish'])->name('documents.unpublish');

    // Projects & programmes.
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    Route::post('projects/{project}/publish', [ProjectController::class, 'publish'])->name('projects.publish');
    Route::post('projects/{project}/unpublish', [ProjectController::class, 'unpublish'])->name('projects.unpublish');

    // Announcements — vacancies & tenders.
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('announcements/{announcement}/duplicate', [AnnouncementController::class, 'duplicate'])->name('announcements.duplicate');
    Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('announcements.publish');
    Route::post('announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->name('announcements.unpublish');

    // Regional reference data (regions & their curated districts).
    Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('regions/create', [RegionController::class, 'create'])->name('regions.create');
    Route::post('regions', [RegionController::class, 'store'])->name('regions.store');
    Route::get('regions/{region}/edit', [RegionController::class, 'edit'])->name('regions.edit');
    Route::put('regions/{region}', [RegionController::class, 'update'])->name('regions.update');
    Route::delete('regions/{region}', [RegionController::class, 'destroy'])->name('regions.destroy');

    // Media library (browse all media, upload reusable assets, remove them).
    Route::get('media', [MediaController::class, 'index'])->name('media');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    // JSON endpoints for the in-editor media picker (list + inline upload).
    Route::get('media/library', [MediaController::class, 'library'])->name('media.library');
    Route::post('media/library', [MediaController::class, 'upload'])->name('media.upload');
    Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Editorial taxonomy manager (news categories + tags).
    Route::get('taxonomy', [TaxonomyController::class, 'index'])->name('taxonomy');
    Route::put('taxonomy', [TaxonomyController::class, 'update'])->name('taxonomy.update');

    // Site content pages (informational pages under the editorial workflow).
    Route::get('pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
    Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');

    // Home page composition manager.
    Route::get('home-blocks', [HomeBlockController::class, 'index'])->name('home_blocks');
    Route::put('home-blocks', [HomeBlockController::class, 'update'])->name('home_blocks.update');

    // Site settings & navigation menus.
    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('menu', [MenuController::class, 'index'])->name('menu');
    Route::put('menu', [MenuController::class, 'update'])->name('menu.update');

    // Citizen submissions (electronic reception).
    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::put('submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
    Route::post('submissions/{submission}/comments', [SubmissionController::class, 'comment'])->name('submissions.comment');
    Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');

    // Users & roles administration.
    Route::get('users', [UserController::class, 'index'])->name('users');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('roles', [RoleController::class, 'index'])->name('roles');

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
        'profile' => 'Профиль пользователя',
    ];
    foreach ($stubs as $path => $title) {
        Route::get($path, fn () => Inertia::render('section', ['sectionKey' => $path, 'title' => $title]))
            ->name(str_replace('-', '_', $path));
    }

    Route::get('section/{key}', SectionController::class)->name('section');
});
