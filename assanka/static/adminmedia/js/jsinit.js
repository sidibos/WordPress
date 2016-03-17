var adminPanelCopies = {};
var activetabledata = {};
var activetables = {};
var rightpanelfloating = false;
var acp = {};

$(function() {
	
	// Stripe the data tables
	$("table.data tbody tr:odd").addClass("stripeodd");
	$("table.data tbody tr:even").addClass("stripeeven");

	// Enable "select all" checkboxes on data tables
	$('table.data input.select-all').change(function() {
		var index = 0;
		var el = this;
		while ($(el).prev('th').length) {
			++index;
			el = $(el).prev('th')[0];
		}
		var checked = this.checked;
		$('tbody tr', $(this).parents('table.data')).each(function() {
			$('td:eq(' + index + ') :checkbox', this).attr('checked', checked);
		});
	});

	// Add close buttons to event messages
	$("#pinnedevents .evtclose, #floatingevents .evtclose").click(function() { $(this.parentNode).remove(); });

	// Show hidden rows if they contain a validation error
	$('tr.field-hidden:has(label.invalid,a.label.invalid)').removeClass('field-hidden');

	// Enforce maxlength attribute in textareas
	var objs = document.getElementsByTagName("textarea");
	for (var textareanum = 0; textareanum < objs.length; textareanum++) {
		if (objs[textareanum].getAttribute("maxlength")) {
			objs[textareanum].onkeyup = function() {
				var maxlength = parseInt(this.getAttribute('maxlength'));
				if (this.value.length > maxlength) this.value = this.value.substring(0, maxlength);
			};
		}
	}

	// Add collapse/expand buttons and event controller for them
	if ($('#top').length) {
		$("<img src=\"/corestatic/adminmedia/img/colcollapse.gif\" id=\"admincollapsebutton\"/>").prependTo(document.body).click(function() {
			if (adminPanelCopies.left) {
				$("#admincollapsebutton").attr("src", "/corestatic/adminmedia/img/colcollapse.gif");
				$("#centrepanel").before(adminPanelCopies.left);
				if (adminPanelCopies.right) $("#centrepanel").after(adminPanelCopies.right);
				adminPanelCopies = {};
			} else {
				$("#admincollapsebutton").attr("src", "/corestatic/adminmedia/img/colexpand.gif");
				adminPanelCopies.left = $("#leftpanel").remove().get(0);
				adminPanelCopies.right = $("#rightpanel").remove().get(0);
			}
		});
	}

	// Context-sensitive help on form fields
	if ($("#helpheader").length) {
		$("form :input:not([type=submit])").focus(function() {
			var name = $(this).attr("name");
			if (name && name.substr(name.length-2,2)=="[]") name=name.substr(0,name.length-2);
			if (name) {
				$("#helpheader").css("margin-top", "0");
				var offsetField = $(this).offset().top;
				var offsetHelphead = $("#helpheader").offset().top;
				var offset = ((offsetField-offsetHelphead) > 0) ? (offsetField-offsetHelphead) : 0;
				$("div.help").css("display", "none");
				var helpdiv = $("#help"+name);
				if (!helpdiv.length) helpdiv = $("#nohelpavail");
				helpdiv.css("display", "block");
				$("#helpheader").css("margin-top", offset+"px");
			}
		});
	}

	// Iconlists
	if ($("ul.iconlist").length) {
		drawIconLists();
		$(window).resize(drawIconLists);
	}

	// Active tables
	$("table.activetableform").each(function() {
		activetables[$(this).attr("name")] = new ActiveTable(this);
		ActiveTable_refreshTable($(this).attr("name"));
	});

	// File upload fields
	$("input.fupload").each(function() {
		$(this).after("<iframe src='/corestatic/uploader/upload.php?uploaddest="+((typeof $(this).attr("uploaddest") != "undefined") ? urlenc($(this).attr("uploaddest")) : "")+"&showfile="+((typeof $(this).val() != "undefined") ? urlenc($(this).val()) : "")+"&uplid="+urlenc($(this).attr("id"))+"&imagecapture="+urlenc($(this).attr("imagecapture"))+"' frameborder='0' style='width: 400px; height:"+(parseInt($(this).attr("imagecapture"))?'40':'25')+"px; margin:0; padding:0'></iframe>");
	});
	
	// Sidebar anchoring
	if ($("#rightpanelfloattogglecontainer").length) {
		var cookieparts = document.cookie.split(';');
		for (var i=0; i < cookieparts.length; i++) {
			var cookiechunk = cookieparts[i];
			while (cookiechunk.charAt(0) == ' ') cookiechunk = cookiechunk.substring(1, cookiechunk.length);
			if (cookiechunk.indexOf("adminrightpanelfloat") == 0) {
				if (cookiechunk.substring(21,22) == "1") {
					rightpanelfloating = true;
					$("#rightpanelfloattogglecontainer").addClass("floatingon");
				}
			}
		}
		var offsetToWindow = $("#rightpanelfloattogglecontainer").offset().top;

		$(window).scroll(function() {
			if (!rightpanelfloating) return;
			var scrollOffset = document.body.scrollTop - offsetToWindow;
			var maxScrollOffset = $("#rightpanel").get(0).offsetHeight - $("#rightpanelfloater").get(0).offsetHeight;
			if (scrollOffset > 0) {
				$("#rightpanelfloatpositioner").stop().animate({"paddingTop":Math.min(scrollOffset, maxScrollOffset)}, {duration:100, "easing":"linear", "queue":false});
			} else {
				$("#rightpanelfloatpositioner").stop().animate({"paddingTop":0}, {duration:100, "easing":"linear", "queue":false});
			}
		});
	}

	// Turn a label into an input on focus
	var applyLabelClick = function(el) {

		var replaceWithInputField = function() {
			$(this).hide();
			var label = this;
			var saveLabel = function(e) {
				if (typeof e.which != 'undefined' && e.which != 0 && e.which != 10 && e.which != 13) {
					return;
				}
				$(label).html($(this).val()).show();
				$(this).hide().css('width', $(label).width() + 'px');
			}
			$('input', $(this).parents('td').get(0)).show().blur(saveLabel).keypress(saveLabel).select();
			return false;
		}

		el.unbind().click(replaceWithInputField).focus(replaceWithInputField);
	};
	
	// Make the labels of described fields editable
	$('input.field-description').each(function() {
		var label = $('label', $(this).parents('td').get(0));
		var text = label.text().replace('<', '&lt;').replace('>', '&gt;');
		var newLabel = $('<a href="javascript:void(0)" class="label" title="Edit this label">'+text+'</a>');
		if (label.hasClass('invalid')) {
			newLabel.addClass('invalid');
			newLabel.attr('title', label.attr('title'));
		}
		newLabel.after(":");
		label.before(newLabel).remove();
		$(this).css('width', $(newLabel).width() + 'px');
		$(this).parents(".formlabel").removeAttr("nowrap");
		applyLabelClick(newLabel);
	});

	// Support adding multiple fields
	$('tr.field-multiple').each(function() {
		
		// Add an 'append new item' button to the row
		var tr = this;
		var el = $('td:last', this);
		el.append($('<a class="link-add" href="javascript:void(0)"><span>+</span></a>').click(function() {
			addCopyOfTableRowToForm(tr);
		})).append($('<a class="link-remove" href="javascript:void(0)"><span>X</span></a>').click(function() {

			// When the button is clicked, confirm that the user wishes to
			// remove the current row, and then remove it from the form
			var tr = $(this).parents('tr').get(0);
			var label = $('label,a.label', tr).text();
			var inp = $('input,select,textarea', $(this).parents('td').get(0));
			if (!inp.val() || confirm('Do you really want to remove this ' + label + '?')) {
				var name = inp.attr('name');

				// If the global rebuildFieldShow function is defined (which deals with hiding and and showing
				// of optional fields), and there remains only one of the current field (so that after it
				// is removed, there will be zero of it) the function can be called at this point to 
				// remove the field, and show the 'show hidden field' button if necessary
				if (typeof rebuildFieldShow != 'undefined' && $('[name="' + name + '"]').length == 1) {
					inp.val('');
					$(tr).addClass('field-hidden');
					rebuildFieldShow();
				
				// Otherwise, just remove the field from the DOM
				} else {
					$(tr).remove();
				}
			}
		}));
	});

	// prepare expandable rightnav
	if ($('#rightpanel.expandable').length) {
		var shown = [];
		$('#rightpanelfloater > h3').each(function() {
			if ($(this).hasClass('show')) {
				shown.push(this);
			}
			$(this).click(toggleRightNav);
			$(this).css('cursor', 'pointer');
		});
		toggleRightNav(null, null, shown);
	}
});

function addCopyOfTableRowToForm(tr) {
	var newTr = $(tr).clone(true);
	$(tr).after(newTr);
	$($('td:last input, td:last textarea', newTr).val('').get(0)).focus();
}

/**
 * Expands/collapses the content of a rightnav section.
 * Set rightnav_expandable to 1 to enable, add the CSS class
 * "show" to any sections' H3 to show by default, and the
 * "exclusive" class to sections which should close all others
 * when expanded.
 *
 * To manually trigger a toggle after setup, call:
 * $(elements).each(toggleRightNav);
 *
 * @param	int		k
 *   The element index passed by jQuery, ignored.
 * @param	int		v
 *   The element passed by jQuery, ignored.
 * @param	array	targets
 *   An array of sections' H3 elements to show by default.
 */
function toggleRightNav(k, v, targets) {
	if (targets) {

		// setup: targets is an array of elements to initially show
		var isHidden = true;
		var isExclusive = $(targets[0]).hasClass('exclusive');
		var hide = 'hide', show = 'show';
	} else {

		// click: this is the clicked h3
		targets = [this];
		var isHidden = $(this).next(':not(h3):visible').length == 0;
		var isExclusive = $(this).hasClass('exclusive');
		var hide = 'slideUp', show = 'slideDown';
	}
	var act;
	$('#rightpanelfloater').children().each(function() {
		if ($(this).attr('tagName') == 'H3') {
			act = false;
			if (!in_array(this, targets)) {
				if (isExclusive || isHidden && $(this).hasClass('exclusive')) {
					act = isHidden ? hide : show;
				}
			} else {
				act = isHidden ? show : hide;
			}
			if (act) {
				$(this).css('backgroundImage', 'url(/corestatic/adminmedia/img/icons/h3sectionmarker' +
					(act == show ? '' : 'collapsed') + '.png)');
			}
		} else {
			if (act) {
				$(this)[act]();
			}
		}
	});
}

function descriptionEdit(el) {
	var span = $(this).parents('span.field-description');
	$('span.field-description-display', span).hide();
	$('input', span).show();
}

function drawIconLists() {
	$("ul.iconlist").each(function() {
		$(this).css("height", "").css("display", "block");
		var itemwidth = 100;
		$("a", this).each(function() {
			if (($(this).width()+30) > itemwidth) itemwidth = $(this).width() + 30;
		});
		var item1top = $("li:eq(0)", this).offset().top;
		var contwidth = $(this).parent().width();
		var numcols = Math.floor(contwidth/itemwidth);
		var numitems = $("li", this).length;
		var percol = Math.ceil(numitems/numcols);
		var curitem = 0;
		var lastcol = 0;
		var botoffset = 0;
		$("li", this).css("margin-top", "").css("margin-left", "").css("width", itemwidth+"px");
		$("li", this).each(function() {
			curitem++;
			var thiscol = Math.ceil(curitem/percol)-1;
			$(this).css("margin-left", (itemwidth*thiscol)+"px");
			if (thiscol!=lastcol) {
				var os = $(this).offset().top;
				if (os > botoffset) botoffset = os;
				$(this).css("margin-top", "-"+(os-item1top)+"px");
				lastcol = thiscol;
			}
		});
		$(this).height((botoffset-item1top)+"px");
	});
}

function toggleRightPanelFloating() {
	$("#rightpanelfloattogglecontainer").blur();
	rightpanelfloating = !rightpanelfloating;
	if (!rightpanelfloating) {
		$("#rightpanelfloatpositioner").stop().animate({"paddingTop":0}, {duration:100, "easing":"linear", "queue":false});
	}
	$("#rightpanelfloattogglecontainer").toggleClass("floatingon");		
	var date = new Date();
	date.setTime(date.getTime() + (31*24*3600*1000));
	document.cookie = "adminrightpanelfloat="+(rightpanelfloating?"1":"0")+"; expires="+date.toGMTString()+"; path=/";
}

ActiveTable = function(el) {
	var thegroupname = this.grpname = $(el).attr("name");
	this.tblid = $(el).attr("tableid");
	this.frmid = $(el).attr("formid");
	this.ajaxurl = $(el).attr("ajaxurl");
	$("#"+this.tblid).attr("atgrpname", this.grpname);
	if (!this.ajaxurl) this.ajaxurl = location.href;
	$("#acttbl_"+this.grpname+"_btn").click(this.add.bind(this));
	$(":input", el).keydown(function(e) {
		if (e.keyCode == 13 && !$(this).get(0).autocompleter) {
			$("#acttbl_"+thegroupname+"_btn").click();
			e.cancelBubble = true;
			e.returnValue = false;
			return false;
		}
	}).attr('autocompletesubmitbuttonid', "#acttbl_"+this.grpname+"_btn");
}
ActiveTable.prototype.add = function () {
	var btn = $("#acttbl_"+this.grpname+"_btn").get(0);
	btn.disabled = true;
	var data = {ajxgrp:this.grpname};
	$(".acttbl_"+this.grpname+"_field").each(function() {
		data[this.name] = this.value;
	});
	var acttbl = this;
	$.post(this.ajaxurl, data, function(r) {
		if (r.result=='OK') {
			try { var rowhtml = decodeURIComponent(r.row) } catch(e) { throw("Malformed response - did you forget to URLEncode it?") };
			if ($("#"+acttbl.tblid+"_"+r.key).length) {
				var prevrow = $("#"+acttbl.tblid+"_"+r.key).prev();
				$("#"+acttbl.tblid+"_"+r.key).remove();
				if (prevrow.length) {
					prevrow.after(rowhtml);
				} else {
					$("#"+acttbl.tblid+" tbody").prepend(rowhtml);
				}
			} else {
				$("#"+acttbl.tblid).append(rowhtml);
			}
			$(".acttbl_"+acttbl.grpname+"_field").each(function() {
				$(this).val("").change();
			});
			activetabledata[acttbl.grpname][r.key] = r.data;

			// Remove any "there is nothing to display here row"
			if ($("#"+acttbl.tblid+"_nothingrow")!='undefined') $("#"+acttbl.tblid+"_nothingrow").remove();
			ActiveTable_refreshTable(acttbl.grpname);

		} else {
			if (typeof r.msg == 'undefined') r.msg = 'There was a problem submitting your data, and no further information is available.  Please contact your adminstrator if this problem persists';
			alert(r.msg);
		}
		btn.disabled = false;
	}, 'json');
}
ActiveTable_refreshTable = function (grpname) {
	var newserialdata = [];
	var acttbl = activetables[grpname];
	$("#"+acttbl.tblid+" tbody tr").removeClass("stripeodd").removeClass("stripeeven");
	$("#"+acttbl.tblid+" tbody tr:odd").addClass("stripeodd");
	$("#"+acttbl.tblid+" tbody tr:even").addClass("stripeeven");
	$("#"+acttbl.tblid+" tbody tr").each(function() {
		var cell = $("td:last", this);
		if (!$(".deletebtn", cell).length) {
			cell.prepend('<img src="/corestatic/adminmedia/img/icons/delete.gif" width="16" height="16" class="deletebtn" />');
		}
		var rowtypekey = typeof($(this).attr("rowkey"));
		if ($(this).attr("deleted")!=1 && rowtypekey != "undefined") {
			newserialdata[newserialdata.length] = $(this).attr("rowkey").replace(',', '%2C')+","+activetabledata[acttbl.grpname][$(this).attr("rowkey")];
		}
	});
	$("#frm"+acttbl.frmid+"_"+acttbl.grpname).val(newserialdata.join("\n"));
	$("#"+acttbl.tblid+" img.deletebtn").click(ActiveTable_deleteHandler);
}
ActiveTable_deleteHandler = function (el) {
	var rowtodel = $(this).parent().parent();
	var acttbl = activetables[rowtodel.parent().parent().attr("atgrpname")];
	if (rowtodel.attr("deleted")==1) {
		rowtodel.css("text-decoration", "none").attr("deleted", 0);
		$(this).attr("src", "/corestatic/adminmedia/img/icons/delete.gif");
	} else {
		rowtodel.css("text-decoration", "line-through").attr("deleted", 1);
		$(this).attr("src", "/corestatic/adminmedia/img/icons/undelete.gif");
	}
	ActiveTable_refreshTable(acttbl.grpname);
}

Function.prototype.bind = function(obj) {
	var method = this, temp = function() {
		return method.apply(obj, arguments);
	};
	return temp;
} 

function urlenc(str) {
	return encodeURIComponent(str).replace(/\'/g, "%27");
}

function in_array (needle, haystack, argStrict) {
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: vlado houba
    // +   input by: Billy
    // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: in_array('van', ['Kevin', 'van', 'Zonneveld']);
    // *     returns 1: true
    // *     example 2: in_array('vlado', {0: 'Kevin', vlado: 'van', 1: 'Zonneveld'});
    // *     returns 2: false
    // *     example 3: in_array(1, ['1', '2', '3']);
    // *     returns 3: true
    // *     example 3: in_array(1, ['1', '2', '3'], false);
    // *     returns 3: true
    // *     example 4: in_array(1, ['1', '2', '3'], true);
    // *     returns 4: false

    var key = '', strict = !!argStrict;

    if (strict) {
        for (key in haystack) {
            if (haystack[key] === needle) {
                return true;
            }
        }
    } else {
        for (key in haystack) {
            if (haystack[key] == needle) {
                return true;
            }
        }
    }

    return false;
}
