<?php
namespace AdvancedNewsletter\Core;

class RSSToEmail {

    private $wpdb;
    private $table_rss_feeds;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_rss_feeds = $wpdb->prefix . 'advnews_rss_feeds';

        // Schedule RSS check
        add_action('advnews_check_rss_feeds', [$this, 'check_all_feeds']);

        // Admin AJAX
        add_action('wp_ajax_advnews_add_rss_feed', [$this, 'ajax_add_feed']);
        add_action('wp_ajax_advnews_get_rss_feeds', [$this, 'ajax_get_feeds']);
        add_action('wp_ajax_advnews_test_rss_feed', [$this, 'ajax_test_feed']);
    }

    /**
     * Create RSS feeds table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'advnews_rss_feeds';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            feed_url text NOT NULL,
            template_id bigint(20) DEFAULT NULL,
            list_id bigint(20) DEFAULT NULL,
            frequency varchar(20) DEFAULT 'daily',
            send_time varchar(10) DEFAULT '09:00',
            max_items int(11) DEFAULT 5,
            last_check datetime DEFAULT NULL,
            last_sent datetime DEFAULT NULL,
            last_item_date datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            email_subject varchar(500) DEFAULT NULL,
            email_preheader varchar(255) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add RSS feed
     */
    public function add_feed($data) {
        $feed_data = [
            'name' => sanitize_text_field($data['name']),
            'feed_url' => esc_url_raw($data['feed_url']),
            'template_id' => intval($data['template_id'] ?? 0),
            'list_id' => intval($data['list_id'] ?? 0),
            'frequency' => sanitize_text_field($data['frequency'] ?? 'daily'),
            'send_time' => sanitize_text_field($data['send_time'] ?? '09:00'),
            'max_items' => intval($data['max_items'] ?? 5),
            'status' => 'active',
            'email_subject' => sanitize_text_field($data['email_subject'] ?? '{{site_name}} - Latest Updates'),
            'email_preheader' => sanitize_text_field($data['email_preheader'] ?? ''),
            'created_date' => current_time('mysql')
        ];

        $result = $this->wpdb->insert($this->table_rss_feeds, $feed_data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Check all active RSS feeds
     */
    public function check_all_feeds() {
        $feeds = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_rss_feeds} WHERE status = 'active'"
        );

        foreach ($feeds as $feed) {
            if ($this->should_check_feed($feed)) {
                $this->check_feed($feed);
            }
        }
    }

    /**
     * Check if feed should be checked based on frequency
     */
    private function should_check_feed($feed) {
        if (!$feed->last_check) {
            return true;
        }

        $last_check = strtotime($feed->last_check);
        $current_time = current_time('timestamp');

        switch ($feed->frequency) {
            case 'hourly':
                return ($current_time - $last_check) >= 3600;
            case 'daily':
                return ($current_time - $last_check) >= 86400;
            case 'weekly':
                return ($current_time - $last_check) >= 604800;
            default:
                return false;
        }
    }

    /**
     * Check RSS feed for new items
     */
    public function check_feed($feed) {
        // Update last check time
        $this->wpdb->update(
            $this->table_rss_feeds,
            ['last_check' => current_time('mysql')],
            ['id' => $feed->id]
        );

        // Fetch RSS feed
        $rss = fetch_feed($feed->feed_url);

        if (is_wp_error($rss)) {
            error_log('RSS Feed Error: ' . $rss->get_error_message());
            return false;
        }

        $maxitems = $rss->get_item_quantity($feed->max_items);
        $rss_items = $rss->get_items(0, $maxitems);

        if (empty($rss_items)) {
            return false;
        }

        // Check for new items
        $new_items = [];
        $last_item_date = $feed->last_item_date ? strtotime($feed->last_item_date) : 0;

        foreach ($rss_items as $item) {
            $item_date = $item->get_date('U');

            if ($item_date > $last_item_date) {
                $new_items[] = [
                    'title' => $item->get_title(),
                    'link' => $item->get_permalink(),
                    'description' => $item->get_description(),
                    'content' => $item->get_content(),
                    'date' => $item->get_date('Y-m-d H:i:s'),
                    'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                    'image' => $this->get_item_image($item)
                ];
            }
        }

        if (!empty($new_items)) {
            // Send email with new items
            $this->send_rss_email($feed, $new_items);

            // Update last item date
            $latest_date = $rss_items[0]->get_date('Y-m-d H:i:s');
            $this->wpdb->update(
                $this->table_rss_feeds,
                [
                    'last_item_date' => $latest_date,
                    'last_sent' => current_time('mysql')
                ],
                ['id' => $feed->id]
            );
        }

        return true;
    }

    /**
     * Get item image
     */
    private function get_item_image($item) {
        // Try enclosure
        $enclosure = $item->get_enclosure();
        if ($enclosure && $enclosure->get_thumbnail()) {
            return $enclosure->get_thumbnail();
        }

        // Try content
        $content = $item->get_content();
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * Send RSS email
     */
    private function send_rss_email($feed, $items) {
        // Build email content
        $content = $this->build_rss_email_content($items);

        // Create campaign
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';

        $campaign_data = [
            'name' => $feed->name . ' - ' . current_time('Y-m-d H:i'),
            'subject' => $feed->email_subject,
            'preheader' => $feed->email_preheader,
            'template_id' => $feed->template_id,
            'content' => $content,
            'status' => 'draft',
            'created_date' => current_time('mysql')
        ];

        $this->wpdb->insert($table_campaigns, $campaign_data);
        $campaign_id = $this->wpdb->insert_id;

        // Add campaign to list
        if ($feed->list_id) {
            $table_campaign_lists = $this->wpdb->prefix . 'advnews_campaign_lists';
            $this->wpdb->insert($table_campaign_lists, [
                'campaign_id' => $campaign_id,
                'list_id' => $feed->list_id
            ]);
        }

        // Send campaign
        $sender = new EmailSender();
        $sender->create_campaign_queue($campaign_id);

        return $campaign_id;
    }

    /**
     * Build RSS email content
     */
    private function build_rss_email_content($items) {
        $html = '<div class="rss-items">';

        foreach ($items as $item) {
            $html .= '<div class="rss-item" style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e1e1e1;">';

            if (!empty($item['image'])) {
                $html .= '<div class="rss-item-image" style="margin-bottom: 15px;">';
                $html .= '<img src="' . esc_url($item['image']) . '" alt="" style="max-width: 100%; height: auto; border-radius: 4px;" />';
                $html .= '</div>';
            }

            $html .= '<h2 style="margin: 0 0 10px; font-size: 24px; line-height: 1.3;">';
            $html .= '<a href="' . esc_url($item['link']) . '" style="color: #333; text-decoration: none;">' . esc_html($item['title']) . '</a>';
            $html .= '</h2>';

            if (!empty($item['author'])) {
                $html .= '<p style="margin: 0 0 10px; color: #666; font-size: 14px;">By ' . esc_html($item['author']) . ' - ' . date('F j, Y', strtotime($item['date'])) . '</p>';
            }

            $html .= '<div style="margin: 15px 0; color: #555; line-height: 1.6;">';
            $html .= wp_kses_post($item['description']);
            $html .= '</div>';

            $html .= '<a href="' . esc_url($item['link']) . '" class="button" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;">Read More</a>';

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Test RSS feed
     */
    public function test_feed($feed_url) {
        $rss = fetch_feed($feed_url);

        if (is_wp_error($rss)) {
            return [
                'success' => false,
                'message' => $rss->get_error_message()
            ];
        }

        $items = $rss->get_items(0, 5);

        return [
            'success' => true,
            'items' => array_map(function($item) {
                return [
                    'title' => $item->get_title(),
                    'link' => $item->get_permalink(),
                    'date' => $item->get_date('Y-m-d H:i:s')
                ];
            }, $items)
        ];
    }

    /**
     * AJAX: Add RSS feed
     */
    public function ajax_add_feed() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $feed_id = $this->add_feed($_POST);

        if ($feed_id) {
            wp_send_json_success([
                'message' => 'RSS feed added',
                'feed_id' => $feed_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add RSS feed']);
        }
    }

    /**
     * AJAX: Get RSS feeds
     */
    public function ajax_get_feeds() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $feeds = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_rss_feeds} ORDER BY created_date DESC"
        );

        wp_send_json_success(['feeds' => $feeds]);
    }

    /**
     * AJAX: Test RSS feed
     */
    public function ajax_test_feed() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $feed_url = esc_url_raw($_POST['feed_url'] ?? '');
        $result = $this->test_feed($feed_url);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
