<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;

require_once('defines.php');
require_once(dirname(__DIR__).'/models/UserModel.php');

class UserController {

    protected $model;

    public function __construct(ContainerInterface $container) {
        $this->model = new UserModel($container->get('db'));
    }

    public function getLogin(Request $req, Response $res, array $args){
        return $res->withJson(["username"=>isset($_SESSION["username"]) ? $_SESSION["username"]: (isset($_SESSION["userid"]) ? $this->model->getUsername($_SESSION["userid"]):null), "userid"=>isset($_SESSION["userid"]) ? $_SESSION["userid"]: 0, "isadmin"=>isset($_SESSION['isadmin']) ? $_SESSION['isadmin']: 0]);
    }

    public function login(Request $req, Response $res, array $args) {
        $post = $req->getParsedBody();
        $row = $this->model->login($post["username"], $post["password"]);
        if($row === false) {
            return $res->withStatus(401)->withJson(["error"=>"Incorrect login."]);
        } else {
            $_SESSION["userid"] = $row["id"];
            if($row["isadmin"]==1) {
                $_SESSION["isadmin"] = $row["isadmin"];
            }
            return $res->withJson(["username"=>$this->model->getUsername($_SESSION["userid"]), "userid"=>$_SESSION["userid"], "isadmin"=>isset($_SESSION["isadmin"]) ? $_SESSION["isadmin"] : 0]);
        }
    }

    public function logout(Request $req, Response $res, array $args) {
        session_destroy();
        return $res;
    }

    public function signup(Request $req, Response $res, array $args) {
        $post = $req->getParsedBody();
        if($post["username"]=="" || $post["password"]=="") {
            return $res->withJson(["error"=>"Username and/or password blank."]);
        } elseif(!filter_var($post["username"], FILTER_VALIDATE_EMAIL)) {
            return $res->withJson(["error"=>"That is not a valid email address."]); 
        } elseif(strlen($post['password'])<8) {
            return $res->withJson(["error"=>"Password should be at least 8 characters."]); 
        } elseif($post["password"] != $post["password2"]) {
            return $res->withJson(["error"=>"Passwords do not match."]); 
        } else {
            $result = $this->model->signup($post["username"], $post["password"]);
            if($result === false) {
                return $res->withJson(["error"=>"This username already exists, please choose another one."]);
            } else {
                return $res->withJson(["username"=>$post["username"]]);
            }
        }
    }
}
?>
