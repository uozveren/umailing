<?php
namespace AdvancedNewsletter\Core;

class Segmentation {

    private $wpdb;
    private $table_segments;
    private $table_subscribers;
    private $table_opens;
    private $table_clicks;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_segments = $wpdb->prefix . 'advnews_segments';
        $this->table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $this->table_opens = $wpdb->prefix . 'advnews_opens';
        $this->table_clicks = $wpdb->prefix . 'advnews_clicks';

        add_action('wp_ajax_advnews_create_segment', [$this, 'ajax_create_segment']);
        add_action('wp_ajax_advnews_get_segments', [$this, 'ajax_get_segments']);
        add_action('wp_ajax_advnews_get_segment_subscribers', [$this, 'ajax_get_segment_subscribers']);
    }

    /**
     * Create database table for segments
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'advnews_segments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            conditions longtext DEFAULT NULL,
            type varchar(50) DEFAULT 'dynamic',
            subscriber_count int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get subscribers matching segment conditions
     */
    public function get_segment_subscribers($segment_id) {
        $segment = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_segments} WHERE id = %d",
            $segment_id
        ));

        if (!$segment) {
            return [];
        }

        $conditions = json_decode($segment->conditions, true);
        return $this->apply_conditions($conditions);
    }

    /**
     * Apply conditions to get matching subscribers
     */
    private function apply_conditions($conditions) {
        $where = ['s.status = "active"'];
        $joins = [];

        foreach ($conditions as $condition) {
            switch ($condition['field']) {
                case 'subscription_date':
                    $where[] = $this->build_date_condition('s.subscription_date', $condition);
                    break;

                case 'opened_campaign':
                    $joins[] = "INNER JOIN {$this->table_opens} o ON s.id = o.subscriber_id";
                    if (!empty($condition['campaign_id'])) {
                        $where[] = $this->wpdb->prepare('o.campaign_id = %d', $condition['campaign_id']);
                    }
                    break;

                case 'clicked_campaign':
                    $joins[] = "INNER JOIN {$this->table_clicks} c ON s.id = c.subscriber_id";
                    if (!empty($condition['campaign_id'])) {
                        $where[] = $this->wpdb->prepare('c.campaign_id = %d', $condition['campaign_id']);
                    }
                    break;

                case 'not_opened':
                    $days = intval($condition['value'] ?? 30);
                    $where[] = "s.id NOT IN (
                        SELECT subscriber_id FROM {$this->table_opens}
                        WHERE opened_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    )";
                    break;

                case 'engagement_score':
                    // High, Medium, Low engagement
                    $score = $this->calculate_engagement_score_sql();
                    $where[] = $this->build_score_condition($score, $condition);
                    break;

                case 'custom_field':
                    $where[] = $this->build_custom_field_condition($condition);
                    break;

                case 'location':
                    $where[] = $this->wpdb->prepare(
                        "s.subscription_ip LIKE %s",
                        '%' . $this->wpdb->esc_like($condition['value']) . '%'
                    );
                    break;
            }
        }

        $joins_sql = implode(' ', array_unique($joins));
        $where_sql = implode(' AND ', $where);

        $query = "SELECT DISTINCT s.* FROM {$this->table_subscribers} s
                  {$joins_sql}
                  WHERE {$where_sql}";

        return $this->wpdb->get_results($query);
    }

    /**
     * Build date condition
     */
    private function build_date_condition($field, $condition) {
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'];

        if ($condition['type'] === 'relative') {
            // e.g., "last 30 days"
            $days = intval($value);
            return "{$field} >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        } else {
            // Absolute date
            return $this->wpdb->prepare("{$field} {$operator} %s", $value);
        }
    }

    /**
     * Calculate engagement score SQL
     */
    private function calculate_engagement_score_sql() {
        return "(
            (SELECT COUNT(*) FROM {$this->table_opens} WHERE subscriber_id = s.id) * 2 +
            (SELECT COUNT(*) FROM {$this->table_clicks} WHERE subscriber_id = s.id) * 5
        )";
    }

    /**
     * Build score condition
     */
    private function build_score_condition($score_sql, $condition) {
        $level = $condition['value'];

        switch ($level) {
            case 'high':
                return "{$score_sql} >= 50";
            case 'medium':
                return "{$score_sql} BETWEEN 10 AND 49";
            case 'low':
                return "{$score_sql} < 10";
            default:
                return '1=1';
        }
    }

    /**
     * Build custom field condition
     */
    private function build_custom_field_condition($condition) {
        $field_name = $condition['field_name'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $sql = "JSON_EXTRACT(s.custom_fields, '$.{$field_name}')";

        switch ($operator) {
            case 'equals':
                return $this->wpdb->prepare("{$sql} = %s", $value);
            case 'contains':
                return $this->wpdb->prepare("{$sql} LIKE %s", '%' . $this->wpdb->esc_like($value) . '%');
            case 'starts_with':
                return $this->wpdb->prepare("{$sql} LIKE %s", $this->wpdb->esc_like($value) . '%');
            case 'not_empty':
                return "{$sql} IS NOT NULL AND {$sql} != ''";
            default:
                return '1=1';
        }
    }

    /**
     * Create a new segment
     */
    public function create_segment($data) {
        $segment_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'conditions' => json_encode($data['conditions']),
            'type' => sanitize_text_field($data['type'] ?? 'dynamic'),
            'created_date' => current_time('mysql')
        ];

        $result = $this->wpdb->insert($this->table_segments, $segment_data);

        if ($result) {
            $segment_id = $this->wpdb->insert_id;
            $this->update_segment_count($segment_id);
            return $segment_id;
        }

        return false;
    }

    /**
     * Update segment subscriber count
     */
    public function update_segment_count($segment_id) {
        $subscribers = $this->get_segment_subscribers($segment_id);
        $count = count($subscribers);

        $this->wpdb->update(
            $this->table_segments,
            ['subscriber_count' => $count],
            ['id' => $segment_id]
        );

        return $count;
    }

    /**
     * AJAX: Create segment
     */
    public function ajax_create_segment() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $segment_id = $this->create_segment($_POST);

        if ($segment_id) {
            wp_send_json_success([
                'message' => 'Segment created',
                'segment_id' => $segment_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to create segment']);
        }
    }

    /**
     * AJAX: Get segments
     */
    public function ajax_get_segments() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $segments = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_segments} ORDER BY created_date DESC"
        );

        wp_send_json_success(['segments' => $segments]);
    }

    /**
     * AJAX: Get segment subscribers
     */
    public function ajax_get_segment_subscribers() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $segment_id = intval($_POST['segment_id'] ?? 0);
        $subscribers = $this->get_segment_subscribers($segment_id);

        wp_send_json_success([
            'subscribers' => $subscribers,
            'count' => count($subscribers)
        ]);
    }

    /**
     * Predefined segments
     */
    public function get_predefined_segments() {
        return [
            [
                'name' => 'Highly Engaged',
                'description' => 'Subscribers who frequently open and click emails',
                'conditions' => [
                    ['field' => 'engagement_score', 'value' => 'high']
                ]
            ],
            [
                'name' => 'Inactive Subscribers',
                'description' => 'No activity in last 90 days',
                'conditions' => [
                    ['field' => 'not_opened', 'value' => 90]
                ]
            ],
            [
                'name' => 'New Subscribers',
                'description' => 'Subscribed in last 30 days',
                'conditions' => [
                    ['field' => 'subscription_date', 'type' => 'relative', 'value' => 30]
                ]
            ],
            [
                'name' => 'Never Opened',
                'description' => 'Subscribers who never opened any email',
                'conditions' => [
                    ['field' => 'not_opened', 'value' => 999999]
                ]
            ]
        ];
    }
}
