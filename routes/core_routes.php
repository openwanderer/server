<?php

/*
 * Core routes.
 *
 * Of interest to practically any OpenWanderer app.
 */

require_once('controllers/PanoController.php');
require_once('controllers/UserController.php');

return function($app) {
    $app->get('/panorama/{id:[0-9]+}', \PanoController::class.":getById");
    $app->delete('/panorama/{id:[0-9]+}', \PanoController::class.":deletePano");
    $app->get('/panorama/{id:[0-9]+}.jpg', \PanoController::class.":getPanoImage");
    $app->post('/panorama/{id}/authorise', \PanoController::class.":authorisePano");
    $app->get('/nearest/{lon}/{lat}', \PanoController::class.":getNearest"); 
    $app->get('/panos', \PanoController::class.":getByBbox"); 
    $app->get('/panos/mine', \PanoController::class.":getAllByUser"); 
    $app->get('/panos/unauthorised', \PanoController::class.":getUnauthorised"); 
    $app->post('/panorama/{id}/rotate', \PanoController::class.":rotate"); 
    $app->post('/panorama/{id}/move', \PanoController::class.":move");
    $app->post('/panoramas/move', \PanoController::class.":moveMulti");
    $app->post('/panorama/upload', \PanoController::class.":uploadPano");

    $app->get('/login', \UserController::class.':getLogin'); 
    $app->post('/login', \UserController::class.':login');
    $app->post('/logout', \UserController::class.':logout');
    $app->post('/signup', \UserController::class.':signup');

    $app->post('/sequence/create', \PanoController::class.":createSequence");
    $app->get('/sequence/{id}', \PanoController::class.":getSequence");
}


?>
