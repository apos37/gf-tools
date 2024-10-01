jQuery( $ => {
    // console.log( 'Debugging Script Loaded...' );

    /**
     * FORM DEBUGGING
     */
    $( document ).on( 'click', '.gfat_debug_form_link a', function( e ) {
        e.preventDefault();

        if ( typeof gfat_debugging === 'undefined' ) {
            console.error( 'gfat_debugging is not defined.' );
            return;
        }

        var nonce = gfat_debugging.nonce;
        var id = gfat_debugging.form_id;

        if ( nonce && id ) {
            runAjax( nonce, 'form', id );
        }
    } );


    /**
     * ENTRY DEBUGGING
     */
    $( document ).on( 'click', '.gfat_debug_entry_link a', function( e ) {
        e.preventDefault();

        if ( typeof gfat_debugging === 'undefined' ) {
            console.error( 'gfat_debugging is not defined.' );
            return;
        }

        var nonce = gfat_debugging.nonce;
        var id = gfat_debugging.entry_id;

        if ( nonce && id ) {
            runAjax( nonce, 'entry', id );
        }
    } );


    /**
     * THE AJAX
     */
    function runAjax( nonce, type, id ) {
        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: gfat_debugging.ajax_url,
            data: {
                action: 'get_object_array',
                nonce: nonce,
                type: type,
                id: id
            },
            success: function( response ) {
                if ( response.type == 'success' ) {
                    createPopup( response.data, type, id, response.form_id );
                    
                } else {
                    console.error( 'Error message:', response.msg );
                    alert( gfat_debugging.text.something_went_wrong + response.msg );
                }
            }
        } );
    }


    /**
     * Create and show the popup
     */
    function createPopup( data, type, id, formID ) {
        // Remove any existing popup
        $('#gfat-popup-container').remove();

        var typeCapitalized = type.charAt(0).toUpperCase() + type.slice(1);
        var inclSaved = ( type == 'form' ) ? `<p>${gfat_debugging.text.form_saved}</p>` : '';
        var inclFormID = ( type == 'entry' ) ? `<p>${gfat_debugging.text.form_id}: ${formID}</p>` : '';

        // Create the popup elements
        var popupHtml = `
            <div id="gfat-popup-container">
                <div id="gfat-popup-overlay"></div>
                <div id="gfat-popup-content">
                    <button id="gfat-popup-close">&times;</button>
                    <div id="gfat-popup-title">
                        <h1>${typeCapitalized} ID: ${id}</h1>
                        ${inclSaved}
                        ${inclFormID}
                    </div>
                    <pre id="gfat-popup-data">${data}</pre>
                </div>
            </div>
        `;

        // Append popup to the body
        $('body').append(popupHtml);

        // Show the popup
        $('#gfat-popup-container').show();

        // Close the popup on click
        $('#gfat-popup-close').on('click', function() {
            $('#gfat-popup-container').remove();
        });

        // Close the popup when clicking outside the content
        $('#gfat-popup-overlay').on('click', function() {
            $('#gfat-popup-container').remove();
        });
    }
} )