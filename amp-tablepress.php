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
		'ascText'       => html_entity_decode( '&blacktriangleup;', ENT_HTML5, 'UTF-8' ),
		'descText'      => html_entity_decode( '&blacktriangledown;', ENT_HTML5, 'UTF-8' ),
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

	$output = sprintf(
		'<amp-script src="%s" sandbox="allow-forms">%s</amp-script>',
		esc_url( get_amp_script_src( $render_options['simple_datatables'] ) ),
		$wrapper
	);

	wp_enqueue_style( STYLE_HANDLE );

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
