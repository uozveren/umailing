<?php
/**
 * Newsletter Subscription Form Template
 *
 * @var array $atts Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

$form_id = 'advnews-form-' . wp_generate_password(8, false);
$show_name = ($atts['show_name'] ?? 'yes') === 'yes';
$lists = $atts['lists'] ?? '1';
?>

<div class="advnews-subscription-form">
    <?php if (!empty($atts['title'])): ?>
        <h3 class="advnews-form-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>

    <?php if (!empty($atts['description'])): ?>
        <p class="advnews-form-description"><?php echo esc_html($atts['description']); ?></p>
    <?php endif; ?>

    <form id="<?php echo esc_attr($form_id); ?>" class="advnews-form" method="post">
        <?php if ($show_name): ?>
            <div class="advnews-form-group">
                <label for="<?php echo esc_attr($form_id); ?>-name">
                    <?php _e('Name', 'advanced-newsletter'); ?>
                </label>
                <input
                    type="text"
                    id="<?php echo esc_attr($form_id); ?>-name"
                    name="name"
                    class="advnews-input"
                    placeholder="<?php esc_attr_e('Your name', 'advanced-newsletter'); ?>"
                />
            </div>
        <?php endif; ?>

        <div class="advnews-form-group">
            <label for="<?php echo esc_attr($form_id); ?>-email">
                <?php _e('Email', 'advanced-newsletter'); ?> <span class="required">*</span>
            </label>
            <input
                type="email"
                id="<?php echo esc_attr($form_id); ?>-email"
                name="email"
                class="advnews-input"
                placeholder="<?php esc_attr_e('your@email.com', 'advanced-newsletter'); ?>"
                required
            />
        </div>

        <?php
        // Get GDPR setting
        global $wpdb;
        $table_settings = $wpdb->prefix . 'advnews_settings';
        $gdpr_enabled = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
            'gdpr_enabled'
        ));

        if ($gdpr_enabled === '1'):
        ?>
            <div class="advnews-form-group advnews-gdpr">
                <label>
                    <input
                        type="checkbox"
                        name="gdpr_consent"
                        value="1"
                        required
                    />
                    <?php _e('I agree to receive newsletter emails and accept the privacy policy.', 'advanced-newsletter'); ?>
                    <span class="required">*</span>
                </label>
            </div>
        <?php endif; ?>

        <input type="hidden" name="lists" value="<?php echo esc_attr($lists); ?>" />
        <input type="hidden" name="source" value="website" />

        <div class="advnews-form-group">
            <button type="submit" class="advnews-submit-btn">
                <?php echo esc_html($atts['button_text'] ?? __('Subscribe', 'advanced-newsletter')); ?>
            </button>
        </div>

        <div class="advnews-message" style="display: none;"></div>
    </form>
</div>

<script type="text/javascript">
(function($) {
    'use strict';

    $('#<?php echo esc_js($form_id); ?>').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('.advnews-submit-btn');
        var $message = $form.find('.advnews-message');

        $btn.prop('disabled', true).text('<?php esc_js(_e('Processing...', 'advanced-newsletter')); ?>');
        $message.hide();

        $.ajax({
            url: advNewsletter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'advnews_subscribe',
                nonce: advNewsletter.nonce,
                email: $form.find('[name="email"]').val(),
                name: $form.find('[name="name"]').val(),
                gdpr_consent: $form.find('[name="gdpr_consent"]').is(':checked') ? 1 : 0,
                lists: $form.find('[name="lists"]').val(),
                source: $form.find('[name="source"]').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).fadeIn();
                    $form[0].reset();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).fadeIn();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text(advNewsletter.strings.error).fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js($atts['button_text'] ?? __('Subscribe', 'advanced-newsletter')); ?>');
            }
        });
    });
})(jQuery);
</script>
