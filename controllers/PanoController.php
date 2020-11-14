<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Container\ContainerInterface;


require_once(dirname(__DIR__).'/models/Photosphere.php');
require_once(dirname(__DIR__).'/models/PanoDao.php');


class PanoController {
    protected $uid, $model;

    public function __construct(ContainerInterface $container) {
        $this->uid = isset($_SESSION["userid"]) ? $_SESSION["userid"] : null;
        $this->model = new PanoDao($container->get('db'));
    }

    function getById(Request $req, Response $res, array $args){ 
        if(isset($_SESSION["isadmin"])) {
            $row = $this->model->getById($args["id"]);
        } else {
            $row = $this->model->getByIdAuthorised($args["id"], $this->uid);
        }
        if($row) {
            $row["seqid"] = $this->model->getSequenceForPano($args["id"]);
            return $res->withJson($row);
        } else {
        return $res->withStatus(404)->withJson(["error"=>"Cannot find pano with that ID or you are not authorised to view it."]);
        }
    }
    

    function getNearest(Request $req, Response $res, array $args)  {
        if(preg_match("/^-?[\d\.]+$/", $args['lon']) &&
            preg_match("/^-?[\d\.]+$/", $args['lat'])) {
            $row = $this->model->getNearest($args["lon"], $args["lat"]);
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
                    if(isset($_SESSION["isadmin"])) {
                        $rows = $this->model->getByBbox($bb);
                    } else {
                        $rows = $this->model->getByBboxAuthorised($bb, $this->uid);
                    }
                    $geojson = ["type"=>"FeatureCollection","features"=>[]];
                    foreach($rows as $row) {
                        $f =  ['type'=>'Feature', "geometry"=>
                                ["type"=>"Point",
                                 "coordinates"=>[$row['lon'],$row['lat']]],
                            'properties'=>
                                ['poseheadingdegrees'=>$row['poseheadingdegrees'],'id'=>$row['id'], "userid"=>$row['userid']]];
                        $geojson["features"][] = $f;
                    }
                    return $res->withJson($geojson);
                }
            }
        }
    }

    private function getPanosByUser(Request $req, Response $res, array $args, $sql="") {
        $_SESSION["userid"] = 1;
        if(!isset($_SESSION["userid"])) {    
            return $res->withStatus(401);
        } else {
            $rows = $this->model->getPanosByCriterion("WHERE userid=? $sql", [$_SESSION["userid"]]);
            return $res->withJson($rows);
        }
    }

    function getAllByUser(Request $req, Response $res, array $args) {
        return $this->getPanosByUser($req, $res, $args);
    }

    function getUnauthorised(Request $req, Response $res, array $args) {
        if(!isset($_SESSION["isadmin"])) {    
            return $res->withStatus(401);
        } else {
            $rows = $this->model->getPanosByCriterion("WHERE authorised=0");
            return $res->withJson($rows);
        } 
    }

    function rotate (Request $req, Response $res, array $args) {
        return $this->doRotate($req, $res, $args["id"]);
    }

    private function doRotate(Request $req, Response $res, $id) {
        if($this->authorisedToChange($id)) {
            $post = $req->getParsedBody();
            $this->model->rotate($id, $post["poseheadingdegrees"]);
        } else {
            $res->withStatus(401);
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
                $this->model->move($id, $post["lon"], $post["lat"]);
            } else {
                $res->withStatus(400);    
            } 
        } else {
            $res->withStatus(401);
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
                    $this->model->move($id, $post["lon"], $post["lat"]);
                    $successful[] = ["id"=>$id,"lat"=>$pano['lat'],"lon"=>$pano['lon']];
                } 
            }
        } 
        return $res->withJson($successful);
    }


    private function authorisedToChange($panoid) {
        $uid = isset($_SESSION["userid"]) ? $_SESSION["userid"]: $this->uid;
        if(isset($_SESSION["isadmin"])) {
            return true;
        } elseif($uid) {
            return $this->model->authorisedToChange($panoid, $uid);
        } else {
            return false;
        }
    }

    private function setUserId($uid) {
        $this->uid = $uid;
    }

    private function authorisedToUpload() {
        return $this->uid !== null;
    }

    function deletePano(Request $req, Response $res, array $args) {
        return $this->doDeletePano($req, $res, $args["id"]);
    }

    private function doDeletePano(Request $req, Response $res, $id) {
        if(ctype_digit($id)) {
            if($this->authorisedToChange($id)) {
                $this->model->delete($id);
                return $res->withJson(["id"=>$id]);
            } else {
                return $res->withStatus(401)->withJson(["error"=>"not authorised to delete this pano"]);
            }
        } else {
                return $res->withStatus(400)->withJson(["error"=>"ID not a digit"]);
        }
    }
    
    function authorisePano(Request $req, Response $res, array $args) {
        if(ctype_digit($args["id"])) {
            if($this->authorisedToChange($args["id"])) {
                $this->model->authorise($args["id"]);
            } else {
                return $res->withStatus(401);
            }
        } else {
            return $res->withStatus(400);
        }
    }

    function uploadPano(Request $req, Response $res, array $args) {
        if(isset($_SESSION['userid']) && ctype_alnum($_SESSION['userid'])) {
            $this->setUserId($_SESSION['userid']);
        }
        return $this->doUploadPano($req, $res, $args);
    }

    private function doUploadPano(Request $req, Response $res, array $args) {
        $files = $req->getUploadedFiles();
        $post = $req->getParsedBody();
        $error = $warning = null;
        $id = 0;
        
        $errorCode = 400;
        if(!$this->authorisedToUpload()) {
            $errorCode = 401;
            $error = "You must be authenticated to upload panos.";
        } elseif(empty($files['file'])){
            $error = "No panorama provided. It's possible that this error might be generated due to your pano being too large.";
        } else {
            $pano= $files['file'];
            if($pano->getError() != UPLOAD_ERR_OK) {
                $error = "No file uploaded. Your file probably exceeds the max file size of ". MAX_FILE_SIZE. "MB. Error code=". $pano->getError();
            } else {
                $size = $pano->getSize();    
                if($size > MAX_FILE_SIZE * 1048576) {
                    $error = "Exceeded file size of ".MAX_FILE_SIZE." MB";
                } else {
                    $tmpName=$pano->file;
                    $imageData = getimagesize($tmpName);
                    if($imageData===false || $imageData[2]!=IMAGETYPE_JPEG) {
                        $error = "Not a JPEG image!";
                    } else {
                        $photosphere = new Photosphere($tmpName);
                        $gpano = $photosphere->hasGPano();
                        if($gpano===false) {
                            $warning="no XMP tags, you'll later need to orient this manually.";
                        } else {
                            $heading = $photosphere->getGPanoAttribute('PoseHeadingDegrees');
                        }
                        $lat=$photosphere->getLatitude();
                        $lon=$photosphere->getLongitude();
                        if($lon!==false && $lat!==false && preg_match("/^-?[\d\.]+$/", $lon) && preg_match("/^-?[\d\.]+$/", $lat)) {
                            $id = $this->model->insertPano($lon, $lat, $gpano && $heading !== false ? $heading: 0, $this->uid, $photosphere->getTimestamp());
                        } else {
                            $error = "No lat/lon information found in panorama. Not uploaded.";
                        }
                        if($id > 0) {
                            try {
                                $result = $pano->moveTo(OTV_RAW_UPLOADS."/".$id.".jpg");
                            } catch(Exception $e) {
                                $authorisedCode = 500;
                                $error = $e->getMessage();
                                $this->model->delete($id);
                            }
                        }
                    }
                }
            }
        }
        $result = [];
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
        } 
        return $res->withStatus($authorisedCode)->withJson($result);
    }

    public function createSequence(Request $req, Response $res, array $args) {
        $ids = $req->getParsedBody();
        $panos = [];
        $seqid = 0;
        foreach($ids as $id) {    
            $pano = $this->model->getById($id);
            if($pano !== null) {
                $panos[] = $pano;    
            }
        }
        if(count($panos) > 0) {
            $seqid = $this->model->createSequence($panos);
        }
        $res->getBody()->write($seqid);
        return $res->withStatus($seqid > 0 ? 200: 400);
    }

    public function getSequence(Request $req, Response $res, array $args) {
        $feature = $this->model->getSequence($args["id"]);
        if($feature !== false) {
            return $res->withJson($feature);
        }
        return $res->withStatus(404);
    }
}
?>
