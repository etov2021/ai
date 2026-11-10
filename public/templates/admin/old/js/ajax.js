var ajaxMessage;

$(document).ajaxError(function(event, request, settings) {
	try {
		var repsonse = JSON.parse(request.responseText);
		smart.alert(repsonse.error.message, 'error');
		if ($.blockUI) {
			$.unblockUI();
		}
	} catch (e) {}
});

$(document).ajaxStart(function(e) {
	if (ajaxMessage !== undefined) {
		//$.growlUI('Изпращане на заявката ...', '', 10000);
	}
});

$(document).ajaxSuccess(function(e) {
	if (ajaxMessage !== undefined) {
		$.growlUI(ajaxMessage);
	}
});

$(document).ajaxStop(function(e) {
	ajaxMessage = undefined;
});