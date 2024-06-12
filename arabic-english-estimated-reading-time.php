<?php
/**
 *
 * Plugin Name: Arabic & English Estimated Reading Time
 * Plugin URI: https://github.com/Abdoo-mayhob/Estimated-Reading-Time
 * Description: Calculate and Show Estimated Reading Time in your posts in Both Arabic and English.
 * Version: 1.0.2
 * Author: Abdoo
 * Author URI: https://abdoo.me
 * License: GPLv2 or later
 * Text Domain: arabic-english-estimated-reading-time
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
 * - Add Customization filters and hooks
 */

// If this file is called directly, abort.
defined('ABSPATH') or die;

// Load Translation Files (Translations only needed in admin area)
add_action('plugins_loaded', function() {
    load_plugin_textdomain('arabic-english-estimated-reading-time', false, dirname(  plugin_basename( __FILE__ ) ) . '/languages/' );
},0);


add_action('init', function(){
    EstReadTime::I();
},10);


/**
 * Main Class.
 */
class EstReadTime {

    public const READ_ETA_META_FIELD_NAME = 'read_eta';
    public const ERT_SETTINGS = 'read_eta';

    // Plugin Settings and Default Values (Used when options not set yet)
    public $settings = [];
    public const SETTINGS_DEFAULT = [
        'ar_prefix' => 'الوقت المقدر للقراءة: ',
        'ar_suffix_s' => 'دقيقة',
        'ar_suffix_p' => 'دقائق',
        'en_prefix' => 'Estimated Time of Reading: ',
        'en_suffix' => 'Min',
        'en_wpm' => 300,
        'ar_wpm' => 250,
        'edit_yoast_schema' => true,
        'exclude_images' => true,
    ];

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
        add_shortcode('est-read-time-widget', [$this,'shortcode_widget']);
        add_shortcode('est-read-time', [$this,'shortcode']);

        // Load Plugin Settings 
        $this->settings = get_option(self::ERT_SETTINGS, self::SETTINGS_DEFAULT);
    }


    // --------------------------------------------------------------------------------------
    // Admin Columns
    public function admin_columns($columns)
    {
        $columns['read_eta'] = __('Estimated Reading Time', 'arabic-english-estimated-reading-time');
        return $columns;
    }

    public function admin_columns_content($column_name){
        if( 'read_eta' == $column_name){
            echo esc_html(self::get_etr());
        }
    }

    // --------------------------------------------------------------------------------------
    // Admin Menu
    public function admin_menu() {
        add_options_page(
            __('Estimated Reading Time Settings', 'arabic-english-estimated-reading-time'), 
            __('Reading Time', 'arabic-english-estimated-reading-time'),
            'manage_options', 'est-read-time', [$this, 'view_admin']);
    }

    public function view_admin($post) {
        require_once __DIR__ . '/admin.php';
    }

    // --------------------------------------------------------------------------------------
    // MetaBox UI
    public function meta_boxes() {
        add_meta_box('read_eta-meta-box', __('Estimated Reading Time', 'arabic-english-estimated-reading-time'), [$this,'meta_box_cb'], 'post', 'side', 'high');
    }

    public function meta_box_cb($post) {

        $value = get_post_meta($post->ID, self::READ_ETA_META_FIELD_NAME, true);

        if(empty($value))
            $value = self::get_etr($post);


        wp_nonce_field('est_meta_box_nonce', 'est_meta_box_nonce');
        ?>
        <input type="text" name="<?php echo esc_attr(self::READ_ETA_META_FIELD_NAME)?>" value="<?php echo esc_attr($value)?>"/>
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
    public function get_etr($post = null) {

        $post = $post ?? get_post();

        $word_count = self::get_word_count($post);

        $lang = self::get_post_language($post);

        $s = $this->settings;
        
        $wpm = $s["{$lang}_wpm"];

        $etr = $word_count / $wpm;
        $etr += $this->get_images_etr($post, $wpm);
        $etr = ceil($etr);


        if('ar' == $lang ){


            if( 1 > $etr)
                $etr = 1;

            if (1 == $etr || 10 < $etr ) {
                $etr .=  ' ' . $s['ar_suffix_s'];
            } else {
                $etr .= ' ' . $s['ar_suffix_p'];
            }
            $etr = $s['ar_prefix'] . ' ' . $etr;
        }
        elseif('en' == $lang) {

            if( 1 > $etr)
                $etr = 1;

            $etr .= ' ' . $s['en_suffix'];
            $etr = $s['en_prefix'] . ' ' . $etr ;
        }

        return $etr;
    }

    /**
	 * Get the accoutned additional reading time for images
	 *
	 * Calculate additional reading time added by images in posts. 
     * Images Calculations Math: https://blog.medium.com/read-time-and-you-bc2048ab620c
	 *
	 * @param WP_Post $post The post object.
	 * @param int $wpm words per minute.
	 * @return int  Additional seconds to added to the reading time.
	 */
	public function get_images_etr( $post, $wpm ) {

        $feature_disabled = $this->settings['exclude_images'] ?? self::SETTINGS_DEFAULT['exclude_images'];
        if($feature_disabled)
            return 0;

		$additional_time = 0;

        $total_n_of_images = substr_count( get_post_field( 'post_content', $post->ID ) , '<img ' );

		// For the first image add 12 seconds, second image add 11, ..., for image 10+ add 3 seconds.
		for ( $i = 1; $i <= $total_n_of_images; $i++ ) {
			if ( $i >= 10 ) {
				$additional_time += 3;
			} else {
				$additional_time += ( 12 - ( $i - 1 ) );
			}
		}

		return $additional_time/60;
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
    
        $enable_feature = $this->settings['edit_yoast_schema'] ?? self::SETTINGS_DEFAULT['edit_yoast_schema'];
        if(!$enable_feature)
        return $data;
    
        // Get the reading duration
        $reading_duration = self::get_etr();
    
        // Add the reading duration to the schema
        if (!empty($reading_duration)) {
            $data['readingTime'] = $reading_duration;
        }
    
        return $data;
    }
    
    // --------------------------------------------------------------------------------------
    // ShortCode
    public function shortcode_widget(){
        ob_start();
        ?>
        <div class="ert" style="display: flex;align-items: center;justify-content: flex-start;gap: 6px;">
            <img width="16" height="16" style="height: 16px; width: 16px;" src="<?php echo esc_url(plugin_dir_url(__FILE__) . "assets/clock.svg")?>" alt="CLock Icon">
            <?php echo wp_kses_post($this->get_etr()) ?>
        </div>
        <?php 
        return ob_get_clean();
    }

    public function shortcode(){
        return wp_kses_post($this->get_etr());
    }

}