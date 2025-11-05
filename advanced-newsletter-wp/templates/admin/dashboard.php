<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$dashboard = new AdvancedNewsletter\Admin\Dashboard();
$stats = $dashboard->get_stats();
?>

<div class="wrap advnews-dashboard">
    <h1><?php _e('Newsletter Dashboard', 'advanced-newsletter'); ?></h1>

    <div class="advnews-stats-grid">
        <!-- Subscribers Stats -->
        <div class="advnews-stat-box">
            <div class="advnews-stat-icon subscribers">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="advnews-stat-content">
                <h3><?php echo number_format($stats['subscribers']['total']); ?></h3>
                <p><?php _e('Active Subscribers', 'advanced-newsletter'); ?></p>
                <span class="advnews-stat-meta">
                    +<?php echo number_format($stats['subscribers']['new_this_month']); ?> <?php _e('this month', 'advanced-newsletter'); ?>
                </span>
            </div>
        </div>

        <!-- Campaigns Stats -->
        <div class="advnews-stat-box">
            <div class="advnews-stat-icon campaigns">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="advnews-stat-content">
                <h3><?php echo number_format($stats['campaigns']['sent']); ?></h3>
                <p><?php _e('Campaigns Sent', 'advanced-newsletter'); ?></p>
                <span class="advnews-stat-meta">
                    <?php echo number_format($stats['campaigns']['draft']); ?> <?php _e('drafts', 'advanced-newsletter'); ?>
                </span>
            </div>
        </div>

        <!-- Opens Stats -->
        <div class="advnews-stat-box">
            <div class="advnews-stat-icon opens">
                <span class="dashicons dashicons-visibility"></span>
            </div>
            <div class="advnews-stat-content">
                <h3><?php echo number_format($stats['engagement']['total_opens']); ?></h3>
                <p><?php _e('Total Opens', 'advanced-newsletter'); ?></p>
            </div>
        </div>

        <!-- Clicks Stats -->
        <div class="advnews-stat-box">
            <div class="advnews-stat-icon clicks">
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <div class="advnews-stat-content">
                <h3><?php echo number_format($stats['engagement']['total_clicks']); ?></h3>
                <p><?php _e('Total Clicks', 'advanced-newsletter'); ?></p>
            </div>
        </div>
    </div>

    <div class="advnews-dashboard-section">
        <h2><?php _e('Recent Campaigns', 'advanced-newsletter'); ?></h2>

        <?php if (!empty($stats['recent_campaigns'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Subject', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Status', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Sent', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Opens', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Clicks', 'advanced-newsletter'); ?></th>
                        <th><?php _e('Date', 'advanced-newsletter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_campaigns'] as $campaign): ?>
                        <tr>
                            <td><strong><?php echo esc_html($campaign->name); ?></strong></td>
                            <td><?php echo esc_html($campaign->subject); ?></td>
                            <td>
                                <span class="advnews-status advnews-status-<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($campaign->total_sent); ?></td>
                            <td>
                                <?php echo number_format($campaign->total_opens); ?>
                                <?php if ($campaign->total_sent > 0): ?>
                                    (<?php echo round(($campaign->total_opens / $campaign->total_sent) * 100, 1); ?>%)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo number_format($campaign->total_clicks); ?>
                                <?php if ($campaign->total_sent > 0): ?>
                                    (<?php echo round(($campaign->total_clicks / $campaign->total_sent) * 100, 1); ?>%)
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->created_date))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No campaigns yet.', 'advanced-newsletter'); ?>
                <a href="<?php echo admin_url('admin.php?page=advanced-newsletter-campaigns'); ?>" class="button button-primary">
                    <?php _e('Create Your First Campaign', 'advanced-newsletter'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>

    <div class="advnews-dashboard-section">
        <h2><?php _e('Quick Actions', 'advanced-newsletter'); ?></h2>
        <div class="advnews-quick-actions">
            <a href="<?php echo admin_url('admin.php?page=advanced-newsletter-campaigns'); ?>" class="button button-primary button-large">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('New Campaign', 'advanced-newsletter'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=advanced-newsletter-subscribers'); ?>" class="button button-large">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Manage Subscribers', 'advanced-newsletter'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=advanced-newsletter-analytics'); ?>" class="button button-large">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('View Analytics', 'advanced-newsletter'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=advanced-newsletter-settings'); ?>" class="button button-large">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Settings', 'advanced-newsletter'); ?>
            </a>
        </div>
    </div>
</div>
