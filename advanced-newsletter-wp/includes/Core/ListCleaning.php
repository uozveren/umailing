<?php
namespace AdvancedNewsletter\Core;

class ListCleaning {

    private $wpdb;
    private $table_subscribers;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_subscribers = $wpdb->prefix . 'advnews_subscribers';

        add_action('advnews_clean_lists', [$this, 'run_automated_cleaning']);
        add_action('wp_ajax_advnews_clean_list_preview', [$this, 'ajax_preview_cleaning']);
        add_action('wp_ajax_advnews_clean_list_execute', [$this, 'ajax_execute_cleaning']);
    }

    /**
     * Get cleaning criteria
     */
    public function get_cleaning_criteria() {
        return [
            'inactive_90_days' => [
                'name' => 'Inactive for 90+ days',
                'description' => 'Subscribers who haven\'t opened any email in 90 days',
                'severity' => 'low'
            ],
            'inactive_180_days' => [
                'name' => 'Inactive for 180+ days',
                'description' => 'Subscribers who haven\'t opened any email in 180 days',
                'severity' => 'medium'
            ],
            'inactive_365_days' => [
                'name' => 'Inactive for 365+ days',
                'description' => 'Subscribers who haven\'t opened any email in a year',
                'severity' => 'high'
            ],
            'never_opened' => [
                'name' => 'Never opened',
                'description' => 'Subscribers who never opened any email',
                'severity' => 'medium'
            ],
            'high_bounce_rate' => [
                'name' => 'High bounce rate',
                'description' => 'Subscribers with 3+ bounces',
                'severity' => 'high'
            ],
            'invalid_email' => [
                'name' => 'Invalid email',
                'description' => 'Email addresses that don\'t match valid format',
                'severity' => 'critical'
            ],
            'duplicate_emails' => [
                'name' => 'Duplicate emails',
                'description' => 'Keep only the most recent subscription',
                'severity' => 'medium'
            ]
        ];
    }

    /**
     * Preview cleaning results
     */
    public function preview_cleaning($criteria) {
        $results = [];

        foreach ($criteria as $criterion) {
            $subscribers = $this->get_subscribers_by_criterion($criterion);
            $results[$criterion] = [
                'count' => count($subscribers),
                'sample' => array_slice($subscribers, 0, 10)
            ];
        }

        return $results;
    }

    /**
     * Get subscribers matching criterion
     */
    private function get_subscribers_by_criterion($criterion) {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';
        $table_bounces = $this->wpdb->prefix . 'advnews_bounces';

        switch ($criterion) {
            case 'inactive_90_days':
                return $this->get_inactive_subscribers(90);

            case 'inactive_180_days':
                return $this->get_inactive_subscribers(180);

            case 'inactive_365_days':
                return $this->get_inactive_subscribers(365);

            case 'never_opened':
                return $this->wpdb->get_results("
                    SELECT s.*
                    FROM {$this->table_subscribers} s
                    WHERE s.status = 'active'
                    AND s.id NOT IN (SELECT DISTINCT subscriber_id FROM {$table_opens})
                ");

            case 'high_bounce_rate':
                return $this->wpdb->get_results("
                    SELECT s.*, COUNT(b.id) as bounce_count
                    FROM {$this->table_subscribers} s
                    INNER JOIN {$table_bounces} b ON s.id = b.subscriber_id
                    WHERE s.status = 'active'
                    GROUP BY s.id
                    HAVING bounce_count >= 3
                ");

            case 'invalid_email':
                return $this->wpdb->get_results("
                    SELECT *
                    FROM {$this->table_subscribers}
                    WHERE status = 'active'
                    AND (
                        email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
                        OR email LIKE '%@example.com'
                        OR email LIKE '%@test.com'
                        OR email LIKE '%@temp%'
                    )
                ");

            case 'duplicate_emails':
                return $this->wpdb->get_results("
                    SELECT s1.*
                    FROM {$this->table_subscribers} s1
                    INNER JOIN (
                        SELECT email, MIN(id) as keep_id
                        FROM {$this->table_subscribers}
                        GROUP BY email
                        HAVING COUNT(*) > 1
                    ) s2 ON s1.email = s2.email
                    WHERE s1.id != s2.keep_id
                ");

            default:
                return [];
        }
    }

    /**
     * Get inactive subscribers
     */
    private function get_inactive_subscribers($days) {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT s.*
            FROM {$this->table_subscribers} s
            LEFT JOIN {$table_opens} o ON s.id = o.subscriber_id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING COALESCE(MAX(o.opened_date), s.subscription_date) <= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }

    /**
     * Execute cleaning
     */
    public function execute_cleaning($criteria, $action = 'unsubscribe') {
        $results = [
            'total_processed' => 0,
            'by_criterion' => []
        ];

        foreach ($criteria as $criterion) {
            $subscribers = $this->get_subscribers_by_criterion($criterion);
            $count = 0;

            foreach ($subscribers as $subscriber) {
                switch ($action) {
                    case 'delete':
                        $this->wpdb->delete($this->table_subscribers, ['id' => $subscriber->id]);
                        $count++;
                        break;

                    case 'unsubscribe':
                        $this->wpdb->update(
                            $this->table_subscribers,
                            [
                                'status' => 'unsubscribed',
                                'unsubscribed_date' => current_time('mysql')
                            ],
                            ['id' => $subscriber->id]
                        );
                        $count++;
                        break;

                    case 'archive':
                        $this->wpdb->update(
                            $this->table_subscribers,
                            ['status' => 'archived'],
                            ['id' => $subscriber->id]
                        );
                        $count++;
                        break;
                }
            }

            $results['by_criterion'][$criterion] = $count;
            $results['total_processed'] += $count;
        }

        return $results;
    }

    /**
     * Send re-engagement campaign
     */
    public function send_reengagement_campaign($subscribers, $campaign_id) {
        $table_queue = $this->wpdb->prefix . 'advnews_queue';

        foreach ($subscribers as $subscriber) {
            $this->wpdb->insert($table_queue, [
                'campaign_id' => $campaign_id,
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'status' => 'pending',
                'priority' => 8, // Higher priority
                'created_date' => current_time('mysql')
            ]);
        }

        return count($subscribers);
    }

    /**
     * Get list health score
     */
    public function get_list_health_score() {
        $total_subscribers = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->table_subscribers} WHERE status = 'active'
        ");

        if ($total_subscribers == 0) {
            return 0;
        }

        $criteria = [
            'inactive_90_days',
            'never_opened',
            'high_bounce_rate',
            'invalid_email'
        ];

        $total_issues = 0;

        foreach ($criteria as $criterion) {
            $subscribers = $this->get_subscribers_by_criterion($criterion);
            $total_issues += count($subscribers);
        }

        $health_score = max(0, 100 - (($total_issues / $total_subscribers) * 100));

        return round($health_score, 1);
    }

    /**
     * Get cleaning recommendations
     */
    public function get_recommendations() {
        $recommendations = [];

        $inactive_365 = count($this->get_inactive_subscribers(365));
        if ($inactive_365 > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => "Remove {$inactive_365} inactive subscribers",
                'description' => 'These subscribers haven\'t engaged in over a year',
                'action' => 'clean_inactive_365'
            ];
        }

        $never_opened = count($this->get_subscribers_by_criterion('never_opened'));
        if ($never_opened > 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => "Send re-engagement to {$never_opened} subscribers",
                'description' => 'These subscribers never opened any email',
                'action' => 'reengage_never_opened'
            ];
        }

        $high_bounce = count($this->get_subscribers_by_criterion('high_bounce_rate'));
        if ($high_bounce > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'title' => "Remove {$high_bounce} high bounce subscribers",
                'description' => 'These emails are likely invalid or no longer exist',
                'action' => 'remove_high_bounce'
            ];
        }

        return $recommendations;
    }

    /**
     * Run automated cleaning
     */
    public function run_automated_cleaning() {
        // Auto-remove high bounce rate subscribers
        $high_bounce = $this->get_subscribers_by_criterion('high_bounce_rate');
        foreach ($high_bounce as $subscriber) {
            $this->wpdb->update(
                $this->table_subscribers,
                ['status' => 'unsubscribed'],
                ['id' => $subscriber->id]
            );
        }

        // Auto-remove invalid emails
        $invalid = $this->get_subscribers_by_criterion('invalid_email');
        foreach ($invalid as $subscriber) {
            $this->wpdb->delete($this->table_subscribers, ['id' => $subscriber->id]);
        }
    }

    /**
     * AJAX: Preview cleaning
     */
    public function ajax_preview_cleaning() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $criteria = $_POST['criteria'] ?? [];
        $results = $this->preview_cleaning($criteria);

        wp_send_json_success($results);
    }

    /**
     * AJAX: Execute cleaning
     */
    public function ajax_execute_cleaning() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $criteria = $_POST['criteria'] ?? [];
        $action = sanitize_text_field($_POST['action_type'] ?? 'unsubscribe');

        $results = $this->execute_cleaning($criteria, $action);

        wp_send_json_success($results);
    }
}
