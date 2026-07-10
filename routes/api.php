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
use App\Http\Controllers\SavedServiceController;
use App\Http\Controllers\SavedSellerController;
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
Route::get('/service-tags', [ServiceController::class, 'tags']);
Route::get('/payment-methods', [BookingController::class, 'paymentMethods']);

Route::get('/guest/services', [BookingController::class, 'availableServices']);

Route::get('categories', [CategoryController::class, 'index']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    // Dashboard & Profile
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/delete-details', [ProfileController::class, 'deleteDetails']);
    // Route::post('/auth/change-password', [PasswordController::class, 'changePassword']);
    Route::post('/user/submit-seller-request', [SellerRequestController::class, 'submitSellerRequest']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-one-as-read', [NotificationController::class, 'markOneAsRead']);
    Route::post('/notifications/delete', [NotificationController::class, 'destroy']);

    // Reviews
    Route::post('/buyer/reviews', [ReviewController::class, 'store']);

    // Messages / Chat
    Route::post('/chat/send', [MessageController::class, 'store']);
    Route::post('/chat/history', [MessageController::class, 'getMessages']);
    Route::get('/chat/conversations', [MessageController::class, 'conversations']);
    Route::get('/chat/unread', [MessageController::class, 'unread']);
    Route::post('/chat/mark-as-read', [MessageController::class, 'markAsRead']);

    // Seller Routes
    Route::prefix('seller')->group(function () {
        Route::get('/dashboard', [ServiceController::class, 'dashboard']);
        Route::get('/analytics', [ServiceController::class, 'analytics']);
        Route::get('/earnings', [ServiceController::class, 'earnings']);
        Route::get('/services', [ServiceController::class, 'myServices']);
        Route::get('/services/show/{service_id?}', [ServiceController::class, 'showOwnService']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::post('/services/update', [ServiceController::class, 'update']);
        Route::post('/services/delete', [ServiceController::class, 'destroy']);
        Route::post('/services/change-status', [ServiceController::class, 'changeServiceStatus']);
        Route::get('/bookings', [ServiceController::class, 'bookings']);
        Route::get('/bookings/show/{booking_id?}', [ServiceController::class, 'showBooking']);
        Route::post('/bookings/change-status', [ServiceController::class, 'changeBookingStatus']);
    });

    // Buyer Routes
    Route::prefix('buyer')->group(function () {
        Route::post('/book-service', [BookingController::class, 'store']);
        Route::get('/my-orders', [BookingController::class, 'myBookings']);
        Route::get('/bookings/show/{booking_id?}', [BookingController::class, 'show']);
        Route::post('/bookings/accept-completion', [BookingController::class, 'acceptCompletion']);
        Route::get('/services/show/{service_id?}', [ServiceController::class, 'show']);
        Route::get('/stats', [BookingController::class, 'buyerStats']);
        Route::get('/saved-services', [SavedServiceController::class, 'index']);
        Route::post('/save-service', [SavedServiceController::class, 'store']);
        Route::post('/saved-services/delete', [SavedServiceController::class, 'destroy']);
        Route::get('/saved-sellers', [SavedSellerController::class, 'index']);
        Route::post('/save-seller', [SavedSellerController::class, 'store']);
        Route::post('/saved-sellers/delete', [SavedSellerController::class, 'destroy']);
    });

});

// Admin Routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Admin Auth
    Route::get('/profile', [AdminLoginController::class, 'profile']);
    Route::post('/profile/update', [AdminLoginController::class, 'updateProfile']);
    Route::post('/profile/delete-details', [AdminLoginController::class, 'deleteProfileDetails']);
    Route::post('/logout', [AdminLoginController::class, 'logout']);

    // Admin User Management
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/show/{user_id?}', [AdminUserController::class, 'show']);
    Route::put('/users/update', [AdminUserController::class, 'update']);
    Route::post('/users/delete', [AdminUserController::class, 'destroy']);
    Route::post('/users/toggle-ban', [AdminUserController::class, 'toggleBan']);
    Route::get('/dashboard-stats', [AdminUserController::class, 'getDashboardStats']);
    Route::get('/services', [AdminUserController::class, 'services']);
    Route::get('/services/show/{service_id?}', [AdminUserController::class, 'showService']);
    Route::post('/services/delete', [AdminUserController::class, 'deleteService']);
    Route::post('broadcast', [AdminUserController::class, 'broadcastMessage']);
    Route::put('/approve-seller', [AdminUserController::class, 'approveSeller']);
    Route::put('/reject-seller', [AdminUserController::class, 'rejectSeller']);
    Route::get('/seller-requests', [AdminUserController::class, 'sellerRequests']);
    Route::get('/seller-requests/show/{user_id?}', [AdminUserController::class, 'showSellerRequest']);

    // Admin Category Management
    Route::post('categories/create', [CategoryController::class, 'store']);
    Route::post('categories/update', [CategoryController::class, 'update']);
    Route::post('categories/delete', [CategoryController::class, 'destroy']);

});
