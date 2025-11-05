<?php
namespace AdvancedNewsletter\Admin;

class Analytics {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        add_action('wp_ajax_advnews_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_advnews_get_campaign_analytics', [$this, 'ajax_get_campaign_analytics']);
    }

    public function ajax_get_analytics() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $period = sanitize_text_field($_POST['period'] ?? 'month'); // week, month, year

        $analytics = [
            'overview' => $this->get_overview_stats(),
            'growth' => $this->get_subscriber_growth($period),
            'engagement' => $this->get_engagement_stats($period),
            'top_campaigns' => $this->get_top_campaigns($period)
        ];

        wp_send_json_success($analytics);
    }

    public function ajax_get_campaign_analytics() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $campaign_id = intval($_POST['campaign_id'] ?? 0);

        if (!$campaign_id) {
            wp_send_json_error(['message' => 'Invalid campaign ID']);
        }

        $analytics = [
            'summary' => $this->get_campaign_summary($campaign_id),
            'opens_over_time' => $this->get_opens_over_time($campaign_id),
            'clicks_by_url' => $this->get_clicks_by_url($campaign_id),
            'top_subscribers' => $this->get_top_engaged_subscribers($campaign_id)
        ];

        wp_send_json_success($analytics);
    }

    private function get_overview_stats() {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';
        $table_opens = $this->wpdb->prefix . 'advnews_opens';
        $table_clicks = $this->wpdb->prefix . 'advnews_clicks';

        return [
            'total_subscribers' => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_subscribers} WHERE status = 'active'"),
            'total_campaigns' => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns} WHERE status = 'sent'"),
            'total_opens' => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_opens}"),
            'total_clicks' => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_clicks}"),
            'avg_open_rate' => $this->calculate_average_open_rate(),
            'avg_click_rate' => $this->calculate_average_click_rate()
        ];
    }

    private function get_subscriber_growth($period) {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';

        $date_format = $period === 'week' ? '%Y-%m-%d' : ($period === 'month' ? '%Y-%m-%d' : '%Y-%m');
        $date_range = $period === 'week' ? 'DATE_SUB(NOW(), INTERVAL 7 DAY)' : ($period === 'month' ? 'DATE_SUB(NOW(), INTERVAL 30 DAY)' : 'DATE_SUB(NOW(), INTERVAL 1 YEAR)');

        $growth = $this->wpdb->get_results("
            SELECT DATE_FORMAT(subscription_date, '{$date_format}') as date,
                   COUNT(*) as count
            FROM {$table_subscribers}
            WHERE subscription_date >= {$date_range}
                  AND status = 'active'
            GROUP BY date
            ORDER BY date ASC
        ");

        return $growth;
    }

    private function get_engagement_stats($period) {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';
        $table_clicks = $this->wpdb->prefix . 'advnews_clicks';

        $date_format = $period === 'week' ? '%Y-%m-%d' : ($period === 'month' ? '%Y-%m-%d' : '%Y-%m');
        $date_range = $period === 'week' ? 'DATE_SUB(NOW(), INTERVAL 7 DAY)' : ($period === 'month' ? 'DATE_SUB(NOW(), INTERVAL 30 DAY)' : 'DATE_SUB(NOW(), INTERVAL 1 YEAR)');

        $opens = $this->wpdb->get_results("
            SELECT DATE_FORMAT(opened_date, '{$date_format}') as date,
                   COUNT(*) as count
            FROM {$table_opens}
            WHERE opened_date >= {$date_range}
            GROUP BY date
            ORDER BY date ASC
        ");

        $clicks = $this->wpdb->get_results("
            SELECT DATE_FORMAT(clicked_date, '{$date_format}') as date,
                   COUNT(*) as count
            FROM {$table_clicks}
            WHERE clicked_date >= {$date_range}
            GROUP BY date
            ORDER BY date ASC
        ");

        return [
            'opens' => $opens,
            'clicks' => $clicks
        ];
    }

    private function get_top_campaigns($period) {
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';
        $date_range = $period === 'week' ? 'DATE_SUB(NOW(), INTERVAL 7 DAY)' : ($period === 'month' ? 'DATE_SUB(NOW(), INTERVAL 30 DAY)' : 'DATE_SUB(NOW(), INTERVAL 1 YEAR)');

        return $this->wpdb->get_results("
            SELECT id, name, subject, total_sent, total_opens, total_clicks,
                   ROUND((total_opens / NULLIF(total_sent, 0)) * 100, 2) as open_rate,
                   ROUND((total_clicks / NULLIF(total_sent, 0)) * 100, 2) as click_rate
            FROM {$table_campaigns}
            WHERE status = 'sent' AND sent_date >= {$date_range}
            ORDER BY total_opens DESC
            LIMIT 10
        ");
    }

    private function get_campaign_summary($campaign_id) {
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';

        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_campaigns} WHERE id = %d",
            $campaign_id
        ));

        if (!$campaign) {
            return null;
        }

        $open_rate = $campaign->total_sent > 0 ? round(($campaign->total_opens / $campaign->total_sent) * 100, 2) : 0;
        $click_rate = $campaign->total_sent > 0 ? round(($campaign->total_clicks / $campaign->total_sent) * 100, 2) : 0;

        return [
            'campaign' => $campaign,
            'open_rate' => $open_rate,
            'click_rate' => $click_rate,
            'bounce_rate' => $campaign->total_sent > 0 ? round(($campaign->total_bounces / $campaign->total_sent) * 100, 2) : 0,
            'unsubscribe_rate' => $campaign->total_sent > 0 ? round(($campaign->total_unsubscribes / $campaign->total_sent) * 100, 2) : 0
        ];
    }

    private function get_opens_over_time($campaign_id) {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT DATE_FORMAT(opened_date, '%%Y-%%m-%%d %%H:00:00') as hour,
                   COUNT(*) as count
            FROM {$table_opens}
            WHERE campaign_id = %d
            GROUP BY hour
            ORDER BY hour ASC
        ", $campaign_id));
    }

    private function get_clicks_by_url($campaign_id) {
        $table_clicks = $this->wpdb->prefix . 'advnews_clicks';

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT url, COUNT(*) as clicks, COUNT(DISTINCT subscriber_id) as unique_clicks
            FROM {$table_clicks}
            WHERE campaign_id = %d
            GROUP BY url
            ORDER BY clicks DESC
            LIMIT 20
        ", $campaign_id));
    }

    private function get_top_engaged_subscribers($campaign_id) {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';
        $table_clicks = $this->wpdb->prefix . 'advnews_clicks';
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT s.id, s.email, s.name,
                   COUNT(DISTINCT o.id) as opens,
                   COUNT(DISTINCT c.id) as clicks
            FROM {$table_subscribers} s
            LEFT JOIN {$table_opens} o ON s.id = o.subscriber_id AND o.campaign_id = %d
            LEFT JOIN {$table_clicks} c ON s.id = c.subscriber_id AND c.campaign_id = %d
            WHERE (o.id IS NOT NULL OR c.id IS NOT NULL)
            GROUP BY s.id
            ORDER BY (opens + clicks) DESC
            LIMIT 20
        ", $campaign_id, $campaign_id));
    }

    private function calculate_average_open_rate() {
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';

        $result = $this->wpdb->get_row("
            SELECT AVG(CASE WHEN total_sent > 0 THEN (total_opens / total_sent) * 100 ELSE 0 END) as avg_rate
            FROM {$table_campaigns}
            WHERE status = 'sent' AND total_sent > 0
        ");

        return $result ? round($result->avg_rate, 2) : 0;
    }

    private function calculate_average_click_rate() {
        $table_campaigns = $this->wpdb->prefix . 'advnews_campaigns';

        $result = $this->wpdb->get_row("
            SELECT AVG(CASE WHEN total_sent > 0 THEN (total_clicks / total_sent) * 100 ELSE 0 END) as avg_rate
            FROM {$table_campaigns}
            WHERE status = 'sent' AND total_sent > 0
        ");

        return $result ? round($result->avg_rate, 2) : 0;
    }
}
