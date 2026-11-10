$(function() {
	$('nav ul').icymenu({
        'closedSign': '<em class="fa fa-plus-square-o"></em>',
        'openedSign': '<em class="fa fa-minus-square-o"></em>'
    })
});

$.fn.extend({
    'icymenu': function(config) {
        var defaultOptions = {
            'accordion': true,
            'speed': 250,
            'closedSign': '[+]',
            'openedSign': '[-]'
        };
        var options = $.extend(defaultOptions, config);
        var item    = $(this);
        item.find('li').each(function() {
        	if (0 !== $(this).find('ul').size()) {
        		$(this).find('a:first').append('<b class="collapse-sign">' + options.closedSign + '</b>');
        		if ('#' == $(this).find('a:first').attr('href')) {
        			$(this).find('a:first').on('click', function() {
                        return false;
                    });
        		}
        	}
        });
        item.find('li.active').each(function() {
            $(this).parents('ul').show();
            $(this).parents('ul').parent('li').find('b:first').html(options.openedSign);
            $(this).parents('ul').parent('li').addClass('open');
        });
        item.find('li a').on('click', function() {
        	if (0 !== $(this).parent().find('ul').size()) {
        		if (options.accordion) {
        			if (!$(this).parent().find('ul').is(':visible')) {
        				var parents = $(this).parent().parents('ul');
    					var visible = item.find('ul:visible');
    					visible.each(function(visibleEl) {
	                        var flag = true;
	                        parents.each(function(parentEl) {
	                        	if (parents[parentEl] == visible[visibleEl]) {
	                        		flag = false;
	                        	}
	                        });
	                        if (flag && $(this).parent().find('ul') != visible[visibleEl]) {
	                        	$(this).slideUp(options.speed, function() {
	                        		$(this).parent('li').removeClass('open');
	                        		$(this).parent('li').find('b:first').html(options.closedSign);
		                        });
	                        }
	        			});
        			}
        		}
        		if ($(this).parent().find('ul:first').is(':visible') && !$(this).parent().find('ul:first').hasClass('active')) {
        			$(this).parent().find('ul:first').slideUp(options.speed, function() {
                        $(this).parent('li').removeClass('open');
                        $(this).parent('li').find('b:first').html(options.closedSign);
                    });
        		} else {
        			$(this).parent().find('ul:first').slideDown(options.speed, function() {
                        $(this).parent('li').addClass('open');
                        $(this).parent('li').find('b:first').html(options.openedSign);
                    });
        		}
        	}
        })
    }
});