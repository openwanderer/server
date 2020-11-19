import * as OpenWanderer from './jsapi/index.js';
import XHRPromise from './xhrpromise.js';

const parts = window.location.href.split('?');     
const get = { }

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

const seqProvider = new OpenWanderer.SimpleSequenceProvider({
    sequenceUrl: '/sequence/{id}'
});

const navigator = new OpenWanderer.Navigator({
    api: { 
        byId: '/panorama/{id}', 
        panoImg: '/panorama/{id}.jpg',
        nearest: '/nearest/{lon}/{lat}'
    },
    loadSequence: seqProvider.getSequence.bind(seqProvider)
});

if(get.lat && get.lon) {
    navigator.findPanoramaByLonLat(get.lon, get.lat);
} else {
    navigator.loadPanorama(get.id || 1);
}

document.getElementById('uploadBtn').addEventListener("click", async(e) => {
    const panofiles = document.getElementById("panoFiles").files;
    if(panofiles.length == 0) {
        alert('No files selected!');
    } else {
        const panoids = [];
        for(let i=0; i<panofiles.length; i++) {
            const formData = new FormData();
            formData.append("file", panofiles[i]);
            const request = new XHRPromise({
                url: '/panorama/upload',
                progress: e => {
                    const pct = Math.round(e.loaded / e.total * 100);
                    showProgress(pct, e.loaded, e.total);
                }    
            });
    
            try {
                const result = await request.post(formData);
                showProgress(0);
                const json = JSON.parse(result.responseText);
                if(json.error) {
                    alert(`Upload error: ${json.error}`);
                } else if (json.warning) {
                    alert(`Upload warning: ${json.warning}`);
                } else if (result.status != 200) {
                    alert(`HTTP error: status ${result.status}`);
                } else if (json.id) {
                    panoids.push(json.id);
                } 
            } catch (e) {
                alert(`Network error: ${e}`);
            }
        }
        if(panoids.length > 0) {
            try { 
                const response = await fetch('/sequence/create', {
                    method: 'POST',
                    body: JSON.stringify(panoids),
                    headers: {
                        'Content-Type' : 'application/json'
                    }
                });
                const seqid = await response.text();
                alert(`Sequence uploaded with ID ${seqid}`);
            } catch(e) {
                alert(`Could not create sequence: error=${e}`);
            }
        }
    }
});


function showProgress (pct, loaded, total) {
    document.getElementById('uploadProgress').innerHTML = 
        pct > 0 ? `Uploaded ${loaded}, total: ${total} (${pct}%)` : "";
    document.getElementById('progress').value = Math.round(pct);
}

