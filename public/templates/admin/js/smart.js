var smart = 
{
	alert: function (text) 
	{ 
		window.alert(app.translate(text)); 
	},
	confirm: function (text, callback) 
	{
		if (window.confirm(app.translate(text))) {
			callback && callback(true);
			return true;
		}
		
		callback && callback(false);
		return false;
	}
};

if (typeof(swal) === 'function') 
{
	smart.alert = function (text, type) 
	{
		Swal.fire({
			  icon: type || 'warning',
			  text: app.translate(text)
		});
	};
	
	smart.confirm = function (text, callback) 
	{
		Swal.fire({
			  title: app.translate(text),
			  icon: "warning",
			  showCancelButton: true,
			  confirmButtonColor: "#3085d6",
			  cancelButtonColor: "#d33",
			  confirmButtonText: app.translate('Да'),
			  cancelButtonText: app.translate('Не')
			}).then((result) => {
				callback(true);
			});
	}
}

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

$(document).ajaxSuccess(function(e) {
	if (ajaxMessage !== undefined) {
		$.growlUI(ajaxMessage);
	}
});

$(document).ajaxStop(function(e) {
	ajaxMessage = undefined;
});