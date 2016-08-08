<?php
/*
Plugin Name: Vox Usini
Plugin URI: https://github.com/medfreeman/wp-voxusini
Description: Provides an interface for maintaining voxusini monthly pdf issue
Version: 1.1.1
Author: Mehdi Lahlou
Author URI: https://github.com/medfreeman
Author Email: mehdi.lahlou@free.fr
License: GPLv3

  Copyright 2014-2016 Mehdi Lahlou (mehdi.lahlou@free.fr)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

require_once 'tgmpa/class-tgm-plugin-activation.php';

class Vox {

	const POST_TYPE = 'vox';
	const LANG_PREFIX = 'vox';
	const FIELDS_PREFIX = 'vox_';
	const YEAR_FIELD = self::FIELDS_PREFIX . 'year';
	const MONTH_FIELD = self::FIELDS_PREFIX . 'month';
	const PDF_FIELD = self::FIELDS_PREFIX . 'pdf';
	const ADMIN_YEAR_FILTER = 'filter_year';
	const AJAX_ACTION = 'get_vox_months_selectbox';
	const AJAX_NONCE = 'vox-ajax-nonce';
	const AJAX_NONCE_FIELD = 'voxNonce';
	const YEAR_START = 1995;
	const YEAR_QUERY_VAR = 'vox_year';
	const YEAR_REWRITE_TAG = 'annee';
	const IMAGE_SIZE_THUMB = 'vox-thumb';
	const IMAGE_SIZE_THUMB_2X = 'vox-thumb-2x';
	const ADMIN_COLUMN_FEATURED = 'featured';
	const ADMIN_COLUMN_PDF = 'pdf';
	const YEARS_ARRAY_CACHE_KEY = 'vox_years_array';
	const YEAR_LOWEST_CACHE_KEY = 'vox_year_lowest';
	const YEAR_HIGHEST_CACHE_KEY = 'vox_year_highest';
	const USINE_TGMPA_ID = 'usine.ch';

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// TGM Plugin activation library - plugin dependencies
		add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );

		// Register post type
		add_action( 'init', array( $this, 'register_post_type_vox' ) );
		// meta boxes plugin
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_meta_boxes' ) );
		// Add pdf thumbnail format
		add_action( 'init', array( $this, 'thumbnail_sizes' ) );

		// Register admin styles and scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_register_scripts' ) );

		// Add featured image and pdf column to admin vox list
		add_filter( 'manage_posts_columns', array( $this, 'admin_add_featured_image_column' ) );
		add_filter( 'manage_posts_custom_column', array( $this, 'admin_manage_featured_image_column' ), 10, 2 );
		add_filter( 'manage_posts_columns', array( $this, 'admin_add_pdf_column' ) );
		add_filter( 'manage_posts_custom_column', array( $this, 'admin_manage_pdf_column' ), 10, 2 );

		// Remove months filter dropdown from admin vox list
		add_filter( 'disable_months_dropdown', '__return_true', self::POST_TYPE );
		// Add year filter to admin vox list
		add_action( 'restrict_manage_posts', array( $this, 'admin_filter_list_by_year' ), self::POST_TYPE );
		add_filter( 'parse_query', array( $this, 'admin_filter_year_add_meta_query' ) );

		// Register admin ajax request
		add_action( 'wp_ajax_' . self::AJAX_ACTION , array( $this, 'ajax_get_months_select_options' ) ); // for logged in user

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_redirect', array( $this, 'return_file_http' ) );
		add_action( 'pre_get_posts', array( $this, 'alter_vox_archive_query' ) );

		add_action( 'updated_post_meta', array( $this, 'admin_clear_year_cache_keys' ), 10, 4 );
	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {
		$this->register_post_type_vox();
		flush_rewrite_rules();
	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {
		flush_rewrite_rules();
	} // end deactivate

	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {

		load_plugin_textdomain( self::LANG_PREFIX, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	} // end plugin_textdomain

	/**
	* Register the required plugins for this theme.
	*
	* In this example, we register five plugins:
	* - one included with the TGMPA library
	* - two from an external source, one from an arbitrary source, one from a GitHub repository
	* - two from the .org repo, where one demonstrates the use of the `is_callable` argument
	*
	* The variables passed to the `tgmpa()` function should be:
	* - an array of plugin arrays;
	* - optionally a configuration array.
	* If you are not changing anything in the configuration array, you can remove the array and remove the
	* variable from the function call: `tgmpa( $plugins );`.
	* In that case, the TGMPA default settings will be used.
	*
	* This function is hooked into `tgmpa_register`, which is fired on the WP `init` action on priority 10.
	*/
	public function register_required_plugins() {
		/*
		 * Array of plugin arrays. Required keys are name and slug.
		 * If the source is NOT from the .org repo, then source is also required.
		 */
		$plugins = array(
				// This is an example of how to include a plugin from the WordPress Plugin Repository.
			array(
				'name'      => 'Meta Box',
				'slug'      => 'meta-box',
				'required'  => true,
			),
		);
			/*
		 * Array of configuration settings. Amend each line as needed.
		 *
		 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
		 * strings available, please help us make TGMPA even better by giving us access to these translations or by
		 * sending in a pull-request with .po file(s) with the translations.
		 *
		 * Only uncomment the strings in the config array if you want to customize the strings.
		 */
		$config = array(
			'id'           => self::USINE_TGMPA_ID,                 // Unique ID for hashing notices for multiple instances of TGMPA.
			'menu'         => 'tgmpa-install-plugins', // Menu slug.
			'parent_slug'  => 'plugins.php',            // Parent menu slug.
			'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the 		arent menu used.
			'has_notices'  => true,                    // Show admin notices or not.
			'dismissable'  => false,                    // If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => false,                   // Automatically activate plugins after installation or not.
			'message'      => '',                      // Message to output right before the plugins table.
		);

		tgmpa( $plugins, $config );
	}

	public function register_post_type_vox() {
		define( 'EP_VOX', 8388608 );

		register_post_type( self::POST_TYPE, array(
			'label' => __( 'Vox', self::LANG_PREFIX ),
			'singular_label' => __( 'Vox', self::LANG_PREFIX ),
			'labels' => array( 'add_new_item' => __( 'Ajouter un Vox', self::LANG_PREFIX ) ),
			'public' => true,
			'show_ui' => true,
			'menu_position' => 26,
			'menu_icon' => plugins_url( 'images/icons/vox.png', __FILE__ ),
			'query_var' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => array( 'title', 'thumbnail' ),
			'rewrite' => array( 'slug' => self::POST_TYPE, 'with_front' => false, 'ep_mask' => EP_VOX ),
			'has_archive' => self::POST_TYPE,
			'labels' => array(
				'archives' => __( 'les voxs', self::LANG_PREFIX ),
			),
		));
		// Then you can endpoint rewrite rules to this endpoint mask
		global $wp_rewrite;

		add_rewrite_endpoint( 'cover', EP_VOX );
		add_rewrite_rule( '^' . self::POST_TYPE . '/' . self::YEAR_REWRITE_TAG . '/([0-9]{4})/?', 'index.php?post_type=' . self::POST_TYPE . '&' . self::YEAR_QUERY_VAR . '=$matches[1]', 'top' );
	} // end register_post_type_vox

	/**
	 * Registers meta-box plugin
	 */
	public function register_meta_boxes( $meta_boxes ) {

		$meta_boxes[] = array(
			'title' => esc_html__( 'Date du vox', self::LANG_PREFIX ),
			'post_types' => 'vox',
			'fields' => array(
				array(
					'name'        => '',
					'id'          => self::MONTH_FIELD,
					'type'        => 'select',
					// Array of 'value' => 'Label' pairs for select box
					'options'     => self::get_months_array(),
					// Select multiple values, optional. Default is false.
					'multiple'    => false,
					'std'         => self::get_month_default(),
					'placeholder' => esc_html__( 'Mois', self::LANG_PREFIX ),
				),
				array(
					'name'        => '',
					'id'          => self::YEAR_FIELD,
					'type'        => 'select',
					// Array of 'value' => 'Label' pairs for select box
					'options'     => self::get_years_array(),
					// Select multiple values, optional. Default is false.
					'multiple'    => false,
					'std'         => self::get_year_default(),
					'placeholder' => esc_html__( 'Année', self::LANG_PREFIX ),
				),
			),
		);

		$meta_boxes[] = array(
			'title' => esc_html__( 'Joindre un pdf', self::LANG_PREFIX ),
			'post_types' => 'vox',
			'fields' => array(
				array(
					'name'             => '',
					'id'               => self::PDF_FIELD,
					'type'             => 'file_advanced',
					'max_file_uploads' => 1,
					'mime_type'        => 'application/pdf', // Leave blank for all file types
				),
			),
		);

		return $meta_boxes;
	} // end register_meta_boxes

	/**
	 * Custom thumbnail size for vox thumbnails in archive page
	 */
	public function thumbnail_sizes() {
		add_image_size( self::IMAGE_SIZE_THUMB, 125, 180, false );
		add_image_size( self::IMAGE_SIZE_THUMB_2X, 250, 360, false );
	} // end pdf_thumbnail_size

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function admin_register_scripts() {
		$screen = get_current_screen();
		if ( 'vox' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_script( 'admin-selectboxes', plugins_url( 'js/admin/vendor/jquery.selectboxes.pack.js', __FILE__ ), array( 'jquery' ), false, true );
		wp_enqueue_script( 'admin-vox', plugins_url( 'js/admin/vox.js', __FILE__ ), array( 'jquery', 'admin-selectboxes' ), false, true );

		global $post;
		$ajax_nonce = wp_create_nonce( self::AJAX_NONCE );
		wp_localize_script( 'admin-vox', 'wpvox', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action' => self::AJAX_ACTION,
			'nonce' => $ajax_nonce,
			'nonceField' => self::AJAX_NONCE_FIELD,
			'yearField' => self::YEAR_FIELD,
			'monthField' => self::MONTH_FIELD,
			'postId' => $post->ID,
		));
	} // end register_admin_scripts

	/**
	 * Adds a featured image column to vox admin list
	 */
	public function admin_add_featured_image_column( $columns ) {
		$columns[ self::ADMIN_COLUMN_FEATURED ] = __( 'Image à la une', self::LANG_PREFIX );
		return $columns;
	} // end admin_add_featured_image

	/**
	 * Renders the featured image column in vox admin list
	 */
	public function admin_manage_featured_image_column( $column_name, $post_id ) {
		if ( self::ADMIN_COLUMN_FEATURED === $column_name ) {
			echo get_the_post_thumbnail( $post_id, self::IMAGE_SIZE_THUMB );
		}
	} // end admin_manage_featured_image

	/**
	 * Adds a pdf column to vox admin list
	 */
	public function admin_add_pdf_column( $columns ) {
		$columns[ self::ADMIN_COLUMN_PDF ] = __( 'Fichier pdf joint', self::LANG_PREFIX );
		return $columns;
	} // end admin_add_featured_image

	/**
	 * Renders the pdf column in vox admin list
	 */
	public function admin_manage_pdf_column( $column_name, $post_id ) {
		if ( self::ADMIN_COLUMN_PDF === $column_name ) {
			$pdf_attachment_id = get_post_meta( $post_id, self::PDF_FIELD, true );
			if ( ! $pdf_attachment_id ) {
				return;
			}
			$pdf_url = wp_get_attachment_url( $pdf_attachment_id );
			if ( ! $pdf_url ) {
				return;
			}
			echo '<p><a href="' . esc_url( $pdf_url ) . '" target="_blank"><img src="' . esc_url( plugins_url( 'images/icons/vox_big.png', __FILE__ ) ) . '"></a></p>';
		}
	} // end admin_manage_featured_image

	/**
	 * Adds a filter by year dropdown in admin vox list
	 */
	public function admin_filter_list_by_year() {
		$first_year = self::get_year_post_highest();
		if ( ! $first_year ) {
			return;
		}
		$last_year = self::get_year_post_lowest();
		if ( $first_year === $last_year ) {
			return;
		}

		echo '<select name="' . esc_attr( self::ADMIN_YEAR_FILTER ) . '">';

		$years_array = self::get_years_post_array();
		$selected_year = isset( $_GET[ self::ADMIN_YEAR_FILTER ] ) ? absint( $_GET[ self::ADMIN_YEAR_FILTER ] ) : 0; // input var ok.

		$selected = '';
		if ( 0 === $selected_year ) {
			$selected = ' selected="selected"';
		}
		echo '<option value="' . esc_attr( 0 ) . '"' . esc_html( $selected ) . '>' . esc_html( __( 'Année du vox', self::LANG_PREFIX ) ) .'</option>';
		foreach ( $years_array as $year ) {
			$selected = '';
			if ( $selected_year === $year ) {
				$selected = ' selected="selected"';
			}
			echo '<option value="' . esc_attr( $year ) . '"' . esc_html( $selected ) . '>' . esc_html( $year ) .'</option>';
		}

		echo '</select>';
	} // end filter_list_by_year

	/**
	 * Parse filter by year in admin vox list request
	 * Add query var
	 */
	public function admin_filter_year_add_meta_query( $query ) {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( 'vox' !== $screen->post_type || 'edit' !== $screen->base || ! $query->is_main_query() ) {
			return;
		}

		$first_year = self::get_year_post_highest();
		if ( ! $first_year ) {
			return;
		}

		$query->set( 'orderby', array(
			'year_clause' => 'DESC',
			'month_clause' => 'DESC',
		));

		$query->set( 'meta_query',
			array(
				'relation' => 'AND',
				'year_clause' => array(
					'key' => self::YEAR_FIELD,
					'type' => 'NUMERIC',
				),
				'month_clause' => array(
					'key' => self::MONTH_FIELD,
					'type' => 'NUMERIC',
				),
			)
		);

		$current_year = isset( $_GET[ self::ADMIN_YEAR_FILTER ] ) ? absint( $_GET[ self::ADMIN_YEAR_FILTER ] ) : 0; // input var ok.

		if ( 0 === $current_year ) {
			return;
		}

		$query->set( 'meta_query', array_merge_recursive( $query->query_vars['meta_query'],
			array(
				'year_clause' => array(
					'value' => $current_year,
				),
			)
		));

		$query->set( 'posts_per_page', 12 );
	} // end admin_filter_year_add_meta_query

	/**
	 * ajax get months select options
	 */
	public function ajax_get_months_select_options() {
		check_ajax_referer( self::AJAX_NONCE, self::AJAX_NONCE_FIELD );
		$year = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0; // Input var okay.
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0; // Input var okay.

		$html = self::get_months_available_select_options( $year, $post_id );

		header( 'Content-type: application/json' );
		echo json_encode( array( 'html' => $html ) );
		exit;
	} // end ajax_get_months_select_options

	/**
	 * register cover query var
	 */
	public function query_vars( $qv ) {
		$qv[] = 'cover';
		$qv[] = self::YEAR_QUERY_VAR;
		return $qv;
	} // end query_vars

	/**
	 * return pdf file
	 */
	public function return_file_http() {
		global $wp_query;
		if ( is_singular( 'vox' ) ) {
			global $post;

			$pdf_attachment_mode = false;

			if ( isset( $wp_query->query_vars['cover'] ) ) {
				$attachment_id = get_post_thumbnail_id( $post->ID );
			} else {
				$attachment_id = get_post_meta( $post->ID, self::PDF_FIELD, true );
				$pdf_attachment_mode = true;
			}

			$guid = wp_get_attachment_url( $attachment_id );

			/** Get the file name of the attachment to output in the header */
			$file_name = basename( get_attached_file( $attachment_id ) );

			/** Get the location of the file on the server */
			$file_location = ABSPATH . substr( $guid, strpos( $guid, 'wp-content' ) );

			if ( $pdf_attachment_mode ) {
				header( 'Content-type: application/pdf' );
				header( 'Content-Disposition: attachment; filename=' . $file_name );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				header( 'Content-Length: ' . filesize( $file_location ) );
			} else {
				/** Get the file Mime Type */
				$finfo = new finfo;
				$mime_type = $finfo->file( $file_location, FILEINFO_MIME );

				header( 'Content-type: ' . $mime_type, true, 200 );
			}

			readfile( $file_location );
			exit();
		}
	} // end return_file_http

	/**
	 * alter vox archive by year
	 */
	public function alter_vox_archive_query( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_archive() && isset( $query->query_vars['post_type'] ) && 'vox' === $query->query_vars['post_type'] ) {

			$first_year = self::get_year_post_highest();
			if ( ! $first_year ) {
				return;
			}

			$last_year = self::get_year_post_lowest();

			$query->set( 'max_num_pages', ceil( $first_year - $last_year + 1 ) );

			$query->set( 'posts_per_page', -1 );
			$query->set( 'orderby', array(
				'year_clause' => 'DESC',
				'month_clause' => 'ASC',
			));

			if ( isset( $query->query_vars[ self::YEAR_QUERY_VAR ] ) && ( absint( $query->query_vars[ self::YEAR_QUERY_VAR ] ) > $first_year || absint( $query->query_vars[ self::YEAR_QUERY_VAR ] ) < $last_year ) ) {
				$query->is_404 = true;
				return;
			}

			$year = isset( $query->query_vars[ self::YEAR_QUERY_VAR ] ) ? absint( $query->query_vars[ self::YEAR_QUERY_VAR ] ) : $first_year;

			$query->set( 'meta_query',
				array(
					'relation' => 'AND',
					'year_clause' => array(
						'key' => self::YEAR_FIELD,
						'value' => $year,
						'type' => 'NUMERIC',
					),
					'month_clause' => array(
						'key' => self::MONTH_FIELD,
						'type' => 'NUMERIC',
					),
				)
			);
		}
	} // end alter_vox_archive_query

	public function admin_clear_year_cache_keys( $meta_id, $object_id, $meta_key, $_meta_value ) {
		if ( self::YEAR_FIELD !== $meta_key ) {
			return;
		}
		wp_cache_delete( self::YEAR_LOWEST_CACHE_KEY );
		wp_cache_delete( self::YEAR_HIGHEST_CACHE_KEY );
		wp_cache_delete( self::YEARS_ARRAY_CACHE_KEY );
	}

	/**
	 * Get highest year in a post - int
	 */
	public static function get_year_post_highest() {
		$highest_year = wp_cache_get( self::YEAR_HIGHEST_CACHE_KEY );

		if ( ! $highest_year ) {
			$vox_highest_year_query = new WP_Query( array(
				'post_type'      => 'vox',
				'meta_key'       => self::YEAR_FIELD,
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'cache_results'  => true,
			) );
			if ( ! $vox_highest_year_query->have_posts() ) {
				return false;
			}
			$vox_highest_year_query->next_post();
			$highest_year = absint( get_post_meta( $vox_highest_year_query->post->ID, 'vox_year', true ) );

			wp_cache_set( self::YEAR_HIGHEST_CACHE_KEY, $highest_year );
		}

		return $highest_year;
	} // end get_year_post_highest

	/**
	 * Get lowest year in a post - int
	 */
	public static function get_year_post_lowest() {
		$lowest_year = wp_cache_get( self::YEAR_LOWEST_CACHE_KEY );

		if ( ! $lowest_year ) {
			$vox_lowest_year_query = new WP_Query( array(
				'post_type'      => self::POST_TYPE,
				'meta_key'       => self::YEAR_FIELD,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'cache_results'  => true,
			) );
			if ( ! $vox_lowest_year_query->have_posts() ) {
				return false;
			}
			$vox_lowest_year_query->next_post();
			$lowest_year = absint( get_post_meta( $vox_lowest_year_query->post->ID, 'vox_year', true ) );

			wp_cache_set( self::YEAR_LOWEST_CACHE_KEY, $lowest_year );
		}

		return $lowest_year;
	} // end get_year_post_lowest

	/**
	 * Get list of months for select - array
	 */
	private static function get_months_array() {
		$month_names = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$month_names[ $i ] = ucwords( date_i18n( 'F',mktime( 0, 0, 0, $i, 1, 2012 ) ) );
		}

		return $month_names;
	} // end get_month_array

	/**
	 * Get default month - string
	 */
	private static function get_month_default() {
		$current_month = date( 'm' );
		if ( 12 === absint( $current_month ) ) {
			return '1';
		}
		return ( absint( $current_month ) + 1 ) . '';
	} // end get_month_default

	/**
	 * Get list of years for select - array
	 */
	private static function get_years_array() {
		$current_year = absint( date( 'Y' ) );
		$years = array();
		for ( $y = self::YEAR_START; $y <= $current_year + 1; $y++ ) {
			$years[ $y ] = $y . '';
		}

		return $years;
	} // end get_years_array

	/**
	 * Get default year - string
	 */
	private static function get_year_default() {
		$current_year = date( 'Y' );
		$current_month = date( 'm' );
		if ( 12 === absint( $current_month ) ) {
			return date( 'Y', strtotime( '+1 year' ) );
		}
		return $current_year;
	} // end get_year_default

	/**
	 * Get all unique years of voxs - array
	 */
	private static function get_years_post_array() {
		$years_array = wp_cache_get( self::YEARS_ARRAY_CACHE_KEY );

		if ( ! $years_array ) {
			$args = array(
				'post_type'      => self::POST_TYPE,
				'meta_key'       => self::YEAR_FIELD,
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'cache_results'  => true,
			);
			$vox_all_query = new WP_Query( $args );

			$years_array = array();
			while ( $vox_all_query->have_posts() ) {
				$vox_all_query->next_post();
				array_push( $years_array, absint( get_post_meta( $vox_all_query->post->ID, 'vox_year', true ) ) );
			}
			$years_array = array_unique( $years_array );
			wp_cache_set( self::YEARS_ARRAY_CACHE_KEY, $years_array );
		}

		return $years_array;
	} // end get_years_post_array

	/**
	 * Get available months select box - html
	 */
	private static function get_months_available_select_options( $year, $post_id = false ) {
		$months = self::get_months_available( $year, $post_id );

		$post_month = false;
		if ( $post_id ) {
			$post_month = absint( get_post_meta( $post_id, self::MONTH_FIELD, true ) );
		}

		$html = '';

		foreach ( $months as $month => $month_name ) {
			$selected = '';
			if ( $post_month === $month ) {
				$selected = ' selected="selected"';
			}
			$html .= '<option value="' . $month . '"' . $selected . '>' . $month_name . '</option>';
		}

		return $html;
	} // end get_months_available_select_options

	/**
	 * get months available in chosen year - array
	 */
	private static function get_months_available( $year, $post_id = false ) {
		$args = array(
			'post_type' => self::POST_TYPE,
			'meta_query' => array(
				array(
					'key'       => self::YEAR_FIELD,
					'value'     => $year,
					'compare'   => '=',
					'type'      => 'NUMERIC',
				),
			),
			'post_status' => 'publish',
			'posts_per_page' => 12,
			'no_found_rows' => true,
			'cache_results'  => true,
		);

		if ( $post_id ) {
			$args['post__not_in'] = array( $post_id );
		}

		$voxs = new WP_Query( $args );

		$months = self::get_months_array();
		while ( $voxs->have_posts() ) {
			$voxs->next_post();
			$month = absint( get_post_meta( $voxs->post->ID, self::MONTH_FIELD, true ) );
			unset( $months[ $month ] );
		}

		return $months;
	} // end get_months_available

	/**
	 * Get previous voxs link - html
	 */
	public static function get_previous_voxs_link( $label = null, $class = null ) {
		global $wp_query;

		$first_year = self::get_year_post_highest();
		if ( ! $first_year ) {
			return;
		}

		$year = isset( $wp_query->query_vars[ self::YEAR_QUERY_VAR ] ) ? absint( $wp_query->query_vars[ self::YEAR_QUERY_VAR ] ) : $first_year;

		$prevyear = $year + 1;

		if ( null === $label ) {
			$label = __( 'Next Year &raquo;' );
		}

		$class = null === $class ? '' : 'class="' . $class . '" ';

		if ( ! is_single() && ( $prevyear <= $first_year ) ) {
			return '<a ' . $class . 'href="' . self::get_yearnum_link( $prevyear ) . '">' . preg_replace( '/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label ) . '</a>';
		}
	} // end get_previous_voxs_link

	/**
	 * Echo previous voxs link - html
	 */
	public static function previous_voxs_link( $label = null, $class = null ) {
		echo wp_kses( self::get_previous_voxs_link( $label, $class ), array( 'a' => array( 'href' => array(), 'class' => array() ) ) );
	} // end previous_voxs_link

	/**
	 * Get next voxs link - html
	 */
	public static function get_next_voxs_link( $label = null, $class = null ) {
		global $wp_query;

		$first_year = self::get_year_post_highest();
		if ( ! $first_year ) {
			return;
		}

		$last_year = self::get_year_post_lowest();

		$year = isset( $wp_query->query_vars[ self::YEAR_QUERY_VAR ] ) ? absint( $wp_query->query_vars[ self::YEAR_QUERY_VAR ] ) : $first_year;

		$nextyear = $year - 1;

		if ( null === $label ) {
			$label = __( 'Next Year &raquo;' );
		}

		$class = null === $class ? '' : 'class="' . $class . '" ';

		if ( ! is_single() && ( $nextyear >= $last_year ) ) {
			return '<a ' . $class . 'href="' . self::get_yearnum_link( $nextyear ) . '">' . preg_replace( '/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label ) . '</a>';
		}
	} // end get_next_voxs_link

	/**
	 * Echo next voxs link - html
	 */
	public static function next_voxs_link( $label = null, $class = null ) {
		echo wp_kses( self::get_next_voxs_link( $label, $class ), array( 'a' => array( 'href' => array(), 'class' => array() ) ) );
	} // end next_voxs_link

	/**
	 * Get voxs archive page link corresponding to specific year - html
	 */
	public static function get_yearnum_link( $yearnum = 1, $escape = true ) {
		global $wp_rewrite;

		$yearnum = (int) $yearnum;

		$first_year = self::get_year_post_highest();

		$request = remove_query_arg( self::YEAR_QUERY_VAR );

		$home_root = wp_parse_url( home_url() );
		$home_root = ( isset( $home_root['path'] ) ) ? $home_root['path'] : '';
		$home_root = preg_quote( $home_root, '|' );

		$request = preg_replace( '|^'. $home_root . '|i', '', $request );
		$request = preg_replace( '|^/+|', '', $request );

		if ( ! $wp_rewrite->using_permalinks() || is_admin() ) {
			$base = trailingslashit( get_bloginfo( 'url' ) );

			if ( $yearnum > 1 ) {
				$result = add_query_arg( self::YEAR_QUERY_VAR, $yearnum, $base . $request );
			} else {
				$result = $base . $request;
			}
		} else {
			$qs_regex = '|\?.*?$|';
			preg_match( $qs_regex, $request, $qs_match );

			if ( ! empty( $qs_match[0] ) ) {
					$query_string = $qs_match[0];
					$request = preg_replace( $qs_regex, '', $request );
			} else {
					$query_string = '';
			}

			$request = preg_replace( '|' . self::YEAR_REWRITE_TAG . '/\d+/?$|', '', $request );
			$request = preg_replace( '|^' . preg_quote( $wp_rewrite->index, '|' ) . '|i', '', $request );
			$request = ltrim( $request, '/' );

			$base = trailingslashit( get_bloginfo( 'url' ) );

			if ( $wp_rewrite->using_index_permalinks() && ( $yearnum < $first_year || '' !== $request ) ) {
					$base .= $wp_rewrite->index . '/';
			}
			if ( $yearnum < $first_year ) {
					$request = ( ( ! empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( self::YEAR_REWRITE_TAG . '/' . $yearnum, self::YEAR_QUERY_VAR );
			}

			$result = $base . $request . $query_string;
		}

		if ( $escape ) {
			return esc_url( $result );
		} else {
			return esc_url_raw( $result );
		}
	} // end get_yearnum_link
} // end class

$vox = new Vox();

if ( ! function_exists( 'vox_pagination' ) ) {

	function vox_pagination( $before = '', $after = '' ) {
		global $wp_query;

		$first_year = Vox::get_year_post_highest();
		if ( ! $first_year ) {
			return;
		}
		$last_year = Vox::get_year_post_lowest();

		$year = isset( $wp_query->query_vars[ Vox::YEAR_QUERY_VAR ] ) && absint( $wp_query->query_vars[ Vox::YEAR_QUERY_VAR ] ) <= $first_year && absint( $wp_query->query_vars[ Vox::YEAR_QUERY_VAR ] ) >= $last_year ? absint( $wp_query->query_vars[ Vox::YEAR_QUERY_VAR ] ) : $first_year;

		$paged = $first_year - $year + 1;

		$max_page = isset( $wp_query->query_vars['max_num_pages'] ) ? absint( $wp_query->query_vars['max_num_pages'] ) : 1;
		if ( 1 === $max_page ) {
			return;
		}

		$pages_to_show = 7;
		$pages_to_show_minus_1 = $pages_to_show - 1;
		$half_page_start = floor( $pages_to_show_minus_1 / 2 );
		$half_page_end = ceil( $pages_to_show_minus_1 / 2 );
		$start_page = $paged - $half_page_start;
		if ( $start_page <= 0 ) {
			$start_page = 1;
		}
		$end_page = $paged + $half_page_end;
		if ( ( $end_page - $start_page ) !== $pages_to_show_minus_1 ) {
			$end_page = $start_page + $pages_to_show_minus_1;
		}
		if ( $end_page > $max_page ) {
			$start_page = $max_page - $pages_to_show_minus_1;
			$end_page = $max_page;
		}
		if ( $start_page <= 0 ) {
			$start_page = 1;
		}

		$first_year = Vox::get_year_post_highest();

		echo esc_html( $before ) . '<ul class="pagination--vox pagination__list clearfix">';

		if ( $start_page > 1 ) {
			echo '<li class="pagination__item first"><a class="pagination__link" href="' . esc_url( Vox::get_yearnum_link( $first_year ) ) . '" title="First">' . esc_html( '&laquo;' ) . '</a></li>';
		}

		if ( $paged > 1 ) {
			echo '<li class="pagination__item previous">';
			Vox::previous_voxs_link( '&lsaquo;', 'pagination__link' );
			echo '</li>';
		}

		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			$year = $first_year + 1 - $i;
			if ( absint( $i ) === $paged ) {
				echo '<li class="pagination__item current">' . esc_html( $year ) . '</li>';
			} else {
				echo '<li class="pagination__item"><a class="pagination__link" href="' . esc_url( Vox::get_yearnum_link( $year ) ) . '">' . esc_html( $year ) . '</a></li>';
			}
		}

		if ( $paged < $max_page ) {
			echo '<li class="pagination__item next">';
			Vox::next_voxs_link( '&rsaquo;', 'pagination__link' );
			echo '</li>';
		}

		if ( $end_page < $max_page ) {
			echo '<li class="pagination__item last"><a class="pagination__link" href="' . esc_url( Vox::get_yearnum_link( $last_year ) ) . '" title="Last">' . esc_html( '&raquo;' ) . '</a></li>';
		}
		echo '</ul>' . esc_html( $after ) . '';
	}
}

if ( ! function_exists( 'vox_is_paged' ) ) {

	function vox_is_paged() {
		global $wp_query;

		$first_year = Vox::get_year_post_highest();
		if ( isset( $wp_query->query_vars['vox_year'] ) && $first_year !== $wp_query->query_vars['vox_year'] ) {
			return true;
		}
		return false;
	}
}

include_once 'widget/vox-widget.class.php';
