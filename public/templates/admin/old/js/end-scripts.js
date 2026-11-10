if (typeof app !== 'undefined' && typeof app.language !== 'undefined') {
	
	if (app.language.code == 'bg') {
	    var rege = /[a-z]/ig;
	} else {
	    var rege = /[а-я]/ig;
	}
	
	$('body').on('keyup keydown', 'input.countrySymbols, textarea.countrySymbols', function (e) {
	    $(this).val($(this).val().replace(rege, ""));
	    return true;
	});
}

// progressive app


/*if (location.protocol == 'https:' && typeof app !== 'undefined' && app.useWorker && "serviceWorker" in navigator) {
	navigator.serviceWorker.register(app.url.server + app.url.base + "/sw.js", { scope: app.url.server + app.url.base + "/" });
}


window.addEventListener('beforeinstallprompt', function (e) {
	e.preventDefault();
});

window.setTimeout(function () {
	$('[data-service-type="autocomplete"]').each(function () {
		$(this).attr('autocomplete', 'autocomplete_off_' + +(new Date));
	});
}, 73);
*/