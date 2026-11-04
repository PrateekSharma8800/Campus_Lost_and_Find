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

// Signed-in areas
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/admin',     [AdminController::class, 'index'],     ['admin']);

return $router;
