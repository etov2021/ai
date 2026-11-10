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
		swal(app.translate(text), null, (type || 'warning'));
	};
	
	smart.confirm = function (text, callback) 
	{
		swal({
			title: app.translate(text),
			showCancelButton: true,
			confirmButtonText: app.translate('Да'),
			cancelButtonText: app.translate('Не')
		}, callback);
	}
}