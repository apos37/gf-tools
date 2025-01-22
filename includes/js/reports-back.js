jQuery( $ => {
    // console.log( 'Back-End Reports Script Loaded...' );

    $( document ).on( 'change', '#gfat-form-input', function( e ) {
        const formID = $( this ).val();
        $( `#gfat_fields_page .inside .gfat-form-section:not([data-form-id="0"])` ).remove();
        $( `#gfat_fields_export .inside .gfat-form-section:not([data-form-id="0"])` ).remove();

        if ( formID != 0 ) {
            const nonce = gfat_reports_back.nonce_for_preview;
            if ( nonce ) {

                // Set the ajax args
                $.ajax( {
                    type: 'POST',
                    dataType: 'json',
                    url: gfat_reports_back.ajaxurl,
                    data: { 
                        action: 'report_get_form_fields', 
                        nonce: nonce, 
                        formID: formID 
                    },
                    success: function( response ) {
                        if ( response.type == 'success' ) {

                            const title = response.title;
                            const fields = response.fields;

                            addFieldsToMetaBox( formID, title, fields, 'page' );
                            addFieldsToMetaBox( formID, title, fields, 'export' );

                        // Failure
                        } else {
                            console.log( `Oh no! We could not fetch the fields for form ${formID}.` );
                        }
                    }
                } );
            }
        }
    } );

    // Clicking a field box
    $( document ).on( 'change', '.gfat-field-cont input[type="checkbox"]', function( e ) {
        const checkbox = $( this );
        const btn = checkbox.closest('.gfat-field-cont');
        
        // Highlight
        var isChecked = checkbox.is( ':checked' );
        if ( isChecked ) {
            btn.addClass( 'highlighted' );
        } else {
            btn.removeClass( 'highlighted' );
        }

        if ( checkbox.hasClass( 'page' ) ) {
            const name = checkbox.attr( 'id' );
            const label = btn.find( 'label' ).text();

            if ( !isChecked ) {
                removeFieldFromTable( name );
            } else {
                addFieldToTable( name, label );
            }
        }
    } );

    // Edit labels
    $( document ).on( 'click', '.gfat-edit-label', function( e ) {
        e.preventDefault();

        var editLink = $( this );
        var label = editLink.siblings( 'label' );
        var checkbox = editLink.siblings( 'input[type="checkbox"]' );
        
        // Save
        if ( editLink.text() === '[Save]' ) {
            var newValue = editLink.siblings( '.edit-input' ).val();
            
            label.text( newValue );
            checkbox.val( newValue );
            
            editLink.siblings( '.edit-input' ).remove();
            label.show();
            editLink.text( '[Edit]' );

            var id = editLink.data( 'id' );
            $( `th.col-${id}` ).text( newValue );

        // Edit
        } else {
            var currentText = label.text();
            
            label.hide();
            $( '<input type="text" class="edit-input" />' ).val( currentText ).insertBefore( editLink );
            
            editLink.text('[Save]');
        }
    } );

    // Handle Enter key while editing labels
    $( document ).on( 'keydown', '.edit-input', function( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            $( this ).siblings( '.gfat-edit-label' ).click();
        }
    } );

    // Function to add fields to the meta box
    function addFieldsToMetaBox( formID, title, fields, type ) {
        if ( !title.endsWith( 'Form' ) ) {
            title = title + ' Form';
        }
        const formSectionContainer = $( `#gfat_fields_${type} .inside` );
        const newFormSection = $( '<div>', { 
            id: 'gfat-form-section-' + formID, 
            class: 'gfat-form-section',
            'data-form-id': formID
        } )
        .append( '<h3>' + title + '</h3>' )
        .append( '<div class="gfat-form-cont"></div>' );

        // Append the new form section to the container
        formSectionContainer.append( newFormSection );

        // Iterate through fields and add checkboxes
        fields.forEach( field => {
            const fieldHTML = `
                <div class="gfat-field-cont">
                    <input type="checkbox" id="${type}-field-${formID}-${field.id}" class="${type}" name="${type}_fields[${formID}][${field.id}]" value="${field.label}">
                    <label for="${type}-field-${formID}-${field.id}">${field.label}</label>
                    <a href="#" class="gfat-edit-label" aria-label="Edit Column Label" title="Edit Column Label" data-id="${type}-field-${formID}-${field.id}">[Edit]</a>
                </div>`;
            
            newFormSection.find( '.gfat-form-cont' ).append( fieldHTML );
        } );
    }

    // Function to add fields as columns in the preview table
    function addFieldToTable( id, label ) {
        $( '#gfat-preview-table thead tr' ).append(
            `<th class="col-${id}">${label}</th>`
        );

        $( '#gfat-preview-table tbody tr' ).each( function() {
            $( this ).append( `<td class="col-${id}">Example</td>` );
        } );
        
        const currentCols = parseInt( $( '#gfat-preview-table' ).attr( 'data-cols' ), 10 ) || 0;
        $( '#gfat-preview-table' ).attr( 'data-cols', currentCols + 1 );

        const partialId = id.replace( 'page-field-', '' );
        $( '#gfat-page-order-by' ).append(
            `<option value="${partialId}" class="">${label}</option>`
        );
    }

    // Function to remove fields from the table
    function removeFieldFromTable( id ) {
        $( `#gfat-preview-table thead .col-${id}` ).remove();

        $( '#gfat-preview-table tbody tr' ).each( function() {
            $( this ).find(`.col-${id}`).remove();
        });

        const currentCols = parseInt( $( '#gfat-preview-table' ).attr( 'data-cols' ), 10 ) || 0;
        $( '#gfat-preview-table' ).attr( 'data-cols', Math.max( currentCols - 1, 0 ) );

        const partialId = id.replace( 'page-field-', '' );
        $( `#gfat-page-order-by option[value="${partialId}"]` ).remove();
    }

    // Select All checkboxes
    $( document ).on( 'change', '.gfat-select-all', function( e ) {
        const type = $( this ).data( 'type' );
        var checkboxes = $( `#gfat_fields_${type} input.${type}` );
        if ( $( this ).is( ':checked' ) ) { 
            checkboxes.prop( 'checked', true );
            checkboxes.parent().addClass( 'highlighted' );
        } else {
            checkboxes.prop( 'checked', false );
            checkboxes.parent().removeClass( 'highlighted' );
        }
    } );

    // Function to update the "select all" checkbox
    function updateSelectAllCheckbox() {
        // Iterate over each "select all" checkbox
        $( '.gfat-select-all' ).each( function() {
            const type = $( this ).data( 'type' );
            const checkboxes = $( `#gfat_fields_${type} input.${type}` );
            const allChecked = checkboxes.length === checkboxes.filter( ':checked' ).length;
            
            // Set the "select all" checkbox based on the state of individual checkboxes
            $( this ).prop( 'checked', allChecked );
        } );
    }

    // Call the function on page load
    updateSelectAllCheckbox();

    // Optional: Call the function whenever an individual checkbox changes
    $( document ).on( 'change', '.gfat-form-section input[type="checkbox"]', function() {
        updateSelectAllCheckbox();
    } );

    // Click to copy shortcode
    $( '.code-box' ).on( 'click', '.copy-button', function( e ) {
        e.preventDefault();
        var codeHtml = $( this ).siblings( 'pre' ).html();
        var codeText = codeHtml.replace( /<br\s*\/?>/gi, '\n' ).replace( /&nbsp;/g, ' ' );
        navigator.clipboard.writeText( codeText )
            .then( function() {
                alert( 'Copied to clipboard!' );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );
} )