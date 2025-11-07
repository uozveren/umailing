<?php
namespace AdvancedNewsletter\Core;

class EmailSender {

    private $wpdb;
    private $table_queue;
    private $table_campaigns;
    private $table_subscribers;
    private $table_opens;
    private $table_clicks;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_queue = $wpdb->prefix . 'advnews_queue';
        $this->table_campaigns = $wpdb->prefix . 'advnews_campaigns';
        $this->table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $this->table_opens = $wpdb->prefix . 'advnews_opens';
        $this->table_clicks = $wpdb->prefix . 'advnews_clicks';
    }

    /**
     * Process email queue
     */
    public function process_queue() {
        $emails_per_batch = $this->get_setting('emails_per_batch', 50);

        // Get pending emails from queue
        $queue_items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_queue}
            WHERE status = 'pending' AND attempts < max_attempts
            ORDER BY priority DESC, created_date ASC
            LIMIT %d",
            $emails_per_batch
        ));

        foreach ($queue_items as $item) {
            $this->send_queue_item($item);
        }
    }

    /**
     * Send individual queue item
     */
    private function send_queue_item($item) {
        // Update attempts
        $this->wpdb->update(
            $this->table_queue,
            ['attempts' => $item->attempts + 1],
            ['id' => $item->id]
        );

        // Get campaign
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_campaigns} WHERE id = %d",
            $item->campaign_id
        ));

        if (!$campaign) {
            $this->mark_queue_item_failed($item->id, 'Campaign not found');
            return;
        }

        // Get subscriber
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_subscribers} WHERE id = %d",
            $item->subscriber_id
        ));

        if (!$subscriber) {
            $this->mark_queue_item_failed($item->id, 'Subscriber not found');
            return;
        }

        // Prepare email content
        $content = $this->prepare_email_content($campaign, $subscriber);
        $subject = $this->replace_placeholders($campaign->subject, $subscriber, $campaign);

        // Get sender info
        $from_name = $campaign->from_name ?: $this->get_setting('sender_name', get_bloginfo('name'));
        $from_email = $campaign->from_email ?: $this->get_setting('sender_email', get_option('admin_email'));
        $reply_to = $campaign->reply_to ?: $this->get_setting('reply_to', get_option('admin_email'));

        // Prepare headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
            sprintf('Reply-To: %s', $reply_to)
        ];

        // Add tracking pixel if enabled
        if ($this->get_setting('tracking_enabled', '1') === '1') {
            $content = $this->add_tracking_pixel($content, $campaign->id, $subscriber->id);
            $content = $this->add_click_tracking($content, $campaign->id, $subscriber->id);
        }

        // Send email
        $result = wp_mail($subscriber->email, $subject, $content, $headers);

        if ($result) {
            // Mark as sent
            $this->wpdb->update(
                $this->table_queue,
                [
                    'status' => 'sent',
                    'sent_date' => current_time('mysql')
                ],
                ['id' => $item->id]
            );

            // Update campaign stats
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->table_campaigns} SET total_sent = total_sent + 1 WHERE id = %d",
                $campaign->id
            ));
        } else {
            // Mark as failed if max attempts reached
            if ($item->attempts + 1 >= $item->max_attempts) {
                $this->mark_queue_item_failed($item->id, 'Failed to send after max attempts');
            }
        }
    }

    /**
     * Prepare email content
     */
    private function prepare_email_content($campaign, $subscriber) {
        $content = $campaign->content;

        // Get template if specified
        if ($campaign->template_id) {
            $table_templates = $this->wpdb->prefix . 'advnews_templates';
            $template = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$table_templates} WHERE id = %d",
                $campaign->template_id
            ));

            if ($template) {
                $content = str_replace('{{content}}', $content, $template->content);
            }
        }

        // Replace placeholders
        $content = $this->replace_placeholders($content, $subscriber, $campaign);

        return $content;
    }

    /**
     * Replace placeholders in content
     */
    private function replace_placeholders($content, $subscriber, $campaign) {
        $unsubscribe_url = add_query_arg([
            'advnews_action' => 'unsubscribe',
            'token' => $subscriber->unsubscribe_token
        ], home_url());

        $placeholders = [
            '{{email}}' => $subscriber->email,
            '{{name}}' => $subscriber->name ?: $subscriber->email,
            '{{first_name}}' => $this->get_first_name($subscriber->name),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{subject}}' => $campaign->subject ?? '',
            '{{preheader}}' => $campaign->preheader ?? '',
            '{{year}}' => date('Y'),
            '{{unsubscribe_url}}' => $unsubscribe_url,
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Get first name from full name
     */
    private function get_first_name($full_name) {
        if (empty($full_name)) {
            return '';
        }
        $parts = explode(' ', $full_name);
        return $parts[0];
    }

    /**
     * Add tracking pixel
     */
    private function add_tracking_pixel($content, $campaign_id, $subscriber_id) {
        $tracking_url = add_query_arg([
            'advnews_action' => 'track_open',
            'c' => $campaign_id,
            's' => $subscriber_id,
            't' => md5($campaign_id . $subscriber_id . wp_salt())
        ], home_url());

        $pixel = sprintf('<img src="%s" width="1" height="1" border="0" alt="" />', $tracking_url);

        // Add before closing body tag or at the end
        if (stripos($content, '</body>') !== false) {
            $content = str_ireplace('</body>', $pixel . '</body>', $content);
        } else {
            $content .= $pixel;
        }

        return $content;
    }

    /**
     * Add click tracking to links
     */
    private function add_click_tracking($content, $campaign_id, $subscriber_id) {
        // Find all links
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href=([\"\'])(.*?)\1/i', $content, $matches);

        if (!empty($matches[2])) {
            foreach ($matches[2] as $url) {
                // Skip unsubscribe and tracking URLs
                if (strpos($url, 'advnews_action') !== false) {
                    continue;
                }

                $tracking_url = add_query_arg([
                    'advnews_action' => 'track_click',
                    'c' => $campaign_id,
                    's' => $subscriber_id,
                    'url' => urlencode($url),
                    't' => md5($campaign_id . $subscriber_id . $url . wp_salt())
                ], home_url());

                $content = str_replace('href="' . $url . '"', 'href="' . $tracking_url . '"', $content);
                $content = str_replace("href='" . $url . "'", "href='" . $tracking_url . "'", $content);
            }
        }

        return $content;
    }

    /**
     * Track email open
     */
    public function track_open($campaign_id, $subscriber_id, $token) {
        // Verify token
        if ($token !== md5($campaign_id . $subscriber_id . wp_salt())) {
            return false;
        }

        // Check if already tracked
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_opens} WHERE campaign_id = %d AND subscriber_id = %d",
            $campaign_id,
            $subscriber_id
        ));

        if (!$existing) {
            $this->wpdb->insert($this->table_opens, [
                'campaign_id' => $campaign_id,
                'subscriber_id' => $subscriber_id,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'opened_date' => current_time('mysql')
            ]);

            // Update campaign stats
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->table_campaigns} SET total_opens = total_opens + 1 WHERE id = %d",
                $campaign_id
            ));

            // Trigger action hooks for lead scoring and webhooks
            do_action('advnews_email_opened', $subscriber_id, $campaign_id);
        }

        // Return 1x1 transparent GIF
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * Track click
     */
    public function track_click($campaign_id, $subscriber_id, $url, $token) {
        // Verify token
        if ($token !== md5($campaign_id . $subscriber_id . $url . wp_salt())) {
            wp_redirect($url);
            exit;
        }

        // Track click
        $this->wpdb->insert($this->table_clicks, [
            'campaign_id' => $campaign_id,
            'subscriber_id' => $subscriber_id,
            'url' => $url,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'clicked_date' => current_time('mysql')
        ]);

        // Trigger action hooks for lead scoring and webhooks
        do_action('advnews_email_clicked', $subscriber_id, $campaign_id, $url);

        // Update campaign stats (unique clicks)
        $unique_clicks = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT subscriber_id) FROM {$this->table_clicks} WHERE campaign_id = %d",
            $campaign_id
        ));

        $this->wpdb->update(
            $this->table_campaigns,
            ['total_clicks' => $unique_clicks],
            ['id' => $campaign_id]
        );

        // Redirect to original URL
        wp_redirect($url);
        exit;
    }

    /**
     * Create campaign queue
     */
    public function create_campaign_queue($campaign_id) {
        // Get campaign
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_campaigns} WHERE id = %d",
            $campaign_id
        ));

        if (!$campaign) {
            return false;
        }

        // Get recipients (subscribers in campaign lists)
        $table_campaign_lists = $this->wpdb->prefix . 'advnews_campaign_lists';
        $table_subscriber_lists = $this->wpdb->prefix . 'advnews_subscriber_lists';

        $recipients = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT s.id, s.email
            FROM {$this->table_subscribers} s
            INNER JOIN {$table_subscriber_lists} sl ON s.id = sl.subscriber_id
            INNER JOIN {$table_campaign_lists} cl ON sl.list_id = cl.list_id
            WHERE cl.campaign_id = %d AND s.status = 'active'",
            $campaign_id
        ));

        // Add to queue
        foreach ($recipients as $recipient) {
            $this->wpdb->insert($this->table_queue, [
                'campaign_id' => $campaign_id,
                'subscriber_id' => $recipient->id,
                'email' => $recipient->email,
                'status' => 'pending',
                'priority' => 5,
                'attempts' => 0,
                'max_attempts' => 3,
                'created_date' => current_time('mysql')
            ]);
        }

        // Update campaign
        $this->wpdb->update(
            $this->table_campaigns,
            [
                'status' => 'sending',
                'total_recipients' => count($recipients)
            ],
            ['id' => $campaign_id]
        );

        return count($recipients);
    }

    /**
     * Mark queue item as failed
     */
    private function mark_queue_item_failed($queue_id, $error_message) {
        $this->wpdb->update(
            $this->table_queue,
            [
                'status' => 'failed',
                'error_message' => $error_message
            ],
            ['id' => $queue_id]
        );
    }

    /**
     * Get client IP
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return sanitize_text_field($ip);
    }

    /**
     * Get setting
     */
    private function get_setting($key, $default = '') {
        $table_settings = $this->wpdb->prefix . 'advnews_settings';
        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$table_settings} WHERE setting_key = %s",
            $key
        ));

        return $value !== null ? $value : $default;
    }
}
