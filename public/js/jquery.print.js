(function($) {
    var opt;

    function uaMatch (ua) {
	    ua = ua.toLowerCase();
	    var match = /(chrome)[ /]([w.]+)/.exec(ua) || /(webkit)[ /]([w.]+)/.exec(ua) || /(opera)(?:.*version|)[ /]([w.]+)/.exec(ua) || /(msie) ([w.]+)/.exec( ua ) || ua.indexOf("compatible") < 0 && /(mozilla)(?:.*? rv:([w.]+)|)/.exec(ua) || [];
	    return {
	        browser: match[1] || "",
	        version: match[2] || "0"
	    };
	}
    
    matched = uaMatch(navigator.userAgent);

    $.fn.jqprint = function (options) {
        opt = $.extend({}, $.fn.jqprint.defaults, options);

        var $element = (this instanceof jQuery) ? this : $(this);

        if (opt.operaSupport && matched.browser == 'opera')
        {
            var tab = window.open("","jqPrint-preview");
            tab.document.open();

            var doc = tab.document;
        }
        else
        {
            var $iframe = $("<iframe style='width: 800px; height: 800px;' />");

            if (!opt.debug) { $iframe.css({ position: "absolute", width: "0px", height: "0px", left: "-600px", top: "-600px" }); }

            $iframe.appendTo("body");
            var doc = $iframe[0].contentWindow.document;
        }

        if (opt.importCSS)
        {
        	if ($('base').length) {
            	doc.write('<base href="' +  $('base').attr('href') + '">');
        	}
        	
            if ($("link[media=print]").length > 0)
            {
                $("link[media=print]").each( function() {
                    doc.write("<link type='text/css' rel='stylesheet' href='" + $(this).attr("href") + "' media='print' />");
                });
            }
            else
            {
                $("link").each( function() {
                    doc.write("<link type='text/css' rel='stylesheet' href='" + $(this).attr("href") + "' />");
                });
            }
        }

        if (opt.printContainer) { 
        	doc.write($element.outer()); 
    	} else { 
    		$element.each(function() { 
    			doc.write($(this).html()); 
			}); 
		}

        if ($.isFunction(opt.callBack)) {
        	opt.callBack($(doc));
        }
        
        doc.close();

        (opt.operaSupport && matched.browser == 'opera' ? tab : $iframe[0].contentWindow).focus();
        
        setTimeout( function() { (opt.operaSupport && matched.browser == 'opera' ? tab : $iframe[0].contentWindow).print(); if (tab) { tab.close(); } }, 1000);
    }

    $.fn.jqprint.defaults = {
		debug: true,
		importCSS: true,
		printContainer: true,
		operaSupport: true,
		callBack : null
	};

    // Thanks to 9__, found at http://users.livejournal.com/9__/380664.html
    jQuery.fn.outer = function() {
      return $($('<div></div>').html(this.clone())).html();
    }
})(jQuery);