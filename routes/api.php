<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
// use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Seller\ServiceController;
use App\Http\Controllers\SellerRequestController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'store']);
Route::post('/admin/login', [AdminLoginController::class, 'store']);

Route::post('/verify-email', [VerificationController::class, 'verify']);

Route::post('/forgot-password', [ForgotPasswordController::class, 'store']);
Route::post('/reset-password', [ResetPasswordController::class, 'store']);

Route::post('/seller/reviews', [ReviewController::class, 'getSellerReviews']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/top-rated', [ServiceController::class, 'topRated']);

Route::get('/guest/services', [BookingController::class, 'availableServices']);

Route::get('categories', [CategoryController::class, 'index']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    // Dashboard & Profile
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // Route::post('/auth/change-password', [PasswordController::class, 'changePassword']);
    Route::post('/user/submit-seller-request', [SellerRequestController::class, 'submitSellerRequest']);
    Route::put('/admin/approve-seller', [AdminUserController::class, 'approveSeller']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead']);

    // Reviews
    Route::post('/buyer/reviews', [ReviewController::class, 'store']);

    // Messages / Chat
    Route::post('/chat/send', [MessageController::class, 'store']);
    Route::post('/chat/history', [MessageController::class, 'getMessages']);

    // Seller Routes
    Route::prefix('seller')->group(function () {
        Route::post('/services', [ServiceController::class, 'store']);
        Route::post('/services/update', [ServiceController::class, 'update']);
        Route::post('/services/delete', [ServiceController::class, 'destroy']);
        Route::post('/bookings/change-status', [ServiceController::class, 'changeBookingStatus']);
    });

    // Buyer Routes
    Route::prefix('buyer')->group(function () {
        Route::post('/book-service', [BookingController::class, 'store']);
        Route::get('/my-orders', [BookingController::class, 'myBookings']);
    });

});

// Admin Routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Admin User Management
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/update', [AdminUserController::class, 'update']);
    Route::post('/users/delete', [AdminUserController::class, 'destroy']);
    Route::post('/users/toggle-ban', [AdminUserController::class, 'toggleBan']);
    Route::get('/dashboard-stats', [AdminUserController::class, 'getDashboardStats']);
    Route::post('broadcast', [AdminUserController::class, 'broadcastMessage']);
    Route::post('/users/approve', [AdminUserController::class, 'approveUser']);

    // Admin Category Management
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/update', [CategoryController::class, 'update']);
    Route::post('categories/delete', [CategoryController::class, 'destroy']);

});
