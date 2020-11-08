OpenWanderer / OTV-4
====================

This is the OpenWanderer server component. The code is partly based on that of OpenTrailView, but with some significant differences. To try and keep the platform "clean", the aim now will be to use purely sequence-based navigation on OpenWanderer, in which navigation from one pano to another is done by following an uploaded sequence. The OSM-based navigation of current OpenTrailView will not, at least for now, be included. The next version of OpenTrailView, OTV-4, will be based on OpenWanderer.

Licensing
---------

As of the first commit on October 10, 2020, the code is now licensed under the Lesser GNU General Public License, by agreement between both OpenWanderer repository owners (@mrAceT and @nickw). The exception is third-party code such as `geojson-path-finder` which is licensed separately, details in the relevant directories. This has been done to:

- ensure that any changes to OpenWanderer itself will remain Free and open source (if you change OpenWanderer, you must make the modified code available under a compatible free software license); 
- but also allow proprietary applications to *use* OpenWanderer code.

Any further changes to the current OpenTrailView - OTV360; repo [here](https://gitlab.com/nickw1/opentrailview) will remain under the GPL v3.
