<?php
// If this file is called directly, abort.
defined('ABSPATH') or die;



// The Default Value for each of the options
$settings_defaults = self::SETTINGS_DEFAULT;

// Save Settings
if ($_POST['submit'] ?? false) {


    if ( ! isset( $_POST['ert_nonce'] ) ) wp_die("Nonce failed, try again.");

    if ( wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['ert_nonce'] ) ) , 'ert_nonce' ) == false)
        wp_die("Nonce failed, try again.");


    $settings['ar_prefix'] = isset($_POST['ar_prefix']) ? sanitize_text_field(trim($_POST['ar_prefix'])) : '';
    $settings['ar_suffix_s'] = isset($_POST['ar_suffix_s']) ? sanitize_text_field(trim($_POST['ar_suffix_s'])) : '';
    $settings['ar_suffix_p'] = isset($_POST['ar_suffix_p']) ? sanitize_text_field(trim($_POST['ar_suffix_p'])) : '';
    $settings['en_prefix'] = isset($_POST['en_prefix']) ? sanitize_text_field(trim($_POST['en_prefix'])) : '';
    $settings['en_suffix'] = isset($_POST['en_suffix']) ? sanitize_text_field(trim($_POST['en_suffix'])) : '';

    $settings['en_wpm'] = isset($_POST['en_wpm']) ? intval($_POST['en_wpm']) : $settings_defaults['en_wpm'];
    $settings['ar_wpm'] = isset($_POST['ar_wpm']) ? intval($_POST['ar_wpm']) : $settings_defaults['ar_wpm'];

    $settings['edit_yoast_schema'] = isset($_POST['edit_yoast_schema']);
    $settings['exclude_images'] = isset($_POST['exclude_images']);

    $settings['send_diagnostic'] = isset($_POST['send_diagnostic']);

    update_option(self::ERT_SETTINGS, $settings);
    add_settings_error('ERT_SETTINGS', 'VALID_ERT_SETTINGS', 'Updated successfully.', 'updated');
    
}
// Reset Settings
elseif ($_POST['reset_all'] ?? false) {
    update_option(self::ERT_SETTINGS, $settings_defaults);
    add_settings_error('ERT_SETTINGS', 'VALID_ERT_SETTINGS', 'Updated successfully.', 'warning');
}

$settings = get_option(self::ERT_SETTINGS, $settings_defaults);

// Code Shorteners
$s = $settings;
$sd = $settings_defaults;

// Display settings errors
settings_errors('ERT_SETTINGS');

?>
<form method="post">
<div class="wrap">
    <h1><?php esc_html_e('Estimated Reading Time Settings', 'arabic-english-estimated-reading-time') ?></h1>
    <div class="row" style="display: flex;justify-content: space-between;">
        <div class="col" style="width: 440px;">
            <h2><?php esc_html_e('Basic Section', 'arabic-english-estimated-reading-time') ?></h2>
            <?php wp_nonce_field('ert_nonce', 'ert_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Arabic Prefix', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="text" name="ar_prefix" value="<?php echo esc_attr($s['ar_prefix'] ?? $sd['ar_prefix']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Arabic Suffix (Single)', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="text" name="ar_suffix_s" value="<?php echo esc_attr($s['ar_suffix_s'] ?? $sd['ar_suffix_s']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Arabic Suffix (Plural)', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="text" name="ar_suffix_p" value="<?php echo esc_attr($s['ar_suffix_p'] ?? $sd['ar_suffix_p']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('English Prefix', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="text" name="en_prefix" value="<?php echo esc_attr($s['en_prefix'] ?? $sd['en_prefix']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('English Suffix', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="text" name="en_suffix" value="<?php echo esc_attr($s['en_suffix'] ?? $sd['en_suffix']); ?>" /></td>
                </tr>
            </table>

            <hr />
            <h2 class="collapsed"><?php esc_html_e("Advanced Section",'arabic-english-estimated-reading-time')?></h2>
            <a href="javascript:void(0)" class="toggle-adv-settings">Show</a>
            <a href="javascript:void(0)" class="toggle-adv-settings" style="display: none;">Hide</a>

            <table class="form-table" id="adv-settings" style="display: none;">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Words Per Minutes for English content', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="number" name="en_wpm" value="<?php echo esc_attr($s['en_wpm'] ?? $sd['en_wpm']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Words Per Minutes for Arabic content', 'arabic-english-estimated-reading-time'); ?></th>
                    <td><input type="number" name="ar_wpm" value="<?php echo esc_attr($s['ar_wpm'] ?? $sd['ar_wpm']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Insert "readingTime" into Yoast SEO Schema ?', 'arabic-english-estimated-reading-time'); ?></th>
                    <td>
                        <!-- Rounded switch -->
                        <label class="switch">
                            <input type="checkbox" name="edit_yoast_schema" <?php checked($s['edit_yoast_schema'] ?? $sd['edit_yoast_schema']) ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Exclude Images from Reading Estimation ?', 'arabic-english-estimated-reading-time'); ?></th>
                    <td>
                        <!-- Rounded switch -->
                        <label class="switch">
                            <input type="checkbox" name="exclude_images" <?php checked($s['exclude_images'] ?? $sd['exclude_images']) ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Reset All Settings to Default', 'arabic-english-estimated-reading-time'); ?></th>
                    <td>
                        <input type="submit" class="button button-link-delete alert-confirm" value="<?php echo esc_attr(__('Reset All Settings','arabic-english-estimated-reading-time'))?>" name="reset_all">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </div>
        <div class="col" style="width: 45vw;">
            <h2>ShortCode</h2>
            <div>
                <?php esc_html_e('Show Estimated Reading Time Widget', 'arabic-english-estimated-reading-time') ?>
                <br>
                <strong>[est-read-time-widget]</strong>
            </div>
            <br><br>
            <div>
                <?php esc_html_e('Show Estimated Reading Time without any other html', 'arabic-english-estimated-reading-time') ?>
                <br>
                <strong>[est-read-time]</strong>
            </div>
            <br><br>
            <div>
                <?php esc_html_e('Show Estimated Reading Time without any other words or html, only the number.', 'arabic-english-estimated-reading-time') ?>
                <br>
                <strong>[est-read-time-raw]</strong>
            </div>
            <h2>Help me improve this plugin !</h2>
            <div>
                <?php 
                echo wp_kses_post("
                Hello there ! I'm Abdoo ! (the plugin developer) <br>
                If you have any feature suggustions, bug reporting or feedback, <br>
                Please let me know by email on <a href='mailto:abdoo@abdoo.me'>abdoo@abdoo.me</a>, I'll reply ASAP.
                ", 'arabic-english-estimated-reading-time'); ?>
            </div>
            <br>
            <hr />
            <br>
            <label>
                <input type="checkbox" name="send_diagnostic" <?php checked($s['send_diagnostic'] ?? $sd['send_diagnostic']) ?>>
                <?php esc_html_e('Send non sensitive data about this website to the developer.', 'arabic-english-estimated-reading-time') ?>
                <br>
                <?php esc_html_e('This will help me improve the plugin a lot ! your date is safe and will not be shared with anyone or will not be used for marketing.', 'arabic-english-estimated-reading-time') ?>
            </label>
        </div>
    </div>
</div>

<script>
    jQuery('.toggle-adv-settings').click(function(e) {
        jQuery('#adv-settings').toggle();
        jQuery('.toggle-adv-settings').toggle();
    });
    document.querySelectorAll('.alert-confirm').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm("<?php esc_html_e('Are You Sure?', 'arabic-english-estimated-reading-time'); ?>")) {
                e.preventDefault();
            }
        });
    });
</script>

<style>
    .form-table tr>* {
        width: 50%;
    }

    .form-table input[type="text"] {
        width: 100%;
    }

    /* The switch - the box around the slider */
    .switch {
        position: relative;
        display: inline-block;
        width: 30px;
        height: 17px;
    }

    /* Hide default HTML checkbox */
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    /* The slider */
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 13px;
        width: 13px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
    }

    input:checked+.slider {
        background-color: #2196F3;
    }

    input:focus+.slider {
        box-shadow: 0 0 1px #2196F3;
    }

    input:checked+.slider:before {
        -webkit-transform: translateX(13px);
        -ms-transform: translateX(13px);
        transform: translateX(13px);
    }

    /* Rounded sliders */
    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }
</style>