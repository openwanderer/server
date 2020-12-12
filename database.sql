CREATE TABLE panoramas(id serial, the_geom geometry, ele FLOAT, poseheadingdegrees FLOAT, authorised INT DEFAULT 0, userid INT DEFAULT 0, timestamp INT);
CREATE TABLE users (id serial, username VARCHAR(255), password VARCHAR(255), isadmin INT DEFAULT 0);
CREATE TABLE sequence_geom(id serial, the_geom geometry);
CREATE TABLE sequence_panos(id serial, sequenceid INT, panoid INT);
