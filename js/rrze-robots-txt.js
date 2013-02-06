jQuery(document).ready(function($) {
    if ($('input[name=blog_public]').attr('checked')) {
      $('.form-table tr:eq(5)').hide();
    }
    else {
      $('.form-table tr:eq(5)').show();
    }
    
    $('input[name=blog_public]').click(function() {
        if ($(this).is(':checked')) {
          $('.form-table tr:eq(5)').hide();
        }
        else {
          $('.form-table tr:eq(5)').show();
        }
    });
});