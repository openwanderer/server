<?php

/*
 * Core routes.
 *
 * Of interest to practically any OpenWanderer app.
 */

namespace OpenWanderer;

use \OpenWanderer\Controllers\SessionPanoController;
use \OpenWanderer\Controllers\UserController;

return function($app) {
    $app->get('/panorama/{id:[0-9]+}', SessionPanoController::class.":getById");
    $app->delete('/panorama/{id:[0-9]+}', SessionPanoController::class.":deletePano");
    $app->get('/panorama/{id:[0-9]+}.jpg', SessionPanoController::class.":getPanoImage");
    $app->post('/panorama/{id}/authorise', SessionPanoController::class.":authorisePano");
    $app->get('/nearest/{lon}/{lat}', SessionPanoController::class.":getNearest"); 
    $app->get('/panos', SessionPanoController::class.":getByBbox"); 
    $app->get('/panos/mine', SessionPanoController::class.":getAllByUser"); 
    $app->get('/panos/unauthorised', SessionPanoController::class.":getUnauthorised"); 
    $app->post('/panorama/{id}/rotate', SessionPanoController::class.":rotate"); 
    $app->post('/panorama/{id}/move', SessionPanoController::class.":move");
    $app->post('/panoramas/move', SessionPanoController::class.":moveMulti");
    $app->post('/panorama/upload', SessionPanoController::class.":uploadPano");

    $app->get('/login', UserController::class.':getLogin'); 
    $app->post('/login', UserController::class.':login');
    $app->post('/logout', UserController::class.':logout');
    $app->post('/signup', UserController::class.':signup');

    $app->post('/sequence/create', SessionPanoController::class.":createSequence");
    $app->get('/sequence/{id}', SessionPanoController::class.":getSequence");
}


?>
