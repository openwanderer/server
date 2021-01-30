OpenWanderer server 
===================

This is the OpenWanderer server, now available as a Composer package.

`composer install openwanderer/openwanderer`

The OpenWanderer server, based on the [Slim Framework v4](https://slimframework.com) manages panoramas, including linked sequences of panoramas, and allows clients to upload, retrieve, edit and delete panoramas.

Creating an OpenWanderer server
-------------------------------

A minimal example would be as follows:
```php
<?php
require 'vendor/autoload.php';

use \OpenWanderer\OpenWanderer as OpenWanderer;

$app = OpenWanderer::createApp(["auth" => true]);
$app->run();
?>
```

Note the use of the `OpenWanderer::createApp()` static method. This takes as an argument an associative array of options. Currently there is only one option: `auth`, which controls whether a login is needed to perform sensitive tasks (e.g. panorama upload, panorama editing or deletion). The method returns a `\Slim\App` object which can then be further manipulated if needed.

API endpoints
-------------

The following API endpoints are available with an OpenWanderer server. Note that Slim route parameter syntax is used in this documentation, so that for example `{lon}` is a placeholder for longitude, and `{id:[0-9]+}` is a placeholder for an ID which must be numeric.

These endpoints are available whether `auth` is `true` or `false`:

`GET /panorama/{id:[0-9]+}` - retrieves information about a panorama. Returns a JSON object containing `id`, `lon`, `lat`, `ele` (elevation), `seqid` (the ID of the sequence it belongs to, if it does) and the three rotation angles for the panorama: `pan`, `tilt` and `roll`.  In addition, if `auth` is set to true, the panorama has to be authorised (the `authorised` field in the database set to 1) for it to be accessible to users other than its owner or the administrator. This is to allow for operations such as face or license-plate blurring, which will soon be added to the [bot](https://github.com/openwanderer/server-bot). So if you want all panoramas to be visible immediately, you should set `auth` to `false`.

`GET /panorama/{id:[0-9]+}.jpg` - retrieves the actual panorama with the given ID. Again, the panorama has to be authorised to be visible to non-owner or non-admin users if `auth` is set to `true`.

`DELETE /panorama/{id:[0-9]+}` - deletes the given panorama. If `auth` was set to `true`, only administrators or panorama owners may delete it; otherwise, 401 is returned.

`GET /nearest/{lon]/{lat}` - will retrieve the nearest panorama to a given latitude and longitude.

`GET /panos` - retrieves panoramas by bounding box. Expects a query string parameter `bbox` containing a comma-separated list of bounding box parameters in order west, south, east and north.

`POST /panorama/{id:[0-9]+}/rotate` - sets the pan, tilt and roll to the values supplied in a JSON object containing `pan`, `tilt` and `roll` fields, sent in the request body. If `auth` is set to `true`, only the panorama owner or administrators can perform this operation, otherwise 401 is returned.

`POST /panorama/{id:[0-9]+/move` - moves the panorama position to the given latitude and longitude, supplied as `lat` and `lon` fields within a JSON object sent in the request body. If `auth` is set to `true`, only the panorama owner or administrators can perform this operation, otherwise 401 is returned.

`POST /panorama/{id:[0-9]+/moveMulti` - moves multiple panoramas. A JSON object containing the panorama IDs as keys and JSON objects as specified in `move`, above, should be supplied.

`POST /panorama/upload` - uploads a panorama, supplied as POST data `file` with a type of `file`.

`POST /sequence/create` - creates a new sequence. Expects a JSON array containing the panorama IDs which will make up the sequence, in intended sequence order, sent within the request body. If `auth` is true, only logged-in users may create a sequence, otherwise 401 is returned.

`GET /sequence/{id:[0-9]+}` - retrieves a sequence, with the full details of the panoramas contained within it (see `GET /panorama/{id}` above) as a JSON array of objects.

These endpoints are only available if `auth` is `true`:

`GET /user/login` - gets login information about the current user, as a JSON object containing `username`, `userid` and `isadmin` fields. If the user is not logged in, `username` will be `null` and the other fields will be 0.

`POST /user/login` - logs in a user and sets a session variable `userid`. Expects `username` and `password` POST fields. Returns a JSON object, containing `username`, `userid` and `isadmin` fields (on success) or an `error` field (on error).

`POST /user/logout` - logs out a user by destroying the session.

`POST /user/signup` - signs up a new user. Expects `username`, `password` and `password` POST fields. Performs validation, such as checking that the `username` is an email address (this can be used for example to perform forgotten password functionality, though this is not available by default), checks the password is at least 8 characters, check the two password fields match, and check a user of that username has not signed up already. Returns a JSON object, with a `username` field (on success) or `error` field (on error).

`GET /panos/mine` - returns the panoramas belonging to the currently logged-in user, as JSON, as an array of JSON objects as described in `GET /panorama/{id}` above. Returns 401 if not logged in.

Setting environment variables
-----------------------------

OpenWanderer uses the `.env` file format to control environment variables. You should create a `.env` file in the root directory for your OpenWanderer app, and set the following fields:

- `OTV_UPLOADS` - the directory where panorama files will be uploaded to.
- `MAX_FILE_SIZE` - the maximum file size to accept for panoramas, in MB. Should match the `php.ini` setting.
- `DB_USER` - your database user.
- `DB_DBASE` - the database holding the panoramas.
- `BASE_PATH` (optional) - set to the path (relative to your server root) holding your OpenWanderer app. If omitted, it is assumed the app is in your server root.

Example app
-----------

A full working example app is available in [this repository](https://github.com/openwanderer/example-app). It contains an example `.env` file, `.env-example`, which you need to copy as a `.env` file and edit accordingly.

Licensing
---------

As of the first commit on October 10, 2020, the code is now licensed under the Lesser GNU General Public License, by agreement between both OpenWanderer repository owners (@mrAceT and @nickw). The exception is third-party code such as `geojson-path-finder` which is licensed separately, details in the relevant directories. This has been done to:

- ensure that any changes to OpenWanderer itself will remain Free and open source (if you change OpenWanderer, you must make the modified code available under a compatible free software license); 
- but also allow proprietary applications to *use* OpenWanderer code.

Any further changes to the current OpenTrailView - OTV360; repo [here](https://gitlab.com/nickw1/opentrailview) will remain under the GPL v3.

Please see [here](https://github.com/openwanderer/example-app) for an example app built using the OpenWanderer server.
