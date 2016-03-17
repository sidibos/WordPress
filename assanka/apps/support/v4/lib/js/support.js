$(function() {
	if ($("#supportcontactinfo").length) {
		$("#chksendemail").click(function() {
			if (this.checked) {
				$("#txtuseremail").attr("disabled", false).get(0).focus();
				$("#selemailnotifylevel").attr("disabled", false);
			} else {
				$("#txtuseremail,#selemailnotifylevel").attr("disabled", true);
			}
		});
		$("#chksendsms").click(function() {
			if (this.checked) {
				$("#txtusermobile").attr("disabled", false).get(0).focus();
				$("#selsmsnotifylevel").attr("disabled", false);
			} else {
				$("#txtusermobile,#selsmsnotifylevel").attr("disabled", true);
			}
		});
		if (!$("#chksendemail").get(0).checked) $("#txtuseremail,#selemailnotifylevel").attr("disabled", true);
		if (!$("#chksendsms").get(0).checked) $("#txtusermobile,#selsmsnotifylevel").attr("disabled", true);
	}
	if ($("#blank").length) {
		$("#frmsupportnew_projectcode").change(function() {
			$("div.supportpkgdesc").css("display", "none");
			if (clientpackagearray[$(this).get(0).selectedIndex-1]) {
				$("#"+clientpackagearray[$(this).get(0).selectedIndex-1]).css("display", "block");
			} else {
				$("#blank").css("display", "block");
			}
		});
		$("#frmsupportnew_projectcode").change();
	}
	$("#frmsupportnew_title, #frmsupportedit_title").focus();
	
	// On the view page, if the occurrences dropdown exists, update it to redraw the graph as necessary
	if ($("#occurrenceslist").length) {
		$("#occurrenceslist").change(function() {
			$("#occurrencesgraph").attr("src", "requests/occurencesgraph?id="+supportrequestid+"&showhost="+$("#occurrenceslist").val());
		});
	}

	$("#frmsupportedit_classification, #frmsupportedit_resolution, #frmsupportedit_assignedto, #frmsupportedit_charge, #frmsupportedit_status").change(updateComment);
	if ($("#frmsupportedit_classification").length) updateComment();

});


function updateComment() {
	var curstatus = $("#frmsupportedit_status").val();
	if (curstatus == "new" || curstatus == "deferred") {
		$("#frmsupportedit_assignedto").attr("disabled", true).get(0).selectedIndex = 0;
		$("#frmsupportedit_resolution").attr("disabled", true).get(0).selectedIndex = 0;
	} else if (curstatus == "assigned") {
		$("#frmsupportedit_assignedto").attr("disabled", false);
		$("#frmsupportedit_resolution").attr("disabled", true).get(0).selectedIndex = 0;
	} else if (curstatus == "scheduled for release") {
		$("#frmsupportedit_assignedto").attr("disabled", false);
		$("#frmsupportedit_resolution").attr("disabled", false);
	} else {
		$("#frmsupportedit_assignedto").attr("disabled", true).get(0).selectedIndex = 0;
		$("#frmsupportedit_resolution").attr("disabled", false);
	}
	if (curstatus != $("#frmsupportedit_previousstatus").val()) {
		$("#autocommentstatus").css("display", "inline").html("Status changed to "+curstatus+".");
	} else {
		$("#autocommentstatus").css("display", "none");
	}

	var curassign = $("#frmsupportedit_assignedto").val();
	if (curassign != $("#frmsupportedit_previousassignedto").val()) {
		if (curassign == "") {
			$("#autocommentassignedto").css("display", "inline").html("Unassigned.");
		} else {
			$("#autocommentassignedto").css("display", "inline").html("Assigned to "+curassign+".");
		}
	} else {
		$("#autocommentassignedto").css("display", "none");
	}

	var curcharge = $("#frmsupportedit_charge").val();
	if (!curcharge) curcharge = 0;
	if ($("#frmsupportedit_previouscharge").val() != curcharge) {
		if ($("#frmsupportedit_previouscharge").val() == "0") $("#autocommentcharge").html("Applied a charge of &pound;"+curcharge+".");
		else $("#autocommentcharge").html("Adjusted the charge to &pound;"+curcharge+".");
		$("#autocommentcharge").css("display", "inline");
	} else {
		$("#autocommentcharge").css("display", "none");
	}

	var curinv = $("#frmsupportedit_invoicenum").val();
	var prvinv = $("#frmsupportedit_previousinvoicenum").val();
	if (curinv != prvinv) {
		if (!curinv && prvinv) $("#autocommentinvoicenum").html("Removed the invoice number.");
		else if (curinv && !prvinv) $("#autocommentinvoicenum").html("Invoice number "+curinv+".");
		else if (curinv != prvinv) $("#autocommentinvoicenum").html("Invoice number changed to "+curinv+".");
		$("#autocommentinvoicenum").css("display", "inline");
	} else {
		$("#autocommentinvoicenum").css("display", "none");
	}

	var curclass = $("#frmsupportedit_classification").val();
	if (curclass != $("#frmsupportedit_previousclassification").val()) {
		$("#autocommentclassification").html("Classified this request as category "+curclass+".").css("display", "inline");
	} else {
		$("#autocommentclassification").css("display", "none");
	}

	var curres = $("#frmsupportedit_resolution").val();
	if (curres != $("#frmsupportedit_previousresolution").val()) {
		if (curres) {
			$("#autocommentresolution").html("Entered resolution: "+curres+".").css("display", "inline");
		} else {
			$("#autocommentresolution").html("Removed the resolution for this request.").css("display", "inline");
		}
	} else {
		$("#autocommentresolution").css("display", "none");
	}
	$("#frmsupportedit_notificationopts_1").get(0).checked = ((curassign == thisUser) || (curassign == "")) ? false : true;
	$("input[name='notificationopts[]']").filter("value=['author']").get(0).checked = (reqAuthor == thisUser) ? false : true;
}
