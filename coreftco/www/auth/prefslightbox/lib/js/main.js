function reloadParent() {
	
	// Setting a returnurl inluding a hash doesn't close the dialog - strip the anchor if present
	if (returnurl.indexOf('#') != -1) returnurl = returnurl.substring(0, returnurl.indexOf('#'));
	top.location.href = returnurl;
}

function showSaveProgress() {
	obj('successpanel').style.display='block';
	if (obj('formpanel')) obj('formpanel').style.display='none';
}

function obj(name) {
	return document.getElementById(name);
}