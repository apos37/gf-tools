jQuery( $ => {
    // console.log( 'Mark Resolved Script Loaded...' );

    $( document ).on( 'click', '.gfat-mark-resolved', function( e ) {
        e.preventDefault();

        if ( typeof gfat_mark_resolved === 'undefined' ) {
            console.error( 'gfat_mark_resolved is not defined.' );
            return;
        }

        const link = $( this );
        const status = link.attr( 'data-status' );
        const entryID = link.attr( 'data-entry' );
        var nonce = gfat_mark_resolved.nonce;

        if ( nonce && status && entryID ) {
            $.ajax( {
                type: 'post',
                dataType: 'json',
                url: gfat_mark_resolved.ajax_url,
                data: {
                    action: 'mark_resolved',
                    nonce: nonce,
                    entryID: entryID,
                    status: status
                },
                success: function( response ) {

                    if ( response.type == 'success' ) {

                        var rowActions = link.closest( '.row-actions' );
                        var colResolved = $( `#entry_row_${entryID} .field_id-resolved` );
                        var colResolvedDate = $( `#entry_row_${entryID} .field_id-resolved_date` );
                        var colResolvedBy = $( `#entry_row_${entryID} .field_id-resolved_by` );

                        if ( status == 'in_progress' ) {
                            link.addClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.in-progress-sep' ).addClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.resolved' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.resolved-sep' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.unresolved' ).removeClass( 'hide' );
                            if ( colResolved ) {
                                colResolved.text( gfat_mark_resolved.text.in_progress );
                            }
                        } else if ( status == 'resolved' ) {
                            rowActions.find( '.gfat-mark-resolved.in-progress' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.in-progress-sep' ).removeClass( 'hide' );
                            link.addClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.resolved-sep' ).addClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.unresolved' ).removeClass( 'hide' );
                            if ( colResolved ) {
                                colResolved.text( gfat_mark_resolved.text.resolved );
                            }
                        } else if ( status == 'unresolved' ) {
                            rowActions.find( '.gfat-mark-resolved.in-progress' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.in-progress-sep' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.resolved' ).removeClass( 'hide' );
                            rowActions.find( '.gfat-mark-resolved.resolved-sep' ).addClass( 'hide' );
                            link.addClass( 'hide' );
                            if ( colResolved ) {
                                colResolved.text( gfat_mark_resolved.text.unresolved );
                            }
                        }

                        if ( colResolvedDate ) {
                            colResolvedDate.text( response.resolved_date );
                        }
                        if ( colResolvedBy ) {
                            colResolvedBy.text( response.resolved_by );
                        }
                        
                    } else {
                        console.error( 'Error message:', response.msg );
                        alert( gfat_mark_resolved.text.something_went_wrong + response.msg );
                    }
                },
                error: function( xhr, status, error ) {
                    console.error( 'AJAX Error:', status, error );
                    console.error( 'Response:', xhr.responseText );
                }
            } );
        }
    } );
} )