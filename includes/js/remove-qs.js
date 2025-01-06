jQuery( document ).ready( function( $ ) {
    // console.log( 'Remove QS JS Loaded...' );

    if ( typeof gfadvtools_remove_qs !== 'undefined' && gfadvtools_remove_qs && gfadvtools_remove_qs.title != '' ) {
        if ( history.pushState ) {
            var obj = { Title: gfadvtools_remove_qs.title, Url: gfadvtools_remove_qs.url };
            window.history.pushState( obj, obj.Title, obj.Url );
        }
    }
} );