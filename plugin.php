<?php
/*
	Plugin Name: Category in Permalink
	Description: Overwrite the default category that gets included in the post permalink.
	Tags: category, categories, posts, admin, seo
	Plugin URI: https://github.com/kasparsd/category-in-permalink
	GitHub URI: https://github.com/kasparsd/category-in-permalink
	Author: Kaspars Dambis
	Author URI: http://kaspars.net
	Version: 0.1.1
	Tested up to: 4.0
	License: GPL2
	Text Domain: category-in-permalink
*/


CategoryInPermalink::instance();


class CategoryInPermalink {

	private $cache = array();

	
	public static function instance() {

		static $instance;

		if ( ! $instance )
			$instance = new self();

		return $instance;

	}


	private function __construct() {

		$permalink_structure = get_option('permalink_structure');

		// Make sure we have the category placeholder in the permalink structure
		if ( stripos( $permalink_structure, '%category%' ) == false )
			return;

		// Overwrite the default category selection in post permalinks
		add_filter( 'post_link_category', array( $this, 'use_custom_category' ), 10, 3 );

		add_action( 'add_meta_boxes', array( $this, 'admin_metabox' ) );

		add_action( 'save_post', array( $this, 'admin_metabox_save' ) );

		add_action( 'plugins_loaded', array( $this, 'init_l10n' ) );

	}


	function init_l10n() {

		load_plugin_textdomain( 'category-in-permalink', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}


	function admin_metabox() {

		add_meta_box(
			'category-in-permalink',
			__( 'Category in Permalink', 'category-in-permalink' ),
			array( $this, 'admin_metabox_render' ),
			'post',
			'side'
		);

	}


	function admin_metabox_render( $post ) {

		$post_terms = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );

		if ( empty( $post_terms ) ) {
			
			printf( 
				'<p>%s</p>',
				__( 'No categories selected.', 'category-in-permalink' )
			);

			return;

		} elseif ( sizeof( $post_terms ) == 1 ) {

			printf( 
				'<p>%s</p>',
				__( 'Only one category is currently selected which will be used in the permalink.', 'category-in-permalink' )
			);

			return;

		}

		$primary_cat = get_post_meta( $post->ID, 'category_in_permalink', true );

		$options = array( sprintf( 
				'<option value="">%s</option>',
				__( 'Default Category', 'category-in-permalink' )
			) );

		foreach ( $post_terms as $post_term )
			$options[] = sprintf( 
					'<option value="%d" %s>%s</option>',
					$post_term->term_id,
					selected( $post_term->term_id, $primary_cat, false ),
					esc_html( $post_term->name )
				);

		printf(
			'<select name="category_in_permalink">%s</select>',
			implode( '', $options )
		);

	}


	function admin_metabox_save( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		if ( isset( $_POST['category_in_permalink'] ) && ! empty( $_POST['category_in_permalink'] ) )
			update_post_meta( $post_id, 'category_in_permalink', intval( $_POST['category_in_permalink'] ) );

	}


	function use_custom_category( $cat, $cats, $post ) {

		if ( sizeof( $cats ) < 2 )
			return $cat;

		// Get it from cache maybe
		if ( isset( $this->cache[ $post->ID ] ) )
			return $this->cache[ $post->ID ];

		$terms = array();

		foreach ( $cats as $cat_item )
			$terms[ $cat_item->term_id ] = $cat_item;

		$primary_cat = get_post_meta( $post->ID, 'category_in_permalink', true );

		// Make sure that it is one of the categories assigned to the post
		if ( ! empty( $primary_cat ) && isset( $terms[ $primary_cat ] ) ) {
			
			// Store this in our object cache
			$this->cache[ $post->ID ] = $terms[ $primary_cat ];

			return $terms[ $primary_cat ];

		}

		return $cat;

	}


}

