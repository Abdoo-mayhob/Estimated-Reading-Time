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
 * - WPML Support
 * - Yoast SEO Support
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

    private static $instance;

    public static function I() {
        self::$instance = self::$instance ?? new EstReadTime();
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
    }

    public function admin_menu() {
        add_menu_page(
            _('Estimated Reading Time Settings', 'ert'), 
            _('Estimated Reading Time Settings', 'ert'),
            'manage_options', 'sso', [$this, 'view_admin'], 'dashicons-clock');
    }


    public function meta_boxes() {
        add_meta_box('read_eta-meta-box', __('Estimated Reading Time', 'ert'), [$this,'meta_box_cb'], 'post', 'side', 'high');
    }

    public function view_admin($post) {
        ?><h1><?php _e('Estimated Reading Time Settings', 'ert')?></h1><?php
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

    public function get_eta($post) {
        $word_count = self::get_word_count($post);

        $lang = self::get_post_language($post);
        
        if('ar' == $lang ){

            $etr = ceil($word_count / 250);

            if( 1 > $etr)
                $etr = 1;

            elseif (1 == $etr || 1 < $etr ) {
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
        // If no translation plugin is active, get the default site language
        else {
            $language = substr(get_locale(), 0, 2);
        }
    
        return strtolower($language);
    }
    

}