Assanka.$(function() {
	if (!Assanka.$.cookie("assanka_alerts")) return;
	if (!Assanka.$('#alertscontainer').length) return;

	var alertslist = Assanka.$.parseJSON(decodeURIComponent(Assanka.$.cookie("assanka_alerts")), true);
	if (!alertslist) {
		Assanka.$.cookie('assanka_alerts', null, {path:'/', domain:location.hostname});
		return;
	}

	var numalerts = alertslist.length;
	if (!numalerts) return;

	// Build up the alerts HTML
	var alertsHTML = '';
	for (var i = 0; i < numalerts; i++) {
		alertsHTML += '<div class="alert alert'+alertslist[i]['type']+'">';
		alertsHTML += '<strong>'+alertslist[i]['msg']+'</strong><br />';
		if (typeof(alertslist[i]['desc']) == 'undefined' || alertslist[i]['desc'] == '') {
			alertsHTML += '&nbsp;';
		} else {
			alertsHTML += alertslist[i]['desc'];
		}
		alertsHTML += '</div>';
	}

	// Display the alerts
	Assanka.$('#alertscontainer').html(alertsHTML);

	// Clear the alerts from the cookie
	Assanka.$.cookie('assanka_alerts', null, {path:'/', domain:location.hostname});
});

