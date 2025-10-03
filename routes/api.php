<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CollaborationRequestController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\EarningsDashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReminderController;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);
Route::get('/auth/{provider}/redirect', [SocialController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/users/{id}/follow', [ProfileController::class, 'follow']);
    Route::delete('/users/{id}/unfollow', [ProfileController::class, 'unfollow']);
    Route::get('/users/{id}', [ProfileController::class, 'show']);

    // Message routes
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::patch('/messages/{message}/status', [MessageController::class, 'updateStatus']);
    Route::post('/messages/typing', [MessageController::class, 'typing']);

    // Review routes
    Route::post('collaborations/{collaborationRequest}/reviews', [ReviewController::class, 'store']);
    Route::post('reviews/{review}/flag', [ReviewController::class, 'flag']);
    Route::get('users/{user}/reviews', [ReviewController::class, 'userReviews']);
    
    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::get('admin/reviews/flagged', [ReviewController::class, 'flaggedReviews']);
        Route::post('admin/reviews/{review}', [ReviewController::class, 'adminReview']);
    });

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'delete']);

    // Notification preferences
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

    // Profile Routes
    Route::get('/profile/completion', [ProfileController::class, 'getProfileStatus']);
    Route::post('/profile/change-password', [ProfileController::class, 'managePassword']);
    Route::get('/profile/privacy-visibility-controls', [ProfileController::class, 'Privacy_VisibilityControls']);
    Route::post('/profile/privacy-visibility-controls', [ProfileController::class, 'Privacy_VisibilityControls']);

    // Dispute routes
    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::post('/disputes', [DisputeController::class, 'store']);
    Route::get('/disputes/{id}', [DisputeController::class, 'show']);
    Route::post('/disputes/{id}/respond', [DisputeController::class, 'respond']);
    Route::post('/disputes/{id}/close', [DisputeController::class, 'close']);

    // Report routes
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);

    // Reminder routes
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::get('/reminders/{id}', [ReminderController::class, 'show']);
    Route::post('/reminders/{id}/dismiss', [ReminderController::class, 'dismiss']);

    // Payment Method Routes
    Route::get('/payment-methods', [\App\Http\Controllers\PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [\App\Http\Controllers\PaymentMethodController::class, 'store']);
    Route::delete('/payment-methods/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'destroy']);
});

Route::prefix('collaborations')->group(function () {
    Route::get('/', [CollaborationRequestController::class, 'index']);
    Route::get('/by-email', [CollaborationRequestController::class, 'collaborationsByEmail']);
    Route::get('/{id}', [CollaborationRequestController::class, 'show']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CollaborationRequestController::class, 'update']);
        Route::post('/autosave', [CollaborationRequestController::class, 'autosave']);
        Route::post('/{id}/close', [CollaborationRequestController::class, 'close']);
        Route::post('/{id}/cancel', [CollaborationRequestController::class, 'cancel']);
    });
});

Route::middleware('auth:sanctum')->prefix('applications')->group(function () {
    Route::post('/intent/{collaboration}', [ApplicationController::class, 'createPaymentIntent']);
    Route::post('/{collaboration}', [ApplicationController::class, 'store']);
    Route::put('/{application}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/{application}/withdraw', [ApplicationController::class, 'withdraw']);
});

// Stripe Webhooks
Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Earnings Dashboard
    Route::get('earnings', [EarningsDashboardController::class, 'index']);
});

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users/{user}/status', [AdminController::class, 'updateUserStatus']);
    Route::post('/moderate', [AdminController::class, 'moderateContent']);
    Route::post('/collaborations/{collaborationRequest}', [AdminController::class, 'handleCollaborationRequest']);
    Route::post('/disputes/resolve', [AdminController::class, 'resolveDispute']);
    Route::get('/payments', [AdminController::class, 'paymentLogs']);
    Route::get('/logs', [AdminController::class, 'adminLogs']);

    // Admin Dispute routes
    Route::get('/disputes', [DisputeController::class, 'adminIndex']);
    Route::post('/disputes/{id}/resolve', [DisputeController::class, 'adminResolve']);
    Route::post('/disputes/{id}/review', [DisputeController::class, 'adminReview']);

    // Admin Report routes
    Route::get('/reports', [ReportController::class, 'adminIndex']);
    Route::post('/reports/{id}/review', [ReportController::class, 'adminReview']);
    Route::post('/reports/{id}/mark-review', [ReportController::class, 'adminMarkReview']);
    Route::get('/reports/stats', [ReportController::class, 'adminStats']);

    // Admin Reminder routes
    Route::get('/reminders', [ReminderController::class, 'adminIndex']);
    Route::post('/reminders/send-due', [ReminderController::class, 'adminSendDue']);
    Route::post('/reminders/{id}/send', [ReminderController::class, 'adminSend']);
    Route::post('/reminders/{id}/cancel', [ReminderController::class, 'adminCancel']);
    Route::post('/reminders/schedule', [ReminderController::class, 'adminSchedule']);
    Route::get('/reminders/stats', [ReminderController::class, 'adminStats']);
});