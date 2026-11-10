function chainedOneByOne(config)
{
	var defaultOpt = '';
	var passEmptyOption = false;
	
	if (config.defaultOpt) {
	    defaultOpt = '<option value="">' + config.defaultOpt + '</option>';
	}

	if (config.passEmptyOption) {
		passEmptyOption = config.passEmptyOption;
	}

	$.each(config.chained, function (i, o) {

	    var el = o.from;
	    var to = o.to;
	    var val = o.value;
	    var url = o.url;
	    var params = o.params;
    	var defaultOptTo = o.defaultOpt ? '<option value="">' + o.defaultOpt + '</option>' : defaultOpt;

	    if ($.isArray(val) && val.length) {
	    	val = $.map(val, function (i) {
	    		return i.toString();
	    	});
	    }
	    
	    if (!$(el).length) {
    		console.log('chaingedOneByOne: no from "' + (typeof(el) === 'object' ? 'object' : el) + '" found');
	    }
	    
	    if (!$(to).length) {
    		console.log('chaingedOneByOne: no to "' + (typeof(to) === 'object' ? 'object' : to) + '" found');
	    }
	    
	    function clearSelect () {
	    	
	    	if (!$(to).length) {
	    		return;
	    	}

	    	$(to)[0].options.length = 0;

	    	if (defaultOptTo && typeof $(to).attr('multiple') === 'undefined') {
	    		$(to).html(defaultOptTo);
	    	}

	    	if (config.allowDisable) {
	    		$(to).attr('disabled', true);
	    	}
	    	
	    	if (config.allowDisableColor) {
	    		$(to).css('background-color', config.allowDisableColor);
	    	}
	    }
	    
	    function onCallback(data)
	    {
	    	if (o.callback && typeof o.callback === 'function') {
	    		o.callback.call($(to), data);
    	    }
	    }
	   
	    clearSelect();
	    
	    $(el).on('change', function () {

	    	clearSelect();

	    	if (!$(to).length) {
	    		onCallback([]);
	    		return;
	    	}
	    	
	    	var pstr = '';
	    	
	    	if (passEmptyOption || $(this).val()) {
	    		var keyParam = (typeof el == 'object' || o.keyParam ? (o.keyParam || 'id') : el.substr(1));
	    		pstr = keyParam + '=' + $(this).val();
	    		if (params) {
	    			for (var i = 0; i < params.length; i++) {
	    				pstr += '&' + params[i].substr(1) + '=' + $(params[i]).val();
	    			}
	    		}
	    		
	    		if (typeof(url) === 'undefined') {
	    			console.log('chaingedOneByOne: no url (' + pstr + ') provided for to ' + (typeof(to) === 'object' ? 'object' : to));
	    			return;
	    		}
	    		
	        	$.getJSON(url + '?' + pstr, function (data) {
	        	    var key, value, sel, idx, options = '';
	        	    for (var i in data) {
	        		    sel = '';
	        		    key = i;
	        		    
	        		    if (typeof data[i] === 'object' && data[i]) {
	        		    	var keys = Object.keys(data[i]);
	        		    	key   = data[i][keys[0]];
	        		    	value = data[i][keys[1]];
	        		    } else {
	        		    	value = data[i];
	        		    }
	        		    if ($.isArray(val) && val.length) {
	        		    	idx = $.inArray(key.toString(), val);
	        		    	if (idx !== -1) {
	        		    		val.splice(idx, 1);
	        		    		sel = ' selected="selected"';
	        		    	}
	        		    } else if (val && key == val) {
	        		    	val = 0;
	        		        sel = ' selected="selected"';
	        		    }
	        	        options += '<option' + sel + ' value="' + key + '">' + value + '</option>';
	        	    }
	        	    $(to).append(options);
	        	    if (config.allowDisable) {
	    	    		$(to).attr('disabled', false);
	    	    	}
	        	    if (config.allowDisableColor) {
	    	    		$(to).css('background-color', '');
	    	    	}
	        	    onCallback(data);
        	    	$(to).trigger('change', [true]);
	        	    
	        	});
	    	} else {
	    		clearSelect();
	    		onCallback([]);
	    		$(to).trigger('change', [true]);
	    	}
	    });
	});

	$(config.chained[0].from).trigger('change', [true]);
}