<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->group('api/auth', ['namespace' => 'App\Controllers\Api'], static function ($routes): void {
    $routes->post('login', 'AuthController::login');
    $routes->get('session', 'AuthController::session');
    $routes->post('logout', 'AuthController::logout');
});
$routes->group('api/users', ['namespace' => 'App\Controllers\Api', 'filter' => 'auth'], static function ($routes): void {
    $routes->get('/', 'UserController::index');
    $routes->post('/', 'UserController::create');
    $routes->patch('(:num)/active', 'UserController::setActive/$1');
});
$routes->group('api/state', ['namespace' => 'App\Controllers\Api', 'filter' => 'auth'], static function ($routes): void {
    $routes->get('/', 'StateController::show');
    $routes->put('/', 'StateController::update');
});
