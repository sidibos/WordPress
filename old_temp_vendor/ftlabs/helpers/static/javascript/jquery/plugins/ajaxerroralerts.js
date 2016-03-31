// Assanka extension: add a standard error handler for XMLHTTPRequest problems.  This allows
// script errors to be caught and simple error messages when using JSON-type requests.
jQuery.ajaxSetup({error:function(reqobj, textStatus, error) {
	if (textStatus === "parsererror" && reqobj.responseText) {
		alert("An error occurred while parsing the response:\n\n"+reqobj.responseText);
	} else {
		alert("An error occurred while fetching additional information:\n\n"+textStatus+((reqobj.responseText)?"\n"+reqobj.responseText:"")+((error)?"\n"+error:""));
	}
}});