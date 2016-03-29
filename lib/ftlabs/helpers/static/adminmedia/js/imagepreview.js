

function ImagePreview() {
	ImagePreview.holdframe = false;
	ImagePreview.previewframe = false;
	ImagePreview.mouseoffset = 20;
	ImagePreview.displaytimer = false;
	ImagePreview.xcoord = false;
	ImagePreview.ycoord = false;
}
ImagePreview.init = function () {
	var ipts = document.getElementsByTagName("input");
	for (var i=0; i<ipts.length; i++) {
		if (ipts[i].className.indexOf("imageupload") != -1) {
			var mybody=document.getElementsByTagName("body").item(0);
			ImagePreview.holdframe = document.createElement("DIV");
			ImagePreview.holdframe.setAttribute("id", "trailimage");
			ImagePreview.holdframe.style.position = "absolute";
			ImagePreview.holdframe.style.visibility = "hidden";
			ImagePreview.holdframe.style.left = "0px";
			ImagePreview.holdframe.style.top = "0px";
			ImagePreview.holdframe.style.width = "302px";
			ImagePreview.holdframe.style.height = "252px";
			ImagePreview.holdframe.style.overflow = "hidden";
			var bgiframe = document.createElement("IFRAME");
			bgiframe.className = "uploadiframe";
			bgiframe.style.zIndex = "-20";
			bgiframe.src = "about:blank";
			ImagePreview.holdframe.appendChild(bgiframe);
			ImagePreview.previewframe = document.createElement("DIV");
			ImagePreview.previewframe.style.height = "250px";
			ImagePreview.previewframe.style.backgroundColor = "white";
			ImagePreview.previewframe.style.position = "relative";
			ImagePreview.previewframe.style.border = "1px solid black";
			ImagePreview.holdframe.appendChild(ImagePreview.previewframe);
			mybody.appendChild(ImagePreview.holdframe);
			break;
		}
	}
}

ImagePreview.hidetrail = function () {
	if (ImagePreview.holdframe) ImagePreview.holdframe.style.visibility="hidden";
	document.onmousemove="";
}

ImagePreview.showtrail = function (srcFile) {
	if (!ImagePreview.holdframe) {
		if (ImagePreview.displaytimer) clearTimeout(ImagePreview.displaytimer);
		ImagePreview.displaytimer = setTimeout(function() {ImagePreview.showtrail(srcFile);}, 500);	
	} else {
		if (ImagePreview.previewframe.firstChild) {
			ImagePreview.previewframe.removeChild(ImagePreview.previewframe.firstChild);
		}
		var img = document.createElement("IMG");
		ImagePreview.previewframe.appendChild(img);
		img.style.position = "absolute";
		img.onload = function (event) {
			ImagePreview.sizeimage();
		}
		img.src = srcFile;
		document.onmousemove=ImagePreview.followmouse;
	}
}

ImagePreview.sizeimage = function () {
	var img = ImagePreview.previewframe.firstChild;
	if (img && img.width) {
		if ((img.width > 290) || (img.height > 240)) {
			if ((img.width/290) > (img.height/240)) {
				var scalefactor = img.width/290;
			} else {
				var scalefactor = img.height/240;
			}
			var newwidth = img.width / scalefactor;
			var newheight = img.height / scalefactor;
			img.width = newwidth;
			img.height = newheight;
			img.style.left = "5px";
			img.style.top = "5px";
		}
		if (img.width < 290) img.style.left = (Math.ceil((290-img.width)/2)+5)+"px";
		if (img.height < 240) img.style.top = (Math.ceil((240-img.height)/2)+5)+"px";
		ImagePreview.followmouse();
		ImagePreview.holdframe.style.visibility="visible";
	}
}

ImagePreview.followmouse = function (e) {
	var pf = obj("trailimage");
	if (typeof e != "undefined"){
		ImagePreview.xcoord = e.pageX + 20;
		ImagePreview.ycoord = e.pageY + 20;
	} else if (typeof window.event !="undefined"){
		ImagePreview.xcoord = document.body.scrollLeft+event.clientX+20;
		ImagePreview.ycoord = document.body.scrollTop+event.clientY+20;
	}
	if (ImagePreview.xcoord) {
		pf.style.left = ImagePreview.xcoord+"px"
		pf.style.top = ImagePreview.ycoord+"px"
	}
}

