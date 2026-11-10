if ($.fn.summernote) {

	var ButtonAddWidget = function (context) {
	
		var ui = $.summernote.ui;
	
		// create button
		var button = ui.button({
			contents: '<i class="fa fa-child"/> Widget',
			click: function () {
				var currentWidget = null;
				if ($('#widgetModal').length === 0) {
					$('body').append('<div id="widgetModal" class="modal"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">test</div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Затваряне</button></div></div></div></div>');
					var modalBody = $('#widgetModal .modal-body');
					$('#widgetModal').on('click', '[data-widget]', function (e) {
						var p = $(this).closest('.widget-parameters');
						var v = [];
						var errors = [];
						p.find('input, select').each(function () {
							var iv = $.trim($(this).val());
							var isRequired = parseInt($(this).attr('data-required'));
							if (!iv) {
								if (isRequired) {
									errors.push($(this).attr('data-field'));
									return;
								} else {
									iv = $(this).attr('data-default-value');
								}
							}
							v.push(iv);
						});
						if (errors.length) {
							alert("Моля, попълнете всички задължителни полета: (" + errors.join(", ") + ")");
							return;
						}
						var widgetData = '{' + $(this).attr('data-widget') + ',' + v.join(',') + '}';
						context.invoke('editor.restoreRange');
						var span = document.createElement('span');
						span.setAttribute('data-widget', widgetData);
						span.setAttribute('class', 'widget');
						span.setAttribute('style', 'background-color: yellow; font-weight: bold');
						span.innerHTML = widgetData;
						context.invoke('editor.insertText', ' ');
						context.invoke('editor.insertNode', span);
						context.invoke('editor.insertText', ' ');
						$("#widgetModal").modal("hide");
					});
					$("#widgetModal").on('shown.bs.modal', function () {
						modalBody.html('Зареждане...');
						currentWidget = null;
			            $.post(app.url.base + '/admin/get-widgets', function (res) {
			            	modalBody.html(res);
			            });
					});
				}
				context.invoke('editor.saveRange');
				$("#widgetModal").modal();
			}
		});
	
		return button.render();   // return button as jquery object 
	};
	
	var toolbar = $.summernote.options.toolbar;
	toolbar.push(['addWidget', ['addWidget']]);
	
	$('.summernote').summernote({
		//placeholder: "въведете съдържание...",
		toolbar: toolbar,
		height : 320,
		focus : false,
		tabsize : 4,
		lang: 'bg-BG',
		buttons: {
		    addWidget: ButtonAddWidget,
		},
		callbacks: {
			onInit: function () {

			}
		}
	});
}