

function EventView() {
	var self = this;
	self.evfl = false;
	self.evpn = false;
	self.floaters = false;
	self.currentmsg = 0;
	self.currentpos = 0;
	self.heightspace = 0;
	self.closetimer = false;
	self.closestart = false;
	self.closedelay = 0;

	self.init = function() {
		self.evfl = document.getElementById("floatingevents");
		
		// IE doesn't support defining width by defining left and right
		// positions; so also set a percentage-based width.
		var w = (((parseInt(self.evfl.parentNode.offsetWidth) - 20) / parseInt(self.evfl.parentNode.offsetWidth))*100);
		if (!isNaN(w)) self.evfl.style.width = w + "%";
		
		self.evpn = document.getElementById("pinnedevents");
		self.floaters = self.evfl.getElementsByTagName("div");
		if (self.floaters.length) {
			self.currentpos = (-1 * self.floaters[0].offsetHeight);
			self.floaters[0].style.visibility = "visible";
			self.floaters[0].style.top = self.currentpos + "px";
			self.floaters[0].style.zIndex = self.floaters.length+50;
			setTimeout(self.slideout, 250);
		}
	}

	self.slideout = function() {
		if (self.currentpos >= self.heightspace) {
			self.heightspace += self.floaters[self.currentmsg].offsetHeight;
			self.currentmsg++;
			if (self.currentmsg==self.floaters.length) {
				var alltext = "";
				for (var i=0; i<self.floaters.length; i++) {
					if (self.floaters[i].textContent) alltext += self.floaters[i].textContent;
					else alltext += self.floaters[i].innerText;
				}
				var timenow = new Date();
				self.closestart = timenow.getTime();
				self.closedelay = (alltext.split(" ").length * 200)+1500;
				self.closetimer = setTimeout(self.closeall, self.closedelay);
				return false;
			}
			self.currentpos = ((-1 * self.floaters[self.currentmsg].offsetHeight) + self.heightspace);
			self.floaters[self.currentmsg].style.zIndex = (self.floaters.length - self.currentmsg)+50;
			self.floaters[self.currentmsg].style.visibility = "visible";
			self.floaters[self.currentmsg].style.top = self.currentpos + "px";
		}
		self.currentpos += Math.ceil((self.heightspace - self.currentpos) / 10);
		self.floaters[self.currentmsg].style.top = self.currentpos+"px";
		setTimeout(self.slideout, 20);
	}

	self.pin = function(msg) {
		var h = msg.offsetHeight;
		var m = msg;
		while (m = m.nextSibling)	{
			m.style.top = (m.offsetTop - h) + "px";
		}
		var a = msg.getElementsByTagName("a");
		while (a.length) msg.removeChild(a[0]);
		self.evpn.appendChild(msg);
	}

	self.close = function(msg) {
		self.evfl.removeChild(msg);
	}

	self.closeall = function() {
		var divs = self.evfl.getElementsByTagName("div");
		while (divs.length) self.evfl.removeChild(divs[0]);
	}

	self.pause = function () {
		clearTimeout(self.closetimer);
		var timenow = new Date();
		self.closedelay -= (timenow.getTime() - self.closestart);
	}

	self.resume = function () {
		var timenow = new Date();
		self.closestart = timenow.getTime();
		self.closetimer = setTimeout(self.closeall, self.closedelay);
	}
}