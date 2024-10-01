jQuery( $ => {
    // console.log( 'Settings Script Loaded...' );

    /**
     * API FIELDS
     */

    // API Key View Icon
    $( '#gform_setting_api_spam_key .gform-settings-input__container' ).append(
        '<i class="gfat-toggle-password fa fa-eye" aria-hidden="true"></i>'
    );

    $( document ).on( 'click', '.gfat-toggle-password', function() {
        var passwordField = $( '#api_spam_key' );
        var type = passwordField.attr('type');

        if ( type === 'password' ) {
            passwordField.attr( 'type', 'text' );
            $( this ).removeClass( 'fa-eye' ).addClass( 'fa-eye-slash' );
        } else {
            passwordField.attr( 'type', 'password' );
            $( this ).removeClass( 'fa-eye-slash' ).addClass( 'fa-eye' );
        }
    } );

    // Which fields to show or hide
    var spamFiltering = $( '#spam_filtering' ).val();
    setSpamFilteringFields( spamFiltering );

    $( '#spam_filtering' ).on( 'change', function() {
        setSpamFilteringFields( $( this ).val() );
    } );

    // Set the spam filtering fields
    function setSpamFilteringFields( location ) {
        var apiKey = $( '#gfat_api_key' ).data( 'key' );
        if ( apiKey ) {
            $( '#gfat_api_key, #gfat_api_timestamp' ).css( 'display', 'inline-block !important' );
        } else {
            $( '#gfat_api_key, #gfat_api_timestamp' ).hide();
        }
        if ( location == 'host' ) {
            $( '#api_spam_key, .gfat-toggle-password, #gform_setting_spam_list_url' ).hide();
            $( '#gform_setting_api_spam_key, #gform_setting_generate_api_key' ).show();
        } else if ( location == 'client' ) {
            $( '#gform_setting_api_spam_key, #api_spam_key, .gfat-toggle-password, #gform_setting_spam_list_url' ).show();
            $( '#gform_setting_generate_api_key' ).hide();
        } else {
            $( '#gform_setting_api_spam_key, #gform_setting_spam_list_url, #gform_setting_generate_api_key' ).hide();
        }
    } // End setSpamFilteringFields()

    // Generate an API Key
    $( document ).on( 'click', '#gfat_generate_api_key', function( e ) {
        e.preventDefault();

        var nonce = gfat_spam.nonce;

        if ( nonce ) {
            $.ajax( {
                type: 'post',
                dataType: 'json',
                url: gfat_spam.ajax_url,
                data: {
                    action: 'gfat_generate_api_key',
                    nonce: nonce,
                },
                beforeSend: function() {
                    $( '#gfat_api_timestamp' ).html( '<em>' + gfat_settings.generating_text + '...</em>' );
                },
                success: function( response ) {
                    var apiKey = response.apiKey;
                    var apiMsg = response.apiMsg;
                    $( '#gfat_api_key' ).data( 'key', apiKey ).attr( 'data-key', apiKey ).addClass( 'showCopyAPI' );
                    $( '#gfat_api_timestamp' ).html( `${apiMsg} <code>${apiKey}</code>` ).css( 'display', 'inline-block !important' );
                },
                error: function( xhr, status, error ) {
                    console.error( 'AJAX Error:', status, error );
                    console.error( 'Response:', xhr.responseText );
                }
            } );
        }
    } );

    // Copy button
    $( document ).on( 'click', '#gfat_api_key', function( e ) {
        e.preventDefault();
        var codeText = $( this ).data( 'key' );
        navigator.clipboard.writeText( codeText )
            .then( function() {
                alert( gfat_settings.clipboard_text );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );


    /**
     * DELETE SPAM LIST
     */
    $( '#gfat_delete_spam_list_table' ).on( 'click', function(e) {
        var confirmed = confirm( gfat_settings.confirm_delete_text );
        if ( !confirmed ) {
            e.preventDefault();
        }
    } );


    /**
     * TEXT+ FIELDS
     */
    // Variable to keep track of the current index
    var index = $( '.fields_container .text-plus-row' ).length;

    // Function to update the name attributes of all rows
    function updateIndexes() {
        $( '.fields_container .text-plus-row' ).each( function( i ) {
            $( this ).attr('data-row', i);

            $( this ).find( 'input, select' ).each( function() {
                var name = $( this ).attr( 'name' );
                // Update index in the name attribute
                var newName = name.replace( /\[\d+\]/, '[' + i + ']' );
                $( this ).attr( 'name', newName );
            } );
        } );
        index = $( '.fields_container .text-plus-row' ).length;
    }

    // Add new field
    $( '.add-new-field' ).on( 'click', function() {
        var name = $( this ).data( 'name' );
        var fieldsContainer = $( `#fields_container_${name}` );
        var lastRow = fieldsContainer.find( '.text-plus-row' ).last();
        if ( lastRow.data( 'row' ) == 0 && lastRow.is(':hidden') ) {
            lastRow.show();
        } else {
            var newRow = lastRow.clone();
            newRow.find( 'input' ).val( '' );
            newRow.find( 'select' ).prop( 'selectedIndex', 0 );
            fieldsContainer.append( newRow );
            updateIndexes();
        }
    } );

    // Remove field
    $( '.fields_container' ).on( 'click', '.remove-row', function() {
        var row = $( this ).closest( '.text-plus-row' );
        var rowNum = row.data( 'row' );
        if ( rowNum > 0 ) {
            row.remove();
            updateIndexes();
        } else {
            row.hide();
            row.find( 'input' ).val( '' );
            row.find( 'select' ).prop( 'selectedIndex', 0 );
        }
    } );

    
    /**
     * FORCE META KEY FIELD TO LOWERCASE AND NO SPACES
     */
    // Event listener for inputs with class 'metakey'
    $( '.gform-settings-field__text_plus .fields_container' ).on( 'input', '.metakey', function() {
        var updatedValue = $( this ).val()
            .toLowerCase() // Convert to lowercase
            .replace( /\s+/g, '_' ) // Replace spaces with underscores
            .replace(/[^a-z0-9_]/g, '');

        $( this ).val( updatedValue );
    } );
} )