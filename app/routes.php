<?php
/**
 * Route table. Middleware: 'guest' (logged-out only), 'auth' (logged-in),
 * 'admin' (logged-in admin). Every POST route is CSRF-checked by the Router.
 */

declare(strict_types=1);

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

// Authentication
$router->get('/register',         [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register',        [AuthController::class, 'register'],     ['guest']);
$router->get('/login',            [AuthController::class, 'showLogin'],    ['guest']);
$router->post('/login',           [AuthController::class, 'login'],        ['guest']);
$router->post('/logout',          [AuthController::class, 'logout'],       ['auth']);
$router->get('/forgot-password',  [AuthController::class, 'showForgot'],   ['guest']);
$router->post('/forgot-password', [AuthController::class, 'forgot'],       ['guest']);
$router->get('/reset-password',   [AuthController::class, 'showReset'],    ['guest']);
$router->post('/reset-password',  [AuthController::class, 'reset'],        ['guest']);

// Items (browse and detail pages are public)
$router->get('/items',                [ItemController::class, 'index']);
$router->get('/items/new',            [ItemController::class, 'create'],       ['auth']);  // before /items/{id}
$router->post('/items',               [ItemController::class, 'store'],        ['auth']);
$router->get('/items/{id}',           [ItemController::class, 'show']);
$router->get('/items/{id}/edit',      [ItemController::class, 'edit'],         ['auth']);
$router->post('/items/{id}/edit',     [ItemController::class, 'update'],       ['auth']);
$router->post('/items/{id}/status',   [ItemController::class, 'updateStatus'], ['auth']);
$router->post('/items/{id}/delete',   [ItemController::class, 'destroy'],      ['auth']);
$router->get('/my-items',             [ItemController::class, 'myItems'],      ['auth']);
$router->get('/uploads/{file}',       [ItemController::class, 'photo']);

// Claims
$router->post('/items/{id}/claim',    [ClaimController::class, 'store'],   ['auth']);
$router->post('/claims/{id}/approve', [ClaimController::class, 'approve'], ['auth']);
$router->post('/claims/{id}/reject',  [ClaimController::class, 'reject'],  ['auth']);
$router->get('/my-claims',            [ClaimController::class, 'mine'],    ['auth']);

// Private messages (always about one item, between its poster and one other user)
$router->get('/messages',                 [MessageController::class, 'index'], ['auth']);
$router->get('/messages/{itemId}/{userId}',   [MessageController::class, 'show'],  ['auth']);
$router->post('/messages/{itemId}/{userId}',  [MessageController::class, 'send'],  ['auth']);

// Notifications
$router->get('/notifications',            [NotificationController::class, 'index'],   ['auth']);
$router->post('/notifications/read-all',  [NotificationController::class, 'readAll'], ['auth']);
$router->post('/notifications/{id}/open', [NotificationController::class, 'open'],    ['auth']);

// Reports
$router->get('/reports/new', [ReportController::class, 'create'], ['auth']);
$router->post('/reports',    [ReportController::class, 'store'],  ['auth']);
$router->get('/my-reports',  [ReportController::class, 'mine'],   ['auth']);

// Signed-in areas
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/admin',     [AdminController::class, 'index'],     ['admin']);

return $router;
