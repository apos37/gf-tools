jQuery( $ => {
    // console.log( 'Form Editor Script Loaded...' );
    
    var urlParams = new URLSearchParams( window.location.search );
    var page = urlParams.get( 'page' );
    var id = urlParams.get( 'id' );
    var view = urlParams.get( 'view' );
    var subview = urlParams.get( 'subview' );

    if ( page === 'gf_edit_forms' && id && !view && !subview ) {        
        if ( gfatFields ) {
            gfatFields.forEach( function( gfatField ) {
                var applyTo = gfatField.fields;
                var fieldId = gfatField.id + '_setting';

                applyTo.forEach( function( type ) {
                    if ( !fieldSettings[ type ] ) {
                        fieldSettings[ type ] = "";
                    }

                    fieldSettings[ type ] += ", ." + fieldId;
                } );

                if ( applyTo.length === 0 ) {
                    for ( var type in fieldSettings ) {
                        if ( fieldSettings.hasOwnProperty( type ) ) {
                            fieldSettings[ type ] += ", ." + fieldId;
                        }
                    }
                }
            } );

            jQuery( document ).on( "gform_load_field_settings", function( event, field, form ) {
                gfatFields.forEach( function( gfatField ) {
                    var fieldId = gfatField.id;
                    var fieldType = gfatField.type;

                    if ( fieldType == 'text' ) {
                        jQuery( `#field_${fieldId}` ).val( rgar( field, fieldId ) );

                    } else if ( fieldType == 'checkbox' ) {
                        jQuery( `#field_${fieldId}` ).prop( 'checked', Boolean( rgar( field, fieldId ) ) );
                    }
                } );
            } );
        }
    }
} )