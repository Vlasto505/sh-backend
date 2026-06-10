<?php

use App\Http\Controllers\Admin\AdminCallsController;
use App\Http\Controllers\Admin\AdminProgramsController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public pages
Route::get('/program-a', fn () => Inertia::render('Public/ProgramA'))->name('program-a');
Route::get('/program-b', fn () => Inertia::render('Public/ProgramB'))->name('program-b');
Route::get('/partners',  fn () => Inertia::render('Public/Partners'))->name('partners');
Route::get('/about',     fn () => Inertia::render('Public/About'))->name('about');
Route::get('/contact',   fn () => Inertia::render('Public/Contact'))->name('contact');
Route::get('/privacy',   fn () => Inertia::render('Public/Privacy'))->name('privacy');

// Public news (CMS)
Route::get('/news',           [PublicContentController::class, 'newsIndex'])->name('news.index');
Route::get('/news/{article}', [PublicContentController::class, 'newsShow'])->name('news.show');
Route::get('/sitemap.xml',    [PublicContentController::class, 'sitemap'])->name('sitemap');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Content management (CMS – editor / admin)
    Route::get('/content',              [ContentController::class, 'index'])->name('content.index');
    Route::post('/content',             [ContentController::class, 'store'])->name('content.store');
    Route::put('/content/{article:id}',    [ContentController::class, 'update'])->name('content.update');
    Route::delete('/content/{article:id}', [ContentController::class, 'destroy'])->name('content.destroy');

    // Notifications
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // GDPR – personal data export (right of access)
    Route::get('/profile/data-export', [GdprController::class, 'export'])->name('profile.data-export');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Programs & calls (all authenticated users)
    Route::get('/programs', [ProgramsController::class, 'index'])->name('programs.index');

    // Organizations / companies (Program B partners)
    Route::get('/organizations',                  [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations',                 [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations/{organization}',   [OrganizationController::class, 'show'])->name('organizations.show');
    Route::put('/organizations/{organization}',   [OrganizationController::class, 'update'])->name('organizations.update');
    Route::post('/organizations/{organization}/members',          [OrganizationController::class, 'addMember'])->name('organizations.members.store');
    Route::delete('/organizations/{organization}/members/{user}', [OrganizationController::class, 'removeMember'])->name('organizations.members.destroy');

    // Company assignments (Program B briefs)
    Route::get('/assignments',                 [AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('/assignments',                [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}',    [AssignmentController::class, 'show'])->name('assignments.show');
    Route::put('/assignments/{assignment}',    [AssignmentController::class, 'update'])->name('assignments.update');
    Route::patch('/assignments/{assignment}/status', [AssignmentController::class, 'updateStatus'])->name('assignments.status');
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');

    // Student onboarding profile
    Route::get('/student-profile',         [StudentProfileController::class, 'edit'])->name('student-profile.edit');
    Route::put('/student-profile',         [StudentProfileController::class, 'update'])->name('student-profile.update');
    Route::post('/student-profile/cv',     [StudentProfileController::class, 'uploadCv'])->name('student-profile.cv.store');
    Route::get('/student-profile/cv',      [StudentProfileController::class, 'downloadCv'])->name('student-profile.cv.download');
    Route::delete('/student-profile/cv',   [StudentProfileController::class, 'deleteCv'])->name('student-profile.cv.destroy');

    // Teams (students / team leaders)
    Route::get('/teams',                       [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams',                      [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}',                [TeamController::class, 'show'])->name('teams.show');
    Route::put('/teams/{team}',                [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}',             [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('/teams/{team}/members',       [TeamController::class, 'addMember'])->name('teams.members.store');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
    Route::post('/teams/{team}/leave',         [TeamController::class, 'leave'])->name('teams.leave');

    // Application submission (students)
    Route::get('/applications',                 [ApplicationController::class, 'index'])->name('applications.index');
    Route::post('/applications',                [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}',   [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('/applications/{application}',   [ApplicationController::class, 'update'])->name('applications.update');
    Route::post('/applications/{application}/submit',   [ApplicationController::class, 'submit'])->name('applications.submit');
    Route::post('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
    Route::post('/applications/{application}/documents', [ApplicationController::class, 'uploadDocument'])->name('applications.documents.store');
    Route::delete('/applications/{application}/documents/{attachment}', [ApplicationController::class, 'deleteDocument'])->name('applications.documents.destroy');
    Route::get('/applications/{application}/documents/{attachment}/download', [ApplicationController::class, 'download'])->name('applications.documents.download');

    // Committee evaluation workflow (evaluator + admin)
    Route::get('/evaluations',                          [EvaluationController::class, 'index'])->name('evaluations.index');
    Route::get('/evaluations/{application}',            [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::post('/evaluations/{application}/score',     [EvaluationController::class, 'score'])->name('evaluations.score');
    Route::post('/evaluations/{application}/request-supplement', [EvaluationController::class, 'requestSupplement'])->name('evaluations.supplement');
    Route::post('/evaluations/{application}/decide',    [EvaluationController::class, 'decide'])->name('evaluations.decide');

    // Approved projects: mentor assignment, milestones, consultations
    Route::get('/projects',                     [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{application}',       [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{application}/assign-mentor', [ProjectController::class, 'assignMentor'])->name('projects.assign-mentor');
    Route::post('/projects/{application}/milestones',    [ProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
    Route::put('/projects/{application}/milestones/{milestone}',    [ProjectController::class, 'updateMilestone'])->name('projects.milestones.update');
    Route::delete('/projects/{application}/milestones/{milestone}', [ProjectController::class, 'destroyMilestone'])->name('projects.milestones.destroy');
    Route::post('/projects/{application}/consultations', [ProjectController::class, 'storeConsultation'])->name('projects.consultations.store');

    // Admin area
    Route::middleware('role:admin|super_admin')->prefix('admin')->name('admin.')->group(function () {
        // Programs
        Route::get('/programs',                [AdminProgramsController::class, 'index'])->name('programs');
        Route::post('/programs',               [AdminProgramsController::class, 'store'])->name('programs.store');
        Route::put('/programs/{program}',      [AdminProgramsController::class, 'update'])->name('programs.update');
        Route::delete('/programs/{program}',   [AdminProgramsController::class, 'destroy'])->name('programs.destroy');

        // Calls (výzvy)
        Route::get('/calls',                   [AdminCallsController::class, 'index'])->name('calls');
        Route::post('/calls',                  [AdminCallsController::class, 'store'])->name('calls.store');
        Route::put('/calls/{call}',            [AdminCallsController::class, 'update'])->name('calls.update');
        Route::patch('/calls/{call}/status',   [AdminCallsController::class, 'updateStatus'])->name('calls.status');
        Route::delete('/calls/{call}',         [AdminCallsController::class, 'destroy'])->name('calls.destroy');

        // Bulk messaging (broadcasts)
        Route::get('/broadcasts',  [BroadcastController::class, 'index'])->name('broadcasts');
        Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');

        // Reporting & exports
        Route::get('/reports',                      [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export/applications',  [ReportController::class, 'applications'])->name('reports.applications');
        Route::get('/reports/export/users',         [ReportController::class, 'users'])->name('reports.users');

        // User management (admin: create/edit/activate; super_admin: + roles & delete)
        Route::get('/users',                 [AdminUsersController::class, 'index'])->name('users');
        Route::post('/users',                [AdminUsersController::class, 'store'])->name('users.store');
        Route::put('/users/{user}',          [AdminUsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}',       [AdminUsersController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
