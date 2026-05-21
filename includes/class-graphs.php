<?php
/**
 * Graphs class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Graphs {


    /**
     * Default Colors
     *
     * @var array
     */
    public function get_colors() {
        return apply_filters( 'gfat_graph_colors', [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796',
            '#5a5c69', '#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#e02d1b',
            '#6c757d', '#4066d4', '#15ad77', '#2ba5b5', '#e5a50a', '#d12516',
            '#6610f2', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#0dcaf0',
            '#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14',
            '#ffc107', '#28a745', '#20c997', '#17a2b8', '#adb5bd', '#343a40'
        ] );
    } // End get_colors()


    /**
     * Helper to render graph title
     *
     * @param string $title
     * @return string
     */
    private function get_title_html( $title ) {
        if ( empty( $title ) ) return '';
        return '<div class="gfat-graph-title-wrap">
            <h3 class="gfat-graph-title">' . esc_html( $title ) . '</h3>
            <span class="title-separator separator-border theme-color-bg"></span>
        </div>';
    } // End get_title_html()


    /**
     * Render Bar Chart
     *
     * @param array $data
     * @param string $title
     * @return string
     */
    public function render_bar( $data, $title = '' ) {
        $html = '<div class="gfat-graph-container gfat-bar-chart">';
        $html .= $this->get_title_html( $title );

        $k = 1;
        foreach ( $data as $row ) {
            $html .= '<div class="gfat-bar-row">
                <div class="gfat-bar-info">
                    <span>' . esc_html( $row[ 'label' ] ) . ' (' . intval( $row[ 'count' ] ) . ')</span>
                    <span>' . round( $row[ 'perc' ], 1 ) . '%</span>
                </div>
                <div class="gfat-bar-track">
                    <div class="gfat-bar-fill gfat-graph-color-' . $k . '" style="background:' . $row[ 'color' ] . '; width:' . $row[ 'perc' ] . '%;"></div>
                </div>
            </div>';
            $k++;
        }
        $html .= '</div>';
        return $html;
    } // End render_bar()


    /**
     * Render Pie Chart
     *
     * @param array $data
     * @param string $title
     * @return string
     */
    public function render_pie( $data, $title = '' ) {
        $gradient_parts = [];
        $current_perc = 0;

        foreach ( $data as $row ) {
            $next_perc = $current_perc + $row[ 'perc' ];
            // Fix for floating point precision in CSS gradients
            $next_perc = ( $next_perc > 100 ) ? 100 : $next_perc;
            $gradient_parts[] = "{$row[ 'color' ]} {$current_perc}% {$next_perc}%";
            $current_perc = $next_perc;
        }

        $html = '<div class="gfat-graph-container gfat-pie-wrapper">
            ' . $this->get_title_html( $title ) . '
            <div class="gfat-pie-circle" style="background: conic-gradient(' . implode( ', ', $gradient_parts ) . ');"></div>';
        
            $html .= '<ul class="gfat-graph-legend">';

            $k = 1;
            foreach ( $data as $row ) {
                $html .= '<li class="gfat-legend-item">
                    <span class="gfat-legend-swatch gfat-graph-color-' . $k . '" style="background:' . $row[ 'color' ] . ';"></span>
                    ' . esc_html( $row[ 'label' ] ) . ': <strong>' . round( $row[ 'perc' ], 1 ) . '%</strong> (' . $row[ 'count' ] . ')
                </li>';
                $k++;
            }
            $html .= '</ul>
        </div>';

        return $html;
    } // End render_pie()


    /**
     * Helper to render Line/Spline charts via SVG
     * 
     * @param array $data
     * @param string $title
     * @param bool $curved
     * @return string
     */
    private function render_svg_path( $data, $title = '', $curved = false ) {
        if ( empty( $data ) ) return '';

        $width  = 500;
        $height = 200;
        $padding = 20;
        
        $count = count( $data );
        $x_interval = ( $width - ( $padding * 2 ) ) / ( $count > 1 ? $count - 1 : 1 );
        
        $points = [];
        foreach ( $data as $i => $row ) {
            $x = $padding + ( $i * $x_interval );
            // SVG coordinates Y=0 is top, so we subtract from height
            $y = ( $height - $padding ) - ( ( $row[ 'perc' ] / 100 ) * ( $height - ( $padding * 2 ) ) );
            $points[] = [ 'x' => $x, 'y' => $y ];
        }

        $path_data = "M " . $points[ 0 ][ 'x' ] . "," . $points[ 0 ][ 'y' ];

        if ( $curved && $count > 1 ) {
            for ( $i = 0; $i < $count - 1; $i++ ) {
                $p0 = $points[ $i ];
                $p1 = $points[ $i + 1 ];
                $cp1x = $p0[ 'x' ] + ( $p1[ 'x' ] - $p0[ 'x' ] ) / 2;
                $path_data .= " C $cp1x,{$p0[ 'y' ]} $cp1x,{$p1[ 'y' ]} {$p1[ 'x' ]},{$p1[ 'y' ]}";
            }
        } else {
            foreach ( $points as $i => $p ) {
                if ( $i === 0 ) continue;
                $path_data .= " L " . $p[ 'x' ] . "," . $p[ 'y' ];
            }
        }

        $html = '<div class="gfat-graph-container gfat-svg-wrapper">';
        $html .= $this->get_title_html( $title );
        $html .= '<svg viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="xMidYMid meet" style="width:100%; height:auto; overflow:visible;">';
        
        // Path
        $html .= '<path d="' . $path_data . '" fill="none" stroke="' . $data[ 0 ][ 'color' ] . '" stroke-width="3" stroke-linecap="round" />';
        
        // Dots
        foreach ( $points as $i => $p ) {
            $html .= '<circle cx="' . $p[ 'x' ] . '" cy="' . $p[ 'y' ] . '" r="4" fill="#fff" stroke="' . $data[ 0 ][ 'color' ] . '" stroke-width="2" />';
        }
        
        $html .= '</svg>';
        
        // Legend
        $html .= '<ul class="gfat-graph-legend gfat-inline-legend">';
        
        foreach ( $data as $row ) {
            $html .= '<li class="gfat-legend-item">
                <span class="gfat-legend-swatch" style="background:' . $row[ 'color' ] . ';"></span>
                ' . esc_html( $row[ 'label' ] ) . ': ' . round( $row[ 'perc' ], 1 ) . '%
            </li>';
        }
        $html .= '</ul></div>';

        return $html;
    } // End render_svg_path()


    /**
     * Render Line Chart
     *
     * @param array $data
     * @param string $title
     * @return string
     */
    public function render_line( $data, $title = '' ) {
        return $this->render_svg_path( $data, $title, false );
    } // End render_line()


    /**
     * Render Spline Chart
     *
     * @param array $data
     * @param string $title
     * @return string
     */
    public function render_spline( $data, $title = '' ) {
        return $this->render_svg_path( $data, $title, true );
    } // End render_spline()
    
}