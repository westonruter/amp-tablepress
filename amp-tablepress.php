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
			// @todo For some reason datatables_info is always false.
			'bottom' => $render_options['datatables_info'] || true ? '{info}{pager}' : '{pager}',
		],
	];

	$wrapper_classes = [ 'dataTable-wrapper' ];
	if ( ! $render_options['table_head'] ) {
		$wrapper_classes[] = 'no-header';
	}
	if ( ! $render_options['table_foot'] ) {
		$wrapper_classes[] = 'no-footer';
	}
	if ( $simple_datatables_options['sortable'] ) {
		$wrapper_classes[] = 'sortable';
	}
	if ( $simple_datatables_options['searchable'] ) {
		$wrapper_classes[] = 'searchable';
	}
	if ( $simple_datatables_options['fixedColumns'] ) {
		$wrapper_classes[] = 'fixedColumns';
	}

	$wrapper  = sprintf( '<div class="%s">', esc_attr( implode( ' ', $wrapper_classes ) ) );
	$wrapper .= sprintf( '<div class="dataTable-top">%s</div>', $simple_datatables_options['layout']['top'] );
	$wrapper .= sprintf(
		'<div class="dataTable-container" %s>{table}</div>',
		$simple_datatables_options['scrollY'] ? sprintf( ' style="%s"', esc_attr( 'overflow-y: auto; height:' . $simple_datatables_options['scrollY'] ) ) : ''
	);
	$wrapper .= sprintf( '<div class="dataTable-bottom">%s</div>', $simple_datatables_options['layout']['bottom'] );
	$wrapper .= '</div>';

	// Info placement.
	$info = '';
	if ( $simple_datatables_options['paging'] ) {
		$info = $simple_datatables_options['labels']['info'];
		$info = str_replace( '{start}', '1', $info );
		$info = str_replace( '{end}', $simple_datatables_options['perPage'], $info );
		// @todo Count may need to be minus 1 depending on header.
		$info = str_replace( '{rows}', count( $table['data'] ), $info );
	}
	$wrapper = str_replace( '{info}', $info, $wrapper );

	// Per Page Select.
	if ( $simple_datatables_options['paging'] && $simple_datatables_options['perPageSelect'] ) {
		$dropdown = sprintf(
			'<div class="dataTable-dropdown"><label>%s</label></div>',
			esc_html( $simple_datatables_options['labels']['perPage'] )
		);

		$select_dropdown = '<select class="dataTable-selector">';
		foreach ( $simple_datatables_options['perPageSelect'] as $per_page ) {
			$select_dropdown .= sprintf(
				'<option %s>%s</option>',
				selected( $per_page, $simple_datatables_options['perPage'], false ),
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
	if ( $simple_datatables_options['searchable'] ) {
		$search_input = sprintf(
			'<div class="dataTable-search"><input class="dataTable-input" placeholder="%s" type="text"></div>',
			esc_attr( $simple_datatables_options['labels']['placeholder'] )
		);
	}
	$wrapper = str_replace( '{search}', $search_input, $wrapper );

	$pagination = '<div class="dataTable-pagination"><ul>';
	$page_count = ceil( count( $table['data'] ) / $simple_datatables_options['perPage'] );
	if ( $page_count > 1 ) {
		$pagination .= '<a role="button" tabindex="0" data-page="1">‹</a>';
		for ( $i = 1; $i <= $page_count; $i++ ) {
			$pagination .= sprintf( '<a role="button" tabindex="0" data-page="%d">%d</a>', $i, $i );
		}
		$pagination .= sprintf( '<a role="button" tabindex="0" data-page="%d">›</a>', $page_count );
	}
	$pagination .= '</ul></div>';

	$wrapper = str_replace( '{pager}', $pagination, $wrapper );
	$wrapper = str_replace( '{table}', $output, $wrapper );

	$output = sprintf(
		'<amp-script src="%s" sandbox="allow-forms">%s</amp-script>',
		esc_url( get_amp_script_src( $simple_datatables_options ) ),
		$wrapper
	);

	wp_enqueue_style( STYLE_HANDLE );

	return $output;
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
