<?php
namespace AdvancedNewsletter\Core;

class EmailVerification {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        add_action('wp_ajax_advnews_verify_email', [$this, 'ajax_verify_email']);
        add_action('wp_ajax_advnews_bulk_verify_emails', [$this, 'ajax_bulk_verify']);
    }

    /**
     * Verify single email address
     */
    public function verify_email($email) {
        $results = [
            'email' => $email,
            'valid' => false,
            'checks' => []
        ];

        // Syntax check
        $results['checks']['syntax'] = $this->check_syntax($email);

        // DNS/MX record check
        $results['checks']['dns'] = $this->check_dns($email);

        // Disposable email check
        $results['checks']['disposable'] = !$this->is_disposable($email);

        // Role-based email check
        $results['checks']['role_based'] = !$this->is_role_based($email);

        // Overall validity
        $results['valid'] = $results['checks']['syntax'] &&
                           $results['checks']['dns'] &&
                           $results['checks']['disposable'];

        $results['score'] = $this->calculate_score($results['checks']);
        $results['quality'] = $this->get_quality_grade($results['score']);

        return $results;
    }

    /**
     * Check email syntax
     */
    private function check_syntax($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional checks
        $parts = explode('@', $email);
        if (count($parts) != 2) {
            return false;
        }

        list($local, $domain) = $parts;

        // Check local part
        if (strlen($local) > 64 || strlen($local) == 0) {
            return false;
        }

        // Check domain part
        if (strlen($domain) > 255 || strlen($domain) == 0) {
            return false;
        }

        return true;
    }

    /**
     * Check DNS/MX records
     */
    private function check_dns($email) {
        $domain = substr(strrchr($email, "@"), 1);

        if (!$domain) {
            return false;
        }

        // Check MX records
        $mx_records = [];
        if (getmxrr($domain, $mx_records)) {
            return true;
        }

        // If no MX, check A record
        return checkdnsrr($domain, 'A');
    }

    /**
     * Check if email is from disposable domain
     */
    private function is_disposable($email) {
        $domain = strtolower(substr(strrchr($email, "@"), 1));

        $disposable_domains = [
            'tempmail.com',
            'guerrillamail.com',
            '10minutemail.com',
            'mailinator.com',
            'throwaway.email',
            'trashmail.com',
            'temp-mail.org',
            'yopmail.com',
            'maildrop.cc',
            'getnada.com',
            'mintemail.com',
            'fakeinbox.com'
        ];

        return in_array($domain, $disposable_domains);
    }

    /**
     * Check if email is role-based
     */
    private function is_role_based($email) {
        $local = strtolower(substr($email, 0, strpos($email, '@')));

        $role_based = [
            'admin',
            'administrator',
            'info',
            'support',
            'sales',
            'contact',
            'help',
            'webmaster',
            'noreply',
            'no-reply',
            'mail',
            'marketing',
            'billing',
            'careers'
        ];

        return in_array($local, $role_based);
    }

    /**
     * Calculate verification score
     */
    private function calculate_score($checks) {
        $score = 0;

        if ($checks['syntax']) $score += 40;
        if ($checks['dns']) $score += 40;
        if ($checks['disposable']) $score += 10;
        if ($checks['role_based']) $score += 10;

        return $score;
    }

    /**
     * Get quality grade
     */
    private function get_quality_grade($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 50) return 'Fair';
        return 'Poor';
    }

    /**
     * Bulk verify emails
     */
    public function bulk_verify($emails) {
        $results = [];

        foreach ($emails as $email) {
            $results[] = $this->verify_email($email);

            // Small delay to avoid rate limiting
            usleep(100000); // 0.1 second
        }

        return $results;
    }

    /**
     * Verify all subscribers
     */
    public function verify_all_subscribers() {
        $table_subscribers = $this->wpdb->prefix . 'advnews_subscribers';

        $subscribers = $this->wpdb->get_results(
            "SELECT id, email FROM {$table_subscribers} WHERE status = 'active'"
        );

        $results = [
            'total' => count($subscribers),
            'verified' => 0,
            'invalid' => 0,
            'suspicious' => 0
        ];

        foreach ($subscribers as $subscriber) {
            $verification = $this->verify_email($subscriber->email);

            if ($verification['valid']) {
                $results['verified']++;
            } else {
                $results['invalid']++;

                // Mark as suspicious in custom fields
                $custom_fields = [
                    'email_verification_score' => $verification['score'],
                    'email_verification_quality' => $verification['quality'],
                    'email_verification_date' => current_time('mysql')
                ];

                $this->wpdb->update(
                    $table_subscribers,
                    ['custom_fields' => json_encode($custom_fields)],
                    ['id' => $subscriber->id]
                );
            }
        }

        return $results;
    }

    /**
     * Get email suggestions for typos
     */
    public function suggest_email($email) {
        if (!strpos($email, '@')) {
            return null;
        }

        list($local, $domain) = explode('@', $email);

        // Common domain typos
        $common_domains = [
            'gmail.com' => ['gmai.com', 'gmial.com', 'gmal.com', 'gmil.com'],
            'yahoo.com' => ['yaoo.com', 'yaho.com', 'yhoo.com'],
            'hotmail.com' => ['hotmial.com', 'hotmil.com', 'hotml.com'],
            'outlook.com' => ['outlok.com', 'outloo.com']
        ];

        foreach ($common_domains as $correct => $typos) {
            if (in_array($domain, $typos)) {
                return $local . '@' . $correct;
            }
        }

        return null;
    }

    /**
     * AJAX: Verify email
     */
    public function ajax_verify_email() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $email = sanitize_email($_POST['email'] ?? '');

        if (!$email) {
            wp_send_json_error(['message' => 'Invalid email']);
        }

        $result = $this->verify_email($email);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Bulk verify
     */
    public function ajax_bulk_verify() {
        check_ajax_referer('advnews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $emails = array_map('sanitize_email', $_POST['emails'] ?? []);

        if (empty($emails)) {
            wp_send_json_error(['message' => 'No emails provided']);
        }

        $results = $this->bulk_verify($emails);

        wp_send_json_success($results);
    }
}
