<?php
namespace AdvancedNewsletter\Admin;

use AdvancedNewsletter\Core\Subscriber;

class Subscribers {

    public function __construct() {
        add_action('wp_ajax_advnews_get_subscribers', [$this, 'ajax_get_subscribers']);
        add_action('wp_ajax_advnews_add_subscriber', [$this, 'ajax_add_subscriber']);
        add_action('wp_ajax_advnews_delete_subscriber', [$this, 'ajax_delete_subscriber']);
        add_action('wp_ajax_advnews_bulk_delete_subscribers', [$this, 'ajax_bulk_delete']);
        add_action('wp_ajax_advnews_export_subscribers', [$this, 'ajax_export_subscribers']);
        add_action('wp_ajax_advnews_import_subscribers', [$this, 'ajax_import_subscribers']);
    }

    public function ajax_get_subscribers() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $subscriber = new Subscriber();

        $args = [
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'list_id' => intval($_POST['list_id'] ?? 0),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'subscription_date'),
            'order' => sanitize_text_field($_POST['order'] ?? 'DESC'),
            'limit' => intval($_POST['limit'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0)
        ];

        $subscribers = $subscriber->get_all($args);
        $total = $subscriber->get_count($args);

        wp_send_json_success([
            'subscribers' => $subscribers,
            'total' => $total
        ]);
    }

    public function ajax_add_subscriber() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $subscriber = new Subscriber();
        $result = $subscriber->subscribe($_POST);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_delete_subscriber() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }

        $subscriber = new Subscriber();
        $result = $subscriber->delete($id);

        if ($result) {
            wp_send_json_success(['message' => 'Subscriber deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete subscriber']);
        }
    }

    public function ajax_bulk_delete() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            wp_send_json_error(['message' => 'No subscribers selected']);
        }

        $subscriber = new Subscriber();
        $result = $subscriber->bulk_delete($ids);

        if ($result) {
            wp_send_json_success(['message' => 'Subscribers deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete subscribers']);
        }
    }

    public function ajax_export_subscribers() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $subscriber = new Subscriber();

        $args = [
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'list_id' => intval($_POST['list_id'] ?? 0),
            'limit' => 999999,
            'offset' => 0
        ];

        $subscribers = $subscriber->get_all($args);

        // Generate CSV
        $csv = "Email,Name,Status,Subscription Date\n";

        foreach ($subscribers as $sub) {
            $csv .= sprintf(
                '"%s","%s","%s","%s"' . "\n",
                $sub->email,
                $sub->name,
                $sub->status,
                $sub->subscription_date
            );
        }

        wp_send_json_success([
            'csv' => $csv,
            'filename' => 'subscribers-' . date('Y-m-d') . '.csv'
        ]);
    }

    public function ajax_import_subscribers() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }

        $file = $_FILES['file'];
        $csv = array_map('str_getcsv', file($file['tmp_name']));
        $headers = array_shift($csv);

        $subscriber = new Subscriber();
        $imported = 0;
        $skipped = 0;

        foreach ($csv as $row) {
            $data = array_combine($headers, $row);

            if (empty($data['email']) || !is_email($data['email'])) {
                $skipped++;
                continue;
            }

            $result = $subscriber->subscribe([
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'source' => 'import',
                'gdpr_consent' => 1
            ]);

            if ($result['success']) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        wp_send_json_success([
            'message' => sprintf('Imported %d subscribers, skipped %d', $imported, $skipped),
            'imported' => $imported,
            'skipped' => $skipped
        ]);
    }
}
