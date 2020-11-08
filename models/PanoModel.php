<?php

class PanoModel {

    function __construct($db) {
        $this->db = $db;
    }

    function getById($id) {
        $stmt = $this->db->prepare("SELECT *,ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat FROM panoramas WHERE id=?");
        $stmt->execute([$id]);
        return $this->getRowOrNull($stmt);
    }

    function getByIdAuthorised($id, $uid) {
        $stmt = $this->db->prepare("SELECT id,ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat, poseheadingdegrees FROM panoramas WHERE id=:id AND (authorised=1 OR userid=:uid)");
        $stmt->execute([':id'=>$id, ':uid'=>$uid]);
        return $this->getRowOrNull($stmt);
    }

    function getNearest($lon, $lat) {
        $geom = "ST_Distance(ST_GeomFromText('POINT({$lon} {$lat})',4326),the_geom)";
        $results = $this->db->query("SELECT id,ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat, poseheadingdegrees FROM panoramas ORDER BY $geom LIMIT 1");
        $row = $results->fetch(PDO::FETCH_ASSOC);
        return $row;
    }

    function getByBbox($bb) {
        $stmt=$this->db->prepare("SELECT id, ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat, poseheadingdegrees,userid FROM panoramas WHERE ST_X(the_geom) BETWEEN :w AND :e AND ST_Y(the_geom) BETWEEN :s AND :n");
        $stmt->execute([":w"=>$bb[0], ":s"=>$bb[1], ":e"=>$bb[2], ":n"=>$bb[3]]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    function getByBboxAuthorised($bb, $uid) {
        $stmt=$this->db->prepare("SELECT id, ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat, poseheadingdegrees,userid FROM panoramas WHERE ST_X(the_geom) BETWEEN :w AND :e AND ST_Y(the_geom) BETWEEN :s AND :n AND (authorised=1 OR userid=:uid)");
        $stmt->execute([":w"=>$bb[0], ":s"=>$bb[1], ":e"=>$bb[2], ":n"=>$bb[3], ":uid"=>$uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getPanosByCriterion($sql, array $boundData=[]) {
        $stmt = $this->db->prepare("SELECT id, poseheadingdegrees, timestamp, authorised, ST_X(the_geom) AS lon, ST_Y(the_geom) AS lat FROM panoramas $sql ORDER BY id");
        $result=$stmt->execute($boundData);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function rotate($id, $heading) {
          $stmt = $this->db->prepare("UPDATE panoramas SET poseheadingdegrees=:deg WHERE id=:id");
        $stmt->execute([":id"=>$id,":deg"=>$heading]);
    }

    function move($id, $lon, $lat) {    
        $geom = "ST_GeomFromText('POINT($lon $lat)',4326)";
        $stmt = $this->db->prepare("UPDATE panoramas SET the_geom=$geom WHERE id=:id");
        $stmt->execute([":id"=>$id]);
    }

    function authorisedToChange($panoid, $uid) {
          $stmt = $this->db->prepare("SELECT * FROM panoramas WHERE id=?");
        $stmt->execute([$panoid]);
        $row = $stmt->fetch();
        return $row["userid"]==$uid;
    }

    function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM panoramas WHERE id=?");
        $stmt->execute([$id]);
        $file = OTV_RAW.UPLOADS."/$id.jpg";
        if(file_exists($file)) {
            unlink($file);
        }
    }

    function authorise($id) {
        $stmt = $this->db->prepare("UPDATE panoramas SET authorised=1 WHERE id=?");
           $stmt->execute([$id]);
    }

    function createSequence($panos) {
          $list = implode(",", array_map (function($pano) {
                return "$pano[lon] $pano[lat]";
            }, $panos));
        $this->db->query("INSERT INTO sequence_geom (the_geom) VALUES (ST_GeomFromText('LINESTRING($list)', 4326))");
        $seqid = $this->db->lastInsertId();
        foreach($panos as $pano) {
            $stmt = $this->db->prepare("INSERT INTO sequence_panos (sequenceid, panoid) VALUES(?,?)");
            $stmt->execute([$seqid, $pano["id"]]);
        }
        return $seqid;
    }

    function getSequence($seqid) {
        $stmt = $this->db->prepare("SELECT ST_AsGeoJSON(the_geom) AS json FROM sequence_geom WHERE id=?");
        $stmt->execute([$seqid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row !== false) {
            $feature = [ "type" => "Feature", "geometry" => json_decode($row["json"])];
            $stmt2 = $this->db->prepare("SELECT panoid FROM sequence_panos WHERE sequenceid=? ORDER BY id");
            $stmt2->execute([$seqid]);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $ids = array_map (function($row) { return $row["panoid"]; } , $rows);
            $feature["properties"]["ids"] = $ids;
            return $feature;
        }
        return false;
    }

    function insertPano($lon, $lat, $heading, $uid, $timestamp) {
        $geometry="ST_GeomFromText('POINT($lon $lat)',4326)";
        $stmt = $this->db->query("INSERT INTO panoramas (the_geom,poseheadingdegrees,userid,timestamp,authorised)  VALUES ($geometry,$heading, '$uid',$timestamp, 0)");
        $id = $this->db->lastInsertId();
        return $id;
    }

    private function getRowOrNull($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row !== false) {
            return $row;
        }
        return null;
    }
}

?>
