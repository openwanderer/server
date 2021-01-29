<?php
namespace OpenWanderer;

require_once(__DIR__.'/../Controllers/UserController.php');

return function($app) {
    $app->get('/user/login', UserController::class.":getLogin");
    $app->post('/user/login', UserController::class.":login");
    $app->post('/user/logout', UserController::class.":logout");
    $app->post('/user/signup', UserController::class.":signup");
};

?>





















