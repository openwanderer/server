<?php

/*
 * SessionPanoController
 *
 * Subclass of PanoController that performs admin and user checks using 
 * session variables which match those in the user management system.
 */

namespace OpenWanderer\Controllers;

use \Psr\Container\ContainerInterface;

class SessionPanoController extends PanoController {

    public function __construct(ContainerInterface $c) {
        parent::__construct($c);
    }

    protected function isAdminUser() {
        return isset($_SESSION["isadmin"]) && $_SESSION["isadmin"] == 1;
    }

    protected function getUserId() {
        return isset($_SESSION["userid"]) ? $_SESSION["userid"] : 0;
    }
}

?>
