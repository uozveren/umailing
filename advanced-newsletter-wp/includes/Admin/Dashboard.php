<?php
namespace AdvancedNewsletter\Admin;

class Dashboard {

    public function __construct() {
        // Constructor can be used for hooks if needed
    }

    public function get_stats() {
        global $wpdb;

        $table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $table_campaigns = $wpdb->prefix . 'advnews_campaigns';
        $table_opens = $wpdb->prefix . 'advnews_opens';
        $table_clicks = $wpdb->prefix . 'advnews_clicks';

        // Get subscriber stats
        $total_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_subscribers} WHERE status = 'active'");
        $pending_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_subscribers} WHERE status = 'pending'");
        $unsubscribed = $wpdb->get_var("SELECT COUNT(*) FROM {$table_subscribers} WHERE status = 'unsubscribed'");

        // Get new subscribers this month
        $new_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_subscribers}
            WHERE status = 'active' AND MONTH(subscription_date) = %d AND YEAR(subscription_date) = %d",
            date('m'),
            date('Y')
        ));

        // Get campaign stats
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns}");
        $sent_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns} WHERE status = 'sent'");
        $draft_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns} WHERE status = 'draft'");
        $scheduled_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns} WHERE status = 'scheduled'");

        // Get engagement stats
        $total_opens = $wpdb->get_var("SELECT COUNT(*) FROM {$table_opens}");
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$table_clicks}");

        // Get recent campaigns
        $recent_campaigns = $wpdb->get_results(
            "SELECT * FROM {$table_campaigns}
            ORDER BY created_date DESC
            LIMIT 5"
        );

        return [
            'subscribers' => [
                'total' => (int) $total_subscribers,
                'pending' => (int) $pending_subscribers,
                'unsubscribed' => (int) $unsubscribed,
                'new_this_month' => (int) $new_this_month
            ],
            'campaigns' => [
                'total' => (int) $total_campaigns,
                'sent' => (int) $sent_campaigns,
                'draft' => (int) $draft_campaigns,
                'scheduled' => (int) $scheduled_campaigns
            ],
            'engagement' => [
                'total_opens' => (int) $total_opens,
                'total_clicks' => (int) $total_clicks
            ],
            'recent_campaigns' => $recent_campaigns
        ];
    }
}
