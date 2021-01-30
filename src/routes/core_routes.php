<?php

/*
 * Core routes.
 *
 * Of interest to practically any OpenWanderer app.
 */

namespace OpenWanderer;

use \OpenWanderer\Controllers\SessionPanoController;
use \OpenWanderer\Controllers\PanoController;
use \OpenWanderer\Controllers\UserController;

return function($app, $auth) {
    $class = $auth ? SessionPanoController::class : PanoController::class;
    $app->get('/panorama/{id:[0-9]+}', $class.":getById");
    $app->delete('/panorama/{id:[0-9]+}', $class.":deletePano");
    $app->get('/panorama/{id:[0-9]+}.jpg', $class.":getPanoImage");
    $app->post('/panorama/{id:[0-9]+}/authorise', $class.":authorisePano");
    $app->get('/nearest/{lon}/{lat}', $class.":getNearest"); 
    $app->get('/panos', $class.":getByBbox"); 
    $app->post('/panorama/{id:[0-9]+}/rotate', $class.":rotate"); 
    $app->post('/panorama/{id:[0-9]+}/move', $class.":move");
    $app->post('/panoramas/move', $class.":moveMulti");
    $app->post('/panorama/upload', $class.":uploadPano");

    $app->post('/sequence/create', $class.":createSequence");
    $app->get('/sequence/{id:[0-9]+}', $class.":getSequence");
}


?>
