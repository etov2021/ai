function getAjaxLoaderImg()
{
	return '<img style="margin-left: 42%;" src="templates/admin/img/ajax-loader-global.gif" alt="" />';
}

function getAjaxLoader()
{
	return '<div style="padding: 10px 0;">' + getAjaxLoaderImg() + '</div>';
}

$('body').on('click', '.remove', function (e) {
	var that = $(this);
	smart.confirm("Сигурни ли сте, че искате да изтриете записа?", function (yes) {
		if (yes) window.location.href = that.attr('href');
	});
	return false;
});

$('body').on('click', '[data-location-href]', function () {
    window.location.href = $(this).attr('data-location-href');
    return false;
});

$('body').on('click', '[data-handler="removeImage"]', function () {
	var that = $(this);
	var canAjaxMessage = that.attr('data-ajax-message');
	var canConfirm = that.attr('data-confirm');
	var removeElement = that.attr('data-remove-element') || 'tr';
	var callback = function () {
		if (canAjaxMessage) {
			ajaxMessage = canAjaxMessage;
		}
		$.post(that.attr('data-remove-url'), function () {
    		that.closest(removeElement).remove();
    	});
	};
	if (!canConfirm) {
		callback();
	} else {
		smart.confirm(canConfirm, function (yes) {
			if (yes) callback();
		});
	}
	return false;
});


$('body').on('click','.not-implemented',function(e) {
	
	$.bigBox({
		title : "NOT IMPLEMENTED",
		content : "This function is not implemented yet",
		color : "#C46A69",
		//timeout: 6000,
		icon : "fa fa-warning shake animated",
		//number : "1",
		timeout : 6000
	});

	e.preventDefault();

})

function URL_add_parameter(url, param, value){
    var hash       = {};
    var parser     = document.createElement('a');

    parser.href    = url;

    var parameters = parser.search.split(/\?|&/);

    for(var i=0; i < parameters.length; i++) {
        if(!parameters[i])
            continue;

        var ary      = parameters[i].split('=');
        hash[ary[0]] = ary[1];
    }

    hash[param] = value;

    var list = [];  
    Object.keys(hash).forEach(function (key) {
        list.push(key + '=' + hash[key]);
    });

    parser.search = '?' + list.join('&');
    return parser.href;
}

function build_options(values, empty, emptyValue)
{
	var options = '';
	
	if (typeof empty !== 'undefined') {
		var emptyValue = emptyValue || "";
		options += '<option value="' + emptyValue +'">' + empty + '</option>';
	}
	
	for (var i = 0; i < values.length; ++i) {
    	var value = values[i];
		options += '<option value="' + value.key + '">' + value.value + '</option>';
	}
	
	return options;
}

var hasStorage = typeof(Storage) !== undefined;

function marshalTabs()
{
	var currentIndex = 0;
	var storageTab = {};
	var storage = window.sessionStorage;
	
	if (hasStorage) {
		var storageItemTab = storage.getItem('tab');
		if (storageItemTab) {
			try {
				storageTab = JSON.parse(storageItemTab);
				if (storageTab.url === app.url.route) {
					currentIndex = storageTab.index;
				} else {
					currentIndex = 0;
					storage.setItem('tab', JSON.stringify({}));
				}
			} catch (e) {}
		}
	}
	

	$('.tabs').tabs({
		active: currentIndex,
		activate: function (event, ui) {
			if (hasStorage && !$(this).hasClass('fresh-tabs')) {
				storageTab.url = app.url.route;
				storageTab.index = ui.newTab.index();
				storage.setItem('tab', JSON.stringify(storageTab));
			}
		}
	});
	
	$('.tabs').show();
}

$.fn.removeClassPrefix = function (prefix) {
    this.each( function ( i, it ) {
        var classes = it.className.split(" ").map(function (item) {
           return item.indexOf(prefix) === 0 ? "" : item;
        });
        it.className = classes.join(" ");
    });

    return this;
}

function marshalDatepicker() 
{
	if ($.fn.datepicker) {	
		$('.datepicker').datepicker({
			prevText: '<i class="fa fa-chevron-left"></i>',
		    nextText: '<i class="fa fa-chevron-right"></i>'
		});
	}
}

function hexToRGBA(hex, opacity)
{
    return 'rgba(' + (hex = hex.replace('#', '')).match(new RegExp('(.{' + hex.length/3 + '})', 'g')).map(function(l) { return parseInt(hex.length%2 ? l+l : l, 16) }).concat(isFinite(opacity) ? opacity : 1).join(',') + ')';
}

$(function () {

	$('[data-handler="slug"]').on('click', function () {
		var source = $(this).attr('data-source');
		var target = $(this).attr('data-target');
		if (source && target) {
			source = $('input[name="' + source + '"]');
			target = $('input[name="' + target + '"]');
			if (source.length && target.length) {
				target.parent().block({message: ''});
				$.post(app.url.routeSlug, {name: source.val()}, function (res) {
					target.val(res);
				}).always(function () {
					target.parent().unblock();
				});
			}
		}
		return false;
	});
	
	$('[data-handler="maxlength-count"]').each(function () {
		
		var maxlen = $(this).attr('maxlength');
		
		if (!maxlen) {
			return;
		}
		
		$(this).after('<div class="count text-right"><span>' + (maxlen - $(this).val().length) + '</span></div>');
		
		$(this).on('input propertychange', function () {
			var len = $(this).val().length;
			$(this).parent().find('.count span').html(maxlen - len);
		});
		
	});

	$('.sortable-table table tbody').sortable({
		handle: '.handle',
		axis: 'y',
		placeholder: "ui-state-highlight",
	    helper: function(e, ui) {
	        ui.children().each(function() {
	            $(this).width($(this).width());
	        });
	        return ui;
	    },
	    update: function (event, ui) {
	        var that = $(this);
	        var data = that.sortable('serialize');
	        var error = 0;
	        var container = that.closest('.sortable-table');
	        var dataAjaxMessage = container.attr('data-ajax-message');
	        if (dataAjaxMessage) {
	        	ajaxMessage = app.translate(dataAjaxMessage);
	        }
	        $.post(container.attr('data-url'), data, function () {}).error(function () {
				window.setTimeout(function () { window.location.reload(true); }, 1000);
	        });
	    }
	});
	
	var globalFileManagerDialog = $("#globalFileManagerDialog");

	if (globalFileManagerDialog.length) {
		globalFileManagerDialog.dialog({
			title: app.translate('File manager'),
		    autoOpen: false,
		    modal: true,
		    position: ['top', 20],
		    width: '80%',
		    height: $(document).height() - 40,
		    open: function () {
		    	$('.ui-dialog').css('position', 'fixed');
		    },
		    close: function () {
		    	$(this).html('');
		    }
		});
		var globalFileManager = $('#globalFileManager');
		if (globalFileManager.length) {
			globalFileManager.on('click', function () {
				var iframe = $('<iframe style="width: 100%; min-height: 100%;" src="' + $(this).attr('href') + '" frameborder="0"></iframe>');
				globalFileManagerDialog.html(iframe);
				globalFileManagerDialog.dialog('open');
				return false;
			});
		}
	}
	
	var globalSolrPreviewDialog = $("#globalSolrPreviewDialog");
	if (globalSolrPreviewDialog.length) {
		globalSolrPreviewDialog.dialog({
			title: app.translate('Solr search preview'),
		    autoOpen: false,
		    modal: true,
		    position: ['top', 20],
		    width: '80%',
		   // height: $(document).height() - 40,
		    minHeight: 'auto',
		    height: "auto",
		    show: "slideDown",
		    maxHeight: $(window).height() - 40,
		    open: function () {
		    	$('.ui-dialog').css('position', 'fixed');
		    },
		    close: function () {
		    	$(this).find('#solrSearchQuery').val('');
		    	$(this).find('#solrSearchItems').html('');
		    }
		});
		var globalSolrPreview = $('#globalSolrPreview');
		if (globalSolrPreview.length) {
			globalSolrPreview.on('click', function (e) {
				globalSolrPreviewDialog.dialog('open');
				return false;
			});
			
			$("#globalSolrPreviewDialog").on('click','#solrSearchBtn' , function(){
				
				var href = $(this).attr('data-url');
				var uri = encodeURI($('input#solrSearchQuery').val());;
				if(href && uri) {
					$("#solrSearchItems").html('');
					$.post(href, {uri:uri}, function (res)  {
						$("#solrSearchItems").html(res);				
					});
				}
				return false;
			})
		}
	}
	
	$('body').append('<div id="dialog"></div>');
	
	if (app.language['current'].code != app.language['default'].code) {
		
		$('[data-lang-helper]').each(function () {
			var label = $(this).closest('div[class^="col-"]').find('label:not(".nolang")');
			if (label.length) {
				label.prepend('<span class="lang-helper fa fa-external-link" style="cursor: pointer;" data-label="' + label.text().replace(/\*/g, "") + '" data-name="' + $(this).attr('data-lang-helper') + '"> </span> ');
			}
		});

		$(document).on('click', '.lang-helper', function (e) {
			
			var helper = $(this);
			
			var btnAdd = app.translate('Заместване');
			
			var buttons = {};
			
			/*buttons[btnAdd] = function() {
        		var el = $('[name="' + helper.attr('data-name') + '"]');
        		if (el.hasClass('summernote')) {
        			el.summernote("code", $(this).html());
        		} else {
        			el.val($(this).html());
        		}
                $(this).dialog("close");
            };*/
            
		    $("#dialog").dialog({
		        //modal: true,
		    	autoOpen: false,
		        width: 500,
		        position: ['top', 20],
		        title: helper.attr('data-label'),
		        close: function(event, ui) {
		        	$(this).html('');
		        },
		        buttons: buttons,
		        open: function() {
		        	var btn = $('.ui-dialog-buttonpane').find('button:contains("' + btnAdd + '")');
		        	btn.addClass('btn');
		        	btn.addClass('btn-primary');
		        }
		    });

			$.post((helper.attr('data-url') || app.url.routeLangHelper), {field: helper.attr('data-name')}, function (res) {
				$("#dialog").html(res);
				$('#dialog').closest('.ui-dialog').css('position', 'fixed');
				$('#dialog').dialog('open');
			});
		});
	}

	$('[data-lang-icon]').each(function () {
		var tmp = '<img src="templates/admin/images/blank.gif" class="flag flag-' + app.language['current'].locale + '" alt="' + app.language['current'].name + '"> ';
		if ($(this)[0].nodeName === 'LABEL') {
			$(this).prepend(tmp);
		} else {
			var label = $(this).closest('div[class^="col-"]').find('label:not(".nolang")');
			if (label.length) {
				label.prepend(tmp);
			}
		}
	});
	
	// tab
	marshalTabs();
	
	if ($.fn.select2) {
		$(".select2").select2({
			language: app.language.ui.code
		});
	}
	
	marshalDatepicker();
		
	var gridCheckAllLogic = {};

	function colorCheckedRow(inputs)
	{
		inputs.each(function () {
			if ($(this).is(':checked')) {
				$(this).closest('tr').addClass('active');
			} else {
				$(this).closest('tr').removeClass('active');
			}
		});
	}
	
	$('body').on('click', '.grid-form input[data-handler="grid.checkall"]', function () {
		var that = $(this);
		var rel  = that.attr('data-rel');
		var form = that.closest('form');
		var inputs = form.find('input[name="' + rel + '"]');
		var inputsChecked = form.find('input[name="' + rel + '"]:checked');
 		if (that.is(':checked')) {
 			inputs.prop('checked', true);
		} else {
			inputs.prop('checked', false);
		}
		if (gridCheckAllLogic[rel] === undefined) {
			gridCheckAllLogic[rel] = true;
			inputs.on('click', function () {
				if (inputsChecked.length === inputs.length) {
					that.prop('checked', true);
				} else {
					that.prop('checked', false);
				}
			});
		}
		colorCheckedRow(inputs);
	});
	
	$('body').on('click', '.grid-form table tbody tr td input.grid-column-checkbox', function () {
		var form = $(this).closest('form');
		colorCheckedRow(form.find('input[name="' + $(this).attr('name') + '"]'));
	});
	
	/*$('body').on('click', '.grid-column-checkbox', function () {
		if ($('.grid-column-checkbox').filter(':checked').length) {
			$('.grid-buttons input[type="submit"][data-action]').show();
		} else {
			$('.grid-buttons input[type="submit"][data-action]').hide();
		}
	});*/

	$('.grid-form .grid-buttons input[type="submit"]').on('click', function () {

		var that = $(this);
		var rel  = that.attr('data-rel');
		var form = that.closest('form');

		if (that.attr('data-action') === undefined) {
			return true;
		}

		if (form.length === 0) {
			smart.alert('Липсва форма');
			return false;
		}

		var inputsChecked = form.find('input[name="' + rel + '"]:checked');

		if (rel !== undefined && inputsChecked.length === 0) {
			smart.alert('Моля, изберете поне 1 запис');
		} else {

			function submitForm()
			{
				form.attr('action', that.attr('data-action'));
				form.submit();
			}

			if (that.attr('data-confirm') === undefined) {
				submitForm();
			} else {
				smart.confirm(that.attr('data-confirm'), function (yes) {
					if (yes) submitForm();
				});
			}
		}

		return false;
	});
	
	$('[data-action="minifyMenu"]').on('click', function () {
		if (typeof(Storage) !== undefined) {
			window.sessionStorage.setItem('minified', $('body').hasClass('minified') ? 0 : 1);
		}
	});
});