<?php
/**
 *
 * Plugin Name: Estimated Reading Time
 * Plugin URI: https://github.com/Abdoo-mayhob/Estimated-Reading-Time
 * Description: Calculate and Show Estimated Reading Time in your posts in Both Arabic and English.
 * Version: 1.0.0
 * Author: Abdoo
 * Author URI: https://abdoo.me
 * License: GPL3
 * Text Domain: ert
 * Domain Path: /languages
 *
 * ===================================================================
 * 
 * Copyright 2024  Abdullatif Al-Mayhob, Abdoo abdoo.mayhob@gmail.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * ===================================================================
 * 
 * TODO:
 * - Shortcode
 * - Math Rank Support
 * 
 */

// If this file is called directly, abort.
defined('ABSPATH') or die;


add_action('init', function(){
    EstReadTime::I();
});


/**
 * Main Class.
 */
class EstReadTime {

    public const READ_ETA_META_FIELD_NAME = 'read_eta';
    public const ERT_SETTINGS = 'read_eta';

    // Refers to a single instance of this class
	private static $instance = null;

    /**
	 * Creates or returns a single instance of this class
	 *
	 * @return EstReadTime a single instance of this class.
	 */
    public static function I() {
        self::$instance = self::$instance ?? new EstReadTime();
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_filter('manage_posts_columns', [$this,'admin_columns']);
        add_action('manage_posts_custom_column', [$this,'admin_columns_content']);
        add_filter('wpseo_schema_article', [$this,'add_reading_duration_to_yoast_schema']);
    }


    // --------------------------------------------------------------------------------------
    // Admin Columns
    public function admin_columns($columns)
    {
        $columns['read_eta'] = _('Estimated Reading Time', 'ert');
        return $columns;
    }

    public function admin_columns_content($column_name){
        if( 'read_eta' == $column_name){
            echo self::get_eta();
        }
    }

    // --------------------------------------------------------------------------------------
    // Admin Menu
    public function admin_menu() {
        add_options_page(
            _('Estimated Reading Time Settings', 'ert'), 
            _('Est Read Time', 'ert'),
            'manage_options', 'ert', [$this, 'view_admin']);
    }

    public function view_admin($post) {
        require_once __DIR__ . '/admin.php';
    }

    // --------------------------------------------------------------------------------------
    // MetaBox UI
    public function meta_boxes() {
        add_meta_box('read_eta-meta-box', __('Estimated Reading Time', 'ert'), [$this,'meta_box_cb'], 'post', 'side', 'high');
    }

    public function meta_box_cb($post) {

        $value = get_post_meta($post->ID, self::READ_ETA_META_FIELD_NAME, true);

        if(empty($value))
            $value = self::get_eta($post);


        wp_nonce_field('est_meta_box_nonce', 'est_meta_box_nonce');
        ?>
        <input type="text" name="<?= self::READ_ETA_META_FIELD_NAME?>" value="<?= $value; ?>"/>
        <?php
    
    }

    // --------------------------------------------------------------------------------------
    // Main Logic & Calculator Functions

    /**
     * Calculates estimated reading time (ERT) for a post.
     *
     * @param WP_Post|null $post The post object. Defaults to current post.
     * @return string ERT in minutes, in the language of the post.
     */
    public function get_eta($post = null) {

        $post = $post ?? get_post();

        $word_count = self::get_word_count($post);

        $lang = self::get_post_language($post);
        
        if('ar' == $lang ){

            $etr = ceil($word_count / 250);

            if( 1 > $etr)
                $etr = 1;

            if (1 == $etr || 10 < $etr ) {
                $etr .= ' دقيقة';
            } else {
                $etr .= ' دقائق ';
            }
        }
        elseif($lang == 'en') {

            $etr = ceil($word_count / 300);

            if( 1 > $etr)
                $etr = 1;

            $etr .= ' Mins';
        }

        return $etr;
    }

    /**
     * Gets word count of a post in any langague.
     *
     * @param WP_Post $post The post object.
     * @return int Word count.
     */
    public function get_word_count($post) {
        $custom_value = get_post_meta($post->ID, self::READ_ETA_META_FIELD_NAME, true);

        if(!empty($custom_value))
            return $custom_value;

        $post_content = get_post_field( 'post_content', $post->ID );
        $post_content = strip_shortcodes( $post_content );
        $post_content = wp_strip_all_tags( $post_content );
        $word_count =  count(preg_split( '/\s+/', $post_content ));

        $word_count = apply_filters( 'ert_get_word_count', $word_count );

        return $word_count;
    }

    /**
     * Gets post language based on active translation plugin, or site language by default.
     * Suported Plugins: WPML, PolyLang
     *
     * @param WP_Post $post The post object.
     * @return string Post language (ex: 'ar' 'en').
     */
    public function get_post_language($post) {
    
        $language = '';
    
        // Check if WPML is active
        if (defined('ICL_SITEPRESS_VERSION')) {
            $language = apply_filters('wpml_post_language_details', NULL, $post->ID)['language_code'];
        }
        // Check if Polylang is active
        elseif (function_exists('pll_current_language')) {
            $language = pll_get_post_language($post->ID);
        }

        // If no translation plugin is active, or an error happened, get the default site language.
        if(empty($language)){
            $language = substr(get_locale(), 0, 2);
        }

    
        return strtolower($language);
    }

    // --------------------------------------------------------------------------------------
    // Yoast SEO Support

    /**
     * Modifies Yoast SEO schema to include reading duration.
     *
     * @filter wpseo_schema_article
     *
     * @param array $data Existing schema data.
     * @return array Modified schema data.
     */
    public function add_reading_duration_to_yoast_schema($data) {
    
        // Get the reading duration
        $reading_duration = self::get_eta();
    
        // Add the reading duration to the schema
        if (!empty($reading_duration)) {
            $data['readingTime'] = $reading_duration;
        }
    
        return $data;
    }
    

}