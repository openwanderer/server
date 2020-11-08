<?php

require 'vendor/autoload.php';
require 'defines.php';

use \Slim\Factory\AppFactory;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;

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


$app->run();

?>