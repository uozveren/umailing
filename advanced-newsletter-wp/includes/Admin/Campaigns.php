<?php
namespace AdvancedNewsletter\Admin;

use AdvancedNewsletter\Core\EmailSender;

class Campaigns {

    private $wpdb;
    private $table_campaigns;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_campaigns = $wpdb->prefix . 'advnews_campaigns';

        add_action('wp_ajax_advnews_get_campaigns', [$this, 'ajax_get_campaigns']);
        add_action('wp_ajax_advnews_save_campaign', [$this, 'ajax_save_campaign']);
        add_action('wp_ajax_advnews_delete_campaign', [$this, 'ajax_delete_campaign']);
        add_action('wp_ajax_advnews_send_campaign', [$this, 'ajax_send_campaign']);
        add_action('wp_ajax_advnews_schedule_campaign', [$this, 'ajax_schedule_campaign']);
        add_action('wp_ajax_advnews_send_test_email', [$this, 'ajax_send_test_email']);
    }

    public function ajax_get_campaigns() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);

        $where = ['1=1'];

        if ($status) {
            $where[] = $this->wpdb->prepare("status = %s", $status);
        }

        if ($search) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = $this->wpdb->prepare("(name LIKE %s OR subject LIKE %s)", $search_like, $search_like);
        }

        $where_clause = implode(' AND ', $where);

        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_campaigns}
            WHERE {$where_clause}
            ORDER BY created_date DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_campaigns} WHERE {$where_clause}");

        wp_send_json_success([
            'campaigns' => $campaigns,
            'total' => (int) $total
        ]);
    }

    public function ajax_save_campaign() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'subject' => sanitize_text_field($_POST['subject'] ?? ''),
            'preheader' => sanitize_text_field($_POST['preheader'] ?? ''),
            'from_name' => sanitize_text_field($_POST['from_name'] ?? ''),
            'from_email' => sanitize_email($_POST['from_email'] ?? ''),
            'reply_to' => sanitize_email($_POST['reply_to'] ?? ''),
            'template_id' => intval($_POST['template_id'] ?? 0),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft')
        ];

        if (empty($data['name']) || empty($data['subject'])) {
            wp_send_json_error(['message' => 'Name and subject are required']);
        }

        if ($id) {
            // Update existing campaign
            $data['updated_date'] = current_time('mysql');
            $result = $this->wpdb->update($this->table_campaigns, $data, ['id' => $id]);
        } else {
            // Create new campaign
            $data['created_by'] = get_current_user_id();
            $data['created_date'] = current_time('mysql');
            $result = $this->wpdb->insert($this->table_campaigns, $data);
            $id = $this->wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to save campaign']);
        }

        // Update campaign lists
        if (isset($_POST['lists'])) {
            $this->update_campaign_lists($id, $_POST['lists']);
        }

        wp_send_json_success([
            'message' => 'Campaign saved',
            'campaign_id' => $id
        ]);
    }

    public function ajax_delete_campaign() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }

        $result = $this->wpdb->delete($this->table_campaigns, ['id' => $id]);

        if ($result) {
            // Clean up related data
            $table_campaign_lists = $this->wpdb->prefix . 'advnews_campaign_lists';
            $table_queue = $this->wpdb->prefix . 'advnews_queue';

            $this->wpdb->delete($table_campaign_lists, ['campaign_id' => $id]);
            $this->wpdb->delete($table_queue, ['campaign_id' => $id]);

            wp_send_json_success(['message' => 'Campaign deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete campaign']);
        }
    }

    public function ajax_send_campaign() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }

        $sender = new EmailSender();
        $recipients = $sender->create_campaign_queue($id);

        if ($recipients === false) {
            wp_send_json_error(['message' => 'Campaign not found']);
        }

        if ($recipients === 0) {
            wp_send_json_error(['message' => 'No recipients found']);
        }

        wp_send_json_success([
            'message' => sprintf('Campaign queued for %d recipients', $recipients),
            'recipients' => $recipients
        ]);
    }

    public function ajax_schedule_campaign() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);
        $scheduled_date = sanitize_text_field($_POST['scheduled_date'] ?? '');

        if (!$id || !$scheduled_date) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $result = $this->wpdb->update(
            $this->table_campaigns,
            [
                'status' => 'scheduled',
                'scheduled_date' => $scheduled_date
            ],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to schedule campaign']);
        }

        wp_send_json_success(['message' => 'Campaign scheduled']);
    }

    public function ajax_send_test_email() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? 'Test Email');

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $result = wp_mail($email, $subject, $content, $headers);

        if ($result) {
            wp_send_json_success(['message' => 'Test email sent']);
        } else {
            wp_send_json_error(['message' => 'Failed to send test email']);
        }
    }

    private function update_campaign_lists($campaign_id, $lists) {
        $table_campaign_lists = $this->wpdb->prefix . 'advnews_campaign_lists';

        // Delete existing
        $this->wpdb->delete($table_campaign_lists, ['campaign_id' => $campaign_id]);

        // Insert new
        if (!empty($lists) && is_array($lists)) {
            foreach ($lists as $list_id) {
                $this->wpdb->insert($table_campaign_lists, [
                    'campaign_id' => $campaign_id,
                    'list_id' => intval($list_id)
                ]);
            }
        }
    }
}
