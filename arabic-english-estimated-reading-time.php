<?php

/**
 *
 * Plugin Name: Arabic & English Estimated Reading Time
 * Plugin URI: https://github.com/Abdoo-mayhob/Estimated-Reading-Time
 * Description: Calculate and Show Estimated Reading Time in your posts in Both Arabic and English.
 * Version: 1.2.0
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
 * - Fix Metafield 
 * - Review SEO attr 
 * - Diagnoses sending on options update not stable
 */

// If this file is called directly, abort.
defined('ABSPATH') or die;

// Load Translation Files (Translations only needed in admin area)
add_action('plugins_loaded', function () {
    load_plugin_textdomain('arabic-english-estimated-reading-time', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}, 0);


add_action('init', function () {
    EstReadTime::I();
}, 10);


/**
 * Main Class.
 */
class EstReadTime
{

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
        'send_diagnostic' => true,
    ];

    // Refers to a single instance of this class
    private static $instance = null;

    /**
     * Creates or returns a single instance of this class
     *
     * @return EstReadTime a single instance of this class.
     */
    public static function I()
    {
        self::$instance = self::$instance ?? new EstReadTime();
        return self::$instance;
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_action('save_post', [$this, 'save_meta']);
        add_filter('manage_posts_columns', [$this, 'admin_columns']);
        add_action('manage_posts_custom_column', [$this, 'admin_columns_content']);
        add_filter('wpseo_schema_article', [$this, 'add_reading_duration_to_yoast_schema']);
        add_shortcode('est-read-time-widget', [$this, 'shortcode_widget']);
        add_shortcode('est-read-time', [$this, 'shortcode']);
        add_shortcode('est-read-time-raw', [$this, 'shortcode_raw']);

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

    public function admin_columns_content($column_name)
    {
        if ('read_eta' == $column_name) {
            echo esc_html(self::get_etr());
        }
    }

    // --------------------------------------------------------------------------------------
    // Admin Menu
    public function admin_menu()
    {
        add_options_page(
            __('Estimated Reading Time Settings', 'arabic-english-estimated-reading-time'),
            __('Reading Time', 'arabic-english-estimated-reading-time'),
            'manage_options',
            'est-read-time',
            [$this, 'view_admin']
        );
    }

    public function view_admin($post)
    {
        require_once __DIR__ . '/admin.php';
    }

    // --------------------------------------------------------------------------------------
    // MetaBox UI
    public function meta_boxes()
    {
        add_meta_box('read_eta-meta-box', __('Estimated Reading Time', 'arabic-english-estimated-reading-time'), [$this, 'meta_box_cb'], 'post', 'side', 'high');
    }

    public function meta_box_cb($post)
    {
        $value = $this->get_custom_post_etr($post);
        $note = '';
        if(empty($value)){
            $value = $this->calc_etr_raw();
            $note = __('You didn\'t specify a value for this post, auto generated one for you.', 'arabic-english-estimated-reading-time');
        }
        wp_nonce_field('ert_mb_nonce', 'ert_mb_nonce');
?>
        <input type="number" name="<?php echo esc_attr(self::READ_ETA_META_FIELD_NAME) ?>" value="<?php echo esc_attr($value) ?>" />
        <br>
        <?php
        echo esc_html($note);

    }
    
    public function save_meta($post_id){

        // Security
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ( ! isset( $_POST['ert_mb_nonce'] ) ) return;

        if ( wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['ert_mb_nonce'] ) ) , 'ert_mb_nonce' ) == false)
            return;
    
        $etr = $_POST[self::READ_ETA_META_FIELD_NAME];
        if (!empty($_POST[self::READ_ETA_META_FIELD_NAME]) && is_numeric($etr)){
            $etr = (int) $etr;
            update_post_meta($post_id, self::READ_ETA_META_FIELD_NAME, $etr);
        }
    
    }

    // --------------------------------------------------------------------------------------
    // Main Logic & Calculator Functions

    /**
     * Get post user set value for a specific post. Get Raw int value.
     *
     * @param WP_Post|null $post The post object. Defaults to current post.
     * @return int|false ERT in minutes. false if no spacific value was set.
     */
    public function get_custom_post_etr($post = null)
    {
        $post = $post ?? get_post();

        $etr = get_post_meta( $post->ID, self::READ_ETA_META_FIELD_NAME, true );
        if(empty($etr) || (! is_numeric($etr))){
            return false;
        }
        return (int)$etr;
    }
    /**
     * Calculates estimated reading time (ERT) for a post. Get Raw int value.
     *
     * @param WP_Post|null $post The post object. Defaults to current post.
     * @return int ERT in minutes.
     */
    public function calc_etr_raw($post = null)
    {
        $post = $post ?? get_post();

        $word_count = self::get_word_count($post);

        $lang = self::get_post_language($post);

        $s = $this->settings;

        $wpm = $s["{$lang}_wpm"];

        $etr = $word_count / $wpm;
        $etr += $this->get_images_etr($post, $wpm);
        $etr = ceil($etr);

        return (int)$etr;
    }
    /**
     * Get estimated reading time (ERT) for a post. Get Raw int value.
     * This function will try get the user specified value for the post. if invalid or not set.
     * It will auto calculate the ERT and return it. 
     * 
     * @param WP_Post|null $post The post object. Defaults to current post.
     * @return int ERT in minutes.
     */
    public function get_etr_raw($post = null)
    {
        $post = $post ?? get_post();
        $etr = $this->get_custom_post_etr($post);

        // If user didn't specify a value, auto calc it.
        if(empty($etr)){
            $etr = $this->calc_etr_raw($post);
        }
        return $etr;
    }

    /**
     * Get Full estimated reading time phrase (ERT) for a post. 
     *
     * @param WP_Post|null $post The post object. Defaults to current post.
     * @return string ERT in minutes, in the language of the post.
     */
    public function get_etr($post = null)
    {

        $post = $post ?? get_post();

        $etr = $this->get_etr_raw($post);

        $lang = self::get_post_language($post);
        $s = $this->settings;

        if ('ar' == $lang) {
            if (1 > $etr)
                $etr = 1;

            if (1 == $etr || 10 < $etr) {
                $etr .=  ' ' . $s['ar_suffix_s'];
            } else {
                $etr .= ' ' . $s['ar_suffix_p'];
            }
            $etr = $s['ar_prefix'] . ' ' . $etr;
        } elseif ('en' == $lang) {

            if (1 > $etr)
                $etr = 1;

            $etr .= ' ' . $s['en_suffix'];
            $etr = $s['en_prefix'] . ' ' . $etr;
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
    public function get_images_etr($post, $wpm)
    {

        $post = $post ?? get_post();

        $feature_disabled = $this->settings['exclude_images'] ?? self::SETTINGS_DEFAULT['exclude_images'];
        if ($feature_disabled)
            return 0;

        $additional_time = 0;

        $total_n_of_images = substr_count(get_post_field('post_content', $post->ID), '<img ');

        // For the first image add 12 seconds, second image add 11, ..., for image 10+ add 3 seconds.
        for ($i = 1; $i <= $total_n_of_images; $i++) {
            if ($i >= 10) {
                $additional_time += 3;
            } else {
                $additional_time += (12 - ($i - 1));
            }
        }

        return $additional_time / 60;
    }

    /**
     * Gets word count of a post in any langague.
     *
     * @param WP_Post $post The post object.
     * @return int Word count.
     */
    public function get_word_count($post)
    {
        
        $post = $post ?? get_post();

        $post_content = get_post_field('post_content', $post->ID);
        $post_content = strip_shortcodes($post_content);
        $post_content = wp_strip_all_tags($post_content);
        $word_count =  count(preg_split('/\s+/', $post_content));

        $word_count = apply_filters('ert_get_word_count', $word_count);

        return $word_count;
    }

    /**
     * Gets post language based on active translation plugin, or site language by default.
     * Suported Plugins: WPML, PolyLang
     *
     * @param WP_Post $post The post object.
     * @return string Post language (ex: 'ar' 'en').
     */
    public function get_post_language($post)
    {

        $post = $post ?? get_post();

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
        if (empty($language)) {
            $language = substr(get_locale(), 0, 2);
        }

        $language = apply_filters('ert_get_post_language', $language);

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
    public function add_reading_duration_to_yoast_schema($data)
    {

        $enable_feature = $this->settings['edit_yoast_schema'] ?? self::SETTINGS_DEFAULT['edit_yoast_schema'];
        if (!$enable_feature)
            return $data;

        // Get the reading duration
        $reading_duration = $this->get_etr_raw();

        // Add the reading duration to the schema
        if (!empty($reading_duration)) {
            $reading_duration = apply_filters('ert_add_reading_duration_to_yoast_schema', $reading_duration);
            $data['readingTime'] = $reading_duration;
        }

        return $data;
    }

    // --------------------------------------------------------------------------------------
    // ShortCode
    public function shortcode_widget()
    {
        ob_start();
    ?>
        <div class="ert" style="display: flex;align-items: center;justify-content: flex-start;gap: 6px;">
            <img width="16" height="16" style="height: 16px; width: 16px;" src="<?php echo esc_url(plugin_dir_url(__FILE__) . "assets/clock.svg") ?>" alt="CLock Icon">
            <?php echo wp_kses_post($this->get_etr()) ?>
        </div>
<?php
        return ob_get_clean();
    }

    public function shortcode()
    {
        return wp_kses_post($this->get_etr());
    }
    public function shortcode_raw()
    {
        return wp_kses_post($this->get_etr_raw());
    }

    // --------------------------------------------------------------------------------------
    // Diagnostics
    public function send_diagnostics($action = 'unknown'){

        if(! ($this->settings['send_diagnostic'] ?? false))return;

        $diagnostic = wp_json_encode([
            'plugin_slug' => dirname(plugin_basename(__FILE__)),
            'action' => $action,
            'name' => get_bloginfo('name'),
            'url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
            'lang' => get_locale(),
            'plugin_version' => get_plugin_data(__FILE__)['Version'],
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'theme' => wp_get_theme()->get('Name') . " | v" .  wp_get_theme()->get('Version'),
            'active_plugins' => get_option('active_plugins'),
            'settings' =>  $this->settings,
            'is_multisite' => is_multisite()
        ], JSON_UNESCAPED_UNICODE);
    
        wp_remote_post('https://plugins.abdoo.me/', [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'blocking' => false,
            'sslverify' => false,
            'body' => $diagnostic
        ]);

    }
}


// --------------------------------------------------------------------------------------
// Diagnostics

// Send Diagnostics on plugin activation
register_activation_hook(__FILE__, function(){
    EstReadTime::I()->send_diagnostics('activate');
});

// Send Diagnostics on plugin deactivation
register_deactivation_hook(__FILE__, function(){
    EstReadTime::I()->send_diagnostics('deactivate');
});
// Send Diagnostics on plugin settings update
add_action('update_option_' . EstReadTime::I()::ERT_SETTINGS, function(){
    EstReadTime::I()->send_diagnostics('settings_update');
});

