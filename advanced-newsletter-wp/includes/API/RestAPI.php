<?php
namespace AdvancedNewsletter\API;

use AdvancedNewsletter\Core\Subscriber;

class RestAPI {

    private $namespace = 'advanced-newsletter/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Subscribers endpoints
        register_rest_route($this->namespace, '/subscribers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_subscribers'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_subscriber'],
                'permission_callback' => '__return_true' // Public endpoint
            ]
        ]);

        register_rest_route($this->namespace, '/subscribers/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_subscriber'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_subscriber'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_subscriber'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // Campaigns endpoints
        register_rest_route($this->namespace, '/campaigns', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_campaigns'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_campaign'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_campaign'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_campaign'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_campaign'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_campaign'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics_overview'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_campaign_analytics'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Lists endpoints
        register_rest_route($this->namespace, '/lists', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_lists'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_list'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // Segments endpoints
        register_rest_route($this->namespace, '/segments', [
            'methods' => 'GET',
            'callback' => [$this, 'get_segments'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/segments/(?P<id>\d+)/subscribers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_segment_subscribers'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    /**
     * Permission callback
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }

    // ====================
    // Subscribers
    // ====================

    public function get_subscribers($request) {
        $subscriber = new Subscriber();

        $params = $request->get_params();
        $args = [
            'status' => $params['status'] ?? '',
            'list_id' => $params['list_id'] ?? 0,
            'search' => $params['search'] ?? '',
            'limit' => $params['per_page'] ?? 20,
            'offset' => (($params['page'] ?? 1) - 1) * ($params['per_page'] ?? 20)
        ];

        $subscribers = $subscriber->get_all($args);
        $total = $subscriber->get_count($args);

        return new \WP_REST_Response([
            'data' => $subscribers,
            'total' => $total,
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 20
        ], 200);
    }

    public function get_subscriber($request) {
        $subscriber = new Subscriber();
        $sub = $subscriber->get_by_id($request['id']);

        if (!$sub) {
            return new \WP_Error('not_found', 'Subscriber not found', ['status' => 404]);
        }

        return new \WP_REST_Response($sub, 200);
    }

    public function create_subscriber($request) {
        $subscriber = new Subscriber();
        $result = $subscriber->subscribe($request->get_params());

        if ($result['success']) {
            return new \WP_REST_Response($result, 201);
        } else {
            return new \WP_Error('creation_failed', $result['message'], ['status' => 400]);
        }
    }

    public function update_subscriber($request) {
        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'advnews_subscribers';

        $data = $request->get_params();
        $update_data = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (!empty($update_data)) {
            $result = $wpdb->update($table_subscribers, $update_data, ['id' => $request['id']]);

            if ($result !== false) {
                return new \WP_REST_Response(['message' => 'Subscriber updated'], 200);
            }
        }

        return new \WP_Error('update_failed', 'Failed to update subscriber', ['status' => 400]);
    }

    public function delete_subscriber($request) {
        $subscriber = new Subscriber();
        $result = $subscriber->delete($request['id']);

        if ($result) {
            return new \WP_REST_Response(['message' => 'Subscriber deleted'], 200);
        } else {
            return new \WP_Error('deletion_failed', 'Failed to delete subscriber', ['status' => 400]);
        }
    }

    // ====================
    // Campaigns
    // ====================

    public function get_campaigns($request) {
        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'advnews_campaigns';

        $params = $request->get_params();
        $limit = $params['per_page'] ?? 20;
        $offset = (($params['page'] ?? 1) - 1) * $limit;

        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_campaigns} ORDER BY created_date DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_campaigns}");

        return new \WP_REST_Response([
            'data' => $campaigns,
            'total' => (int) $total,
            'page' => $params['page'] ?? 1,
            'per_page' => $limit
        ], 200);
    }

    public function get_campaign($request) {
        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'advnews_campaigns';

        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_campaigns} WHERE id = %d",
            $request['id']
        ));

        if (!$campaign) {
            return new \WP_Error('not_found', 'Campaign not found', ['status' => 404]);
        }

        return new \WP_REST_Response($campaign, 200);
    }

    public function create_campaign($request) {
        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'advnews_campaigns';

        $data = $request->get_params();

        $campaign_data = [
            'name' => sanitize_text_field($data['name']),
            'subject' => sanitize_text_field($data['subject']),
            'content' => wp_kses_post($data['content'] ?? ''),
            'status' => 'draft',
            'created_date' => current_time('mysql')
        ];

        $result = $wpdb->insert($table_campaigns, $campaign_data);

        if ($result) {
            return new \WP_REST_Response([
                'id' => $wpdb->insert_id,
                'message' => 'Campaign created'
            ], 201);
        }

        return new \WP_Error('creation_failed', 'Failed to create campaign', ['status' => 400]);
    }

    public function send_campaign($request) {
        $sender = new \AdvancedNewsletter\Core\EmailSender();
        $recipients = $sender->create_campaign_queue($request['id']);

        if ($recipients === false) {
            return new \WP_Error('send_failed', 'Campaign not found', ['status' => 404]);
        }

        return new \WP_REST_Response([
            'message' => 'Campaign queued',
            'recipients' => $recipients
        ], 200);
    }

    // ====================
    // Analytics
    // ====================

    public function get_analytics_overview($request) {
        $dashboard = new \AdvancedNewsletter\Admin\Dashboard();
        $stats = $dashboard->get_stats();

        return new \WP_REST_Response($stats, 200);
    }

    public function get_campaign_analytics($request) {
        $analytics = new \AdvancedNewsletter\Admin\Analytics();
        $stats = $analytics->get_campaign_summary($request['id']);

        if (!$stats) {
            return new \WP_Error('not_found', 'Campaign not found', ['status' => 404]);
        }

        return new \WP_REST_Response($stats, 200);
    }

    // ====================
    // Lists
    // ====================

    public function get_lists($request) {
        global $wpdb;
        $table_lists = $wpdb->prefix . 'advnews_lists';

        $lists = $wpdb->get_results("SELECT * FROM {$table_lists} ORDER BY created_date DESC");

        return new \WP_REST_Response(['data' => $lists], 200);
    }

    public function create_list($request) {
        global $wpdb;
        $table_lists = $wpdb->prefix . 'advnews_lists';

        $data = $request->get_params();

        $list_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'created_date' => current_time('mysql')
        ];

        $result = $wpdb->insert($table_lists, $list_data);

        if ($result) {
            return new \WP_REST_Response([
                'id' => $wpdb->insert_id,
                'message' => 'List created'
            ], 201);
        }

        return new \WP_Error('creation_failed', 'Failed to create list', ['status' => 400]);
    }

    // ====================
    // Segments
    // ====================

    public function get_segments($request) {
        $segmentation = new \AdvancedNewsletter\Core\Segmentation();
        // This would need to be implemented in Segmentation class

        global $wpdb;
        $table_segments = $wpdb->prefix . 'advnews_segments';
        $segments = $wpdb->get_results("SELECT * FROM {$table_segments} ORDER BY created_date DESC");

        return new \WP_REST_Response(['data' => $segments], 200);
    }

    public function get_segment_subscribers($request) {
        $segmentation = new \AdvancedNewsletter\Core\Segmentation();
        $subscribers = $segmentation->get_segment_subscribers($request['id']);

        return new \WP_REST_Response([
            'data' => $subscribers,
            'count' => count($subscribers)
        ], 200);
    }
}
