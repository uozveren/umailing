<?php
namespace AdvancedNewsletter\Core;

class Webhooks {

    private $wpdb;
    private $table_webhooks;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_webhooks = $wpdb->prefix . 'advnews_webhooks';

        // Register webhook triggers
        add_action('advnews_subscriber_subscribed', [$this, 'trigger_subscriber_subscribed'], 10, 1);
        add_action('advnews_subscriber_unsubscribed', [$this, 'trigger_subscriber_unsubscribed'], 10, 1);
        add_action('advnews_campaign_sent', [$this, 'trigger_campaign_sent'], 10, 1);
        add_action('advnews_email_opened', [$this, 'trigger_email_opened'], 10, 2);
        add_action('advnews_email_clicked', [$this, 'trigger_email_clicked'], 10, 3);

        // Admin AJAX
        add_action('wp_ajax_advnews_create_webhook', [$this, 'ajax_create_webhook']);
        add_action('wp_ajax_advnews_get_webhooks', [$this, 'ajax_get_webhooks']);
        add_action('wp_ajax_advnews_test_webhook', [$this, 'ajax_test_webhook']);
    }

    /**
     * Create webhooks table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'advnews_webhooks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url text NOT NULL,
            event varchar(100) NOT NULL,
            method varchar(10) DEFAULT 'POST',
            headers text DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            last_triggered datetime DEFAULT NULL,
            last_response_code int(11) DEFAULT NULL,
            last_error text DEFAULT NULL,
            trigger_count int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create a webhook
     */
    public function create_webhook($data) {
        $webhook_data = [
            'name' => sanitize_text_field($data['name']),
            'url' => esc_url_raw($data['url']),
            'event' => sanitize_text_field($data['event']),
            'method' => sanitize_text_field($data['method'] ?? 'POST'),
            'headers' => isset($data['headers']) ? json_encode($data['headers']) : null,
            'status' => 'active',
            'created_date' => current_time('mysql')
        ];

        $result = $this->wpdb->insert($this->table_webhooks, $webhook_data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Trigger webhooks for an event
     */
    public function trigger($event, $data) {
        $webhooks = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_webhooks} WHERE event = %s AND status = 'active'",
            $event
        ));

        foreach ($webhooks as $webhook) {
            $this->send_webhook($webhook, $data);
        }
    }

    /**
     * Send webhook HTTP request
     */
    private function send_webhook($webhook, $data) {
        $headers = $webhook->headers ? json_decode($webhook->headers, true) : [];
        $headers['Content-Type'] = 'application/json';

        $args = [
            'method' => $webhook->method,
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 30
        ];

        $response = wp_remote_request($webhook->url, $args);

        $response_code = wp_remote_retrieve_response_code($response);
        $error = is_wp_error($response) ? $response->get_error_message() : null;

        // Update webhook stats
        $this->wpdb->update(
            $this->table_webhooks,
            [
                'last_triggered' => current_time('mysql'),
                'last_response_code' => $response_code,
                'last_error' => $error,
                'trigger_count' => $webhook->trigger_count + 1
            ],
            ['id' => $webhook->id]
        );

        return !is_wp_error($response) && $response_code >= 200 && $response_code < 300;
    }

    /**
     * Trigger: Subscriber subscribed
     */
    public function trigger_subscriber_subscribed($subscriber_id) {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_subscribers} WHERE id = %d",
            $subscriber_id
        ));

        if ($subscriber) {
            $this->trigger('subscriber.subscribed', [
                'event' => 'subscriber.subscribed',
                'subscriber' => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'name' => $subscriber->name,
                    'status' => $subscriber->status,
                    'subscription_date' => $subscriber->subscription_date
                ],
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Trigger: Subscriber unsubscribed
     */
    public function trigger_subscriber_unsubscribed($subscriber_id) {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_subscribers} WHERE id = %d",
            $subscriber_id
        ));

        if ($subscriber) {
            $this->trigger('subscriber.unsubscribed', [
                'event' => 'subscriber.unsubscribed',
                'subscriber' => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'name' => $subscriber->name
                ],
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Trigger: Campaign sent
     */
    public function trigger_campaign_sent($campaign_id) {
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_campaigns} WHERE id = %d",
            $campaign_id
        ));

        if ($campaign) {
            $this->trigger('campaign.sent', [
                'event' => 'campaign.sent',
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'subject' => $campaign->subject,
                    'total_recipients' => $campaign->total_recipients,
                    'sent_date' => $campaign->sent_date
                ],
                'timestamp' => current_time('mysql')
            ]);
        }
    }

    /**
     * Trigger: Email opened
     */
    public function trigger_email_opened($subscriber_id, $campaign_id) {
        $this->trigger('email.opened', [
            'event' => 'email.opened',
            'subscriber_id' => $subscriber_id,
            'campaign_id' => $campaign_id,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Trigger: Email clicked
     */
    public function trigger_email_clicked($subscriber_id, $campaign_id, $url) {
        $this->trigger('email.clicked', [
            'event' => 'email.clicked',
            'subscriber_id' => $subscriber_id,
            'campaign_id' => $campaign_id,
            'url' => $url,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Available webhook events
     */
    public function get_available_events() {
        return [
            'subscriber.subscribed' => 'When a subscriber signs up',
            'subscriber.unsubscribed' => 'When a subscriber unsubscribes',
            'subscriber.confirmed' => 'When a subscriber confirms email',
            'campaign.sent' => 'When a campaign is sent',
            'email.opened' => 'When an email is opened',
            'email.clicked' => 'When a link is clicked',
            'bounce.received' => 'When an email bounces'
        ];
    }

    /**
     * AJAX: Create webhook
     */
    public function ajax_create_webhook() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $webhook_id = $this->create_webhook($_POST);

        if ($webhook_id) {
            wp_send_json_success([
                'message' => 'Webhook created',
                'webhook_id' => $webhook_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to create webhook']);
        }
    }

    /**
     * AJAX: Get webhooks
     */
    public function ajax_get_webhooks() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $webhooks = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_webhooks} ORDER BY created_date DESC"
        );

        wp_send_json_success(['webhooks' => $webhooks]);
    }

    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $webhook_id = intval($_POST['webhook_id'] ?? 0);

        $webhook = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_webhooks} WHERE id = %d",
            $webhook_id
        ));

        if (!$webhook) {
            wp_send_json_error(['message' => 'Webhook not found']);
        }

        $test_data = [
            'event' => 'test',
            'message' => 'This is a test webhook',
            'timestamp' => current_time('mysql')
        ];

        $result = $this->send_webhook($webhook, $test_data);

        if ($result) {
            wp_send_json_success(['message' => 'Webhook test successful']);
        } else {
            wp_send_json_error(['message' => 'Webhook test failed']);
        }
    }
}
