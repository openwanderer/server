<?php

session_start();

require 'vendor/autoload.php';
require 'defines.php';
$setup_core_routes = require 'routes/core_routes.php';
$setup_login_routes = require 'routes/login_routes.php';

use \Slim\Factory\AppFactory;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;
use \Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$container = new \DI\Container();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$container->set('db', function() {
    $conn = new PDO("pgsql:host=localhost;dbname=".DB_DBASE, DB_USER);
    return $conn;
});

$view = new \Slim\Views\PhpRenderer('views');

$app->get('/', function(Request $req, Response $res, array $args) use ($view) {
    return $view->render($res, 'index.html');
});

$mw =  function(Request $req, RequestHandler $handler) {
    if(isset($_SESSION["userid"])) {
        return $handler->handle($req);
    } else {
        $res = new Response();
        return $res->withStatus(401)->withJson(["error"=>"must be logged in."]);
    }
};

$uploadRoute = $setup_core_routes($app);
$setup_login_routes($app);

$uploadRoute->add($mw);

$app->run();

?>
