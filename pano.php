<?php
session_cache_limiter("");
session_start();
require_once('defines.php');
header('Cache-control: max-age='.(60*60*24*365));
header('Expires: '.gmdate(DATE_RFC1123, time()+60*60*24*365));
$conn = new PDO("pgsql:host=localhost;dbname=".DB_DBASE, DB_USER);
$id = $_GET["id"];
if(ctype_digit($id)) {
    $stmt = $conn->prepare("SELECT * FROM panoramas WHERE id=?");
    $result = $stmt->execute([$id]);
    $row = $stmt->fetch();
    if($row!==false) {
        if(isset($_SESSION["isadmin"]) || $row["authorised"]==1 || (isset($_SESSION["userid"]) && $_SESSION["userid"]==$row["userid"])) {
            header("Content-type: image/jpg");
            $file=OTV_UPLOADS."/$id.jpg";
            header("Content-Length: " . filesize($file));
            if(isset($_GET['resize']) && ctype_digit($_GET['resize'])) {
                list($srcWidth, $srcHeight) = getimagesize($file);
                $im = imagecreatefromjpeg($file);
                $destWidth = $srcWidth * 0.01 * $_GET['resize'];
                $destHeight = $srcHeight * 0.01 * $_GET['resize'];
                $imOut = imagecreatetruecolor($destWidth, $destHeight);
                imagecopyresized($imOut, $im, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
                imagejpeg($imOut);
                imagedestroy($imOut);
                imagedestroy($im);
            } else {
                echo file_get_contents($file);
            }
        } else {
            header("HTTP/1.1 401 Unauthorized");
        }
    } else {
        header("HTTP/1.1 404 Not Found");
    }
} else {
    header("HTTP/1.1 400 Bad Request");
}
?>
