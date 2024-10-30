jQuery( document ).ready( function ($) {
    /**
     * Add event to search for items of supported post types via AJAX.
     */
    $('#maf_attached').on('keyup', function () {
        $.ajax({
            type: "POST",
            url: mafJsVars.ajax_url,
            data: {
                'action': 'maf_search',
                'nonce': mafJsVars.maf_search_nonce,
                'keyword': $(this).val()
            },
            success: function( data ) {
                if( data.success ) {
                    // clear list.
                    $('#maf_attached_list').html('');
                    // add entries from results.
                    $.each(data.results, function( index, value ) {
                        let element = $('<option></option>');
                        element.html(value);
                        element.appendTo( '#maf_attached_list' );
                    });
                }
            }
        });
    });
});