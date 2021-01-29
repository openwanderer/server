<?php

namespace OpenWanderer\Dao;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;

class UserDao {

    protected $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if($row == false) {
            return false;
        } elseif(password_verify($password, $row["password"])) {
            return [ 
                "userid" => $row["id"],
                "isadmin" => $row["isadmin"]
            ];
        } else {
            return false;
        }
    }

    public function signup($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute([$username]);
        if($stmt->fetch()) {
            return false;
        } else {
            $stmt=$this->db->prepare("INSERT INTO users (username, password) VALUES(?,?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            return true;
        }
    }

    public function getUsername($userid) {
    
        $stmt = $this->db->prepare("SELECT username FROM users WHERE id=?");
        $stmt->execute([$userid]);
        $row = $stmt->fetch();
        return $row["username"];
    }
}
?>
