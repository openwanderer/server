import OpenWandererApp from './app.js';

const parts = window.location.href.split('?');     
const get = { };

if(parts.length==2) {         
    if(parts[1].endsWith('#')) {             
        parts[1] = parts[1].slice(0, -1);         
    }         
    const params = parts[1].split('&');         
    for(let i=0; i<params.length; i++) {   
        const param = params[i].split('=');             
        get[param[0]] = param[1];         
    }     
}    

const app = new OpenWandererApp();

if(get.lat && get.lon) {
    app.navigator.findPanoramaByLonLat(get.lon, get.lat);
} else {
    app.navigator.loadPanorama(get.id || 1);
}
