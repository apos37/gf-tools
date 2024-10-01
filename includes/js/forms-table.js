jQuery( $ => {
    // console.log( 'Forms Script Loaded...' );

    /**
     * COPY TO CLIPBOARD
     */
    $( document ).on( 'click', '.copy-shortcode', function( e ) {
        e.preventDefault();
        var shortcode = $( this ).data( 'shortcode' );
        navigator.clipboard.writeText( shortcode )
            .then( function() {
                alert( 'Copied to clipboard!' );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );
} )