<?php
namespace AdvancedNewsletter\Core;

class LeadScoring {

    private $wpdb;
    private $table_subscribers;
    private $table_scores;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_subscribers = $wpdb->prefix . 'advnews_subscribers';
        $this->table_scores = $wpdb->prefix . 'advnews_lead_scores';

        // Update scores on activity
        add_action('advnews_email_opened', [$this, 'score_email_open'], 10, 2);
        add_action('advnews_email_clicked', [$this, 'score_email_click'], 10, 2);
        add_action('advnews_subscriber_subscribed', [$this, 'score_subscription'], 10, 1);
    }

    /**
     * Create lead scoring table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'advnews_lead_scores';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) NOT NULL,
            score int(11) DEFAULT 0,
            activity_type varchar(50) NOT NULL,
            points int(11) DEFAULT 0,
            campaign_id bigint(20) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY activity_type (activity_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Scoring rules
     */
    private function get_scoring_rules() {
        return [
            'subscription' => 10,
            'email_open' => 2,
            'email_click' => 5,
            'form_submit' => 15,
            'link_click' => 3,
            'social_share' => 8,
            'referral' => 20,
            'purchase' => 50,
            'inactive_penalty' => -1, // per day of inactivity
        ];
    }

    /**
     * Add score activity
     */
    public function add_score($subscriber_id, $activity_type, $campaign_id = null) {
        $rules = $this->get_scoring_rules();
        $points = $rules[$activity_type] ?? 0;

        if ($points == 0) {
            return false;
        }

        // Get current total score
        $current_score = $this->get_subscriber_score($subscriber_id);
        $new_score = $current_score + $points;

        // Insert score activity
        $this->wpdb->insert($this->table_scores, [
            'subscriber_id' => $subscriber_id,
            'score' => $new_score,
            'activity_type' => $activity_type,
            'points' => $points,
            'campaign_id' => $campaign_id,
            'created_date' => current_time('mysql')
        ]);

        // Update subscriber's custom fields with score
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT custom_fields FROM {$this->table_subscribers} WHERE id = %d",
            $subscriber_id
        ));

        $custom_fields = $subscriber->custom_fields ? json_decode($subscriber->custom_fields, true) : [];
        $custom_fields['lead_score'] = $new_score;
        $custom_fields['lead_grade'] = $this->calculate_grade($new_score);

        $this->wpdb->update(
            $this->table_subscribers,
            ['custom_fields' => json_encode($custom_fields)],
            ['id' => $subscriber_id]
        );

        return $new_score;
    }

    /**
     * Get subscriber's total score
     */
    public function get_subscriber_score($subscriber_id) {
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT score FROM {$this->table_scores}
            WHERE subscriber_id = %d
            ORDER BY created_date DESC
            LIMIT 1",
            $subscriber_id
        ));

        return $result ? intval($result->score) : 0;
    }

    /**
     * Calculate grade based on score
     */
    private function calculate_grade($score) {
        if ($score >= 100) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 40) return 'C';
        if ($score >= 20) return 'D';
        return 'F';
    }

    /**
     * Get top scored subscribers
     */
    public function get_top_subscribers($limit = 100) {
        $query = "
            SELECT s.*, MAX(ls.score) as lead_score
            FROM {$this->table_subscribers} s
            INNER JOIN {$this->table_scores} ls ON s.id = ls.subscriber_id
            WHERE s.status = 'active'
            GROUP BY s.id
            ORDER BY lead_score DESC
            LIMIT %d
        ";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $limit));
    }

    /**
     * Get subscribers by score range
     */
    public function get_subscribers_by_score($min_score, $max_score = null) {
        $query = "
            SELECT s.*, MAX(ls.score) as lead_score
            FROM {$this->table_subscribers} s
            INNER JOIN {$this->table_scores} ls ON s.id = ls.subscriber_id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING lead_score >= %d
        ";

        if ($max_score) {
            $query .= " AND lead_score <= " . intval($max_score);
        }

        $query .= " ORDER BY lead_score DESC";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $min_score));
    }

    /**
     * Score email open
     */
    public function score_email_open($subscriber_id, $campaign_id) {
        $this->add_score($subscriber_id, 'email_open', $campaign_id);
    }

    /**
     * Score email click
     */
    public function score_email_click($subscriber_id, $campaign_id) {
        $this->add_score($subscriber_id, 'email_click', $campaign_id);
    }

    /**
     * Score new subscription
     */
    public function score_subscription($subscriber_id) {
        $this->add_score($subscriber_id, 'subscription');
    }

    /**
     * Calculate decay for inactive subscribers
     */
    public function apply_inactivity_decay() {
        $table_opens = $this->wpdb->prefix . 'advnews_opens';

        // Get subscribers with no activity in last 30 days
        $inactive_subscribers = $this->wpdb->get_results("
            SELECT s.id,
                   DATEDIFF(NOW(), COALESCE(MAX(o.opened_date), s.subscription_date)) as days_inactive
            FROM {$this->table_subscribers} s
            LEFT JOIN {$table_opens} o ON s.id = o.subscriber_id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING days_inactive >= 30
        ");

        foreach ($inactive_subscribers as $subscriber) {
            $penalty = intval($subscriber->days_inactive / 30); // -1 per 30 days
            $this->add_score($subscriber->id, 'inactive_penalty', null);
        }
    }

    /**
     * Get score breakdown
     */
    public function get_score_breakdown($subscriber_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT activity_type, SUM(points) as total_points, COUNT(*) as count
            FROM {$this->table_scores}
            WHERE subscriber_id = %d
            GROUP BY activity_type
            ORDER BY total_points DESC
        ", $subscriber_id));
    }

    /**
     * Get score history
     */
    public function get_score_history($subscriber_id, $limit = 50) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT *
            FROM {$this->table_scores}
            WHERE subscriber_id = %d
            ORDER BY created_date DESC
            LIMIT %d
        ", $subscriber_id, $limit));
    }
}
