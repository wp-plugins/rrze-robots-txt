jQuery(document).ready(function($) {
    $('#blog-public').live('click', function() {
        $('.form-table tr:eq(1)').show();
	});

	$('#blog-norobots').live('click', function() {
        $('.form-table tr:eq(1)').hide();
	});
    
});