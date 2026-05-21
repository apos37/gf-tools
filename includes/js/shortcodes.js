jQuery( document ).ready( function( $ ) {
    // console.log( 'Shortcodes Script Loaded...' );

    /**
     * ENTRY EXPORT - SELECT ALL
     */
    var checkboxes = $( '#gfat-export-entries-form .field-checkboxes:not(#field-checkbox-selectall)' );
    var selectAll = $( '#gfat-export-entries-form #field-checkbox-selectall' );

    selectAll.on( 'change', function() {
        checkboxes.prop( 'checked', this.checked );
    } );
    
    // Add change event to individual checkboxes
    checkboxes.on( 'change', function() {
        selectAll.prop( 'checked', checkboxes.length === checkboxes.filter( ':checked' ).length );
    } );


    /**
     * ENTRY EXPORT -  PREVENT EXPORT IF NOTHING IS CHECKED
     */
    $( '#gfat-export-entries-form' ).on( 'submit', function( e ) {
        var isChecked = $( '.field-checkboxes:checked' ).length > 0;
        if ( !isChecked ) {
            alert( gfadvtools_shortcodes.check_a_box );
            e.preventDefault();
        }
    } );


    /**
     * REPORTS - LINK FIRST COLUMN TO ENTRY DETAILS
     */
    const modal = $( '#gfat-entry-modal' );
    if ( modal.length ) {

        const modalBody = modal.find( '.gfat-modal-body' );
        const loaderContainer = modal.find( '.gfat-modal-loader-container' );
        const closeBtn = modal.find( '.gfat-modal-close' );

        const openModal = function() {
            modal.show();
            $( 'body' ).css( 'overflow', 'hidden' );
        };

        const closeModal = function() {
            modal.hide();
            $( 'body' ).css( 'overflow', '' );
            modalBody.empty();
        };

        $( '.gfat-entry-link' ).on( 'click', function( e ) {
            e.preventDefault();
            const entryId = $( this ).data( 'entry-id' );
            const formId = $( this ).data( 'form-id' );
            const reportId = $( this ).closest( 'table' ).data( 'report-id' );

            // Show loader container
            loaderContainer.show();
            modalBody.empty();
            openModal();

            $.get( gfadvtools_shortcodes.ajaxurl, { 
                action: 'gfat_get_entry',
                report_id: reportId,
                entry_id: entryId,
                form_id: formId,
                nonce: gfadvtools_shortcodes.nonce
            }).done( function( response ) {
                loaderContainer.hide();

                // Check if the response is the JSON error object from wp_send_json_error
                if ( response.success === false ) {
                    modalBody.html( '<div class="gfat-error-msg"><p>' + response.data + '</p></div>' );
                } else {
                    // response is the raw HTML table
                    modalBody.html( response );
                }
            }).fail( function( jqXHR ) {
                loaderContainer.hide();
                // Fallback message if the server crashes or returns a 400/500 error
                const fallback = "An error occurred while loading the entry.";
                modalBody.html( '<p>' + (gfadvtools_shortcodes.error_loading || fallback) + '</p>' );
                console.error("AJAX Error:", jqXHR.status, jqXHR.statusText);
            });
        });

        closeBtn.on( 'click', closeModal );

        $( window ).on( 'click', function( e ) {
            if ( $( e.target ).is( modal ) ) {
                closeModal();
            }
        });
    }


} )