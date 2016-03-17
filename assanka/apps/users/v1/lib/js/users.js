var path = '/corestatic/imgs/treeview/';
var settings = {};

$(function() {

	$("a.secrights").each(function() {
		var anchor = $(this);
		var path = anchor.attr("href");
		$.getJSON(path+"/lib/ajx/rights", function(response) {
			if (typeof response.rights != 'undefined') {
				anchor.after("<div class='secrightstree'>"+buildtree(response.rights)+"</div>");
				var tree = anchor.next().children("ul");
				if (tree.length) processLevel(tree.get(0), 0, 'rights');
			}
			if (typeof response.settings != 'undefined') {
				for(var i in response.settings) {
					settings[i] = response.settings[i];
				}
			}
		});
	});

	if ($("div.userdetails > ul").length) processLevel($("div.userdetails > ul").get(0), 0, 'details');

	if ($("#frmedituser_password").length) {
		$(document.body).append('<a id="lnkpasssuggest" style="background: url(lib/img/icon_suggest.png) no-repeat; width: 15px; height: 14px; display: block; position: absolute;" title="Insert a suggested password" href="javascript:void(0)" onclick="suggestPassword()"/>');
		var os = $("#frmedituser_password").offset();
		var w = $("#frmedituser_password").width();
		$("#lnkpasssuggest").css("top", (os.top+2)+"px").css("left", (os.left+w+10)+"px");
	}
	
	setUpEventHandlers();
});

function buildtree(data, prefix) {
	if (typeof prefix == "undefined") var prefix = "";
	if (data.notice) return "<em>"+data.notice+"</em>";
	var op = "<ul>";
	for (var i in data) {
		op += "<li><span><input type='checkbox' name='"+prefix+i+"' class='secright' id='chk"+prefix+i+"' onclick='selectRight(this)'";
		if (jQuery.inArray(prefix+i, rights) != -1) op += " checked='checked'";
		op += "><label for='chk"+prefix+i+"'";
		if (typeof data[i] == "string") {
			op += ">"+data[i]+"</label></span></li>";
		} else {
			op += " title='Tick to select all rights within this section'>"+data[i].name+"</label></span>";
			op += buildtree(data[i].components, prefix+i+".");
			op += "</li>";
		}
	}
	op += "</ul>";
	return op;
}

function setUpEventHandlers() {
	$("div.userdetails ul ul span.val").mouseover(function() { $(this).css("background-color", "#FFFFCC") });
	$("div.userdetails ul ul span.val").mouseout(function() { $(this).css("background-color", "") });
	$("div.userdetails ul ul span.val").click(function() {
		if ($(this).is(".val")) {
			var curval = $(this).html();
			$(this).removeClass("val").addClass("valedit").html("<form onsubmit='saveValue(this); return false'><input type='text' value='"+curval+"' /></form>");
			$("input", this).focus().get(0).select();
			$("input", this).blur(function() {
				saveValue($(this).parent().get(0));
			});
		}
	});
}

function saveValue(el) {
	var newval = $("input", el).attr("disabled", true).val();
	var field = $(el).parent().prev().attr("title");
	$.post("lib/ajx/savedets", {username:username,field:field,val:newval}, function(response) {
		if (response=="Unset") {
			$(el).parent().parent().remove();
		} else {
			$(el).parent().addClass("val").removeClass("valedit").html(response);
		}
		setUpEventHandlers();
	});
}

function selectRight(el) {
	el.blur();
	if (!el.checked) {
		$(el).parent().parent().find("input").attr("checked", "");
		$(el).parents().each(function() {
			if (this.tagName=='LI') $(this).children("span").children("input").attr("checked","");
		});
		if ($(el).parent().parent().children("ul").length) {
			$(el).parent().parent().find("ul").css("display", "block");
		}
	} else {
		$(el).parent().parent().find("input").attr("checked", "checked");
		if ($(el).parent().parent().children("ul").length) {
			$(el).parent().parent().find("ul").css("display", "none");
		}
	}
	var rightslist = [];
	$("input.secright").each(function() {
		if (this.checked) rightslist[rightslist.length] = this.name;
	});
	var post = {username:username,rights:rightslist.join(",")};
	$.post("lib/ajx/saverights", post);
}

function showNewItem() {
	keycount = 0;
	var selector = "<select name='key' class='newkey'>";
	for (var i in settings) {
		selector += "<option value='"+i+"'>"+settings[i]+"</option>";
		keycount++;
	}
	selector += "</select>";
	if (!keycount) selector = "<input type='text' name='key' class='newkey' />";
	$("#baseitem > ul").append("<li><span class='wrapper'><form class='newitemform' onsubmit='newValue(this); return false'>"+selector+": <input type='text' name='val' class='newval' /> <input type='button' value='Add' onclick='newValue(this.parentNode)' /></form></span></li>");
	$("form.newitemform .newkey").focus()
	$("form.newitemform .newval").keypress(function(e) { if (e.which==13) newValue(this.parentNode); });

	processLevel($("div.userdetails ul").get(0),0,"details");
}

function newValue(el) {
	var key = $(".newkey", el).val().toLowerCase();
	var val = $(".newval", el).val();
	if (key.length==0 || val.length==0) {
		$(el).parent().parent().remove();
		return false;
	}
	$("input", el).attr("disabled", true);
	$.post("lib/ajx/savedets", {username:username,field:key,val:val}, function(response) {
		$(el).parent().parent().html("<span class='wrapper'><span class='key' title='"+key+"'>"+key+"</span>: <span class='val'>"+response+"</span></span>");
		if (key=="displayname") $("#displayname").html(response);
		setUpEventHandlers();
	});
}

function processLevel(ele,level,type) {
	var li = ele.firstChild;
	if (!li) return;
	if (type=='details' && level > 1) ele.style.display = "none";
	do {
		if (li.tagName == "LI") {
			if (type=='rights') {
				if ($(li).children("span").children("input").length) {
					if ($(li).children("span").children("input").get(0).checked) $("ul", li).css("display", "none");
				}
			}
			var children = li.getElementsByTagName("UL");
			if (level > 0) {
				if (children.length > 0) {
					if (type=='details') {
						var f = folderClick(li);
						li.firstChild.onclick = f;
						setNodeStyle(li, "plus", "details");
					} else {
						setNodeStyle(li, "plus", "rights");
					}
				} else {
					setNodeStyle(li, "join", type);
				}
			}
			if (li.className=="open") {
				var parnode = li;
				var plevel = level;
				while (parnode=parnode.parentNode) {
					if (parnode.tagName=="UL") {
						parnode.style.display = "block";
						plevel--;
						if (plevel > 0) setNodeStyle(parnode.parentNode, "minus", type);
					}
				}
			}
			if (children.length > 0) processLevel(children[0], (level+1), type);
		}
	} while (li=li.nextSibling);
}

function folderClick(el) {
	return (function() {
		var children = el.getElementsByTagName("UL");
		if (children[0].style.display=="block") {
			setNodeStyle(el, "plus", "details");
			//$(children[0]).slideUp(500);
			$(children[0]).css("display", "none");
		} else {
			setNodeStyle(el, "minus", "details");
			//$(children[0]).slideDown(500);
			$(children[0]).css("display", "block");
		}
	});
}

function islast(el) {
	var islast = 1; var nextel = el;
	for (var i=0; i<=5; i++) {
		if (!(nextel=nextel.nextSibling)) break;
		if (nextel.tagName=="LI") {
			islast = 0;
			break;
		}
	}
	return islast;
}

function setNodeStyle(el, icon, type) {
	el.className=icon;
	el.style.backgroundColor = (islast(el)) ? "#F0F0F0" : "transparent";
	if (type=="rights") icon = "join";
	el.style.backgroundImage = "url("+path+icon+((islast(el))?"bottom":"")+".gif)";
}

function suggestPassword() {
	$("#lnkpasssuggest").blur();
	$.get("lib/ajx/generatepassword", function(response) {
		$("#frmedituser_password").val(response);
	});
}