<?php
/**
 * Gravity Forms Error Log
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Logs {

	/**
	 * Store the settings here for the rest of the stuff
	 *
	 * @var array
	 */
	public $plugin_settings;
	public $form_settings;


	/**
	 * Form ID
	 *
	 * @var string
	 */
	public $form_id;


	/**
	 * Log levels used by Gravity Forms' bundled KLogger. Verified against
	 * includes/logging/logging.php and includes/logging/KLogger.php on GF 3.0.
	 * These are not part of GF's public API, so re-verify if GF ever updates
	 * its bundled KLogger version.
	 *
	 * @var int
	 */
	const LOG_LEVEL_DEBUG = 1;
	const LOG_LEVEL_INFO = 2;
	const LOG_LEVEL_WARN = 3;
	const LOG_LEVEL_ERROR = 4;
	const LOG_LEVEL_FATAL = 5;
	const LOG_LEVEL_VALIDATION = 100;


	/**
	 * Cron hook name for the daily auto-delete.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'gfat_logs_daily_cleanup';


	/**
	 * A single UUID generated once per PHP request, reused across every row
	 * inserted during that request so related log lines can be grouped.
	 *
	 * @var string
	 */
	private $request_id;


	/**
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings = false, $form_id = false ) {

		$this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
		$this->form_settings = isset( $form_settings ) ? $form_settings : [];
		$this->form_id = isset( $form_id ) ? $form_id : false;

		if ( !empty( $plugin_settings[ 'logs_enabled' ] ) ) {
			add_action( 'gform_pre_log_message', [ $this, 'capture_gf_log' ], 10, 4 );
		}

		if ( !empty( $plugin_settings[ 'logs_enabled' ] ) && !empty( $plugin_settings[ 'logs_validation_enabled' ] ) ) {
			add_filter( 'gform_validation', [ $this, 'log_validation_failures' ], 9999 );
		}

		add_action( self::CRON_HOOK, [ $this, 'run_cleanup' ] );

	} // End __construct()


	// # SCHEMA / LIFECYCLE ----------------------------------------------------------------------------------------


	/**
	 * Get the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'gfat_error_log';
	} // End table_name()


	/**
	 * Create the table. Call on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			logged_at DATETIME NOT NULL,
			request_id CHAR(36) NOT NULL,
			message_type SMALLINT UNSIGNED NOT NULL,
			message LONGTEXT NOT NULL,
			source VARCHAR(100) NOT NULL,
			source_type VARCHAR(10) NOT NULL,
			form_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			was_gf_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY request_id (request_id),
			KEY source_type (source_type),
			KEY form_id (form_id),
			KEY logged_at (logged_at),
			KEY message_type (message_type)
		) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		self::schedule_cleanup();
	} // End create_table()


	/**
	 * Drop the table. Call on plugin uninstall.
	 */
	public static function drop_table() {
		global $wpdb;
		$table_name = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore
	} // End drop_table()


	/**
	 * Register the daily cleanup cron event if not already scheduled.
	 */
	public static function schedule_cleanup() {
		if ( !wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	} // End schedule_cleanup()


	/**
	 * Unschedule the cleanup cron event. Call on plugin deactivation.
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	} // End unschedule_cleanup()


	/**
	 * Delete rows older than the configured retention period. Hooked to the daily cron.
	 */
	public function run_cleanup() {
		global $wpdb;

		$days = isset( $this->plugin_settings[ 'logs_auto_delete_days' ] ) ? absint( $this->plugin_settings[ 'logs_auto_delete_days' ] ) : 30;

		if ( $days < 1 ) {
			return;
		}

		$table_name = self::table_name();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE logged_at < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )", $days ) ); // phpcs:ignore
	} // End run_cleanup()


	// # CAPTURE -----------------------------------------------------------------------------------------------------


	/**
	 * Lazily generate and reuse a single UUID per PHP request.
	 *
	 * @return string
	 */
	private function get_request_id() {
		if ( !$this->request_id ) {
			$this->request_id = wp_generate_uuid4();
		}

		return $this->request_id;
	} // End get_request_id()


	/**
	 * Derive source_type from the plugin slug reported by GF.
	 *
	 * @param string $plugin
	 * @return string
	 */
	private function get_source_type( $plugin ) {
		if ( empty( $plugin ) ) {
			return 'manual';
		}

		if ( $plugin === 'gravityforms' ) {
			return 'core';
		}

		return 'addon';
	} // End get_source_type()


	/**
	 * Attempt to resolve the current form ID from common request contexts.
	 *
	 * @return int|null
	 */
	private function get_current_form_id() {
		if ( $this->form_id ) {
			return absint( $this->form_id );
		}

		if ( isset( $_POST[ 'gform_submit' ] ) ) { // phpcs:ignore
			return absint( $_POST[ 'gform_submit' ] ); // phpcs:ignore
		}

		if ( isset( $_REQUEST[ 'form_id' ] ) ) { // phpcs:ignore
			return absint( $_REQUEST[ 'form_id' ] ); // phpcs:ignore
		}

		return null;
	} // End get_current_form_id()


	/**
	 * Insert a row into the log table.
	 *
	 * @param int    $message_type One of the LOG_LEVEL_* constants.
	 * @param string $message
	 * @param string $source The plugin slug, or 'manual' if unresolvable.
	 * @param bool   $was_enabled Whether GF's native logging was enabled for this source.
	 */
	private function insert_row( $message_type, $message, $source, $was_enabled = false ) {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			[
				'logged_at'      => current_time( 'mysql', true ),
				'request_id'     => $this->get_request_id(),
				'message_type'   => $message_type,
				'message'        => $message,
				'source'         => $source,
				'source_type'    => $this->get_source_type( $source ),
				'form_id'        => $this->get_current_form_id(),
				'user_id'        => get_current_user_id(),
				'was_gf_enabled' => (int) $was_enabled,
			]
		);
	} // End insert_row()


	/**
	 * Hooked to gform_pre_log_message. Fires before GF writes to its own log file,
	 * regardless of whether GF's own logging is enabled for the plugin.
	 *
	 * @param string $plugin       The plugin slug.
	 * @param string $message      The log message.
	 * @param int    $message_type One of KLogger's DEBUG(1)/INFO(2)/WARN(3)/ERROR(4)/FATAL(5).
	 * @param bool   $enabled      Whether GF's native file logging is enabled for this plugin.
	 */
	public function capture_gf_log( $plugin, $message, $message_type, $enabled ) {
		if ( rgblank( $message ) ) {
			return;
		}
 
		if ( !empty( $this->plugin_settings[ 'logs_errors_only' ] ) && $message_type < self::LOG_LEVEL_ERROR ) {
			return;
		}
 
		if ( $this->message_is_excluded( $message ) ) {
			return;
		}
 
		try {
			$this->insert_row( $message_type, $message, $plugin, $enabled );
		} catch ( Exception $e ) {
			return;
		}
	} // End capture_gf_log()


    /**
	 * Check whether a message contains any of the configured excluded keywords.
	 *
	 * @param string $message
	 * @return bool
	 */
	private function message_is_excluded( $message ) {
		if ( empty( $this->plugin_settings[ 'logs_excluded_keywords' ] ) ) {
			return false;
		}
 
		$keywords = array_filter( array_map( 'trim', explode( ',', $this->plugin_settings[ 'logs_excluded_keywords' ] ) ) );
 
		foreach ( $keywords as $keyword ) {
			if ( stripos( $message, $keyword ) !== false ) {
				return true;
			}
		}
 
		return false;
	} // End message_is_excluded()


	/**
	 * Hooked to gform_validation at low priority so it runs after all other validation
	 * hooks, including add-ons like reCAPTCHA. Logs each field that failed validation,
	 * even when no validation_message was set by whatever caused the failure - this is
	 * the case that makes silent validation failures so hard to track down otherwise.
	 *
	 * @param array $validation_result
	 * @return array
	 */
	public function log_validation_failures( $validation_result ) {
		if ( !empty( $validation_result[ 'is_valid' ] ) ) {
			return $validation_result;
		}

		try {

			$form = $validation_result[ 'form' ];

			foreach ( $form[ 'fields' ] as $field ) {

				if ( empty( $field->failed_validation ) ) {
					continue;
				}

				$label = !empty( $field->label ) ? $field->label : __( 'Untitled Field', 'gf-tools' );
				$reason = !empty( $field->validation_message ) ? $field->validation_message : __( '(no message provided)', 'gf-tools' );
				$message = sprintf( '%s (%d): %s', $label, $field->id, $reason );

				$this->insert_row( self::LOG_LEVEL_VALIDATION, $message, 'gravityforms' );

			}

		} catch ( Exception $e ) {
			return $validation_result;
		}

		return $validation_result;
	} // End log_validation_failures()


	// # VIEWER ------------------------------------------------------------------------------------------------------


	/**
	 * Map of message_type integers to display labels.
	 *
	 * @param int $message_type
	 * @return string
	 */
	public static function get_level_label( $message_type ) {
		$levels = [
			self::LOG_LEVEL_DEBUG      => __( 'Debug', 'gf-tools' ),
			self::LOG_LEVEL_INFO       => __( 'Info', 'gf-tools' ),
			self::LOG_LEVEL_WARN       => __( 'Warn', 'gf-tools' ),
			self::LOG_LEVEL_ERROR      => __( 'Error', 'gf-tools' ),
			self::LOG_LEVEL_FATAL      => __( 'Fatal', 'gf-tools' ),
			self::LOG_LEVEL_VALIDATION => __( 'Validation', 'gf-tools' ),
		];

		return isset( $levels[ $message_type ] ) ? $levels[ $message_type ] : __( 'Unknown', 'gf-tools' );
	} // End get_level_label()


	/**
	 * Pull viewer args from $_GET / $_POST, sanitized.
	 *
	 * @return array
	 */
	public static function get_args_from_request() {
		return [
			'sort'     => isset( $_REQUEST[ 'sort' ] ) ? sanitize_key( $_REQUEST[ 'sort' ] ) : 'desc', // phpcs:ignore
			'per_page' => isset( $_REQUEST[ 'per_page' ] ) ? absint( $_REQUEST[ 'per_page' ] ) : 100, // phpcs:ignore
			'search'   => isset( $_REQUEST[ 'search' ] ) ? sanitize_text_field( $_REQUEST[ 'search' ] ) : '', // phpcs:ignore
			'exclude'  => isset( $_REQUEST[ 'exclude' ] ) ? sanitize_text_field( $_REQUEST[ 'exclude' ] ) : '', // phpcs:ignore
			'source'   => isset( $_REQUEST[ 'source' ] ) ? sanitize_text_field( $_REQUEST[ 'source' ] ) : '', // phpcs:ignore
			'form_id'  => isset( $_REQUEST[ 'form_id' ] ) ? absint( $_REQUEST[ 'form_id' ] ) : 0, // phpcs:ignore
		];
	} // End get_args_from_request()


	/**
	 * Build the WHERE clause and prepared params from the current filter/search state.
	 *
	 * @param array $args
	 * @return array [ string $sql, array $params ]
	 */
	private static function build_where( $args ) {
		global $wpdb;

		$where = [ '1=1' ];
		$params = [];

		if ( !empty( $args[ 'source' ] ) ) {
			$where[] = 'source = %s';
			$params[] = $args[ 'source' ];
		}

		if ( !empty( $args[ 'form_id' ] ) ) {
			$where[] = 'form_id = %d';
			$params[] = absint( $args[ 'form_id' ] );
		}

		if ( !empty( $args[ 'search' ] ) ) {
			$where[] = 'message LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args[ 'search' ] ) . '%';
		}

		if ( !empty( $args[ 'exclude' ] ) ) {
			$exclude_terms = array_filter( array_map( 'trim', explode( ',', $args[ 'exclude' ] ) ) );
			foreach ( $exclude_terms as $term ) {
				$where[] = 'message NOT LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $term ) . '%';
			}
		}

		$sql = implode( ' AND ', $where );

		return [ $sql, $params ];
	} // End build_where()


	/**
	 * Fetch rows for the viewer, applying filters, sort, and a per-page cap.
	 * When combine is true, the cap applies to request_id groups, not raw rows.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_rows( $args ) {
		global $wpdb;

		$table_name = self::table_name();

		list( $where_sql, $params ) = self::build_where( $args );

		$sort_dir = ( isset( $args[ 'sort' ] ) && $args[ 'sort' ] === 'asc' ) ? 'ASC' : 'DESC';
		$per_page = isset( $args[ 'per_page' ] ) ? absint( $args[ 'per_page' ] ) : 100;

		$group_sql = "SELECT DISTINCT request_id, MIN( logged_at ) AS first_logged_at FROM {$table_name} WHERE {$where_sql} GROUP BY request_id ORDER BY first_logged_at {$sort_dir} LIMIT %d"; // phpcs:ignore
		$group_params = array_merge( $params, [ $per_page ] );
		$request_ids = $wpdb->get_col( $wpdb->prepare( $group_sql, $group_params ) ); // phpcs:ignore

		if ( empty( $request_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%s' ) );
		$rows_sql = "SELECT * FROM {$table_name} WHERE request_id IN ( {$placeholders} ) ORDER BY id ASC"; // phpcs:ignore
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $request_ids ) ); // phpcs:ignore

		// MySQL does not preserve IN() order, so re-sort the flat rows to match
		// the already-correctly-ordered $request_ids list from the group query above.
		$rows_by_request = [];
		foreach ( $rows as $row ) {
			$rows_by_request[ $row->request_id ][] = $row;
		}

		$ordered_rows = [];
		foreach ( $request_ids as $request_id ) {
			if ( isset( $rows_by_request[ $request_id ] ) ) {
				foreach ( $rows_by_request[ $request_id ] as $row ) {
					$ordered_rows[] = $row;
				}
			}
		}

		return $ordered_rows;
	} // End get_rows()


	/**
	 * Group flat rows by request_id, preserving row order within each group.
	 *
	 * @param array $rows
	 * @return array
	 */
	private static function group_by_request( $rows ) {
		$groups = [];

		foreach ( $rows as $row ) {

			if ( !isset( $groups[ $row->request_id ] ) ) {
				$groups[ $row->request_id ] = [];
			}

			$groups[ $row->request_id ][] = $row;

		}

		return $groups;
	} // End group_by_request()


    /**
     * Get all plugins registered with GF's logging system, for the Source filter dropdown.
     *
     * @return array
     */
    private static function get_registered_log_sources() {
        return apply_filters( 'gform_logging_supported', [] );
    } // End get_registered_log_sources()


	/**
	 * Render the filter form, plus Download and Clear action forms.
	 *
	 * @param array $args
	 */
	private static function render_filters( $args ) {
		$sources = self::get_registered_log_sources();
		$form_ids = self::get_distinct_form_ids();
		$page = sanitize_text_field( $_GET[ 'page' ] ?? '' ); // phpcs:ignore
		?>
		<div id="gfat-log-toolbar">

			<form id="gfat-log-filters" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
				<input type="hidden" name="tab" value="logs">

				<select name="source">
					<option value=""><?php esc_html_e( 'All Sources', 'gf-tools' ); ?></option>
					<?php foreach ( $sources as $slug => $title ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $args[ 'source' ], $slug ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="form_id">
					<option value="0"><?php esc_html_e( 'All Forms', 'gf-tools' ); ?></option>
					<?php
					$form_titles = self::get_form_titles();
					foreach ( $form_ids as $form_id ) :
						$title = isset( $form_titles[ $form_id ] ) ? $form_titles[ $form_id ] : sprintf( __( 'Form #%d', 'gf-tools' ), $form_id );
						?>
						<option value="<?php echo esc_attr( $form_id ); ?>" <?php selected( $args[ 'form_id' ], $form_id ); ?>><?php echo esc_html( sprintf( '#%d: %s', $form_id, $title ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="sort">
					<option value="desc" <?php selected( $args[ 'sort' ], 'desc' ); ?>><?php esc_html_e( 'Most Recent on Top', 'gf-tools' ); ?></option>
					<option value="asc" <?php selected( $args[ 'sort' ], 'asc' ); ?>><?php esc_html_e( 'Most Recent on Bottom', 'gf-tools' ); ?></option>
				</select>

				<select name="per_page">
					<?php foreach ( [ 25, 50, 100, 200, 500 ] as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $args[ 'per_page' ], $option ); ?>><?php echo esc_html( sprintf( __( 'Last %d', 'gf-tools' ), $option ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="text" name="search" value="<?php echo esc_attr( $args[ 'search' ] ); ?>" placeholder="<?php esc_attr_e( 'Search logs...', 'gf-tools' ); ?>">

				<input type="text" name="exclude" value="<?php echo esc_attr( $args[ 'exclude' ] ); ?>" placeholder="<?php esc_attr_e( 'Exclude keywords, comma separated...', 'gf-tools' ); ?>">

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'gf-tools' ); ?></button>
			</form>

			<div id="gfat-log-actions">

				<form method="post">
					<?php wp_nonce_field( 'gfat_log_action', 'gfat_log_nonce' ); ?>
					<input type="hidden" name="gfat_log_action" value="download_log">
					<button type="submit" class="button"><?php esc_html_e( 'Download Log', 'gf-tools' ); ?></button>
				</form>

				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Clear these log entries? This cannot be undone.', 'gf-tools' ) ); ?>');">
					<?php wp_nonce_field( 'gfat_log_action', 'gfat_log_nonce' ); ?>
					<input type="hidden" name="gfat_log_action" value="clear_log">
					<button type="submit" class="button gfat-caution"><?php esc_html_e( 'Clear Log', 'gf-tools' ); ?></button>
				</form>

			</div>

		</div>
		<?php
	} // End render_filters()


	/**
	 * Render the Easy Reader view: one collapsible block per request_id.
	 *
	 * @param array $rows
	 * @param string $search
	 */
	public static function render_easy( $rows, $search = '' ) {
		if ( empty( $rows ) ) {
			?>
			<p><?php esc_html_e( 'No log entries found.', 'gf-tools' ); ?></p>
			<?php
			return;
		}

		$groups = self::group_by_request( $rows );
		$form_titles = self::get_form_titles();
		?>
		<div id="gfat-log-easy">
			<?php foreach ( $groups as $request_id => $group_rows ) :
				$first = $group_rows[ 0 ];
				$form_label = '';
				if ( $first->form_id ) {
					$title = isset( $form_titles[ $first->form_id ] ) ? $form_titles[ $first->form_id ] : __( 'Unknown Form', 'gf-tools' );
					$form_label = sprintf( ' &mdash; Form #%d: %s', $first->form_id, $title );
				}
				?>
				<details class="gfat-log-group">
					<summary><?php echo esc_html( $first->logged_at ); ?> &mdash; <?php echo esc_html( $first->source ); ?><?php echo esc_html( $form_label ); ?> (<?php echo count( $group_rows ); ?>)</summary>
					<div class="gfat-log-group-body">
						<?php foreach ( $group_rows as $row ) :
							$label = self::get_level_label( $row->message_type );
							?>
							<div class="gfat-log-line gfat-level-<?php echo esc_attr( strtolower( $label ) ); ?>">
								<span class="gfat-log-time"><?php echo esc_html( $row->logged_at ); ?></span>
								<span class="gfat-log-label">(<?php echo esc_html( $row->source_type ); ?> / <?php echo esc_html( $row->source ); ?>)</span>
								<?php echo wp_kses( self::highlight_term( $row->message, $search ), [ 'i' => [ 'class' => [] ] ] ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	} // End render_easy()


	/**
	 * Escape a message and wrap any occurrence of the search term in a highlight tag.
	 *
	 * @param string $message
	 * @param string $search
	 * @return string
	 */
	private static function highlight_term( $message, $search ) {
		$escaped = esc_html( $message );

		if ( $search === '' ) {
			return $escaped;
		}

		$pattern = '/' . preg_quote( esc_html( $search ), '/' ) . '/i';

		return preg_replace_callback( $pattern, function( $matches ) {
			return '<i class="gfat-highlight-search">' . $matches[ 0 ] . '</i>';
		}, $escaped );
	} // End highlight_term()


	/**
	 * Get form_id => title map for all forms, for header labels and the filter dropdown.
	 *
	 * @return array
	 */
	private static function get_form_titles() {
		$forms = GFAPI::get_forms( null );
		$titles = [];

		foreach ( $forms as $form ) {
			$titles[ $form[ 'id' ] ] = $form[ 'title' ];
		}

		return $titles;
	} // End get_form_titles()


	/**
	 * Render the full Logs tab content: filters, actions, and the chosen view.
	 * Called from GF_Advanced_Tools_Dashboard::logs().
	 */
	public static function render_tab() {
		if ( isset( $_POST[ 'gfat_log_action' ] ) && check_admin_referer( 'gfat_log_action', 'gfat_log_nonce' ) ) { // phpcs:ignore
			self::handle_action();
		}

		$args = self::get_args_from_request();
		$rows = self::get_rows( $args );
		?>
		<div id="gfat-logs-wrap">
			<?php
			self::render_filters( $args );
			self::render_easy( $rows, $args[ 'search' ] );
			?>
		</div>
		<?php
	} // End render_tab()


	/**
	 * Handle the Download and Clear form submissions.
	 */
	private static function handle_action() {
		$action = sanitize_key( $_POST[ 'gfat_log_action' ] ); // phpcs:ignore
		$args = self::get_args_from_request();

		if ( $action === 'download_log' ) {
			self::stream_download( $args );
		}

		if ( $action === 'clear_log' ) {
			self::clear_rows( $args );
		}
	} // End handle_action()


	/**
	 * Build a flat text export from filtered rows and stream it as a download.
	 *
	 * @param array $args
	 */
	private static function stream_download( $args ) {
		$args[ 'per_page' ] = 100000;

		$rows = self::get_rows( $args );

		$filename = 'gf-error-log-' . gmdate( 'Y-m-d-His' ) . '.txt';

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		foreach ( $rows as $row ) {

			$label = self::get_level_label( $row->message_type );

			printf(
				"[%s] [%s / %s] (%s) %s\n",
				$row->logged_at,
				$row->source_type,
				$row->source,
				$label,
				$row->message
			);

		}

		exit;
	} // End stream_download()


	/**
	 * Delete rows matching the current filter scope.
	 *
	 * @param array $args
	 * @return int|false
	 */
	private static function clear_rows( $args ) {
		global $wpdb;

		$table_name = self::table_name();

		list( $where_sql, $params ) = self::build_where( $args );

		$sql = "DELETE FROM {$table_name} WHERE {$where_sql}"; // phpcs:ignore

		if ( empty( $params ) ) {
			return $wpdb->query( $sql ); // phpcs:ignore
		}

		return $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
	} // End clear_rows()


	/**
	 * Get the distinct list of form IDs present in the log, for the Forms filter dropdown.
	 *
	 * @return array
	 */
	private static function get_distinct_form_ids() {
		global $wpdb;

		$table_name = self::table_name();

		return $wpdb->get_col( "SELECT DISTINCT form_id FROM {$table_name} WHERE form_id IS NOT NULL ORDER BY form_id ASC" ); // phpcs:ignore
	} // End get_distinct_form_ids()

} // End class GF_Advanced_Tools_Logs