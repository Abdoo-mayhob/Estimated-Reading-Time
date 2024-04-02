<?php

// The Default Value for each of the options
$settings_defaults = self::SETTINGS_DEFAULT;

// Save Settings
if ($_POST['submit'] ?? false) {
    $settings['ar_prefix'] = isset($_POST['ar_prefix']) ? sanitize_text_field(trim($_POST['ar_prefix'])) : '';
    $settings['ar_suffix_s'] = isset($_POST['ar_suffix_s']) ? sanitize_text_field(trim($_POST['ar_suffix_s'])) : '';
    $settings['ar_suffix_p'] = isset($_POST['ar_suffix_p']) ? sanitize_text_field(trim($_POST['ar_suffix_p'])) : '';
    $settings['en_prefix'] = isset($_POST['en_prefix']) ? sanitize_text_field(trim($_POST['en_prefix'])) : '';
    $settings['en_suffix'] = isset($_POST['en_suffix']) ? sanitize_text_field(trim($_POST['en_suffix'])) : '';

    $settings['en_wpm'] = isset($_POST['en_wpm']) ? intval($_POST['en_wpm']) : $settings_defaults['en_wpm'];
    $settings['ar_wpm'] = isset($_POST['ar_wpm']) ? intval($_POST['ar_wpm']) : $settings_defaults['ar_wpm'];

    $settings['edit_yoast_schema'] = isset($_POST['edit_yoast_schema']);
    $settings['exclude_images'] = isset($_POST['exclude_images']);

    update_option(self::ERT_SETTINGS, $settings);
    add_settings_error('ERT_SETTINGS', 'VALID_ERT_SETTINGS', 'Updated successfully.', 'updated');
    // add_settings_error('purchase_key', 'invalid_purchase_key', 'The Purchase Key Failed ! inspect the dd or the SSO API logs for more info');
}
// Reset Settings
elseif($_POST['reset_all'] ?? false) {
    update_option(self::ERT_SETTINGS, $settings_defaults);
    add_settings_error('ERT_SETTINGS', 'VALID_ERT_SETTINGS', 'Updated successfully.', 'warning');
}

$settings = get_option(self::ERT_SETTINGS, $settings_defaults);

// For Debug
echo '<pre style="direction: ltr;display:none;">Settings:';
var_dump($settings);
echo '</pre>';

// Code Shorteners
$s = $settings;
$sd = $settings_defaults;

// Display settings errors
settings_errors('ERT_SETTINGS');

?>
<div class="wrap">
    <h1><?php _e('Estimated Reading Time Settings', 'ert') ?></h1>
    <div class="col" style="width: 440px;">
        <form method="post">
            <h2>Basic Section</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Arabic Prefix', 'ert'); ?></th>
                    <td><input type="text" name="ar_prefix" value="<?php echo esc_attr($s['ar_prefix'] ?? $sd['ar_prefix']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Arabic Suffix (Single)', 'ert'); ?></th>
                    <td><input type="text" name="ar_suffix_s" value="<?php echo esc_attr($s['ar_suffix_s'] ?? $sd['ar_suffix_s']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Arabic Suffix (Plural)', 'ert'); ?></th>
                    <td><input type="text" name="ar_suffix_p" value="<?php echo esc_attr($s['ar_suffix_p'] ?? $sd['ar_suffix_p']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('English Prefix', 'ert'); ?></th>
                    <td><input type="text" name="en_prefix" value="<?php echo esc_attr($s['en_prefix'] ?? $sd['en_prefix']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('English Suffix', 'ert'); ?></th>
                    <td><input type="text" name="en_suffix" value="<?php echo esc_attr($s['en_suffix'] ?? $sd['en_suffix']); ?>" /></td>
                </tr>
            </table>

            <hr />
            <h2 class="collapsed">Advanced Section</h2>
            <a href="javascript:void(0)" class="toggle-adv-settings">Show</a>
            <a href="javascript:void(0)" class="toggle-adv-settings" style="display: none;">Hide</a>

            <table class="form-table" id="adv-settings" style="display: none;">
                <tr valign="top">
                    <th scope="row"><?php _e('Words Per Minutes for English content', 'ert'); ?></th>
                    <td><input type="number" name="en_wpm" value="<?php echo esc_attr($s['en_wpm'] ?? $sd['en_wpm']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Words Per Minutes for Arabic content', 'ert'); ?></th>
                    <td><input type="number" name="ar_wpm" value="<?php echo esc_attr($s['ar_wpm'] ?? $sd['ar_wpm']); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Insert "readingTime" into Yoast SEO Schema ?', 'ert'); ?></th>
                    <td>
                        <!-- Rounded switch -->
                        <label class="switch">
                            <input type="checkbox" name="edit_yoast_schema" <?php checked($s['edit_yoast_schema'] ?? $sd['edit_yoast_schema']) ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Exclude Images from Reading Estimation ?', 'ert'); ?></th>
                    <td>
                        <!-- Rounded switch -->
                        <label class="switch">
                            <input type="checkbox" name="exclude_images" <?php checked($s['exclude_images'] ?? $sd['exclude_images']) ?>>
                            <span class="slider round"></span>
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Exclude Images from Reading Estimation ?', 'ert'); ?></th>
                    <td>
                        <form method="post" action="">
                            <input type="submit" class="button button-link-delete alert-confirm" value="Reset All Settings" name="reset_all">
                        </form>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
</div>

<script>
    jQuery('.toggle-adv-settings').click(function(e) {
        jQuery('#adv-settings').toggle();
        jQuery('.toggle-adv-settings').toggle();
    });
    document.querySelectorAll('.alert-confirm').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm("<?php _e('Are You Sure?', 'ert');?>")) {
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