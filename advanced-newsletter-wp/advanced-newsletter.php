<?php
/**
 * Plugin Name: Advanced Newsletter
 * Plugin URI: https://github.com/yourusername/advanced-newsletter
 * Description: Gelişmiş newsletter yönetimi - Abone yönetimi, kampanya oluşturma, analytics, automation ve daha fazlası
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Newsletter Team
 * Author URI: https://example.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: advanced-newsletter
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADV_NEWSLETTER_VERSION', '1.0.0');
define('ADV_NEWSLETTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADV_NEWSLETTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADV_NEWSLETTER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AdvancedNewsletter\\';
    $base_dir = ADV_NEWSLETTER_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
final class AdvancedNewsletter {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init'], 0);
        add_action('init', [$this, 'load_textdomain']);

        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);

        // AJAX handlers
        add_action('wp_ajax_advnews_subscribe', [$this, 'handle_subscription']);
        add_action('wp_ajax_nopriv_advnews_subscribe', [$this, 'handle_subscription']);
        add_action('wp_ajax_advnews_unsubscribe', [$this, 'handle_unsubscription']);
        add_action('wp_ajax_nopriv_advnews_unsubscribe', [$this, 'handle_unsubscription']);

        // Shortcodes
        add_shortcode('newsletter_form', [$this, 'render_subscription_form']);

        // Cron jobs
        add_action('advnews_send_queue', [$this, 'process_email_queue']);
    }

    public function activate() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'includes/Installer.php';
        AdvancedNewsletter\Installer::activate();

        // Schedule cron job
        if (!wp_next_scheduled('advnews_send_queue')) {
            wp_schedule_event(time(), 'every_minute', 'advnews_send_queue');
        }
    }

    public function deactivate() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'includes/Installer.php';
        AdvancedNewsletter\Installer::deactivate();

        // Remove cron job
        $timestamp = wp_next_scheduled('advnews_send_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'advnews_send_queue');
        }
    }

    public function init() {
        // Initialize components
        $this->init_components();

        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'custom_cron_schedules']);
    }

    private function init_components() {
        // Initialize all components here
        if (is_admin()) {
            new AdvancedNewsletter\Admin\Dashboard();
            new AdvancedNewsletter\Admin\Subscribers();
            new AdvancedNewsletter\Admin\Campaigns();
            new AdvancedNewsletter\Admin\Templates();
            new AdvancedNewsletter\Admin\Analytics();
            new AdvancedNewsletter\Admin\Settings();
        }

        // Core components
        new AdvancedNewsletter\Frontend\Forms();
        new AdvancedNewsletter\Core\EmailSender();
        new AdvancedNewsletter\Core\Segmentation();
        new AdvancedNewsletter\Core\RSSToEmail();
        new AdvancedNewsletter\Core\CustomFields();
        new AdvancedNewsletter\Core\LeadScoring();
        new AdvancedNewsletter\Core\Webhooks();
        new AdvancedNewsletter\Core\ListCleaning();
        new AdvancedNewsletter\Core\SubscriptionPreferences();
        new AdvancedNewsletter\Core\EmailVerification();

        // Integrations
        new AdvancedNewsletter\Integrations\WooCommerce();

        // REST API
        new AdvancedNewsletter\API\RestAPI();
    }

    public function load_textdomain() {
        load_plugin_textdomain('advanced-newsletter', false, dirname(ADV_NEWSLETTER_PLUGIN_BASENAME) . '/languages');
    }

    public function admin_menu() {
        add_menu_page(
            __('Newsletter', 'advanced-newsletter'),
            __('Newsletter', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter',
            [$this, 'render_dashboard'],
            'dashicons-email-alt',
            30
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Dashboard', 'advanced-newsletter'),
            __('Dashboard', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Subscribers', 'advanced-newsletter'),
            __('Subscribers', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter-subscribers',
            [$this, 'render_subscribers']
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Campaigns', 'advanced-newsletter'),
            __('Campaigns', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter-campaigns',
            [$this, 'render_campaigns']
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Templates', 'advanced-newsletter'),
            __('Templates', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter-templates',
            [$this, 'render_templates']
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Analytics', 'advanced-newsletter'),
            __('Analytics', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter-analytics',
            [$this, 'render_analytics']
        );

        add_submenu_page(
            'advanced-newsletter',
            __('Settings', 'advanced-newsletter'),
            __('Settings', 'advanced-newsletter'),
            'manage_options',
            'advanced-newsletter-settings',
            [$this, 'render_settings']
        );
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'advanced-newsletter') === false) {
            return;
        }

        wp_enqueue_style('advanced-newsletter-admin', ADV_NEWSLETTER_PLUGIN_URL . 'assets/css/admin.css', [], ADV_NEWSLETTER_VERSION);
        wp_enqueue_script('advanced-newsletter-admin', ADV_NEWSLETTER_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], ADV_NEWSLETTER_VERSION, true);

        wp_localize_script('advanced-newsletter-admin', 'advNewsletterAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advnews_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'advanced-newsletter'),
                'processing' => __('Processing...', 'advanced-newsletter'),
            ]
        ]);
    }

    public function frontend_assets() {
        wp_enqueue_style('advanced-newsletter-front', ADV_NEWSLETTER_PLUGIN_URL . 'assets/css/frontend.css', [], ADV_NEWSLETTER_VERSION);
        wp_enqueue_script('advanced-newsletter-front', ADV_NEWSLETTER_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], ADV_NEWSLETTER_VERSION, true);

        wp_localize_script('advanced-newsletter-front', 'advNewsletter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advnews_front_nonce'),
            'strings' => [
                'success' => __('Thank you for subscribing!', 'advanced-newsletter'),
                'error' => __('An error occurred. Please try again.', 'advanced-newsletter'),
            ]
        ]);
    }

    public function custom_cron_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'advanced-newsletter')
        ];
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'advanced-newsletter')
        ];
        return $schedules;
    }

    // Page render methods
    public function render_dashboard() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function render_subscribers() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/subscribers.php';
    }

    public function render_campaigns() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/campaigns.php';
    }

    public function render_templates() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/templates.php';
    }

    public function render_analytics() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/analytics.php';
    }

    public function render_settings() {
        require_once ADV_NEWSLETTER_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function handle_subscription() {
        check_ajax_referer('advnews_front_nonce', 'nonce');

        $subscriber = new AdvancedNewsletter\Core\Subscriber();
        $result = $subscriber->subscribe($_POST);

        wp_send_json($result);
    }

    public function handle_unsubscription() {
        check_ajax_referer('advnews_front_nonce', 'nonce');

        $subscriber = new AdvancedNewsletter\Core\Subscriber();
        $result = $subscriber->unsubscribe($_POST);

        wp_send_json($result);
    }

    public function render_subscription_form($atts) {
        $atts = shortcode_atts([
            'title' => __('Subscribe to our Newsletter', 'advanced-newsletter'),
            'description' => '',
            'button_text' => __('Subscribe', 'advanced-newsletter'),
            'show_name' => 'yes',
            'lists' => '',
        ], $atts);

        ob_start();
        require ADV_NEWSLETTER_PLUGIN_DIR . 'templates/frontend/subscription-form.php';
        return ob_get_clean();
    }

    public function process_email_queue() {
        $sender = new AdvancedNewsletter\Core\EmailSender();
        $sender->process_queue();
    }
}

// Initialize the plugin
function advanced_newsletter() {
    return AdvancedNewsletter::instance();
}

advanced_newsletter();
