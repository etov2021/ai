var tinymceConfig = {
	plugins: [
        'advlist autolink lists link image charmap print preview hr anchor pagebreak',
        'searchreplace wordcount visualblocks visualchars code fullscreen',
        'insertdatetime media nonbreaking save table contextmenu directionality',
        'emoticons paste textcolor colorpicker textpattern responsivefilemanager'
	],
    toolbar1: 'insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media | forecolor backcolor emoticons',
    image_advtab: true,
	height: 300,
	relative_urls : true,
	language: 'bg_BG',
	document_base_url : app.url.server + app.url.base + '/',
	external_filemanager_path: "js/plugins/filemanager/",
    filemanager_title: app.translate('File manager'),
    external_plugins: { "filemanager" : app.url.base + "/js/plugins/filemanager/plugin.min.js"}
};

if ($.fn.tinymce) {
	$('.tinymce').tinymce(tinymceConfig);
}