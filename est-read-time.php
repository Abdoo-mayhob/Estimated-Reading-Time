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
 * - Admin Dashboard
 * - Calculator
 * - Metafield
 * - Shortcode
 * - WPML Support
 * - Polylang Support
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
        add_menu_page('SSO', 'SSO', 'manage_options', 'sso', [$this, 'view_admin'], 'dashicons-rest-api');
    }


    public function meta_boxes() {
        add_meta_box('read_eta-meta-box', __('الوقت التقديري للقراءة', 'ert'), [$this,'meta_box_cb'], 'post', 'side', 'high');
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

        if(true){ // TODO: if(post==ar)

            $etr = round($word_count / 250);

            if ($etr == 0) {
                $etr = '1 دقيقة';
            } elseif ($etr == 1 || $etr > 10) {
                $etr = $etr . ' دقيقة';
            } else {
                $etr = $etr . ' دقائق ';
            }
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
        $word_count = count( preg_split( '/\s+/', $post_content ) );

        $word_count = apply_filters( 'ert_get_word_count', $word_count );

        return $word_count;
    }


}