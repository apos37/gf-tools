jQuery( $ => {
    // console.log( 'Dashboard Script Loaded...' );

    /**
     * MERGE TAGS FORM FIELDS DISABLE
     */
    $( '#gfat-merge_tags-entry' ).on( 'input', function() {
        const val = $( this ).val();
        var filterField = $( '#gfat-forms-filter' );
        var option = filterField.find( 'option[value="0"]' );
        if ( val != '' ) {
            filterField.val( 0 );
            filterField.prop( 'disabled', true );
            if ( option.length ) {
                option.text( gfat_dashboard.text.merge_tags_filter_field );
            }
        } else {
            filterField.prop( 'disabled', false );
            if ( option.length ) {
                option.text( '-- ' + gfat_dashboard.text.select_a_form + ' --' );
            }
        }

        var userField = $( '#gfat-merge_tags-id-or-email' );
        if ( val != '' ) {
            userField.val( '' );
            userField.prop( 'disabled', true );
            userField.attr( 'placeholder', gfat_dashboard.text.merge_tags_user_field );
        } else {
            userField.prop( 'disabled', false );
            userField.removeAttr( 'placeholder' );
        }

        var postIdField = $( '#gfat-merge_tags-post' );
        if ( val != '' ) {
            postIdField.val( '' );
            postIdField.prop( 'disabled', true );
            postIdField.attr( 'placeholder', gfat_dashboard.text.merge_tags_postid_entry );
        } else {
            postIdField.prop( 'disabled', false );
            postIdField.removeAttr( 'placeholder' );
        }
    } );


    $( '#gfat-forms-filter' ).on( 'change', function() {
        const val = $( this ).val();

        var postIdField = $( '#gfat-merge_tags-post' );
        if ( val != 0 ) {
            postIdField.val( '' );
            postIdField.prop( 'disabled', true );
            postIdField.attr( 'placeholder', gfat_dashboard.text.merge_tags_postid_form );
        } else {
            postIdField.prop( 'disabled', false );
            postIdField.removeAttr( 'placeholder' );
        }
    } );

    /**
     * COPY TO CLIPBOARD
     */
    $( '.code-box' ).on( 'click', '.copy-button', function( e ) {
        e.preventDefault();
        var codeHtml = $( this ).siblings( 'pre' ).html();
        var codeText = codeHtml.replace( /<br\s*\/?>/gi, '\n' ).replace( /&nbsp;/g, ' ' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' );
        navigator.clipboard.writeText( codeText )
            .then( function() {
                alert( 'Copied to clipboard!' );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );

    $( document ).on( 'click', '.copy-merge-tag', function( e ) {
        e.preventDefault();
        var tag = $( this ).parent().data( 'tag-id' );
        
        navigator.clipboard.writeText( tag )
            .then( function() {
                alert( `Copied ${tag} to clipboard!` );
                $( this ).closest( 'tr' ).removeClass( 'copied' );
            }.bind( this ) )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    
        $( this ).closest( 'tr' ).addClass( 'copied' );
    } );


    /**
     * DELETE ALL SPAM
     */
    $( document ).on( 'click', '.delete-all-spam', function( e ) {
        e.preventDefault();

        // Check if the gfat_dashboard object is defined
        if ( typeof gfat_dashboard === 'undefined' ) {
            console.error( 'gfat_dashboard is not defined.' );
            return;
        }
        
        // Cancel
        if ( !confirm( gfat_dashboard.text.spam_delete_all ) ) {
            return false;
        }

        // Elements
        const button = $( this );
        const deleteButton = button;
        const formActionsDiv = button.closest( '.form-actions' );
        const formID = formActionsDiv.attr( 'data-form-id' );
        const countElement = button.closest( 'tr' ).find( '.count' );
        const progressBarContainer = formActionsDiv.find( '.progress-container' );
        const progressBar = progressBarContainer.find( '.progress' );
        const progressText = progressBarContainer.find( '.progress-text' );

        // Show progress bar
        button.hide();
        progressBarContainer.show();
        progressBar.css( {
            'width': '0%',
            'transition': 'none'
        } );
        progressText.text( '0%' );

        // Re-enable animation after a short delay
        setTimeout( () => {
            progressBar.css( 'transition', 'width 0.5s ease-in-out' );
        }, 100 );

        // Nonce
        var nonce = gfat_dashboard.nonce;

        // Handle canceling during process
        let abortController = new AbortController(); // Create an instance of AbortController
        let operationCanceled = false;

        progressBarContainer.off( 'click' ).on( 'click', function() {
            abortController.abort();
            operationCanceled = true;
            deleteButton.show();
            progressBarContainer.hide();
            progressBar.css( {
                'width': '0%',
                'transition': 'none'
            } );
            progressText.text( '0%' );
            alert( gfat_dashboard.text.spam_canceled );
        } );
    
        // Delete an individual entry
        const deleteEntry = async ( formID, entryID ) => {
            console.log( `Deleting entry (${entryID})...` );
            try {
                await $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: gfat_dashboard.ajax_url,
                    data: { 
                        action: 'delete_spam_entry', 
                        nonce: nonce,
                        formID: formID,
                        entryID: entryID
                    },
                    signal: abortController.signal // Attach the abort signal to the AJAX request
                } );
            } catch ( error ) {
                if ( abortController.signal.aborted ) {
                    console.log( 'Deletion was aborted.' );
                    operationCanceled = true;
                } else {
                    console.error( `Failed to delete entry ${entryID}:`, error );
                }
            }
        }

        // Delete all entries
        const deleteAllEntries = async () => {
            try {
                // Get the entry IDs to delete
                const response = await $.ajax( {
                    type: 'post',
                    dataType: 'json',
                    url: gfat_dashboard.ajax_url,
                    data: {
                        action: 'get_all_spam_entry_ids',
                        nonce: nonce,
                        formID: formID
                    },
                    signal: abortController.signal
                } );

                // If all is well
                if ( response.type == 'success' ) {
                    const entryIDs = response.entry_ids;

                    // Sort the array from smallest to largest number
                    entryIDs.sort( ( a, b ) => a - b );

                    const total = entryIDs.length;
                    let completed = 0;

                    // Iter the entries
                    for ( const entryID of entryIDs ) {

                        // Cancel
                        if ( operationCanceled ) {
                            console.log( 'Operation canceled, skipping remaining entries.' );
                            break;
                        }

                        // Delete
                        try {
                            await deleteEntry( formID, entryID );

                            completed++;

                            // Progress bar
                            const progress = Math.min(Math.round((completed / total) * 100), 100);
                            progressBar.css( 'width', progress + '%' );
                            progressText.text( progress + '%' );

                            // Update the count
                            if ( !operationCanceled ) {
                                countElement.text( total - completed );
                            }

                            // Console, but don't alert
                            if ( progress === 100 ) {
                                progressBarContainer.off( 'click' );
                                progressBar.css( 'cursor', 'default' );
                                console.log( 'All spam entries have been deleted.' );
                            }
                        } catch ( deleteError ) {
                            console.error( `Failed to delete entry ${entryID}:`, deleteError );
                        }
                    }

                } else {
                    console.error( 'Error message:', response.msg );
                    alert( gfat_dashboard.text.something_went_wrong + response.msg );
                }
            } catch ( fetchError ) {
                if ( abortController.signal.aborted ) {
                    deleteButton.show();
                    progressBarContainer.hide();
                    progressBar.css( {
                        'width': '0%',
                        'transition': 'none'
                    } );
                    progressText.text( '0%' );
                } else {
                    alert( gfat_dashboard.text.unexpected_error );
                    console.error( 'Error fetching entry IDs:', fetchError );
                }
            }
        }

        // Do it
        deleteAllEntries();
    } );


    /**
     * DELETE SPAM RECORDS
     */
    // Single
    $( '.delete-spam-record' ).on( 'click', function( e ) {
        // Display confirmation dialog
        var confirmed = confirm( gfat_dashboard.text.spam_delete_record );

        // If user cancels, prevent the default action
        if ( !confirmed ) {
            e.preventDefault();
        }
    } );

    // Selected
    $( '#gfat-spam_list-delete-selected-button' ).on( 'click', function( e ) {
        // Prevent the default action initially
        e.preventDefault();
    
        // Check for selected checkboxes
        var selectedCheckboxes = $( '.wp-list-table input[name="delete_selected[]"]:checked' );
    
        // If no checkboxes are selected, alert the user
        if ( selectedCheckboxes.length === 0 ) {
            alert( gfat_dashboard.text.spam_select_records );

        } else {
            // Display confirmation dialog
            var confirmed = confirm( gfat_dashboard.text.spam_delete_records );
    
            // If user cancels, prevent the default action
            if ( confirmed ) {

                // If confirmed, proceed with the default action (form submission)
                $( '#gfat-spam_list-delete-selected-form' ).submit(); // Change the form selector as needed
            }
        }
    } );

    /**
     * DELETE REPORTS
     */
    // Single
    $( '.delete-report' ).on( 'click', function( e ) {
        // Display confirmation dialog
        var confirmed = confirm( gfat_dashboard.text.report_delete );

        // If user cancels, prevent the default action
        if ( !confirmed ) {
            e.preventDefault();
        }
    } );
    
} )