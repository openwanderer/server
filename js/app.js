import MapManager from './map.js';
import XHRPromise from './xhrpromise.js';
import OpenWanderer from './node_modules/openwanderer-jsapi/index.js';
import Dialog from './dialog.js';

class OpenWandererApp {
    constructor(zoom, resize) {
        this.setupNavigator();
        this.setupMediaQueries();
        this.setupUpload();
        this.setupMap(zoom);
        this.setupModes();
        this.navigator.on('locationChanged',(lon,lat)=> {
            this.lon = lon;
            this.lat = lat;
            if(this.mapMgr) {
                this.mapMgr.setView([lat,lon]/*, zoom*/);
            }
        });
        this.setupSignup();
        this.setupLogin();
        this.setupSearch();
        this.setupRotation();
        this.lat = -181;
        this.lon = -91;
    }

    setupNavigator() {
        const seqProvider = new OpenWanderer.SimpleSequenceProvider({
            sequenceUrl: 'sequence/{id}'
        });

        this.navigator = new OpenWanderer.Navigator({
            api: { 
                byId: 'panorama/{id}', 
                panoImg: 'panorama/{id}.jpg',
                nearest: 'nearest/{lon}/{lat}',
            },
            splitPath: true,
            svgEffects: true,
            loadSequence: seqProvider.getSequence.bind(seqProvider)
        });
    }

    setupModes () {
        document.getElementById("switchModeImg").addEventListener("click", this.switchMode.bind(this));
        this.setupMode(0);
    }

    switchMode() {
        this.setupMode(this.mode==0 ? 1:0);
    }

    setupMode(newMode, loadCentrePano=true) {
        var images = ['images/baseline_panorama_white_18dp.png', 'images/baseline_map_white_18dp.png'], alts = ['Panorama', 'Map'];
        document.getElementById('switchModeImg').src = images[newMode==0 ? 1:0];
        document.getElementById('switchModeImg').alt = alts[newMode==0 ? 1:0];
        document.getElementById('switchModeImg').title = alts[newMode==0 ? 1:0];
        

        switch(newMode) {
            case 0:
                document.getElementById('pano').style.display = 'block';
                document.getElementById('drag').style.display = 'none';
                document.getElementById('rotate').style.display = 'none';
                document.getElementById('delete').style.display = 'none';
                document.getElementById('select').style.display = 'none';
                document.getElementById('searchContainer').style.display = 'none';
                this.setupMapPreview();
                
                if(this.mode==1 && loadCentrePano === true) {
                    var mapCentre = this.mapMgr.map.getCenter();
                    fetch(`/nearest/${mapCentre.lng}/${mapCentre.lat}`).then(response => response.json()).then (data=> {
                        if(data.id != this.navigator.curPanoId) {
                            this.navigator.loadPanorama(data.id);
                        }
                    });
                }
                
                break;

            case 1:
                
                document.getElementById('pano').style.display = 'none';
                document.getElementById('map').classList.remove('preview');
                this.mapMgr.map.invalidateSize();
                document.getElementById('select').style.display = 'inline';
                document.getElementById('searchContainer').style.display = 'block';
                if(this.userid) {
                    document.getElementById('drag').style.display = 'inline';
                    document.getElementById('rotate').style.display = 'inline';
                    document.getElementById('delete').style.display = 'inline';
                }
                this.mapMgr.setView([this.lat, this.lon]);
                break;

        }
        this.mode = newMode;
    }

    setupMap(zoom) {

        if(!this.mapMgr) {
            this.mapMgr = new MapManager({userProvider: this,
                                onPanoMarkerClick:id=> { 
                                    this.setupMode(0, false);
                                    this.navigator.loadPanorama(id);
                                },
                                onPanoChange: this.navigator.update.bind(this.navigator),
                                onMapChange: (centre,zoom)=> {
                                        localStorage.setItem('lat', centre.lat);
                                        localStorage.setItem('lon', centre.lng);
                                        localStorage.setItem('zoom', zoom);
                                    
                                },
                                zoom: zoom
                            });
            document.getElementById("select").addEventListener("click", 
                this.selectPanoChangeMode.bind(this, 0));
            document.getElementById("rotate").addEventListener("click", 
                this.selectPanoChangeMode.bind(this, 1));
            document.getElementById("drag").addEventListener("click", 
                this.selectPanoChangeMode.bind(this, 2));
            document.getElementById("delete").addEventListener("click", 
                this.selectPanoChangeMode.bind(this, 3));
            this.selectPanoChangeMode(0);
        }
    }

    selectPanoChangeMode(mode) {
        this.mapMgr.panoChangeMode = mode;
        switch(mode) {
            case 0:
                document.getElementById('select').classList.add('selected');
                document.getElementById('rotate').classList.remove('selected');
                document.getElementById('drag').classList.remove('selected');
                document.getElementById('delete').classList.remove('selected');
                break;
            case 1:
                document.getElementById('rotate').classList.add('selected');
                document.getElementById('drag').classList.remove('selected');
                document.getElementById('select').classList.remove('selected');
                document.getElementById('delete').classList.remove('selected');
                break;
            case 2:
                document.getElementById('drag').classList.add('selected');
                document.getElementById('rotate').classList.remove('selected');
                document.getElementById('select').classList.remove('selected');
                document.getElementById('delete').classList.remove('selected');
                break;
            case 3:
                document.getElementById('delete').classList.add('selected');
                document.getElementById('rotate').classList.remove('selected');
                document.getElementById('select').classList.remove('selected');
                document.getElementById('drag').classList.remove('selected');
                break;
        }
    }
            

    setupUpload () {
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
                        url: 'panorama/upload',
                        progress: e => {
                            const pct = Math.round(e.loaded / e.total * 100);
                            this.showProgress(pct, e.loaded, e.total);
                        }    
                    });
                try {
                    const result = await request.post(formData);
                    this.showProgress(0);
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
    }
        
    setupSearch() {
        document.getElementById('search').addEventListener('click', e=> {
            e.stopPropagation();
        });
        document.getElementById('searchResults').addEventListener('click', e=> {
            e.stopPropagation();
        });

        document.getElementById('search').addEventListener('keyup', e=> {
            if(e.key == 'Enter') {
                var q = document.getElementById('q').value;
                this.nominatimSearch(q);
            }
        });

        document.getElementById('searchBtn').addEventListener('click', e=> {
            var q = document.getElementById('q').value;
            this.nominatimSearch(q);
        });
    }

    nominatimSearch(q) {
        fetch(`nomproxy.php?q=${q}`)
                .then(response=>response.json())
                .then(json=> {
                    var nodes = json.filter(o => o.lat != undefined && o.lon != undefined);
                    if(nodes.length==0) {
                        document.getElementById('searchResults').innerHTML = `No results for ${q}!`;
                    } else {
                        document.getElementById('searchResults').innerHTML = '';       
                        var p = document.createElement('p');
                        var strong = document.createElement("strong"); 
                        strong.appendChild(document.createTextNode("Search results from OSM Nominatim"));
                        p.appendChild(strong);
                        document.getElementById('searchResults').appendChild(p);
                        document.getElementById('searchResults').style.display = 'block';    
                        nodes.forEach(o=> {
                            var p = document.createElement('p');
                            p.style.margin = '0px';
                            var a = document.createElement('a');
                            a.href='#';
                            a.innerHTML = o.display_name;
                            a.addEventListener('click', e=> {
                                this.mapMgr.setView([o.lat, o.lon]);
                                document.getElementById('searchResults').style.display='none';    
                            });
                            p.appendChild(a);
                            document.getElementById('searchResults').appendChild(p);

                        });
                }
          });                  
    }

    setupMediaQueries() {
        this.mq = window.matchMedia("(max-width: 600px)");
        this.isMobile = this.mq.matches;
        this.mq.addListener ( mq=> {
            this.isMobile = mq.matches;
        });
    }

    setupMapPreview() {
        document.getElementById('map').classList.add('preview');
        this.mapMgr.map.invalidateSize();
    }
    
    showProgress (pct, loaded, total) {
        document.getElementById('uploadProgress').innerHTML = 
            pct > 0 ? `Uploaded ${loaded}, total: ${total} (${pct}%)` : "";
        document.getElementById('progress').value = Math.round(pct);
    }


    setupRotation() {
        document.getElementById("anticw").addEventListener("click", this.rotatePano.bind(this, -5, 'pan'));
        document.getElementById("cw").addEventListener("click", this.rotatePano.bind(this, 5, 'pan'));
        document.getElementById('tiltminus').addEventListener('click', this.rotatePano.bind(this, -5, 'tilt'));
        document.getElementById('tiltplus').addEventListener('click', this.rotatePano.bind(this, 5, 'tilt'));
        document.getElementById('rollminus').addEventListener('click', this.rotatePano.bind(this, -5, 'roll'));
        document.getElementById('rollplus').addEventListener('click', this.rotatePano.bind(this, 5, 'roll'));
        document.getElementById('save').addEventListener('click', this.saveRotation.bind(this));
    }       

    rotatePano(ang, component) {
        this.navigator.viewer.rotate(ang, component);
    }

    saveRotation() {
        const orientations = Object.assign({}, this.navigator.viewer.orientation);
        Object.keys(orientations).map ( k => { 
            orientations[k] *= 180/Math.PI; 
        });
        fetch(`panorama/${this.navigator.curPanoId}/rotate`, {
            method: 'POST',
            body: JSON.stringify(orientations),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            alert(response.status == 200 ? 'Saved new rotation': (response.status == 401 ? 'This is not your panorama.' : `HTTP error: ${response.status}`));
        })
        .catch(e => {
            alert(`ERROR: ${e}`);
        });
    } 

    setupLogin() {
        this.loginDlg = new Dialog('main',
                { 'Login': this.processLogin.bind(this),
                'Cancel': ()=> { this.loginDlg.hide(); }},
                {
                backgroundColor: "rgba(128,64,0)",
                color: "white",
                textAlign: "center" });
        this.loginDlg.setContent('<h2>Login</h2>'+
            "<p><span id='loginError' class='error'></span><br />"+
            "<label for='username'>Email address</label><br />" +
            "<input id='username' type='text' /> <br />"+
            "<label for='password'>Password</label><br />" +
            "<input id='password' type='password' /> </p>");
        this.loginDlg.div.id='dlgLogin';
        
        fetch ('login').then(resp => resp.json()).then(json => {
                this.username = json.username;
                this.userid = json.userid;
                this.isadmin = json.isadmin;
                this.onLoginStateChange();
        });
        this.username = null;
        this.userid = 0; 
        this.isadmin = 0; 
        this.onLoginStateChange();
    }    

    setupSignup() {
        this.signupDlg = new Dialog('main',
                { 'Signup': this.processSignup.bind(this),
                'Close': ()=> { this.signupDlg.hide(); }},
                {
    
                backgroundColor: "rgba(128,64,0)",
                color: "white", padding: '10px',
                textAlign: "center" });
        this.signupDlg.setContent(
"<h2>Sign up</h2>"+
"<p id='signupMsg' class='error'></p>"+
"<p>Signing up will allow you to upload panoramas, view and position your "+
"existing panoramas, and adjust your panoramas (such as rotate and "+
"move them).</p>" +
"<label for='username'>"+
"Enter your email address:"+
"</label>"+
"<br />"+
"<input name='signup_username' id='signup_username' type='text' />"+
"<br /> <label for='signup_password'>Enter a password: </label> <br />"+
"<input name='signup_password' id='signup_password' type='password'/>" +
"<br /> <label for='password2'>Re-enter your password: </label> <br />"+
"<input name='password2' id='password2' type='password'/>"+
"</div>");

        this.signupDlg.div.id = 'dlgSignup';
    }    


    processLogin() {
        var json=JSON.stringify({"username": document.getElementById("username").value,  "password": document.getElementById("password").value});
        fetch('login', { method: 'POST', headers: {'Content-Type': 'application/json'}, body:json})
            .then(res => {
                if(res.status == 401) {
                   throw('Incorrect login.');
                }
                else return res.json();
             }) 
             .then(json => {
                if(json.error) {
                    document.getElementById("loginError").innerHTML = json.error;
                } else if(json.userid) {
                    this.username = json.username;
                    this.userid = json.userid;
                    this.isadmin = json.isadmin;
                    this.loginDlg.hide();
                    this.onLoginStateChange();
                    this.checkAuthorised(this.navigator.curPanoId);
                }
             })
            .catch(e => { 
                document.getElementById('loginError').innerHTML = e;
            });
    }

    processSignup() {
        var json=JSON.stringify({"username": document.getElementById("signup_username").value,  "password": document.getElementById("signup_password").value, "password2": document.getElementById("password2").value});
        fetch('signup', { method: 'POST', headers: {'Content-Type': 'application/json'}, body:json})
                .then(res => res.json())
                .then(json => {
                    if(json.error) {
                        document.getElementById("signupMsg").innerHTML = json.error;
                    } else if(json.username) {
                        document.getElementById("signupMsg").innerHTML = `Successfully signed up as ${json.username}`; 
                    } else {
                        document.getElementById("signupMsg").innerHTML = 'Failed to add your details.';
                    }
        });
    }

    onLoginStateChange(){
        if(this.userid) {
            this.onLogin();
        } else {
            this.onLogout();
        }
        if(this.mapMgr && this.mapMgr.initialised) {
            this.mapMgr.clearMarkers();
            this.mapMgr.loadPanoramas();
        }
    }

    onLogin() {
        document.getElementById("loginContainer").innerHTML = "";
        var t = document.createTextNode(`Logged in as ${this.username}`);
        var a = document.createElement("a");


        document.getElementById("loginContainer").appendChild(t);
        document.getElementById("loginContainer").appendChild(a);

        a = document.createElement("a");
        a.id="logout";
        a.addEventListener("click", this.logout.bind(this));
        a.appendChild(document.createTextNode(" "));
        a.appendChild(document.createTextNode("Logout"));
        document.getElementById("loginContainer").appendChild(a);
        document.getElementById("upload").style.display = "block";
        if(this.mode == 1) {
            document.getElementById("drag").style.display = "inline";
            document.getElementById("rotate").style.display = "inline";
            document.getElementById("delete").style.display = "inline";
        }
        this.mapMgr.activated = true;    
    }

    onLogout() {
        document.getElementById("loginContainer").innerHTML = "";
        var as = document.createElement("a");
        as.id="signup";
        as.addEventListener("click", this.signupDlg.show.bind(this.signupDlg));
        as.appendChild(document.createTextNode("Sign up"));
        var al = document.createElement("a");
        al.id="login";
        al.addEventListener("click", ()=> {
            this.loginDlg.show();
        });
        al.appendChild(document.createTextNode("Login"));
        document.getElementById("loginContainer").appendChild(as);
        document.getElementById("loginContainer").appendChild(document.createTextNode(" | "));
        document.getElementById("loginContainer").appendChild(al);
        document.getElementById("upload").style.display = "none";
        document.getElementById("drag").style.display = "none";
        document.getElementById("rotate").style.display = "none";
        document.getElementById("delete").style.display = "none";
        this.mapMgr.activated = false;    
    } 

    logout() {
        fetch('logout', {method:"POST"}).then(resp=> {
            this.username = null;
            this.userid = this.isadmin = 0; 
            this.onLoginStateChange();
        });
    }
}

export default OpenWandererApp;
