<?php
/**
 * Plugin Name: AMP TablePress
 *
 * @package   AMP_TablePress
 * @author    Weston Ruter, Google
 * @license   GPL-2.0-or-later
 * @copyright 2019 Google Inc.
 *
 * @wordpress-plugin
 * Plugin Name: TablePress AMPified
 * Description: Adding AMP compatibility on top of the TablePress plugin.
 * Plugin URI:  ...
 * Version:     0.1.0
 * Author:      Weston Ruter, Google
 * Author URI:  https://weston.ruter.net/
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace AMP_TablePress;

const AMP_SCRIPT_REQUEST_QUERY_VAR = 'amp-tablepress-datatable-script';

const STYLE_HANDLE = 'simple-datatables';

/**
 * Determines whether response is an AMP page.
 *
 * @return bool Whether AMP response.
 */
function is_amp() {
	return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
}

/**
 * Register style.
 *
 * @param \WP_Styles $styles Styles.
 */
function register_style( \WP_Styles $styles ) {
	$styles->add(
		STYLE_HANDLE,
		plugin_dir_url( __FILE__ ) . 'Simple-DataTables/dist/style.css',
		[],
		'0.1'
	);
}
add_action( 'wp_default_styles', __NAMESPACE__ . '\register_style' );

/**
 * Filter the generated HTML code for table.
 *
 * @param array $render_options The render options for the table.
 * @param array $table          The current table.
 * @return array Options.
 */
function filter_tablepress_table_render_options( $render_options, $table ) {
	$render_options['datatables_info'] = false;

	if ( is_amp() && $render_options['use_datatables'] && $render_options['table_head'] && count( $table['data'] ) > 1 ) {

		// Prevent enqueueing jQuery DataTables.
		$render_options['use_datatables'] = false;

		// Set flag for wrap_tablepress_table_output_with_amp_script().
		$render_options['use_amp_script_datatables'] = true;
	}
	return $render_options;
}
add_filter( 'tablepress_table_render_options', __NAMESPACE__ . '\filter_tablepress_table_render_options', 1000, 3 );

/**
 * Filter the generated HTML code for table.
 *
 * @param string $output         The generated HTML for the table.
 * @param array  $table          The current table.
 * @param array  $render_options The render options for the table.
 * @return string Output.
 */
function wrap_tablepress_table_output_with_amp_script( $output, $table, $render_options ) {
	if ( ! is_amp() || empty( $render_options['use_amp_script_datatables'] ) ) {
		return $output;
	}

	// @todo Apply filter to customize Simple-Datatables options.
	// @todo Hande: datatables_custom_commands.
	// Note that scrollx is not supported
	$simple_datatables_options = [
		'sortable'      => $render_options['datatables_sort'],
		'paging'        => $render_options['datatables_paginate'],
		'perPage'       => $render_options['datatables_paginate_entries'],
		'perPageSelect' => $render_options['datatables_lengthchange'] ? [ 10, 25, 50, 100 ] : false,
		'searchable'    => $render_options['datatables_filter'],
		'labels'        => [
			'perPage' => __( 'Show {select} entries', 'amp-tablepress' ),
			'noRows'  => __( 'No matching records found', 'amp-tablepress' ),
			'info'    => __( 'Showing {start} to {end} of {rows} entries', 'amp-tablepress' ),
		],
		'scrollY'       => $render_options['datatables_scrolly'],
		'layout'        => [
			'top'    => '{select}{search}',
			'bottom' => $render_options['datatables_info'] ? '{info}{pager}' : '{pager}',
		],
	];

	$before = sprintf( '<amp-script src="%s" sandbox="allow-forms">', esc_url( get_amp_script_src( $simple_datatables_options ) ) );

	$before .= "<div class=\"dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns\">
	<div class=\"dataTable-top\">
		<div class=\"dataTable-dropdown\">
			<label>
				<select class=\"dataTable-selector\"><option value=\"5\">5</option><option value=\"10\" selected=\"\">10</option><option value=\"15\">15</option><option value=\"20\">20</option><option value=\"25\">25</option></select> entries per page
			</label>
		</div>
		<div class=\"dataTable-search\"><input class=\"dataTable-input\" placeholder=\"Search...\" type=\"text\"></div>
	</div>
	<div class=\"dataTable-container\">";

	$after = "	</div>
	<div class=\"dataTable-bottom\">
		<div class=\"dataTable-info\">Showing 1 to 10 of 99 entries</div>
		<div class=\"dataTable-pagination\">
			<li class=\"pager\"><a href=\"#\" data-page=\"1\">‹</a></li>
			<li class=\"active\"><a href=\"#\" data-page=\"1\">1</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"2\">2</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"3\">3</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"4\">4</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"5\">5</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"6\">6</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"7\">7</a></li>
			<li class=\"ellipsis\"><a href=\"#\">…</a></li>
			<li class=\"\"><a href=\"#\" data-page=\"10\">10</a></li>
			<li class=\"pager\"><a href=\"#\" data-page=\"2\">›</a></li>
		</div>
	</div>
</div>";

	$after .= '</amp-script>';

	wp_enqueue_style( STYLE_HANDLE );

	return $before . $output . $after;
}
add_filter( 'tablepress_table_output', __NAMESPACE__ . '\wrap_tablepress_table_output_with_amp_script', 10, 3 );

/**
 * Get URL for the amp-script with the options for a given table.
 *
 * @param array $options Options.
 * @return string Script URL.
 */
function get_amp_script_src( $options ) {
	return add_query_arg(
		AMP_SCRIPT_REQUEST_QUERY_VAR,
		rawurlencode( wp_json_encode( $options ) ),
		home_url( '/' )
	);
}

/**
 * Handle amp-script request.
 */
function handle_amp_script_request() {
	if ( ! isset( $_GET[ AMP_SCRIPT_REQUEST_QUERY_VAR ] ) ) {
		return;
	}

	header( 'Content-Type: text/javascript; charset=utf-8' );

	// @todo Sanitize?
	$options = json_decode( wp_unslash( $_GET[ AMP_SCRIPT_REQUEST_QUERY_VAR ] ), true );

	$error = null;
	if ( json_last_error() ) {
		$error = json_last_error_msg();
	} elseif ( ! is_array( $options ) ) {
		$error = __( 'Query param is not a JSON array.', 'amp-tablepress' );
	}
	if ( $error ) {
		status_header( 400 );
		printf( 'console.error( %s );', wp_json_encode( $error ) );
		exit;
	}

	printf( 'const ampTablePressOptions = %s;', wp_json_encode( $options ) );

	echo file_get_contents( __DIR__ . '/Simple-DataTables/dist/umd/simple-datatables.js' );

	echo file_get_contents( __DIR__ . '/tablepress.js' );

	exit;
}
add_action( 'parse_request', __NAMESPACE__ . '\handle_amp_script_request' );
