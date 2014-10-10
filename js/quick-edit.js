jQuery(document).ready(function() {
    jQuery('.editinline').on('click', function() {
        var tag_id = jQuery(this).parents('tr').attr('id');
        var term_group = jQuery('.term_group', '#' + tag_id).text();
        jQuery(':input[name="term_group"]', '.inline-edit-row').val(term_group);
    });
});
