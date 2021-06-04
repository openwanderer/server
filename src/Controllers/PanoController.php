<?php

namespace OpenWanderer\Controllers;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;

class PanoController {
    protected $uid, $dao;

    public function __construct(ContainerInterface $container) {
        $this->uid = $this->getUserId();
        $this->dao = new \OpenWanderer\Dao\PanoDao($container->get('db'));
    }

    function getById(Request $req, Response $res, array $args){ 
        if($this->isAdminUser()) {
            $row = $this->dao->getById($args["id"]);
        } else {
            $row = $this->dao->getByIdAuthorised($args["id"], $this->uid);
        }
        if($row) {
            $row["seqid"] = $this->dao->getSequenceForPano($args["id"]);
            return $res->withJson($row);
        } else {
        return $res->withStatus(404)->withJson(["error"=>"Cannot find pano with that ID or you are not authorised to view it."]);
        }
    }
    

    function getNearest(Request $req, Response $res, array $args)  {
        if(preg_match("/^-?[\d\.]+$/", $args['lon']) &&
            preg_match("/^-?[\d\.]+$/", $args['lat'])) {
            $row = $this->dao->getNearest($args["lon"], $args["lat"]);
        } else {
            $row=[];
        }
        return $res->withJson($row);
    }
    
    function getByBbox(Request $req, Response $res, array $args) {
        $get = $req->getQueryParams();
        if(isset($get['bbox'])) {
            $bb = explode(",", $get['bbox']);
            if(count($bb)==4) {
                $valid = true;
                foreach($bb as $val) {
                    if(!preg_match("/^-?[\d\.]+$/", $val)) {
                        $valid = false;
                    }
                }
                if($valid) {
                    $rows = [];
                    if($this->isAdminUser()) {
                        $rows = $this->dao->getByBbox($bb);
                    } else {
                        $rows = $this->dao->getByBboxAuthorised($bb, $this->uid);
                    }
                    $geojson = ["type"=>"FeatureCollection","features"=>[]];
                    foreach($rows as $row) {
                        $f =  ['type'=>'Feature', "geometry"=>
                                ["type"=>"Point",
                                 "coordinates"=>[$row['lon'],$row['lat']]],
                            'properties'=>
                                ['poseheadingdegrees'=>$row['poseheadingdegrees'],"pancorrection"=>$row["pancorrection"],'id'=>$row['id'], "userid"=>$row['userid']]];
                        $geojson["features"][] = $f;
                    }
                    return $res->withJson($geojson);
                }
            }
        }
    }

    private function getPanosByUser(Request $req, Response $res, array $args, $sql="") {
        if($this->uid === 0) {
            return $res->withStatus(401)->withJson(["error"=>"not logged in"]);
        } else {
            $rows = $this->dao->getPanosByCriterion("WHERE userid=? $sql", [$this->getUserId()]);
            return $res->withJson($rows);
        }
    }

    function getAllByUser(Request $req, Response $res, array $args) {
        return $this->getPanosByUser($req, $res, $args);
    }

    function rotate (Request $req, Response $res, array $args) {
        return $this->doRotate($req, $res, $args["id"]);
    }

    private function doRotate(Request $req, Response $res, $id) {
        if($this->authorisedToChange($id)) {
            $post = $req->getParsedBody();
            $this->dao->rotate($id, $post["pan"], $post["tilt"], $post["roll"]);
        } else {
           return $res->withStatus(401)->withJson(["error"=>"not authorised to rotate"]);
        }
        return $res;
    }

    function move(Request $req, Response $res, array $args) {
        return $this->doMove($req, $res, $args["id"]);
    }

    private function doMove(Request $req, Response $res, $id) {
        if($this->authorisedToChange($id)) {
            $post = $req->getParsedBody();
            if(preg_match("/^-?[\d\.]+$/", $post['lon']) &&
                preg_match("/^-?[\d\.]+$/", $post['lat'])) {
                $this->dao->move($id, $post["lon"], $post["lat"]);
            } else {
                $res->withStatus(400)->withJson(["error"=>"Invalid input"]);
            } 
        } else {
           return $res->withStatus(401)->withJson(["error"=>"not authorised to move"]);
        }
        return $res;
    }
   
 
    function moveMulti(Request $req, Response $res, array $args) {
        $successful = [];
        $post = $req->getParsedBody();
        foreach ($post as $id=>$pano) {
            if($this->authorisedToChange($id)) {
                if(preg_match("/^-?[\d\.]+$/", $pano['lon']) &&
                    preg_match("/^-?[\d\.]+$/", $pano['lat'])) {
                    $this->dao->move($id, $post["lon"], $post["lat"]);
                    $successful[] = ["id"=>$id,"lat"=>$pano['lat'],"lon"=>$pano['lon']];
                } 
            }
        } 
        return $res->withJson($successful);
    }


    private function authorisedToChange($panoid) {
        if($this->isAdminUser()) {
            return true;
        } elseif($this->uid) {
            return $this->dao->authorisedToChange($panoid, $this->uid);
        } else {
            return false;
        }
    }

    private function setUserId($uid) {
        $this->uid = $uid;
    }

    private function authorisedToUpload() {
        return $this->uid > 0 || $this->isAdminUser();
    }

    function deletePano(Request $req, Response $res, array $args) {
        return $this->doDeletePano($req, $res, $args["id"]);
    }

    private function doDeletePano(Request $req, Response $res, $id) {
        if(ctype_digit($id)) {
            if($this->authorisedToChange($id)) {
                $this->dao->delete($id);
                return $res->withJson(["id"=>$id]);
            } else {
                return $res->withStatus(401)->withJson(["error"=>"not authorised to delete this pano"]);
            }
        } else {
                return $res->withStatus(400)->withJson(["error"=>"ID not a digit"]);
        }
    }
    
    function uploadPano(Request $req, Response $res, array $args) {
        if($this->authorisedToUpload()) {
            return $this->doUploadPano($req, $res, $args);
        }
    }

    private function doUploadPano(Request $req, Response $res, array $args) {
        $files = $req->getUploadedFiles();
        $post = $req->getParsedBody();
        $error = $warning = null;
        $id = 0;
        $lon = $lat = false;
        $ele = 0;
        $result = [];
        
        $errorCode = 400;
        if(!$this->authorisedToUpload()) {
            $errorCode = 401;
            $error = "You must be authenticated to upload panos.";
        } elseif(empty($files['file'])){
            $error = "No panorama provided. It's possible that this error might be generated due to your pano being too large.";
        } else {
            $pano= $files['file'];
            if($pano->getError() != UPLOAD_ERR_OK) {
                $error = "No file uploaded. Your file probably exceeds the max file size of ". $_ENV["MAX_FILE_SIZE"]. "MB. Error code=". $pano->getError();
            } else {
                $size = $pano->getSize();    
                if($size > $_ENV["MAX_FILE_SIZE"] * 1048576) {
                    $error = "Exceeded file size of ".$_ENV["MAX_FILE_SIZE"]." MB";
                } else {
                    $tmpName=$pano->getFilePath(); // Slim 4 update
                    $imageData = getimagesize($tmpName);
                    if($imageData===false || $imageData[2]!=IMAGETYPE_JPEG) {
                        $error = "Not a JPEG image!";
                    } else {
                        $result["post"] =  $post;
                        $ele = isset($post["ele"]) ? $post["ele"] : 0;
                        $photosphere = new \OpenWanderer\Dao\Photosphere($tmpName);
                        $gpano = $photosphere->hasGPano();
                        if($gpano===false) {
                            $warning="no XMP tags, you'll later need to orient this manually.";
                        } else {
                            $heading = $photosphere->getGPanoAttribute('PoseHeadingDegrees');
                        }
                        if(isset($post["lon"]) && isset($post["lat"])) {
                            $lon = $post["lon"];
                            $lat = $post["lat"];
                        } else {
                            $lat=$photosphere->getLatitude();
                            $lon=$photosphere->getLongitude();
                        }
                        if($lon!==false && $lat!==false && preg_match("/^-?[\d\.]+$/", $lon) && preg_match("/^-?[\d\.]+$/", $lat)) {
                            $id = $this->dao->insertPano($lon, $lat, $ele, $gpano && $heading !== false ? $heading: 0, $this->uid, $photosphere->getTimestamp());
                        } else {
                            $error = "No lat/lon information found in panorama. Not uploaded.";
                        }
                        if($id > 0) {
                            try {
                                $result = $pano->moveTo($_ENV["OTV_UPLOADS"]."/".$id.".jpg");
                            } catch(Exception $e) {
                                $authorisedCode = 500;
                                $error = $e->getMessage();
                                $this->dao->delete($id);
                            }
                        }
                    }
                }
            }
        }
        $authorisedCode = 200;
        if($error!==null) {
            $result["error"] = $error;
            $result["files"] = $files;
            $authorisedCode = $errorCode;
        } else {
            if($warning !== null) {
                $result["warning"] = $warning;
            }
            $result["id"] = $id;
            if($lon !== false) {
                $result["lon"] = $lon;
            }
            if($lat !== false) {
                $result["lat"] = $lat;
            }
        } 
        return $res->withStatus($authorisedCode)->withJson($result);
    }

    public function createSequence(Request $req, Response $res, array $args) {
        if($this->authorisedToUpload()) {
            $ids = $req->getParsedBody();
            if(!is_array($ids)) {
                return $res->withStatus(400)->withJson(["error" => "Please supply a JSON array of panorama IDs."]);
            } else {
                $panos = [];
                $seqid = 0;
                foreach($ids as $id) {    
                    $pano = $this->dao->getById($id);
                    if($pano !== null) {
                        $panos[] = $pano;    
                    }
                }
                if(count($panos) > 0) {
                    $seqid = $this->dao->createSequence($panos);
                }
                $res->getBody()->write($seqid);
                return $res->withStatus($seqid > 0 ? 200: 400);
            }
        } else {
            return $res->withStatus(401)->withJson(["error" => "Not authorised to create sequence."]);
        }
    }

    public function getSequence(Request $req, Response $res, array $args) {
        //$feature = $this->dao->getSequence($args["id"]);
        $feature = $this->dao->getSequence($args["id"]);
        if($feature !== false) {
            return $res->withJson($feature);
        }
        return $res->withStatus(404)->withJson(["error"=>"no sequence with that id"]);
    }

    public function getPanoImage(Request $req, Response $res, array $args) {
        $row = $this->dao->getById($args["id"]);
        if($row !== null) {
            if($this->isAdminUser() || $row["authorised"]==1 || $this->uid == $row["userid"]) {
                $file=$_ENV["OTV_UPLOADS"]."/$args[id].jpg";
                if(isset($args["width"])) {
                    $im = imagecreatefromjpeg($file);
                    $srcWidth = imagesx($im);
                    $srcHeight = imagesy($im);
                    $destWidth = $args["width"]; // srcWidth * 0.01 * $_GET['resize'];
                    $aspectRatio = $srcWidth / $srcHeight;
                    $destHeight = $destWidth / $aspectRatio;
                    $imOut = imagecreatetruecolor($destWidth, $destHeight);
                    imagecopyresized($imOut, $im, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
                    ob_start();
                    imagejpeg($imOut);
                    $data = ob_get_contents();
                    ob_end_clean();
                    imagedestroy($imOut);
                    imagedestroy($im);
                    $res->getBody()->write($data);
                } else {
                    $res->getBody()->write(file_get_contents($file));
                }
                return $res->withHeader("Content-Type", "image/jpg")
                    ->withHeader("Content-Length", filesize($file))
                    ->withHeader("Cache-control", "max-age=".(60*60*24*365))
                    ->withHeader("Expires",gmdate(DATE_RFC1123, time()+60*60*24*365));
            } else {
                return $res->withStatus(401)->withJson(["error"=>"not authorised to access image"]);
            }
        } else {
            return $res->withStatus(404)->withJson(["error"=>"image not found"]);
        }
    }

    /* Override to provide admin checks - by default we assume anyone is an
     * admin, this it to allow for easy use on an internal system or for a 
     * demo, etc, without having to implement a login system.
     */
    protected function isAdminUser() {
        return true;
    }

    /* Override to get the current user ID. This might be provided by a 
     * session variable, for instance.
     */
    protected function getUserId() {
        return 0;
    }
}
?>
