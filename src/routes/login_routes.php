<?php
namespace OpenWanderer;

use \OpenWanderer\Controllers\SessionPanoController;
use \OpenWanderer\Controllers\UserController;

return function($app) {
    $app->get('/user/login', UserController::class.":getLogin");
    $app->post('/user/login', UserController::class.":login");
    $app->post('/user/logout', UserController::class.":logout");
    $app->post('/user/signup', UserController::class.":signup");
    $app->get('/panos/mine', SessionPanoController::class.":getAllByUser"); 
};

?>





















