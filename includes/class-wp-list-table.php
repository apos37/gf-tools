<?php
/**
 * Make our own WP List Table
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_WP_List_Table extends WP_List_Table {

    // Default vars
    private $checkbox_name;
    private $checkbox_value;
    private $default_columns;
    private $data;
    private $per_page;
    private $total_count;

    /**
     * Construct
     *
     * @param array $columns
     * @param array $data
     */
    public function __construct( $columns, $data, $per_page = 20, $total_count = -1, $checkbox_name = 'post', $checkbox_value = 'ID' ) {
        if ( $checkbox_name && $checkbox_value ) {
            $columns = array_merge( [ 'cb' => '<input type="checkbox" />' ], $columns );
        }
        $this->checkbox_name = $checkbox_name;
        $this->checkbox_value = $checkbox_value;
        $this->default_columns = $columns;
        $this->data = $data;
        $this->per_page = $per_page;
        $this->total_count = $total_count;
        parent::__construct();
    } // End __construct()

    
    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return $this->default_columns;
    } // End get_columns()


    /**
     * Prepare the items accordingly
     *
     * @return void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        if ( isset( $this->data[0][ 'date' ] ) ) {
            usort( $this->data, function( $a, $b ) {
                $date_a = strtotime( $a[ 'date' ] );
                $date_b = strtotime( $b[ 'date' ] );
                return $date_b - $date_a;
            } );
        }
    
        $per_page = $this->per_page;
        $total_items = $this->total_count;
        $this->items = $this->data;

        $current_page = max( 1, $this->get_pagenum() );
        $total_pages = ceil( $total_items / $per_page );

        if ( $current_page > $total_pages ) {
            $current_page = $total_pages;
        }

        $pagination_args = [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ];
    
        $this->set_pagination_args( $pagination_args );
    } // End prepare_items()


    /**
     * Pagination
     *
     * @param [type] $which
     * @return void
     */
    protected function pagination( $which ) {
        if ( empty( $this->_pagination_args ) ) {
            return;
        }
    
        $total_items     = $this->_pagination_args['total_items'];
        $total_pages     = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }
    
        if ( 'top' === $which && $total_pages > 1 ) {
            $this->screen->render_screen_reader_content( 'heading_pagination' );
        }
    
        $output = '<span class="displaying-num">' . sprintf(
            /* translators: %s: Number of items. */
            _n( '%s item', '%s items', $total_items ),
            number_format_i18n( $total_items )
        ) . '</span>';
    
        $current              = $this->get_pagenum();
        $removable_query_args = wp_removable_query_args();
        $removable_query_args[] = 'delete';
    
        $current_url = (new GF_Advanced_Tools_Helpers)->get_current_url();
    
        $current_url = remove_query_arg( $removable_query_args, $current_url );
    
        $page_links = [];
    
        $total_pages_before = '<span class="paging-input">';
        $total_pages_after  = '</span></span>';
    
        $disable_first = false;
        $disable_last  = false;
        $disable_prev  = false;
        $disable_next  = false;
    
        if ( 1 === $current ) {
            $disable_first = true;
            $disable_prev  = true;
        }
        if ( $total_pages === $current ) {
            $disable_last = true;
            $disable_next = true;
        }
    
        if ( $disable_first ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='first-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( remove_query_arg( 'paged', $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'First page', 'gf-tools' ),
                '&laquo;'
            );
        }
    
        if ( $disable_prev ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='prev-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Previous page', 'gf-tools' ),
                '&lsaquo;'
            );
        }
    
        $html_current_page  = $current;
        $total_pages_before = sprintf(
            '<span class="screen-reader-text">%s</span>' .
            '<span id="table-paging" class="paging-input">' .
            '<span class="tablenav-paging-text">',
            /* translators: Hidden accessibility text. */
            __( 'Current Page', 'gf-tools' )
        );
    
        $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
    
        $page_links[] = $total_pages_before . sprintf(
            /* translators: 1: Current page, 2: Total pages. */
            _x( '%1$s of %2$s', 'gf-tools' ),
            $html_current_page,
            $html_total_pages
        ) . $total_pages_after;
    
        if ( $disable_next ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='next-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Next page', 'gf-tools' ),
                '&rsaquo;'
            );
        }
    
        if ( $disable_last ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='last-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Last page', 'gf-tools' ),
                '&raquo;'
            );
        }
    
        $pagination_links_class = 'pagination-links';
        if ( ! empty( $infinite_scroll ) ) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . implode( "\n", $page_links ) . '</span>';
    
        if ( $total_pages ) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";
    
        echo wp_kses_post( $this->_pagination );
    } // End pagination()


    /**
     * Default
     *
     * @param array $item
     * @param string $column_name
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        if ( isset( $item[ $column_name ] ) ) {
            $value = $item[ $column_name ];
        } else {
            $value = '--';
        }
        
        // Format dates
        if ( $column_name === 'date' ) {
            $value = gmdate( 'F j, Y g:i A T', strtotime( $value ) );
        }
        
        // Link the first col value
        $columns = $this->get_columns();
        $first_col = array_key_first( $columns );
        if ( isset( $item[ 'link' ] ) && $first_col == $column_name ) {
            $value = '<a href="'.$item[ 'link' ].'" target="_blank">'.$value.'</a>';
        }
    
        return $value;
    } // End column_default()


    /**
	 * Get value for checkbox column.
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->checkbox_name,
			wp_strip_all_tags( $item[ $this->checkbox_value ] )
		);
	} // End column_cb()


    /**
     * Make columns sortable
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return [];
    } // End get_sortable_columns()


    /**
     * Sort them
     *
     * @param array $a
     * @param array $b
     * @return array
     */
    protected function usort_recorder( $a, $b ) {
        return 0;
    } // End usort_recorder()


    /**
	 * Generates the table navigation above or below the table
	 *
	 * @param string $which
	 */
	protected function display_tablenav( $which ) {
		?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <?php if ( $this->has_items() ) { ?>
                <div class="alignleft actions bulkactions">
                    <?php $this->bulk_actions( $which ); ?>
                </div>
            <?php }
            $this->extra_tablenav( $which );
            $this->pagination( $which );
            ?>
            <br class="clear" />
        </div>
		<?php
	} // End display_tablenav()
}

