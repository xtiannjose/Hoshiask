<?php

use App\Http\Controllers\Admin\CannedResponseController;
use App\Http\Controllers\Admin\CustomReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentManagementController;
use App\Http\Controllers\Admin\KnowledgeArticleController as AdminKnowledgeArticleController;
use App\Http\Controllers\Admin\ManagedUserAccountController;
use App\Http\Controllers\Admin\ManagedUserCredentialController;
use App\Http\Controllers\Admin\ManagedUserProfileController;
use App\Http\Controllers\Admin\OnlineUserController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TicketActionController;
use App\Http\Controllers\Admin\TicketAssignmentRuleController;
use App\Http\Controllers\Admin\TicketBulkActionController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TicketReplyController as AdminTicketReplyController;
use App\Http\Controllers\Admin\TicketRequesterAccountController;
use App\Http\Controllers\Admin\TicketSavedViewController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\KnowledgeArticleController as ClientKnowledgeArticleController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use App\Http\Controllers\Client\TicketConversationController as ClientTicketConversationController;
use App\Http\Controllers\Client\TicketResolutionController as ClientTicketResolutionController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocationOptionsController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
})->name('home');

Route::get('/health', HealthCheckController::class)->middleware('throttle:30,1')->name('health');

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/ticket-consent', [LegalController::class, 'ticketConsent'])->name('ticket-consent');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(app()->environment('production') ? 'throttle:5,1' : 'throttle:100,1')
    ->name('login.attempt');
Route::get('/register', fn () => redirect('/login'))->name('register');
Route::post('/register', fn () => redirect('/login'))->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:20,1')->name('logout');

Route::middleware(['auth', 'active', 'consent.accepted', 'throttle:120,1'])
    ->get('/locations/municipalities', LocationOptionsController::class)
    ->name('locations.municipalities');

Route::middleware(['auth', 'active'])->prefix('legal')->name('legal.')->group(function () {
    Route::get('/acceptance', [LegalController::class, 'showAcceptance'])->name('acceptance.show');
    Route::post('/acceptance', [LegalController::class, 'accept'])->middleware('throttle:10,1')->name('acceptance.store');
});

// Account Settings
Route::middleware(['auth', 'active', 'consent.accepted', 'role:ticket_console'])->group(function () {
    Route::get('/account/settings', [AuthController::class, 'accountSettings'])->name('account.settings');
    Route::put('/account/settings', [AuthController::class, 'updateAccountSettings'])->middleware('throttle:20,1')->name('account.settings.update');
});

// Client Routes
Route::middleware(['auth', 'active', 'consent.accepted', 'role:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('/knowledge-base', [ClientKnowledgeArticleController::class, 'index'])->name('knowledge-base.index');
    Route::get('/knowledge-base/{knowledgeArticle}', [ClientKnowledgeArticleController::class, 'show'])->name('knowledge-base.show');

    Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [ClientTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [ClientTicketController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [ClientTicketConversationController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [ClientTicketConversationController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/{ticket}/reply', [ClientTicketConversationController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [ClientTicketConversationController::class, 'updateReply'])
        ->middleware('throttle:30,1')
        ->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [ClientTicketConversationController::class, 'deleteReply'])
        ->middleware('throttle:30,1')
        ->name('tickets.replies.delete');
    Route::post('/tickets/{ticket}/resolve', [ClientTicketResolutionController::class, 'resolve'])
        ->middleware('throttle:15,1')
        ->name('tickets.resolve');
    Route::post('/tickets/{ticket}/rate', [ClientTicketResolutionController::class, 'rate'])
        ->middleware('throttle:15,1')
        ->name('tickets.rate');
    Route::post('/tickets/{ticket}/reopen', [ClientTicketResolutionController::class, 'reopen'])
        ->middleware('throttle:15,1')
        ->name('tickets.reopen');
    Route::post('/tickets/{ticket}/cancel', [ClientTicketResolutionController::class, 'cancel'])
        ->middleware('throttle:15,1')
        ->name('tickets.cancel');

    Route::post('/notifications/dismiss', [NotificationController::class, 'clientDismiss'])
        ->middleware('throttle:60,1')
        ->name('notifications.dismiss');
    Route::post('/notifications/clear', [NotificationController::class, 'clientClear'])
        ->middleware('throttle:30,1')
        ->name('notifications.clear');
    Route::post('/notifications/seen/{ticket}', [NotificationController::class, 'clientSeen'])
        ->middleware('throttle:120,1')
        ->name('notifications.seen');
    Route::get('/notifications/open/{ticket}', [NotificationController::class, 'clientOpen'])
        ->name('notifications.open');
});

// Admin Routes
Route::middleware(['auth', 'active', 'consent.accepted', 'role:ticket_console', 'password.change.required'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->name('dashboard');
    Route::get('/reports', [AdminReportController::class, 'index'])
        ->middleware('role:admin_level')
        ->name('reports.index');
    Route::get('/reports/monthly/pdf', [AdminReportController::class, 'monthlyPdf'])
        ->middleware('role:admin_level')
        ->name('reports.monthly.pdf');
    Route::get('/reports/monthly/csv', [AdminReportController::class, 'monthlyCsv'])
        ->middleware('role:admin_level')
        ->name('reports.monthly.csv');
    Route::get('/reports/custom', [CustomReportController::class, 'index'])
        ->middleware('role:admin_level')
        ->name('reports.custom.index');
    Route::get('/reports/custom/csv', [CustomReportController::class, 'csv'])
        ->middleware(['role:admin_level', 'throttle:10,1'])
        ->name('reports.custom.csv');
    Route::get('/reports/custom/pdf', [CustomReportController::class, 'pdf'])
        ->middleware(['role:admin_level', 'throttle:10,1'])
        ->name('reports.custom.pdf');
    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [AdminTicketController::class, 'create'])
        ->middleware('role:ticket_creator')
        ->name('tickets.create');
    Route::post('/tickets', [AdminTicketController::class, 'store'])
        ->middleware(['throttle:20,1', 'role:ticket_creator'])
        ->name('tickets.store');
    Route::get('/tickets/export', [AdminTicketController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('tickets.export');
    // Searched as the person types. 90 is used by no other route, so this has a counter of its own (equal limits share one).
    Route::get('/tickets/requester-accounts', TicketRequesterAccountController::class)
        ->middleware(['throttle:90,1', 'role:ticket_creator'])
        ->name('tickets.requester-accounts');
    Route::post('/ticket-saved-views', [TicketSavedViewController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('ticket-saved-views.store');
    Route::delete('/ticket-saved-views/{ticketSavedView}', [TicketSavedViewController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('ticket-saved-views.destroy');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [AdminTicketReplyController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/{ticket}/acknowledge', [TicketActionController::class, 'acknowledge'])
        ->middleware('throttle:60,1')
        ->name('tickets.acknowledge');
    Route::post('/tickets/bulk-action', TicketBulkActionController::class)
        ->middleware('throttle:20,1')
        ->name('tickets.bulk-action');
    Route::post('/tickets/{ticket}/quick-update', [TicketActionController::class, 'quickUpdate'])
        ->middleware('throttle:60,1')
        ->name('tickets.quick-update');
    Route::post('/tickets/{ticket}/assign', [TicketActionController::class, 'assign'])
        ->middleware('throttle:60,1')
        ->name('tickets.assign');
    Route::post('/tickets/{ticket}/escalate', [TicketActionController::class, 'escalate'])
        ->middleware('throttle:60,1')
        ->name('tickets.escalate');
    Route::post('/tickets/{ticket}/escalate/clear', [TicketActionController::class, 'clearEscalation'])
        ->middleware('throttle:60,1')
        ->name('tickets.escalate.clear');
    Route::post('/tickets/{ticket}/status', [TicketActionController::class, 'updateStatus'])
        ->middleware('throttle:60,1')
        ->name('tickets.status');
    Route::post('/tickets/{ticket}/severity', [TicketActionController::class, 'updateSeverity'])
        ->middleware('throttle:60,1')
        ->name('tickets.severity');
    Route::post('/tickets/{ticket}/type', [TicketActionController::class, 'updateType'])
        ->middleware(['throttle:60,1', 'role:super_user'])
        ->name('tickets.type');
    Route::post('/tickets/{ticket}/dates', [TicketActionController::class, 'updateDates'])
        ->middleware(['throttle:60,1', 'role:admin_level'])
        ->name('tickets.dates');
    Route::post('/tickets/{ticket}/department', [TicketActionController::class, 'updateDepartment'])
        ->middleware(['throttle:60,1', 'role:admin_level'])
        ->name('tickets.department');
    Route::delete('/tickets/{ticket}', [TicketActionController::class, 'destroy'])
        ->middleware(['throttle:20,1', 'role:system_admin'])
        ->name('tickets.destroy');
    Route::post('/tickets/{ticket}/reply', [AdminTicketReplyController::class, 'reply'])
        ->middleware('throttle:60,1')
        ->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [AdminTicketReplyController::class, 'updateReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [AdminTicketReplyController::class, 'deleteReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.delete');
    Route::post('/notifications/dismiss', [NotificationController::class, 'adminDismiss'])
        ->middleware('throttle:60,1')
        ->name('notifications.dismiss');
    Route::post('/notifications/clear', [NotificationController::class, 'adminClear'])
        ->middleware('throttle:30,1')
        ->name('notifications.clear');
    Route::post('/notifications/seen/{ticket}', [NotificationController::class, 'adminSeen'])
        ->middleware('throttle:120,1')
        ->name('notifications.seen');
    Route::get('/notifications/open/{ticket}', [NotificationController::class, 'adminOpen'])
        ->name('notifications.open');

    Route::get('/canned-responses/list', [CannedResponseController::class, 'list'])
        ->middleware('throttle:60,1')
        ->name('canned-responses.list');

    Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
        Route::get('/', [AdminKnowledgeArticleController::class, 'index'])->name('index');
        Route::get('/create', [AdminKnowledgeArticleController::class, 'create'])->name('create');
        Route::post('/', [AdminKnowledgeArticleController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('store');
        Route::get('/{knowledgeArticle}/edit', [AdminKnowledgeArticleController::class, 'edit'])->name('edit');
        Route::put('/{knowledgeArticle}', [AdminKnowledgeArticleController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('update');
        Route::delete('/{knowledgeArticle}', [AdminKnowledgeArticleController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('destroy');
        Route::get('/{knowledgeArticle}', [AdminKnowledgeArticleController::class, 'show'])->name('show');
    });

    Route::middleware('role:shadow')->prefix('system-logs')->name('system-logs.')->group(function () {
        Route::get('/unlock', [SystemLogController::class, 'showUnlockForm'])->name('unlock.show');
        Route::post('/unlock', [SystemLogController::class, 'unlock'])
            ->middleware('throttle:3,1')
            ->name('unlock.store');
        Route::post('/lock', [SystemLogController::class, 'lock'])
            ->middleware('throttle:20,1')
            ->name('lock');
        Route::get('/', [SystemLogController::class, 'index'])
            ->middleware('system_logs.unlocked')
            ->name('index');
        Route::get('/export', [SystemLogController::class, 'export'])
            ->middleware(['system_logs.unlocked', 'throttle:10,1'])
            ->name('export');
    });

    Route::middleware(['role:shadow', 'system_logs.unlocked'])->prefix('online-users')->name('online-users.')->group(function () {
        Route::get('/', [OnlineUserController::class, 'index'])->name('index');
        Route::get('/live', [OnlineUserController::class, 'live'])
            ->middleware('throttle:30,1')
            ->name('live');
        Route::post('/lockouts/clear', [OnlineUserController::class, 'clearLockout'])
            ->middleware('throttle:20,1')
            ->name('lockouts.clear');
        Route::post('/{user}/sign-out', [OnlineUserController::class, 'signOut'])
            ->middleware('throttle:20,1')
            ->name('sign-out');
    });

    // User Management Routes
    Route::middleware('role:admin_level')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/clients', [UserManagementController::class, 'clients'])->name('users.clients');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::get('/users/{user}', [ManagedUserProfileController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [ManagedUserProfileController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [ManagedUserProfileController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('users.update');
        Route::delete('/users/{user}', [ManagedUserAccountController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [ManagedUserAccountController::class, 'toggleStatus'])
            ->middleware('throttle:30,1')
            ->name('users.toggle-status');
        Route::post('/users/{user}/password/reset-default', [ManagedUserCredentialController::class, 'resetManagedUserPassword'])
            ->middleware(['throttle:20,1', 'role:shadow'])
            ->name('users.password.reset-default');
        Route::post('/users/{user}/password/reveal-temporary', [ManagedUserCredentialController::class, 'revealManagedUserPassword'])
            ->middleware(['throttle:20,1', 'role:shadow'])
            ->name('users.password.reveal-temporary');

        Route::get('/canned-responses', [CannedResponseController::class, 'index'])->name('canned-responses.index');
        Route::post('/canned-responses', [CannedResponseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('canned-responses.store');
        Route::put('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('canned-responses.update');
        Route::delete('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'destroy'])
            ->name('canned-responses.destroy');
    });

    Route::middleware('role:system_admin')->group(function () {
        Route::get('/departments', [DepartmentManagementController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentManagementController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('departments.store');
        Route::get('/departments/{department}/edit', [DepartmentManagementController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department}', [DepartmentManagementController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentManagementController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('departments.destroy');

        Route::get('/assignment-rules', [TicketAssignmentRuleController::class, 'index'])->name('assignment-rules.index');
        Route::post('/assignment-rules', [TicketAssignmentRuleController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('assignment-rules.store');
        Route::delete('/assignment-rules/{ticketAssignmentRule}', [TicketAssignmentRuleController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('assignment-rules.destroy');
        Route::put('/assignment-rules/distribution-method', [TicketAssignmentRuleController::class, 'updateDistributionMethod'])
            ->middleware('throttle:20,1')
            ->name('assignment-rules.distribution-method');
    });
});

// Download attachments
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->middleware(['auth', 'active', 'consent.accepted', 'throttle:60,1'])
    ->name('attachments.download');
