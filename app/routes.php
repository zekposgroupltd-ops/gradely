<?php
declare(strict_types=1);

use App\Controllers\{
    AcademicsController, AccountController, AuthController, CardsController, ClassesController, MediaController,
    NotificationsController, PortalController, ReportsController, ResultsController, SchoolController,
    SchoolProfileController, ScoresController, SettingsController, SetupController, StudentsController,
    SubjectsController, SuperController, SuperSchoolsController, SuperToolsController, TeachersController
};
use App\Core\{Middleware as M, Router};

$r = new Router();

$auth = [M::auth()];
$super = [M::auth(), M::role('super_admin')];
$staff = [M::auth(), M::school()];
$admin = [M::auth(), M::school(), M::role('school_admin')];
$perm = static fn(string $p): array => [M::auth(), M::school(), M::can($p)];

// ---------------------------------------------------------------- public
$r->get('/', [PortalController::class, 'home']);
$r->get('/s/{slug}', [PortalController::class, 'forSchool']);
$r->post('/portal/check', [PortalController::class, 'check']);
$r->get('/portal/result', [PortalController::class, 'result']);
$r->post('/portal/finish', [PortalController::class, 'finish']);
$r->get('/media/logo/{id}', [MediaController::class, 'logo']);

$r->get('/login', [AuthController::class, 'showLogin']);
$r->post('/login', [AuthController::class, 'login']);
$r->get('/setup', [SetupController::class, 'show']);
$r->post('/setup', [SetupController::class, 'store']);

// ---------------------------------------------------------------- any signed-in user
$r->post('/logout', [AuthController::class, 'logout'], $auth);
$r->get('/account/password', [AccountController::class, 'password'], $auth);
$r->post('/account/password', [AccountController::class, 'updatePassword'], $auth);
$r->get('/notifications', [NotificationsController::class, 'index'], $auth);
$r->post('/notifications/read', [NotificationsController::class, 'markAllRead'], $auth);

// ---------------------------------------------------------------- Super Administrator
$r->get('/super', [SuperController::class, 'dashboard'], $super);
$r->get('/super/schools', [SuperSchoolsController::class, 'index'], $super);
$r->get('/super/schools/new', [SuperSchoolsController::class, 'create'], $super);
$r->post('/super/schools', [SuperSchoolsController::class, 'store'], $super);
$r->get('/super/schools/{id}', [SuperSchoolsController::class, 'show'], $super);
$r->post('/super/schools/{id}/update', [SuperSchoolsController::class, 'update'], $super);
$r->post('/super/schools/{id}/status', [SuperSchoolsController::class, 'status'], $super);
$r->post('/super/schools/{id}/users/{user_id}/reset', [SuperSchoolsController::class, 'resetPassword'], $super);
$r->get('/super/logs', [SuperToolsController::class, 'logs'], $super);
$r->get('/super/cards', [SuperToolsController::class, 'cards'], $super);
$r->get('/super/admins', [SuperToolsController::class, 'admins'], $super);
$r->post('/super/admins', [SuperToolsController::class, 'storeAdmin'], $super);
$r->post('/super/admins/{id}/toggle', [SuperToolsController::class, 'toggleAdmin'], $super);
$r->get('/super/notifications', [SuperToolsController::class, 'notifications'], $super);
$r->post('/super/notifications', [SuperToolsController::class, 'sendNotification'], $super);
$r->get('/super/settings', [SuperToolsController::class, 'settings'], $super);
$r->post('/super/settings', [SuperToolsController::class, 'saveSettings'], $super);

// ---------------------------------------------------------------- School (tenant always comes from the session)
$r->get('/school', [SchoolController::class, 'dashboard'], $staff);
$r->get('/school/setup', [SchoolController::class, 'setup'], $admin);

$r->get('/school/profile', [SchoolProfileController::class, 'show'], $admin);
$r->post('/school/profile', [SchoolProfileController::class, 'update'], $admin);
$r->post('/school/profile/logo', [SchoolProfileController::class, 'logo'], $admin);

$r->get('/school/settings', [SettingsController::class, 'show'], $admin);
$r->post('/school/settings', [SettingsController::class, 'saveGeneral'], $admin);
$r->post('/school/settings/grades', [SettingsController::class, 'saveGrades'], $admin);
$r->post('/school/settings/components', [SettingsController::class, 'saveComponents'], $admin);

$r->get('/school/academics', [AcademicsController::class, 'index'], $admin);
$r->post('/school/sessions', [AcademicsController::class, 'storeSession'], $admin);
$r->post('/school/sessions/{id}/delete', [AcademicsController::class, 'deleteSession'], $admin);
$r->post('/school/terms', [AcademicsController::class, 'storeTerm'], $admin);
$r->post('/school/terms/{id}/current', [AcademicsController::class, 'setCurrent'], $admin);
$r->post('/school/terms/{id}/update', [AcademicsController::class, 'updateTerm'], $admin);
$r->post('/school/terms/{id}/delete', [AcademicsController::class, 'deleteTerm'], $admin);

$r->get('/school/classes', [ClassesController::class, 'index'], $admin);
$r->post('/school/classes', [ClassesController::class, 'store'], $admin);
$r->post('/school/classes/{id}/delete', [ClassesController::class, 'delete'], $admin);
$r->post('/school/classes/{id}/arms', [ClassesController::class, 'storeArm'], $admin);
$r->post('/school/arms/{id}/delete', [ClassesController::class, 'deleteArm'], $admin);

$r->get('/school/subjects', [SubjectsController::class, 'index'], $admin);
$r->post('/school/subjects', [SubjectsController::class, 'store'], $admin);
$r->post('/school/subjects/assign', [SubjectsController::class, 'assign'], $admin);
$r->post('/school/subjects/{id}/delete', [SubjectsController::class, 'delete'], $admin);

$r->get('/school/teachers', [TeachersController::class, 'index'], $admin);
$r->get('/school/teachers/new', [TeachersController::class, 'create'], $admin);
$r->post('/school/teachers', [TeachersController::class, 'store'], $admin);
$r->get('/school/teachers/{id}/edit', [TeachersController::class, 'edit'], $admin);
$r->post('/school/teachers/{id}', [TeachersController::class, 'update'], $admin);

$students = $perm('students.manage');
$r->get('/school/students', [StudentsController::class, 'index'], $students);
$r->get('/school/students/new', [StudentsController::class, 'create'], $students);
$r->post('/school/students', [StudentsController::class, 'store'], $students);
$r->get('/school/students/import', [StudentsController::class, 'importForm'], $students);
$r->post('/school/students/import', [StudentsController::class, 'import'], $students);
$r->get('/school/students/template.csv', [StudentsController::class, 'template'], $students);
$r->get('/school/students/export.csv', [StudentsController::class, 'export'], $students);
$r->get('/school/students/{id}/edit', [StudentsController::class, 'edit'], $students);
$r->post('/school/students/{id}', [StudentsController::class, 'update'], $students);
$r->post('/school/students/{id}/delete', [StudentsController::class, 'delete'], $students);

$scores = $perm('scores.enter');
$r->get('/school/scores', [ScoresController::class, 'index'], $scores);
$r->post('/school/scores', [ScoresController::class, 'save'], $scores);
$r->get('/school/scores/template.csv', [ScoresController::class, 'template'], $scores);
$r->post('/school/scores/import', [ScoresController::class, 'import'], $scores);

$view = $perm('results.view');
$r->get('/school/results', [ResultsController::class, 'index'], $view);
$r->get('/school/results/export.csv', [ResultsController::class, 'export'], $view);
$r->get('/school/results/comments', [ResultsController::class, 'comments'], $view);
$r->post('/school/results/comments', [ResultsController::class, 'saveComments'], $view);
$r->get('/school/analytics', [ResultsController::class, 'analytics'], $view);
$r->get('/school/archive', [ResultsController::class, 'archive'], $view);
$r->get('/school/reports/student', [ReportsController::class, 'student'], $view);
$r->get('/school/reports/class', [ReportsController::class, 'classBulk'], $view);
// Workflow actions: the controller checks the specific permission (compute needs scores.enter or results.approve).
$r->post('/school/results/compute', [ResultsController::class, 'compute'], $staff);
$r->post('/school/results/approve', [ResultsController::class, 'approve'], $staff);
$r->post('/school/results/revert', [ResultsController::class, 'revert'], $staff);
$r->post('/school/results/publish', [ResultsController::class, 'publish'], $staff);
$r->post('/school/results/unpublish', [ResultsController::class, 'unpublish'], $staff);

$cards = $perm('cards.manage');
$r->get('/school/cards', [CardsController::class, 'index'], $cards);
$r->post('/school/cards/generate', [CardsController::class, 'generate'], $cards);
$r->get('/school/cards/export.csv', [CardsController::class, 'exportInventory'], $cards);
$r->get('/school/cards/batches/{id}', [CardsController::class, 'showBatch'], $cards);
$r->get('/school/cards/batches/{id}/slips', [CardsController::class, 'slips'], $cards);
$r->get('/school/cards/batches/{id}/pins.csv', [CardsController::class, 'pinsCsv'], $cards);
$r->post('/school/cards/batches/{id}/discard', [CardsController::class, 'discardPins'], $cards);
$r->post('/school/cards/batches/{id}/revoke', [CardsController::class, 'revokeBatch'], $cards);
$r->get('/school/cards/{id}', [CardsController::class, 'showCard'], $cards);
$r->post('/school/cards/{id}/revoke', [CardsController::class, 'revoke'], $cards);

return $r;
