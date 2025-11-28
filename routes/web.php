<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PasswordResetLinkController;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\Role;

Route::get('/', function () {
    return view('home');
})->name('home');

// About Us page
Route::get('/about-us', function () {
    return view('about-us');
})->name('about-us');

Route::get('/settings', function () {
    return view('settings');
})->name('settings');

Route::middleware(['auth', Role::class . ':admin,seller'])
    ->get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

   

Route::middleware(['auth', Role::class . ':seller,admin'])
    ->get('/dashboard/client-data', [DashboardController::class, 'getClientData'])
    ->name('dashboard.clientData');




route::middleware('guest')->group(function(){
Route::get('/register', [AuthController::class, 'showRegister'])->name('register.form');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/login', [AuthController::class, 'showlogin'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login');
});

Route::post('/check-email', [AuthController::class, 'checkEmail'])->name('check.email');

Route::get('/payment', function () {
    return view('properties.payment');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/delete-pic', [ProfileController::class, 'deletePic'])->name('profile.deletePic');
    Route::post('/profile/check-password', [ProfileController::class, 'checkPassword'])->name('profile.checkPassword');
});

Route::get('/users-management', [UserController::class, 'usersManagement'])->middleware(Role::class .':admin')->name('users-management');

Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
//Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

Route::middleware(['auth'])->group(function () {
    Route::resource('properties', PropertyController::class)
        ->except(['edit', 'update', 'destroy']); 
});

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/property-details', function() {
    return view('properties.property-details');
})->name('property-details');

Route::post('/properties/{property}/reserve', [PropertyController::class, 'reserve'])
    ->name('properties.reserve');
Route::delete('/properties/{property}/reservation', [PropertyController::class, 'cancelReservation'])
    ->name('properties.cancelReservation');


// Property management routes


Route::middleware(['auth',Role::class.':seller,admin'])->group(function () {

    // Property management page
    Route::get('/property-management', [PropertyController::class, 'propertyManagement'])->name('property-management');

    // Store new property (AJAX or normal form)
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');

    // Edit property form (optional if using modal)
    //Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');

    // Update property (AJAX or normal form)
    Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');

    // Delete property (AJAX or normal form)
    Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
});

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.update');

Route::post('/notifications/mark-as-read', function () {
    Auth::user()->unreadNotifications->markAsRead();
    return response()->json(['success' => true]);
})->name('notifications.markAsRead')->middleware('auth');

Route::resource('users', UserController::class)->middleware(Role::class . ':admin');
