<?php

namespace OpenWanderer;

use \Slim\Factory\AppFactory;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;
use \Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class OpenWanderer {
    
    public static function createApp($options) {
        $dotenv = \Dotenv\Dotenv::createImmutable(".");
        $dotenv->load();
        $setup_core_routes = require 'routes/core_routes.php';
        $setup_login_routes = require 'routes/login_routes.php';
        $container = new \DI\Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(true, true, true);

        if(isset($_ENV["BASE_PATH"])) $app->setBasePath($_ENV["BASE_PATH"]);

        $container->set('db', function() {
            $conn = new \PDO("pgsql:host=localhost;dbname=".$_ENV["DB_DBASE"], $_ENV["DB_USER"]);
            return $conn;
        });

        $view = new \Slim\Views\PhpRenderer('views');

        $app->get('/', function(Request $req, Response $res, array $args) use ($view) {
            return $view->render($res, 'index.html');
        });


        $setup_core_routes($app);

        if(!empty($options["auth"])) {
            $setup_login_routes($app);
        }

        return $app;
    }
}

?>
