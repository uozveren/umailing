<?php
namespace AdvancedNewsletter\Admin;

class Settings {

    private $wpdb;
    private $table_settings;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_settings = $wpdb->prefix . 'advnews_settings';

        add_action('wp_ajax_advnews_get_settings', [$this, 'ajax_get_settings']);
        add_action('wp_ajax_advnews_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_advnews_test_smtp', [$this, 'ajax_test_smtp']);
    }

    public function ajax_get_settings() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $settings = $this->wpdb->get_results(
            "SELECT setting_key, setting_value FROM {$this->table_settings}",
            OBJECT_K
        );

        $formatted_settings = [];
        foreach ($settings as $key => $setting) {
            $formatted_settings[$key] = $setting->setting_value;
        }

        wp_send_json_success(['settings' => $formatted_settings]);
    }

    public function ajax_save_settings() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $settings = $_POST['settings'] ?? [];

        if (empty($settings)) {
            wp_send_json_error(['message' => 'No settings provided']);
        }

        foreach ($settings as $key => $value) {
            $key = sanitize_text_field($key);
            $value = sanitize_text_field($value);

            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT setting_key FROM {$this->table_settings} WHERE setting_key = %s",
                $key
            ));

            if ($existing) {
                $this->wpdb->update(
                    $this->table_settings,
                    ['setting_value' => $value],
                    ['setting_key' => $key]
                );
            } else {
                $this->wpdb->insert($this->table_settings, [
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
            }
        }

        wp_send_json_success(['message' => 'Settings saved']);
    }

    public function ajax_test_smtp() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $test_email = sanitize_email($_POST['test_email'] ?? '');

        if (!is_email($test_email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }

        $subject = 'SMTP Test Email';
        $message = 'This is a test email from Advanced Newsletter plugin. If you received this, your SMTP settings are working correctly!';

        $result = wp_mail($test_email, $subject, $message);

        if ($result) {
            wp_send_json_success(['message' => 'Test email sent successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to send test email']);
        }
    }

    public function get_setting($key, $default = '') {
        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$this->table_settings} WHERE setting_key = %s",
            $key
        ));

        return $value !== null ? $value : $default;
    }
}
