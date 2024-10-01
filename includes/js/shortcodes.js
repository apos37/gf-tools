jQuery( document ).ready(function( $ ) {
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
} )