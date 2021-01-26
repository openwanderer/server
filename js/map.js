import './node_modules/leaflet-rotatedmarker/leaflet.rotatedMarker.js';

class MapManager {
    constructor(options) {
        this.indexedFeatures = [];
        this.activated = false;
        this.panoChangeMode = 0;
        this.userProvider = options.userProvider;
        this.onPanoMarkerClick = options.onPanoMarkerClick;
        this.markerClusterGroup = L.markerClusterGroup({disableClusteringAtZoom: 14});
        this.setupLeafletMap(options.zoom || 16);
        this.onPanoChange = options.onPanoChange;
        this.onMapChange = options.onMapChange || null;
        this.newPanos = {};
        this.initialised = false;
    }

    setupLeafletMap(zoom) {
        if(!this.map) {
            this.map = L.map('map', {maxZoom:20});
            this.map.setZoom(zoom);
            this.map.addLayer(this.markerClusterGroup);
            this.layer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {attribution: 'Map data (c)OpenStreetMap contributors, ODBL; contours SRTM | Map display: &copy; OpenTopoMap (CC-By-SA)', maxZoom: 20, maxNativeZoom: 16});
            this.layer.addTo(this.map);
            this.map.on("dragend", e=> { 
                this.loadPanoramas();
                if(this.onMapChange) {
                    this.onMapChange(this.map.getCenter(), this.map.getZoom());
                }
            });
            this.map.on('zoomstart', e=> { this.oldZoom = e.target.getZoom();});
            this.setupGeojsonLayer();
            this.map.on("zoomend", e=> { 
                if(this.onMapChange) {
                    this.onMapChange(e.target.getCenter(), e.target.getZoom());
                }
            });
            this.map.on("move", e=> {
                var lat = this.map.getCenter().lat.toFixed(4);
                var lon = this.map.getCenter().lng.toFixed(4);
                var zoom= this.map.getZoom();

                window.history.pushState  ({lat:lat, lon:lon, zoom: zoom},"OpenTrailView", `${window.location.href.replace('#','').split("?")[0]}?lat=${lat}&lon=${lon}&zoom=${zoom}`);
                
            });
        }
    }

    setView(latLng, zoom) {
        if(this.map) {
            this.map.setView(latLng, zoom || this.map.getZoom());
            if(!this.locationMarker) {
                this.locationMarker = L.marker(latLng, { icon:
                    L.icon({
                        iconUrl:'images/person.png',
                        iconSize:[24,33],
                        iconAnchor:[12,33]
                    }),
                    zIndexOffset: 1000 
                }).addTo(this.map);
            }
            this.locationMarker.setLatLng(latLng);
            this.loadPanoramas();
            this.initialised = true;
        } 
    }

    loadPanoramas() {

        var w = this.map.getBounds().getSouthWest().lng;    
        var s = this.map.getBounds().getSouthWest().lat;    
        var e = this.map.getBounds().getNorthEast().lng;    
        var n = this.map.getBounds().getNorthEast().lat;    
        
        var resp = fetch(`panos?bbox=${w},${s},${e},${n}`).then(resp=>resp.json()).then(json=> {
            json.features.forEach( f=> { 
                if(!this.indexedFeatures[f.properties.id]) {
                    this.geojsonLayer.addData(f); 
                    this.indexedFeatures[f.properties.id] = f;
                }
            });
        });
    }

    setupGeojsonLayer() {
        if(!this.geojsonLayer) {
            
            var cameraIcon = L.icon({
                iconUrl: 'images/camera.png',
                iconSize:[24,24],
                iconAnchor:[12,12],
                popupAnchor:[8,8]
            });


            this.geojsonLayer = L.geoJSON(null, {
                pointToLayer: (f,latlng)=> {
                    var p = L.marker(latlng, {icon: cameraIcon, rotationAngle: parseInt(f.properties.pan), draggable:true} );
                    p.setRotationAngle(parseFloat(f.properties.pan));
                    this.markerClusterGroup.addLayer(p);
                    return p;
                },
                onEachFeature: (f,layer)=> {
                    layer.on("mousedown", e=> { 
                        if(f.properties.userid==this.userProvider.userid || this.userProvider.isadmin == 1) {
                            if(this.panoChangeMode==1) {
//                                layer.isDown=true;  
                                layer.dragging.disable();
                                this.map.dragging.disable();
                                this.timer = setInterval(this.onPanoMarkerRotate.bind(this, f, layer), 10);
                            } else if(this.panoChangeMode==2) {
                                layer.dragging.enable();
                            }
                        }
                        if(this.panoChangeMode==0) {
                            layer.dragging.disable();
                        }
                    });
//                    layer.on("mousemove", this.onPanoMarkerRotate.bind(this, f, layer));
                    layer.on("mouseup", e=> { 
                        this.onPanoMarkerMouseUp(f, layer);
                    });
                    layer.on("mouseout", e=> { 
                        this.onPanoMarkerMouseUp(f, layer);
                    });

                    layer.on("click", (e)=> {
                        if(this.panoChangeMode==0) {
                            this.onPanoMarkerClick(f.properties.id);
                            this.setView(e.latlng);
                        } else if (this.panoChangeMode==3) {
                            if(f.properties.userid==this.userProvider.userid || this.userProvider.isadmin==1) {
                                this.deletePano(f, layer);
                            } else {
                                alert('You can only delete your own panoramas.');
                            }
                        }
                    });
    
                    layer.on("dragend", e=> {

                        if(this.activated && this.panoChangeMode==2 && (f.properties.userid==this.userProvider.userid || this.userProvider.isadmin==1)) {
                            fetch(`panorama/${f.properties.id}/move`,
                                { body: JSON.stringify(
                                    {lat:layer.getLatLng().lat, lon:layer.getLatLng().lng}),
                                headers: { 'Content-Type': 'application/json'},
                                method:'POST'}).
                                then(response=>response.text()).
                                then(txt=>{ 
                                    this.onPanoChange(f.properties.id,  {position:[layer.getLatLng().lng, layer.getLatLng().lat]});
                                });
                        }
                    });
                }
            } );
        } 
    }

    onPanoMarkerRotate(f, layer) {
        if(this.activated && (f.properties.userid==this.userProvider.userid || this.userProvider.isadmin==1) &&  this.panoChangeMode==1) {
            var newAngle = (layer.options.rotationAngle + 1) % 360;
            layer.setRotationAngle(newAngle);
        }
    }

    onPanoMarkerMouseUp(f,layer) {
        if(this.activated && this.panoChangeMode==1 && (f.properties.userid==this.userProvider.userid || this.userProvider.isadmin == 1) && this.timer) {
//            layer.isDown=false;  
            clearInterval(this.timer);
            this.timer = null;
            this.map.dragging.enable();
            fetch(`panorama/${f.properties.id}/rotate`,
                { body: JSON.stringify(
                    {pan:layer.options.rotationAngle}
                ), 
                headers: { 'Content-Type': 'application/json'}, 
                method: 'POST'}).
                then(response=>response.text()).
                then(txt=>{
                    this.onPanoChange(f.properties.id, {pan: layer.options.rotationAngle});
                });
        }
    }

    addNewPano(id, lat, lon) {
        var cameraIcon = L.icon({
                iconUrl: 'images/camera.png',
                iconSize:[24,24],
                iconAnchor:[12,12],
                popupAnchor:[8,8]
            });
        if(this.newPanos[id]) {
            this.map.removeLayer(this.newPanos[id].icon);
            this.map.removeLayer(this.newPanos[id].circle);
        }
        var latlng = [lat, lon];
        var p = L.marker(latlng, {icon: cameraIcon} ).addTo(this.map);
        var circle = L.circle(latlng, { radius: 30, color:'#ff8800',opacity:0.5}).addTo(this.map);
        this.doSelectNewPano(latlng);
        this.newPanos[id] = { icon: p, circle: circle };
        this.map.addLayer(p);
    }

    selectNewPano(id) {
        if(this.newPanos[id]) {
            var latlng = this.newPanos[id].icon.getLatLng();
            this.doSelectNewPano(latlng);
            this.map.setView(latlng);
        }
    }

    doSelectNewPano(latlng) {
        if(this.selectedNewPano) {
            this.map.removeLayer(this.selectedNewPano);
        }
        this.selectedNewPano = L.circle(latlng, { radius: 50, color: 'red', fillColor: 'red', opacity:0.5}).addTo(this.map);
            
    }

    deletePano(f, layer) {
        fetch(`panorama/${f.properties.id}`, { method: 'DELETE' })
            .then(response => {
                if(response.status == 200) {
                    layer.removeFrom(this.map);
                    this.markerClusterGroup.removeLayer(layer);
                    alert('Panorama deleted.'); 
                } else {
                    alert(`Error deleting panorama: code ${response.status}`); 
                }
            });
    }

    removeNewPanos() {
        for (var i in this.newPanos) {
            this.map.removeLayer(this.newPanos[i].icon);
            this.map.removeLayer(this.newPanos[i].circle);
        }
        this.newPanos = {};
        if(this.selectedNewPano) {
            this.map.removeLayer(this.selectedNewPano);
        }
    }

    clearMarkers() {
        this.geojsonLayer.clearLayers();
        this.markerClusterGroup.clearLayers();
        this.indexedFeatures = [];
    }
}

export default MapManager;

