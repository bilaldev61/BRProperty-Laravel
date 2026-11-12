<?php

use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\AssistantController as AdminAssistantController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CallController as AdminCallController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/buying', [PageController::class, 'buying'])->name('buying');
Route::get('/selling', [PageController::class, 'selling'])->name('selling');
Route::get('/rentals', [PageController::class, 'rentals'])->name('rentals');
Route::get('/property-management', [PageController::class, 'management'])->name('management');
Route::get('/testimonials', [PageController::class, 'testimonials'])->name('testimonials');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

Route::get('/blog', [PageController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [PageController::class, 'post'])->name('blog.show');

Route::get('/for-sale', [PropertyController::class, 'index'])->name('for-sale');
Route::get('/for-rent', [PropertyController::class, 'rentals'])->name('for-rent');
Route::get('/recently-sold', [PropertyController::class, 'sold'])->name('sold');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');

Route::get('/assistant', [AssistantController::class, 'show'])->name('assistant.show');
Route::post('/assistant', [AssistantController::class, 'store'])
    ->middleware('throttle:assistant')
    ->name('assistant.store');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
|
| Sections are gated by the ability that owns them, never by role name, so a
| role that should reach a screen is a change in App\Enums\Role and nothing
| else. Reading and writing are separated throughout: an Agent answers
| enquiries without being able to publish a listing, an Editor publishes
| listings without reading anyone's enquiry.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', DashboardController::class)->middleware('ability:dashboard.view')->name('dashboard');

        Route::resource('properties', AdminPropertyController::class)
            ->only('index')
            ->middleware('ability:properties.view');

        Route::resource('properties', AdminPropertyController::class)
            ->except(['index', 'show'])
            ->middleware('ability:properties.manage');

        Route::delete('properties/{property}/images/{image}', [AdminPropertyController::class, 'destroyImage'])
            ->middleware('ability:properties.manage')
            ->name('properties.images.destroy');

        Route::middleware('ability:enquiries.view')->group(function () {
            Route::get('enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
            Route::get('enquiries/{enquiry}', [AdminEnquiryController::class, 'show'])->name('enquiries.show');
        });

        Route::middleware('ability:enquiries.manage')->group(function () {
            Route::patch('enquiries/{enquiry}', [AdminEnquiryController::class, 'update'])->name('enquiries.update');
            Route::post('enquiries/{enquiry}/draft', [AdminEnquiryController::class, 'draft'])
                ->middleware('throttle:20,1')
                ->name('enquiries.draft');
            Route::post('enquiries/{enquiry}/confirmation', [AdminEnquiryController::class, 'confirmation'])
                ->middleware('throttle:10,1')
                ->name('enquiries.confirmation');
        });

        Route::delete('enquiries/{enquiry}', [AdminEnquiryController::class, 'destroy'])
            ->middleware('ability:enquiries.delete')
            ->name('enquiries.destroy');

        Route::middleware('ability:calls.view')->group(function () {
            Route::get('calls', [AdminCallController::class, 'index'])->name('calls.index');
            Route::get('calls/{call}', [AdminCallController::class, 'show'])->name('calls.show');
        });

        Route::get('appointments', [AdminAppointmentController::class, 'index'])
            ->middleware('ability:appointments.view')
            ->name('appointments.index');

        Route::patch('appointments/{appointment}', [AdminAppointmentController::class, 'update'])
            ->middleware('ability:appointments.manage')
            ->name('appointments.update');

        Route::middleware('ability:assistant.view')->group(function () {
            Route::get('assistant', [AdminAssistantController::class, 'index'])->name('assistant.index');
            Route::get('assistant/questions', [AdminAssistantController::class, 'questions'])->name('assistant.questions');
            Route::get('assistant/usage', [AdminAssistantController::class, 'usage'])->name('assistant.usage');
            Route::get('assistant/{conversation}', [AdminAssistantController::class, 'show'])->name('assistant.show');
        });

        Route::resource('testimonials', AdminTestimonialController::class)
            ->except('show')
            ->middleware('ability:testimonials.manage');

        // Your own account: everyone signs in as themselves, so no ability applies.
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::post('profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');

        Route::middleware('ability:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
            Route::delete('settings/{section}', [SettingsController::class, 'reset'])->name('settings.reset');
        });

        Route::middleware('ability:team.manage')->group(function () {
            Route::get('team', [AdminTeamController::class, 'index'])->name('team.index');
            Route::post('team', [AdminTeamController::class, 'store'])->name('team.store');
            Route::patch('team/{user}', [AdminTeamController::class, 'update'])->name('team.update');
            Route::delete('team/{user}', [AdminTeamController::class, 'destroy'])->name('team.destroy');
        });
    });
});
