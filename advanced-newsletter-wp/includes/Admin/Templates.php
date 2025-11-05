<?php
namespace AdvancedNewsletter\Admin;

class Templates {

    private $wpdb;
    private $table_templates;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_templates = $wpdb->prefix . 'advnews_templates';

        add_action('wp_ajax_advnews_get_templates', [$this, 'ajax_get_templates']);
        add_action('wp_ajax_advnews_save_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_advnews_delete_template', [$this, 'ajax_delete_template']);
    }

    public function ajax_get_templates() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $templates = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_templates} ORDER BY is_default DESC, created_date DESC"
        );

        wp_send_json_success(['templates' => $templates]);
    }

    public function ajax_save_template() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'is_default' => intval($_POST['is_default'] ?? 0)
        ];

        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Name is required']);
        }

        if ($id) {
            $data['updated_date'] = current_time('mysql');
            $result = $this->wpdb->update($this->table_templates, $data, ['id' => $id]);
        } else {
            $data['created_date'] = current_time('mysql');
            $result = $this->wpdb->insert($this->table_templates, $data);
            $id = $this->wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to save template']);
        }

        wp_send_json_success([
            'message' => 'Template saved',
            'template_id' => $id
        ]);
    }

    public function ajax_delete_template() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }

        // Check if it's default template
        $template = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_templates} WHERE id = %d",
            $id
        ));

        if ($template && $template->is_default) {
            wp_send_json_error(['message' => 'Cannot delete default template']);
        }

        $result = $this->wpdb->delete($this->table_templates, ['id' => $id]);

        if ($result) {
            wp_send_json_success(['message' => 'Template deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete template']);
        }
    }
}
