import * as OpenWanderer from './jsapi/index.js';

const seqProvider = new OpenWanderer.SimpleSequenceProvider({
	sequenceUrl: '/sequence/{id}'
});

const navigator = new OpenWanderer.Navigator({
	api: { byId: '/panorama/{id}', panoImg: '/panorama/{id}.jpg' },
	loadSeqProviderFunc: seqProvider.getSequence.bind(seqProvider)
});

navigator.loadPanorama(1);

