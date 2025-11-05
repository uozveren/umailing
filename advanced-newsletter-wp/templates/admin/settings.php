<?php
/**
 * Admin Settings Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap advnews-settings">
    <h1><?php _e('Newsletter Settings', 'advanced-newsletter'); ?></h1>

    <form id="advnews-settings-form">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="sender_name"><?php _e('Sender Name', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sender_name" name="settings[sender_name]" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="sender_email"><?php _e('Sender Email', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="sender_email" name="settings[sender_email]" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="reply_to"><?php _e('Reply-To Email', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="reply_to" name="settings[reply_to]" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="double_optin"><?php _e('Double Opt-In', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="double_optin" name="settings[double_optin]" value="1" />
                            <?php _e('Require email confirmation for new subscribers', 'advanced-newsletter'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gdpr_enabled"><?php _e('GDPR Compliance', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="gdpr_enabled" name="settings[gdpr_enabled]" value="1" />
                            <?php _e('Enable GDPR consent checkbox', 'advanced-newsletter'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tracking_enabled"><?php _e('Email Tracking', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="tracking_enabled" name="settings[tracking_enabled]" value="1" />
                            <?php _e('Enable open and click tracking', 'advanced-newsletter'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="emails_per_batch"><?php _e('Emails Per Batch', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="emails_per_batch" name="settings[emails_per_batch]" class="small-text" min="1" max="100" />
                        <p class="description"><?php _e('Number of emails to send per batch (recommended: 50)', 'advanced-newsletter'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="batch_interval"><?php _e('Batch Interval', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="batch_interval" name="settings[batch_interval]" class="small-text" min="30" max="300" />
                        <p class="description"><?php _e('Seconds between batches (recommended: 60)', 'advanced-newsletter'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2><?php _e('SMTP Settings', 'advanced-newsletter'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="smtp_enabled"><?php _e('Enable SMTP', 'advanced-newsletter'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="smtp_enabled" name="settings[smtp_enabled]" value="1" />
                            <?php _e('Use custom SMTP server', 'advanced-newsletter'); ?>
                        </label>
                    </td>
                </tr>

                <tr id="smtp-settings" style="display:none;">
                    <td colspan="2">
                        <table class="form-table">
                            <tr>
                                <th><label for="smtp_host"><?php _e('SMTP Host', 'advanced-newsletter'); ?></label></th>
                                <td><input type="text" id="smtp_host" name="settings[smtp_host]" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="smtp_port"><?php _e('SMTP Port', 'advanced-newsletter'); ?></label></th>
                                <td><input type="number" id="smtp_port" name="settings[smtp_port]" class="small-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="smtp_username"><?php _e('SMTP Username', 'advanced-newsletter'); ?></label></th>
                                <td><input type="text" id="smtp_username" name="settings[smtp_username]" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="smtp_password"><?php _e('SMTP Password', 'advanced-newsletter'); ?></label></th>
                                <td><input type="password" id="smtp_password" name="settings[smtp_password]" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="smtp_encryption"><?php _e('Encryption', 'advanced-newsletter'); ?></label></th>
                                <td>
                                    <select id="smtp_encryption" name="settings[smtp_encryption]">
                                        <option value="none"><?php _e('None', 'advanced-newsletter'); ?></option>
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php _e('Save Settings', 'advanced-newsletter'); ?></button>
            <button type="button" id="test-smtp" class="button"><?php _e('Test SMTP', 'advanced-newsletter'); ?></button>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Load settings
    $.ajax({
        url: advNewsletterAdmin.ajaxUrl,
        type: 'POST',
        data: {
            action: 'advnews_get_settings',
            nonce: advNewsletterAdmin.nonce
        },
        success: function(response) {
            if (response.success) {
                var settings = response.data.settings;
                Object.keys(settings).forEach(function(key) {
                    var $field = $('[name="settings[' + key + ']"]');
                    if ($field.attr('type') === 'checkbox') {
                        $field.prop('checked', settings[key] === '1');
                    } else {
                        $field.val(settings[key]);
                    }
                });

                // Toggle SMTP settings
                if ($('#smtp_enabled').is(':checked')) {
                    $('#smtp-settings').show();
                }
            }
        }
    });

    // Toggle SMTP settings
    $('#smtp_enabled').on('change', function() {
        $('#smtp-settings').toggle($(this).is(':checked'));
    });

    // Save settings
    $('#advnews-settings-form').on('submit', function(e) {
        e.preventDefault();

        var formData = {};
        $(this).find('[name^="settings"]').each(function() {
            var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
            if ($(this).attr('type') === 'checkbox') {
                formData[name] = $(this).is(':checked') ? '1' : '0';
            } else {
                formData[name] = $(this).val();
            }
        });

        $.ajax({
            url: advNewsletterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_save_settings',
                nonce: advNewsletterAdmin.nonce,
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Test SMTP
    $('#test-smtp').on('click', function() {
        var email = prompt('<?php _e('Enter email address to send test email:', 'advanced-newsletter'); ?>');
        if (!email) return;

        $.ajax({
            url: advNewsletterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_test_smtp',
                nonce: advNewsletterAdmin.nonce,
                test_email: email
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});
</script>
