if($('.tinymce').length) {
	$('.tinymce').each(function(i, el) {
	    var el = $(this), id = 'quilleditor-' + i, val = el.val(), editor_height = 200;
	    var div = $('<div/>').attr('id', id).css('height', editor_height + 'px').html(val);
	    el.addClass('d-none');
	    el.parent().append(div);
	
	    var quill = new Quill('#' + id, {
	        modules: { toolbar: true },
	        theme: 'snow'
	    });
	    quill.on('text-change', function() {
	        el.html(quill.getContents());
	    });
	});
}