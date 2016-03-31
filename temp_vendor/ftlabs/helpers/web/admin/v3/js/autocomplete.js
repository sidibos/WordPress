function AutoCompleteField(fieldID, urlToCheck, hideFieldsArray, minwidth) {
	this.acArray = new Array();
	this.prev = false;
	this.minwidth = (isNaN(minwidth)) ? 250 : minwidth;
	this.theField = document.getElementById(fieldID);
	this.fieldID = fieldID;
	if (!this.theField) return false;
	this.theField.setAttribute("autocomplete", "off");
	var acfield = this;
	this.theField.onblur = function () { acfield.blur(); };
	this.theField.onkeydown = function (e) { return acfield.keyDown(e) };
	this.theField.onkeypress = function (e) { return acfield.keyPress(e) };
	this.theField.onkeyup = function () { if (acfield.acArray["lastkey"] == "delete") acfield.scheduleLookup(2000); else acfield.scheduleLookup(100); };
	this.ACDiv = false;
	this.acArray["url"] = urlToCheck;
	this.acArray["lookuponkey"] = 0;
	this.acArray["curkey"] = 0;
	this.acArray["hidefields"] = hideFieldsArray;
	this.acArray["cache"] = new Array();
	this.lookuptimer = false;
	return;
}

AutoCompleteField.prototype.redraw = function () {
	topPos = 0;
	thisDiv = this.theField;
	while (thisDiv) {
		topPos += thisDiv.offsetTop;
		thisDiv = thisDiv.offsetParent;
	}
	leftPos = 0;
	thisDiv = this.theField;
	while (thisDiv) {
		leftPos += thisDiv.offsetLeft;
		thisDiv = thisDiv.offsetParent;
	}
	this.ACDiv.style.left = leftPos+"px";
	var acdivwidth = this.theField.offsetWidth + 2;
	if (acdivwidth < this.minwidth) acdivwidth = this.minwidth;
	if (window.XMLHttpRequest) acdivwidth = acdivwidth - 4;
	this.ACDiv.style.width = acdivwidth + "px";
	this.ACDiv.style.top = (topPos+this.theField.offsetHeight-1)+"px";
	this.ACDiv.style.display = "block";
}

// When focus is moved away from the field, obviously need to hide the
// autocomplete
AutoCompleteField.prototype.blur = function () {
	if (this.ACDiv) this.ACDiv.style.display = "none";
	for (var i=0; i<this.acArray["hidefields"].length; i++) {
		document.getElementById(this.acArray["hidefields"][i]).style.display = "block";
	}
	this.acArray["display"] = false;
}

// Schedules a lookup event, replacing any existing schedules.
AutoCompleteField.prototype.scheduleLookup = function (delay) {
	if (typeof this.lookuptimer == "number") {
		window.clearTimeout(this.lookuptimer);
		delete this.lookuptimer;
	}
	var self = this;
	this.lookuptimer = window.setTimeout(function() { self.lookup(); }, delay);
}

// Perform a scheduled lookup of the current field contents
AutoCompleteField.prototype.lookup = function () {
	var seltext = getSel(this.theField);
	if (!seltext && this.acArray["lastlookup"]!=this.theField.value && this.theField.value) {
		this.acArray["usercontent"] = this.theField.value;
		this.acArray["display"] = true;
		this.acArray["lookuponkey"] = this.acArray["curkey"];
		
		// If the value has been cached, use the cache
		if (this.acArray["cache"][this.theField.value]) {
			this.acArray["lastlookup"] = this.theField.value;
			this.drawResponse(this.acArray["cache"][this.theField.value]);
			
		// Else start the xmlHttp lookup
		} else {
			var currentTime = new Date();
			if (this.acArray["req"]) delete this.acArray["req"];
			this.acArray["req"] = newXmlHttp();
			var url = this.acArray["url"]+'?field='+URLEncode(this.fieldID)+'&value='+URLEncode(this.theField.value);
			this.acArray["req"].open("GET", url, true);

			// When the response is received, process it
			var acfield = this;
			this.acArray["req"].onreadystatechange = function () { acfield.processResponse() };
			this.acArray["req"].send(null);
			this.acArray["lastlookup"] = this.theField.value;
		}
	}
}

AutoCompleteField.prototype.processResponse = function() {
	if (this.acArray["req"] && this.acArray["req"].readyState == 4) {
		if (this.acArray["req"].status == 200) {
			if (this.acArray["req"].responseXML) {
				var response = this.acArray["req"].responseXML.documentElement;
				if (response) {
					var nodes = response.childNodes;
					var str;
					for (var i=0; i<nodes.length; i++) {
						if (nodes[i].nodeName=="value") origQuery = nodes[i].firstChild.data;
						if (nodes[i].nodeName=="field") fieldID = nodes[i].firstChild.data;
					}
					if (nodes.length) {
						this.acArray["cache"][origQuery] = response;
						this.drawResponse(response);
					}
				}
			} else {
				alert(this.acArray["req"].responseText);
			}
		} else {
			alert(this.acArray["req"].responseText);
		}
		delete this.acArray["req"];
	}
}


// Parse the response to recreate the autosuggestion list
AutoCompleteField.prototype.drawResponse = function (theXML) {

	// Check to see if the div has been created - if not, insert it.  Used to
	// be done on init, but adding items when the page was not fully loaded
	// causes IE issues/
	if (!this.ACDiv) {
		var mybody=document.getElementsByTagName("body").item(0);
		this.ACDiv = document.createElement("DIV");
		this.ACDiv.id = "autocomdiv"+this.fieldID;
		this.ACDiv.style.border = "1px solid black";
		this.ACDiv.style.borderTop = "1px solid #707070";
		this.ACDiv.style.padding = "0";
		this.ACDiv.style.zIndex = "10";
		this.ACDiv.style.position = "absolute";
		this.ACDiv.style.backgroundColor = "white";
		this.ACDiv.style.color = "black";
		this.ACDiv.style.display = "none";
		this.ACDiv.style.fontSize = "10px";
		mybody.appendChild(this.ACDiv);	
	}

	if (theXML.getElementsByTagName('item').length) {
		if (this.acArray["display"] && this.acArray["lookuponkey"] == this.acArray["curkey"]) {

			this.ACDiv.innerHTML = "";
			this.acArray["values"] = new Array();
			for (var i = 0; i < theXML.getElementsByTagName('item').length; i++) {
				newDiv = document.createElement("DIV");
				newDiv.style.padding = "3px";
				newDiv.innerHTML = theXML.getElementsByTagName('item')[i].firstChild.data;
				this.acArray["values"][i] = theXML.getElementsByTagName('item')[i].getAttribute('value');
				this.ACDiv.appendChild(newDiv);
			}
			this.redraw();
			for (var i=0; i<this.acArray["hidefields"].length; i++) {
				document.getElementById(this.acArray["hidefields"][i]).style.display = "none";
			}
			if (this.acArray["backspace"] == true) {
				this.acArray["index"] = -1;
				this.acArray["backspace"] = false;
			} else {
				this.acArray["index"] = 0;
				this.selectItem();
			}
		}
	} else {
		//alert("no results for "+theXML.getElementsByTagName('value')[0].firstChild.data);
		this.blur();
	}
}

// Handle highlighting and selection of items for visual cues
AutoCompleteField.prototype.selectItem = function () {
	currentSelLength = this.acArray["usercontent"].length;
	//obj("helpname").innerHTML += theField.value+" "+currentSelLength+" "+acArray[this.fieldID]["index"]+" ";

	// If a previous item in the selection list was selected, deselect it by removing highlight
	if (!this.ACDiv) return;
	if (this.ACDiv.getElementsByTagName('div').length) this.prev = this.ACDiv.getElementsByTagName('div')[this.acArray["lastindex"]];
	if (this.prev) {
		this.prev.style.backgroundColor = "white";
		this.prev.style.color = "black";
	}

	// Select the new one by highlighting it
	this.ACDiv.style.display = "block";
	this.acArray["display"] = true;
	selection = this.ACDiv.getElementsByTagName('div')[this.acArray["index"]];
	if (!selection) return;
	selection.style.backgroundColor = "#009966";
	selection.style.color = "white";

	// Get the full text value of the suggested phrase
	newText = this.acArray["values"][this.acArray["index"]];
	this.acArray["lastindex"] = this.acArray["index"];
	if (this.theField.createTextRange) {
		this.theField.value = newText;
		var theRange = this.theField.createTextRange(); 
		theRange.moveStart("character", currentSelLength); 
		theRange.moveEnd("character", newText.length); 
		theRange.select();
	} else if (this.theField.setSelectionRange) {
		this.theField.value = newText;
		this.theField.setSelectionRange(currentSelLength, newText.length);

	}
	//obj("helpname").innerHTML += theField.value+"<br />";

}

// KeyDown event occurs after the key is pressed but before it is
// printed to the text box.
AutoCompleteField.prototype.keyDown = function (theevent) {
	var e = (window.event) ? event : theevent;
	//obj("helpname").innerHTML += "keydown: "+e.type+", "+e.keyCode+"<br />";
	if (e && e.keyCode) {
		if (!this.ACDiv) {
			return true;
		}
	
		this.acArray["curkey"]++;

		// Up arrow
		if (e.keyCode == 38 || e.keyCode == 63232) {
			if (this.acArray["index"] > 0) this.acArray["index"]--;
			else this.acArray["index"] = 0;
			this.selectItem(); 
			this.acArray["lastkey"] = "up";
			return false;
		
		// Down arrow
		} else if (e.keyCode == 40 || e.keyCode == 63233) {
			if ((this.acArray["index"] + 1) < this.ACDiv.getElementsByTagName('div').length) this.acArray["index"]++;
			this.selectItem();
			this.acArray["lastkey"] = "down";
			return false;

		// Escape
		} else if (e.keyCode == 27) {
			this.acArray["lastkey"] = "esc";
			e.returnValue = false; 
			e.cancel = true;
			this.blur();
			return false;
		
		// Delete
		} else if (e.keyCode == 8) {
			this.acArray["lastkey"] = "delete";
		
		// Enter / Tab
		} else if (e.keyCode == 13 || e.keyCode == 3 || e.keyCode == 9) {
			this.acArray["lastkey"] = "enter";
			if (this.acArray["display"]) this.selectItem();
			e.returnValue = false; 
			e.cancel = true;
			this.theField.blur();
			var parentForm = false;
			var parent = this.theField.parentNode;
			while (parent && !parentForm) {
				if (parent.tagName == "FORM") parentForm = parent;
				else parent = parent.parentNode;
			}
			if (parentForm) {
				var formNum = -1;
				var elementNum = -1;
				for (var i = 0; i < document.forms.length; i++) {
					if (document.forms[i] == parent) {
						formNum = i;
						break;
					}
				}
				if (formNum > -1) {
					for (var i = 0; i < document.forms[formNum].elements.length; i++) {
						if (document.forms[formNum].elements[i] == this.theField) {
							elementNum = i;
							break;
						}
					}
				}
				if (elementNum > -1) {
					for (var i = elementNum + 1; i < document.forms[formNum].elements.length; i++) {
						if (e.shiftKey) {
							i = i - 2;
							if (i<0) break;
						}
						if (document.forms[formNum].elements[i] && (document.forms[formNum].elements[i].type != "hidden") && !document.forms[formNum].elements[i].disabled) {
							var timerID = setTimeout("document.forms["+formNum+"].elements["+i+"].focus()", 15);
							break;
						}
					}
				}
			}
			return false;
		} else {
			this.acArray["lastkey"] = e.keyCode;
		}
	}
}

// A further intercept to prevent safari/moz processing keys too far
AutoCompleteField.prototype.keyPress = function (theevent) {
	var e = (window.event) ? event : theevent;
	//obj("helpname").innerHTML += "keypress: "+e.type+", "+e.keyCode+"<br />";
	if (e && e.keyCode) {
		if (!this.ACDiv) {
			return true;
		}

		var ua = navigator.userAgent.toLowerCase();
		if (ua.indexOf('applewebkit') > -1) {
			var upKeyCode = 63232;
			var downKeyCode = 63233;
		} else {
			var upKeyCode = 38;
			var downKeyCode = 40;
		}
		
		// Up arrow
		if (e.keyCode == upKeyCode) {
			return false;
		
		// Down arrow
		} else if (e.keyCode == downKeyCode) {
			return false;

		// Enter / Tab
		} else if (e.keyCode == 13 || e.keyCode == 3 || e.keyCode == 9) {
			return false;
		}
	}
}
