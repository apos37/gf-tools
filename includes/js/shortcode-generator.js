jQuery( $ => {

    /**
     * INSERT SHORTCODE BUTTON
     */

    if ( gfat_shortcode_generator.add_generator ) {
        // console.log( 'Shortcode Generator Script Loaded...' );

        // Create the button and generator
        var insertShortcodeBtn = `<button type="button" id="gfat-insert-shortcode-button" class="button gfat-insert-shortcode">${gfat_shortcode_generator.text.btn}</button>`;

        var shortcodeGenerator = `<div id="gfat-shortcode-generator-cont">
            <div id="gfat-shortcode-generator" style="display: none;">
                <div>
                    <select id="gfat-merge-tag" class="gfat-select-field" aria-label="Field">
                        <option>-- ${gfat_shortcode_generator.text.select_a_field} --</option>
                    </select>
                </div>
                <div>
                    <select id="gfat-condition" class="gfat-select-field" aria-label="Condition">
                        <option value="is">${gfat_shortcode_generator.text.is}</option>
                        <option value="isnot">${gfat_shortcode_generator.text.isnot}</option>
                        <option value="greater_than">${gfat_shortcode_generator.text.greater_than}</option>
                        <option value="less_than">${gfat_shortcode_generator.text.less_than}</option>
                        <option value="contains">${gfat_shortcode_generator.text.contains}</option>
                        <option value="starts_with">${gfat_shortcode_generator.text.starts_with}</option>
                        <option value="ends_with">${gfat_shortcode_generator.text.ends_with}</option>
                    </select>
                </div>
                <div>
                    <input type="text" id="gfat-value" class="gfat-text-field" aria-label="Value" placeholder="Value">
                </div>
                <button type="button" id="gfat-insert-shortcode" class="button gfat-insert-shortcode-btn">${gfat_shortcode_generator.text.insert}</button>
            </div>
        </div>`;

        $( '#insert-media-button' ).after( insertShortcodeBtn );
        $( '.wp-media-buttons' ).after( shortcodeGenerator );

        // Add the fields
        var $mergeTagSelect = $('#gfat-merge-tag');
        $mergeTagSelect.empty();
        gfat_shortcode_generator.fields.forEach( function( tag ) {
            $mergeTagSelect.append(`<option value="{:${tag.id}}">${tag.label}</option>`);
        } );

        // Toggle it
        $( '#gfat-insert-shortcode-button' ).on( 'click', function() {
            $( '#gfat-shortcode-generator' ).toggle();
        } );

        // Insert shortcode into editor
        $( '#gfat-insert-shortcode' ).on( 'click', function() {
            var mergeTag = $( '#gfat-merge-tag' ).val();
            var condition = $( '#gfat-condition' ).val();
            var value = $( '#gfat-value' ).val();

            if ( mergeTag && condition && value ) {
                var shortcode = `[gravityforms action="conditional" merge_tag="${mergeTag}" condition="${condition}" value="${value}"]${gfat_shortcode_generator.text.content}[/gravityforms]`;
                window.send_to_editor(shortcode);
                $( '#gfat-shortcode-generator' ).hide();
            } else {
                alert( gfat_shortcode_generator.text.fill_out );
            }
        } );
    }
} )