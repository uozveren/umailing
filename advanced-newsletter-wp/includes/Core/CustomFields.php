<?php
namespace AdvancedNewsletter\Core;

class CustomFields {

    private $wpdb;
    private $table_fields;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_fields = $wpdb->prefix . 'advnews_custom_fields';

        add_action('wp_ajax_advnews_create_custom_field', [$this, 'ajax_create_field']);
        add_action('wp_ajax_advnews_get_custom_fields', [$this, 'ajax_get_fields']);
        add_action('wp_ajax_advnews_update_subscriber_field', [$this, 'ajax_update_subscriber_field']);
    }

    /**
     * Create custom fields table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'advnews_custom_fields';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            field_key varchar(100) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type varchar(50) DEFAULT 'text',
            field_options text DEFAULT NULL,
            required tinyint(1) DEFAULT 0,
            show_in_form tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_key (field_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create a custom field
     */
    public function create_field($data) {
        $field_data = [
            'field_key' => sanitize_key($data['field_key']),
            'field_label' => sanitize_text_field($data['field_label']),
            'field_type' => sanitize_text_field($data['field_type'] ?? 'text'),
            'field_options' => isset($data['field_options']) ? json_encode($data['field_options']) : null,
            'required' => intval($data['required'] ?? 0),
            'show_in_form' => intval($data['show_in_form'] ?? 1),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'created_date' => current_time('mysql')
        ];

        $result = $this->wpdb->insert($this->table_fields, $field_data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get all custom fields
     */
    public function get_all_fields() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_fields} ORDER BY sort_order ASC, created_date ASC"
        );
    }

    /**
     * Get form fields (only those marked to show in form)
     */
    public function get_form_fields() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_fields} WHERE show_in_form = 1 ORDER BY sort_order ASC"
        );
    }

    /**
     * Update subscriber custom field value
     */
    public function update_subscriber_field($subscriber_id, $field_key, $value) {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';

        // Get current custom fields
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT custom_fields FROM {$table_subscribers} WHERE id = %d",
            $subscriber_id
        ));

        if (!$subscriber) {
            return false;
        }

        $custom_fields = $subscriber->custom_fields ? json_decode($subscriber->custom_fields, true) : [];
        $custom_fields[$field_key] = sanitize_text_field($value);

        return $this->wpdb->update(
            $table_subscribers,
            ['custom_fields' => json_encode($custom_fields)],
            ['id' => $subscriber_id]
        );
    }

    /**
     * Get subscriber field value
     */
    public function get_subscriber_field($subscriber_id, $field_key) {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';

        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT custom_fields FROM {$table_subscribers} WHERE id = %d",
            $subscriber_id
        ));

        if (!$subscriber || !$subscriber->custom_fields) {
            return null;
        }

        $custom_fields = json_decode($subscriber->custom_fields, true);
        return $custom_fields[$field_key] ?? null;
    }

    /**
     * Render field HTML for form
     */
    public function render_field($field, $value = '') {
        $required = $field->required ? 'required' : '';
        $html = '';

        switch ($field->field_type) {
            case 'text':
                $html = '<input type="text" name="custom_fields[' . esc_attr($field->field_key) . ']" value="' . esc_attr($value) . '" class="advnews-input" ' . $required . ' />';
                break;

            case 'email':
                $html = '<input type="email" name="custom_fields[' . esc_attr($field->field_key) . ']" value="' . esc_attr($value) . '" class="advnews-input" ' . $required . ' />';
                break;

            case 'number':
                $html = '<input type="number" name="custom_fields[' . esc_attr($field->field_key) . ']" value="' . esc_attr($value) . '" class="advnews-input" ' . $required . ' />';
                break;

            case 'date':
                $html = '<input type="date" name="custom_fields[' . esc_attr($field->field_key) . ']" value="' . esc_attr($value) . '" class="advnews-input" ' . $required . ' />';
                break;

            case 'textarea':
                $html = '<textarea name="custom_fields[' . esc_attr($field->field_key) . ']" class="advnews-input" rows="4" ' . $required . '>' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                $options = $field->field_options ? json_decode($field->field_options, true) : [];
                $html = '<select name="custom_fields[' . esc_attr($field->field_key) . ']" class="advnews-input" ' . $required . '>';
                $html .= '<option value="">Select...</option>';
                foreach ($options as $option) {
                    $selected = ($value == $option) ? 'selected' : '';
                    $html .= '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'checkbox':
                $checked = $value ? 'checked' : '';
                $html = '<label><input type="checkbox" name="custom_fields[' . esc_attr($field->field_key) . ']" value="1" ' . $checked . ' /> ' . esc_html($field->field_label) . '</label>';
                break;

            case 'radio':
                $options = $field->field_options ? json_decode($field->field_options, true) : [];
                foreach ($options as $option) {
                    $checked = ($value == $option) ? 'checked' : '';
                    $html .= '<label style="display: block;"><input type="radio" name="custom_fields[' . esc_attr($field->field_key) . ']" value="' . esc_attr($option) . '" ' . $checked . ' /> ' . esc_html($option) . '</label>';
                }
                break;
        }

        return $html;
    }

    /**
     * Predefined custom fields
     */
    public function install_default_fields() {
        $default_fields = [
            [
                'field_key' => 'company',
                'field_label' => 'Company',
                'field_type' => 'text',
                'show_in_form' => 1
            ],
            [
                'field_key' => 'phone',
                'field_label' => 'Phone',
                'field_type' => 'text',
                'show_in_form' => 1
            ],
            [
                'field_key' => 'birthday',
                'field_label' => 'Birthday',
                'field_type' => 'date',
                'show_in_form' => 0
            ],
            [
                'field_key' => 'country',
                'field_label' => 'Country',
                'field_type' => 'select',
                'field_options' => ['USA', 'Canada', 'UK', 'Turkey', 'Other'],
                'show_in_form' => 1
            ],
            [
                'field_key' => 'interests',
                'field_label' => 'Interests',
                'field_type' => 'textarea',
                'show_in_form' => 0
            ]
        ];

        foreach ($default_fields as $field) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->table_fields} WHERE field_key = %s",
                $field['field_key']
            ));

            if (!$existing) {
                $this->create_field($field);
            }
        }
    }

    /**
     * AJAX: Create custom field
     */
    public function ajax_create_field() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $field_id = $this->create_field($_POST);

        if ($field_id) {
            wp_send_json_success([
                'message' => 'Custom field created',
                'field_id' => $field_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to create custom field']);
        }
    }

    /**
     * AJAX: Get custom fields
     */
    public function ajax_get_fields() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $fields = $this->get_all_fields();
        wp_send_json_success(['fields' => $fields]);
    }

    /**
     * AJAX: Update subscriber field
     */
    public function ajax_update_subscriber_field() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $subscriber_id = intval($_POST['subscriber_id'] ?? 0);
        $field_key = sanitize_key($_POST['field_key'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        $result = $this->update_subscriber_field($subscriber_id, $field_key, $value);

        if ($result !== false) {
            wp_send_json_success(['message' => 'Field updated']);
        } else {
            wp_send_json_error(['message' => 'Failed to update field']);
        }
    }
}
