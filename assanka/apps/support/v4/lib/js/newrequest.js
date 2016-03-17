var currentprojectselection = false;

// When the DOM is ready, add a watcher event to the project dropdown if the user is a developer
$(function() {
	if ($("#frmsupportnew_isdeveloper").val()) {
		currentprojectselection = $("#frmsupportnew_projectcode").val();
		$("#frmsupportnew_projectcode").change(function() { updateProjectSelection(); });
	}
});

// When the project selection dropdown is changed, update the author dropdown appropriately.
function updateProjectSelection() {
	if (currentprojectselection == $("#frmsupportnew_projectcode").val()) return;
	currentprojectselection = $("#frmsupportnew_projectcode").val();

	// If no project was selected, reduce the list of authors to just the developer.
	if (!currentprojectselection) {
		changeAuthorSelectionOptions({0:{name:$("#frmsupportnew_originalrequestauthor").val()}});
	} else {

		// Request the list of current potential authors for the selected project.
		$.getJSON("lib/ajx/getauthors", {projectcode:currentprojectselection}, function(data, status) {
			if (data.error) {
				alert(data.error);
				return;
			}
			changeAuthorSelectionOptions(data);
		});
	}
}

// Update the author selection menu to a new set of choices, maintaining selection if appropriate.
function changeAuthorSelectionOptions(newoptions) {
	var currentauthor = $("#frmsupportnew_requestauthor").val();

	// Build up a new list of select choices
	var newoptionshtml = '<option value=""></option>';
	for (var i in newoptions) {
		if (!newoptions[i].name) continue;
		var name = newoptions[i].name;
		newoptionshtml += '<option value="'+name.replace('"', '\"')+'">'+name+'</option>';
	}
	$("#frmsupportnew_requestauthor").html(newoptionshtml);
	
	$("#frmsupportnew_requestauthor").val(currentauthor);
	if (!$("#frmsupportnew_requestauthor").get(0).selectedIndex) {
		$("#frmsupportnew_requestauthor").val($("#frmsupportnew_originalrequestauthor").val());
	}
}