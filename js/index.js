const OpenWanderer = require('./jsapi');
const XHRPromise = require('./xhrpromise');
const exifr = require('exifr');
const DemTiler = require('jsfreemaplib/demtiler');
const jsfreemaplib = require('jsfreemaplib');

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

const seqProvider = new OpenWanderer.SimpleSequenceProvider({
    sequenceUrl: 'sequence/{id}'
});

const navigator = new OpenWanderer.Navigator({
    api: { 
        byId: 'panorama/{id}', 
        panoImg: 'panorama/{id}.jpg',
        nearest: 'nearest/{lon}/{lat}',
    },
    splitPath: true,
    loadSequence: seqProvider.getSequence.bind(seqProvider)
});

const tiler = new DemTiler('https://hikar.org/webapp/proxy.php?x={x}&y={y}&z={z}');
tiler.setZoom(13);

let origContent = "";

if(get.lat && get.lon) {
    navigator.findPanoramaByLonLat(get.lon, get.lat);
} else {
    navigator.loadPanorama(get.id || 1);
}

fetch('user/login')
    .then(response => response.json())
    .then(json => {
        if(json.userid > 0) {
            onLogin(json);
        } else {
            setupLoginBtn();
        }
    });



document.getElementById('signup').addEventListener('click', async(e) => {

    const response = await fetch('user/signup', {
        method: 'POST',
        body: JSON.stringify({
            "username": document.getElementById('username').value,
            "password": document.getElementById('password').value
        }),
        headers: {
            'Content-Type' : 'application/json'
        }
    });
    if(response.status != 200) {
        alert(`Server error ${response.status}`);
    } else {
        const json = await response.json();
        alert(json.error ? `Error: ${json.error}`: 'Signed up successfully.');
    }
});

document.getElementById('uploadBtn').addEventListener("click", async(e) => {
    const panofiles = document.getElementById("panoFiles").files;
    if(panofiles.length == 0) {
        alert('No files selected!');
    } else {
        const panoids = [];
        for(let i=0; i<panofiles.length; i++) {
            const formData = new FormData();
            formData.append("file", panofiles[i]);
            const exifdata = await getExif(panofiles[i]);
            if(exifdata !== false) {
                formData.append("lon", exifdata.longitude);
                formData.append("lat", exifdata.latitude);
                const sphMerc = tiler.lonLatToSphMerc(exifdata.longitude, exifdata.latitude);
                const dem = await tiler.getData(sphMerc);
                const ele = dem.getHeight(sphMerc[0], sphMerc[1]);
                formData.append("ele", ele);
            }
            const request = new XHRPromise({
                url: 'panorama/upload',
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
                } else {
                    alert('Missing ID in panorama - this should not happen');
                }
            } catch (e) {
                alert(`Network error: ${e}`);
            }
        }
        if(panoids.length > 0) {
            try { 
                const response = await fetch('sequence/create', {
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

async function getExif(file) {         
    try {             
        const exif = await file.arrayBuffer().then(exifr.parse); 
           return exif;         
    } catch(e) {         
        return false;
    }
}


function setupLoginBtn() {
    document.getElementById('login').addEventListener('click', async(e) => {

        const response = await fetch('user/login', {
            method: 'POST',
            body: JSON.stringify({
                "username": document.getElementById('username').value,
                "password": document.getElementById('password').value
            }),
            headers: {
                'Content-Type' : 'application/json'
            }
        });
        if(response.status == 401) {
            alert('Invalid login!');
        } else {
            onLogin(await response.json());
        }
    });
}

function onLogin(json) {
    document.getElementById('upload').style.display = 'block';
    console.log(JSON.stringify(json));
    origContent = document.getElementById('logindiv').innerHTML;
    document.getElementById('logindiv').innerHTML = '';
    const newContent = document.createTextNode(`Logged in as ${json.username}`);
    const logoutBtn = document.createElement('input');
    logoutBtn.setAttribute('type', 'button');
    logoutBtn.setAttribute('value', 'logout');
    logoutBtn.addEventListener('click', e => {
        fetch('user/logout', {
                method: 'POST'
        })
        .then(onLogout);
    });
    document.getElementById('logindiv').appendChild(newContent);
    document.getElementById('logindiv').appendChild(logoutBtn);
}

function onLogout() {
    document.getElementById('logindiv').innerHTML = origContent;
    setupLoginBtn();
    document.getElementById('upload').style.display = 'none';
}        
