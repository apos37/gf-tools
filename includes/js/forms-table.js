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


    /**
     * MOVE FORM STATES OUT OF LINKS
     */
    $( '.toplevel_page_gf_edit_forms #the-list td.title a' ).each( function () {
        const $link = $( this );
        const fullText = $link.text();

        // Look for " — [Label]" at end
        const match = fullText.match( /^(.*?) — (.+)$/ );

        if ( match ) {

            const title = match[1];
            const label = match[2];

            // Set link text to title only
            $link.text( title );

            // Append label after link in a styled <span> with lock icon and title attribute
            const lockedByText = gfat_forms_table.locked_by || '';

            $link.after(
                $( '<span />', {
                    class: 'gf-tools-form-label',
                    'aria-hidden': 'true',
                    html: ' — ' + 
                        $('<span />', {
                            html: label + ' <span class="dashicons dashicons-lock" title="' + lockedByText + '"></span>'
                        }).html()
                } )
            );

            // Optional: update aria-label
            $link.attr( 'aria-label', title + ' (Edit)' );
        }
    } );


    /**
     * REMOVE TRASH LINKS
     */
    $( '.toplevel_page_gf_edit_forms #the-list tr' ).each( function() {
        const mappedForms = gfat_forms_table.mapped_forms || {};
        const lockedFormIds = Object.values( mappedForms ).map( form => form.id );
    
        const row = this;
        const titleCell = row.querySelector( 'td.title' );
        if ( !titleCell ) {
            return;
        }
        const link = titleCell.querySelector( 'a' );
        if ( !link ) {
            return;
        }

        const href = link.getAttribute( 'href' ) || '';
        const formIdMatch = href.match( /id=(\d+)/ );
        if ( !formIdMatch ) {
            return;
        }

        const formId = parseInt( formIdMatch[1], 10 );
        if ( ! lockedFormIds.includes( formId ) ) {
            return;
        }

        const fullText = link.textContent;
        const match = fullText.match( /^(.*?) — (.+)$/ );

        if ( match ) {
            const title = match[1];
            const label = match[2];

            link.textContent = title;

            const labelSpan = document.createElement( 'span' );
            labelSpan.textContent = ' — ' + label;
            labelSpan.className = 'gf-tools-form-label';
            labelSpan.setAttribute( 'aria-hidden', 'true' );

            link.insertAdjacentElement( 'afterend', labelSpan );
            link.setAttribute( 'aria-label', title + ' (Edit)' );
        }

        const trashLink = titleCell.querySelector( '.trash' );
        if ( trashLink ) {
            trashLink.remove();
        }
    } );
} )