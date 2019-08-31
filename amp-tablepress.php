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
 * Plugin Name: AMP TablePress
 * Description: Adding AMP compatibility on top of the TablePress plugin.
 * Plugin URI:  https://github.com/westonruter/amp-tablepress
 * Version:     0.1.3
 * Author:      Weston Ruter, Google
 * Author URI:  https://weston.ruter.net/
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace AMP_TablePress;

const PLUGIN_VERSION = '0.1.3';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const AMP_SCRIPT_REQUEST_QUERY_VAR = 'amp-tablepress-datatable-script';

const AMP_SCRIPT_REQUEST_HMAC_QUERY_VAR = 'amp-tablepress-datatable-script-hmac';

const STYLE_HANDLE = 'simple-datatables';

const SIMPLE_DATATABLES_PATH = 'node_modules/amp-script-simple-datatables';

/**
 * Check whether npm install has been performed.
 *
 * @return bool
 */
function has_installed_dependencies() {
	return ! DEVELOPMENT_MODE || file_exists( __DIR__ . '/' . SIMPLE_DATATABLES_PATH );
}

/**
 * Show error when needing to do npm install.
 */
function show_dependency_installation_admin_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'AMP TablePress', 'amp-tablepress' ); ?>:</strong>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s is the command to run */
					__( 'Unable to initialize plugin due to being installed from source. Please run %s.', 'syntax-highlighting-code-block' ),
					'<code>npm install</code>'
				)
			);
			?>
		</p>
	</div>
	<?php
}

if ( ! has_installed_dependencies() ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\show_dependency_installation_admin_notice' );
	return;
}

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
		plugin_dir_url( __FILE__ ) . SIMPLE_DATATABLES_PATH . '/dist/style.css',
		[],
		PLUGIN_VERSION
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
	$use_datatable = ( $render_options['use_datatables'] && $render_options['table_head'] && count( $table['data'] ) > 1 );
	if ( ! $use_datatable ) {
		return $render_options;
	}

	// Prevent enqueueing jQuery DataTables.
	$render_options['use_datatables'] = false;

	// Set flag for wrap_tablepress_table_output_with_amp_script(), as well as args for cache-busting.
	$render_options['use_simple_datatables'] = [
		'amp'     => is_amp(),
		'version' => PLUGIN_VERSION,
	];

	wp_enqueue_style( STYLE_HANDLE );

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
	if ( empty( $render_options['use_simple_datatables'] ) ) {
		return $output;
	}

	// @todo Apply filter to customize Simple-Datatables options.
	// @todo Hande: datatables_custom_commands.
	// Note that scrollx is not supported
	$render_options['simple_datatables'] = [
		'fixedColumns'  => true,
		'sortable'      => $render_options['datatables_sort'],
		'paging'        => $render_options['datatables_paginate'],
		'perPage'       => $render_options['datatables_paginate_entries'],
		'perPageSelect' => $render_options['datatables_lengthchange'] ? [ 10, 25, 50, 100 ] : false,
		'searchable'    => $render_options['datatables_filter'],
		'labels'        => [
			'placeholder' => __( 'Search...', 'amp-tablepress' ),
			'perPage'     => __( 'Show {select} entries', 'amp-tablepress' ),
			'noRows'      => __( 'No matching records found', 'amp-tablepress' ),
			'info'        => __( 'Showing {start} to {end} of {rows} entries', 'amp-tablepress' ),
		],
		'scrollY'       => $render_options['datatables_scrolly'],
		'layout'        => [
			'top'    => '{select}{search}',
			'bottom' => $render_options['datatables_info'] ? '{info}{pager}' : '{pager}',
		],
		// Note that entity decoding is needed due to WorkerDOM limitation: <https://github.com/ampproject/worker-dom/issues/613>.
		'prevText'      => html_entity_decode( '&lsaquo;', ENT_HTML5, 'UTF-8' ),
		'nextText'      => html_entity_decode( '&rsaquo;', ENT_HTML5, 'UTF-8' ),
		'ascText'       => html_entity_decode( '&#x25b2;', ENT_HTML5, 'UTF-8' ), // Also &blacktriangleup;.
		'descText'      => html_entity_decode( '&#x25bc;', ENT_HTML5, 'UTF-8' ), // Also &blacktriangledown;.
		'truncatePager' => false, // @todo Not supported as true.
		'firstLast'     => false, // @todo Not yet supported as true.
		'columns'       => false, // Disabled.
	];

	$wrapper_classes = [ 'dataTable-wrapper' ];
	if ( ! $render_options['table_head'] ) {
		$wrapper_classes[] = 'no-header';
	}
	if ( ! $render_options['table_foot'] ) {
		$wrapper_classes[] = 'no-footer';
	}
	if ( $render_options['simple_datatables']['sortable'] ) {
		$wrapper_classes[] = 'sortable';
	}
	if ( $render_options['simple_datatables']['searchable'] ) {
		$wrapper_classes[] = 'searchable';
	}
	if ( $render_options['simple_datatables']['fixedColumns'] ) {
		$wrapper_classes[] = 'fixed-columns';
	}

	$wrapper  = sprintf( '<div class="%s">', esc_attr( implode( ' ', $wrapper_classes ) ) );
	$wrapper .= sprintf( '<div class="dataTable-top">%s</div>', $render_options['simple_datatables']['layout']['top'] );
	$wrapper .= sprintf(
		'<div class="dataTable-container" %s>{table}</div>',
		$render_options['simple_datatables']['scrollY'] ? sprintf( ' style="%s"', esc_attr( 'overflow-y: auto; height:' . $render_options['simple_datatables']['scrollY'] ) ) : ''
	);
	$wrapper .= sprintf( '<div class="dataTable-bottom">%s</div>', $render_options['simple_datatables']['layout']['bottom'] );
	$wrapper .= '</div>';

	// Info placement.
	$info = '';
	if ( $render_options['simple_datatables']['paging'] ) {
		$info = $render_options['simple_datatables']['labels']['info'];
		$info = str_replace( '{start}', '1', $info );
		$info = str_replace( '{end}', $render_options['simple_datatables']['perPage'], $info );
		$info = str_replace( '{rows}', count( $table['data'] ) - 1, $info ); // The 1 is for the header row.
	}
	$wrapper = str_replace( '{info}', $info, $wrapper );

	// Per Page Select.
	if ( $render_options['simple_datatables']['paging'] && $render_options['simple_datatables']['perPageSelect'] ) {
		$dropdown = sprintf(
			'<div class="dataTable-dropdown"><label>%s</label></div>',
			esc_html( $render_options['simple_datatables']['labels']['perPage'] )
		);

		$select_dropdown = '<select class="dataTable-selector">';
		foreach ( $render_options['simple_datatables']['perPageSelect'] as $per_page ) {
			$select_dropdown .= sprintf(
				'<option %s>%s</option>',
				selected( $per_page, $render_options['simple_datatables']['perPage'], false ),
				esc_html( $per_page )
			);
		}
		$select_dropdown .= '</select>';

		$dropdown = str_replace( '{select}', $select_dropdown, $dropdown );
		$wrapper  = str_replace( '{select}', $dropdown, $wrapper );
	} else {
		$wrapper = str_replace( '{select}', '', $wrapper );
	}

	// Searchable.
	$search_input = '';
	if ( $render_options['simple_datatables']['searchable'] ) {
		$search_input = sprintf(
			'<div class="dataTable-search"><input class="dataTable-input" placeholder="%s" type="text"></div>',
			esc_attr( $render_options['simple_datatables']['labels']['placeholder'] )
		);
	}
	$wrapper = str_replace( '{search}', $search_input, $wrapper );

	$pagination = '<div class="dataTable-pagination"><ul>';
	$page_count = ceil( ( count( $table['data'] ) - 1 ) / $render_options['simple_datatables']['perPage'] ); // The 1 is for the header row.
	if ( $page_count > 1 ) {
		$pagination .= sprintf( '<li class="pager"><a role="button" tabindex="0" data-page="1">%s</a></li>', $render_options['simple_datatables']['prevText'] );
		for ( $i = 1; $i <= $page_count; $i++ ) {
			$pagination .= sprintf(
				'<li class="%s"></li><a role="button" tabindex="0" data-page="%d">%d</a></li>',
				esc_attr( 1 === $i ? 'active' : '' ),
				$i,
				$i
			);
		}
		$pagination .= sprintf( '<li class="pager"><a role="button" tabindex="0" data-page="%d">%s</a></li>', $page_count, $render_options['simple_datatables']['nextText'] );
	}
	$pagination .= '</ul></div>';

	$wrapper = str_replace( '{pager}', $pagination, $wrapper );

	$wrapper = str_replace(
		'{table}',
		prerender_table( $output, $table, $render_options ),
		$wrapper
	);

	// @todo Use inline script once validator is updated to allow it.
	if ( is_amp() ) {
		$output = sprintf(
			'<amp-script src="%s" sandbox="allow-forms">%s</amp-script>',
			esc_url( get_amp_script_src( $render_options['simple_datatables'], $render_options['html_id'] ) ),
			$wrapper
		);
	} else {
		$output = sprintf(
			'%s<script async src="%s"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$wrapper,
			esc_url( get_amp_script_src( $render_options['simple_datatables'], $render_options['html_id'] ) )
		);
	}

	return $output;
}
add_filter( 'tablepress_table_output', __NAMESPACE__ . '\wrap_tablepress_table_output_with_amp_script', 10, 3 );

/**
 * Filter the generated HTML code for table.
 *
 * @see DataTable.renderHeader()
 * @see DataTable.setColumns()
 *
 * @param string $table_html     The generated HTML for the table.
 * @param array  $table_data     The current table.
 * @param array  $render_options The render options for the table.
 * @return string Output.
 */
function prerender_table( $table_html, $table_data, $render_options ) {
	$dom = new \DOMDocument();

	$libxml_previous_state = libxml_use_internal_errors( true );
	$dom->loadHTML(
		sprintf(
			'<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=%s"></head><body>%s</body></html>',
			esc_attr( get_bloginfo( 'charset' ) ),
			$table_html
		)
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $libxml_previous_state );

	$xpath = new \DOMXPath( $dom );

	/**
	 * Elements.
	 *
	 * @var \DOMElement $table
	 * @var \DOMElement $th
	 * @var \DOMElement $tr
	 */
	$table = $dom->getElementsByTagName( 'table' )->item( 0 );
	$table->setAttribute( 'class', $table->getAttribute( 'class' ) . ' dataTable-table' );

	// Determine the widths of each column.
	$column_max_char_counts = array_fill( 0, count( $table_data['data'][0] ), 0 );
	foreach ( $table_data['data'] as $row_index => $data_row ) {
		foreach ( $data_row as $column_index => $cell ) {
			$column_max_char_counts[ $column_index ] = max(
				$column_max_char_counts[ $column_index ],
				max(
					array_map(
						function( $line ) {
							return strlen( trim( $line ) );
						},
						explode( "\n", $cell )
					)
				)
			);
		}
	}

	// Set the width of each column based on the contents of the cells for the column. This prevents columns from changing width while paginating.
	if ( $render_options['simple_datatables']['fixedColumns'] ) {
		$table_column_char_width = array_sum( $column_max_char_counts );
		foreach ( $xpath->query( '//table/thead/tr/th' ) as $i => $th ) {
			$width = ( $column_max_char_counts[ $i ] / $table_column_char_width ) * 100;
			$th->setAttribute( 'style', sprintf( 'width: %s%%', $width ) );
		}
	}

	// Add sortable attributes.
	if ( $render_options['simple_datatables']['sortable'] ) {
		foreach ( $xpath->query( '//table/thead/tr/th' ) as $th ) {
			$th->setAttribute( 'data-sortable', '' );

			$a = $dom->createElement( 'a' );
			$a->setAttribute( 'role', 'button' );
			$a->setAttribute( 'tabindex', '0' );
			$a->setAttribute( 'class', 'dataTable-sorter' );
			while ( $th->firstChild ) {
				$node = $th->removeChild( $th->firstChild );
				$a->appendChild( $node );
			}
			$th->appendChild( $a );
		}
	}

	// Hide all rows that are not on the first page.
	if ( $render_options['simple_datatables']['perPage'] && $render_options['simple_datatables']['paging'] ) {
		foreach ( $xpath->query( sprintf( '//table/tbody/tr[ position() >= %d ]', $render_options['simple_datatables']['perPage'] + 1 ) ) as $tr ) {
			$tr->setAttribute( 'hidden', '' );
		}
	}

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );

	$table_html = $dom->saveHTML( $body );

	return $table_html;
}

/**
 * Get URL for the amp-script with the options for a given table.
 *
 * @param array  $options Options.
 * @param string $html_id ID for table element.
 * @return string Script URL.
 */
function get_amp_script_src( $options, $html_id ) {
	$arg = wp_json_encode( compact( 'options', 'html_id' ) );

	return add_query_arg(
		[
			AMP_SCRIPT_REQUEST_QUERY_VAR      => rawurlencode( $arg ),
			AMP_SCRIPT_REQUEST_HMAC_QUERY_VAR => rawurlencode( wp_hash( $arg ) ),
		],
		home_url( '/' )
	);
}

/**
 * Handle amp-script request.
 */
function handle_amp_script_request() {
	if ( ! isset( $_GET[ AMP_SCRIPT_REQUEST_QUERY_VAR ] ) || ! isset( $_GET[ AMP_SCRIPT_REQUEST_HMAC_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	header( 'Content-Type: application/javascript; charset=utf-8' );

	$arg_json = wp_unslash( $_GET[ AMP_SCRIPT_REQUEST_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( wp_hash( $arg_json ) !== wp_unslash( $_GET[ AMP_SCRIPT_REQUEST_HMAC_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		die( 'console.error( "HMAC verification failed" );' );
	}

	$arg = json_decode( $arg_json, true );
	if ( ! isset( $arg['options'], $arg['html_id'] ) ) {
		die( 'console.error( "Missing required args." );' );
	}

	$error = null;
	if ( json_last_error() ) {
		$error = json_last_error_msg();
	} elseif ( ! is_array( $arg['options'] ) ) {
		$error = __( 'Query param is not a JSON array.', 'amp-tablepress' );
	}
	if ( $error ) {
		status_header( 400 );
		printf( 'console.error( %s );', wp_json_encode( $error ) );
		exit;
	}

	echo '(function() {';

	printf( 'const tableId = %s;', wp_json_encode( $arg['html_id'] ) );

	printf( 'const ampTablePressOptions = %s;', wp_json_encode( $arg['options'] ) );

	echo file_get_contents( __DIR__ . '/' . SIMPLE_DATATABLES_PATH . '/dist/umd/simple-datatables.js' ); // phpcs:ignore

	echo file_get_contents( __DIR__ . '/init.js' ); // phpcs:ignore

	echo '})();';

	exit;
}
add_action( 'parse_request', __NAMESPACE__ . '\handle_amp_script_request' );

/**
 * Prevent tree-shaking datatable.
 *
 * @link https://github.com/ampproject/amp-wp/pull/1478
 *
 * @param array $sanitizers Sanitizer args.
 * @return array Sanitizer args.
 */
function prevent_tree_shaking_datatable( $sanitizers ) {
	$sanitizers['AMP_Style_Sanitizer']['dynamic_element_selectors'] = array_merge(
		// In case another filter already defined dynamic_element_selectors.
		! empty( $sanitizers['AMP_Style_Sanitizer']['dynamic_element_selectors'] ) ? $sanitizers['AMP_Style_Sanitizer']['dynamic_element_selectors'] : [],
		// Duplicated from protected AMP_Style_Sanitizer::$DEFAULT_ARGS. Should be unnecessary as of <https://github.com/ampproject/amp-wp/pull/1478>.
		[
			'amp-list',
			'amp-live-list',
			'[submit-error]',
			'[submit-success]',
			'amp-script',
		],
		// What we're actually adding.
		[
			'.dataTable-wrapper',
			'.dataTable-pagination',
			'.dataTable-info',
		]
	);
	return $sanitizers;
}
add_filter( 'amp_content_sanitizers', __NAMESPACE__ . '\prevent_tree_shaking_datatable' );
