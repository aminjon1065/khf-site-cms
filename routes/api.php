<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\InstructionController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
 * Public, read-only API (prefix `api/v1`, configured in bootstrap/app.php).
 * Serves only published content to the external Next.js public site.
 * Locale is resolved per request by App\Http\Middleware\ResolveApiLocale.
 */

Route::get('health', [HealthController::class, 'health'])->name('api.health');
Route::get('ready', [HealthController::class, 'ready'])->name('api.ready');

// Home-page composition (enabled blocks + denormalized data).
Route::get('home', [HomeController::class, 'index'])->name('api.home');

// Site settings (footer/header) + navigation menus.
Route::get('settings', [SettingController::class, 'index'])->name('api.settings');
Route::get('menu', [MenuController::class, 'index'])->name('api.menu');
Route::get('search', [SearchController::class, 'index'])->middleware('throttle:60,1')->name('api.search');

// News & official statements.
Route::get('news', [NewsController::class, 'index'])->name('api.news.index');
Route::get('news/{slug}', [NewsController::class, 'show'])->name('api.news.show');

// Site content pages (informational pages).
Route::get('pages', [PageController::class, 'index'])->name('api.pages.index');
Route::get('pages/{slug}', [PageController::class, 'show'])->name('api.pages.show');

// Editorial taxonomy (content categories).
Route::get('categories', [CategoryController::class, 'index'])->name('api.categories.index');

// Population safety instructions (guides).
Route::get('instructions', [InstructionController::class, 'index'])->name('api.instructions.index');
Route::get('instructions/{slug}', [InstructionController::class, 'show'])->name('api.instructions.show');

// Official documents library.
Route::get('documents', [DocumentController::class, 'index'])->name('api.documents.index');

// Projects & programmes.
Route::get('projects', [ProjectController::class, 'index'])->name('api.projects.index');
Route::get('projects/{slug}', [ProjectController::class, 'show'])->name('api.projects.show');

// Announcements (vacancies & tenders).
Route::get('announcements', [AnnouncementController::class, 'index'])->name('api.announcements.index');
Route::get('announcements/{slug}', [AnnouncementController::class, 'show'])->name('api.announcements.show');

// Citizen submissions (electronic reception) — write path, rate-limited.
Route::post('submissions', [SubmissionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.submissions.store');

// Emergency alerts + region map status. `active` before `{slug}`.
Route::get('alerts', [AlertController::class, 'index'])->name('api.alerts.index');
Route::get('alerts/active', [AlertController::class, 'active'])->name('api.alerts.active');
Route::get('alerts/{slug}', [AlertController::class, 'show'])->name('api.alerts.show');
Route::get('regions', [RegionController::class, 'index'])->name('api.regions.index');
Route::get('regions/directory', [RegionController::class, 'directory'])->name('api.regions.directory');
