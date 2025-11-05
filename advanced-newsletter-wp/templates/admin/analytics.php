<?php
/**
 * Admin Analytics Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap advnews-analytics">
    <h1><?php _e('Analytics', 'advanced-newsletter'); ?></h1>

    <div class="advnews-period-selector">
        <button class="button" data-period="week"><?php _e('Last 7 Days', 'advanced-newsletter'); ?></button>
        <button class="button button-primary" data-period="month"><?php _e('Last 30 Days', 'advanced-newsletter'); ?></button>
        <button class="button" data-period="year"><?php _e('Last Year', 'advanced-newsletter'); ?></button>
    </div>

    <div class="advnews-analytics-section">
        <h2><?php _e('Overview', 'advanced-newsletter'); ?></h2>
        <div id="advnews-overview-stats">
            <p><?php _e('Loading analytics...', 'advanced-newsletter'); ?></p>
        </div>
    </div>

    <div class="advnews-analytics-section">
        <h2><?php _e('Subscriber Growth', 'advanced-newsletter'); ?></h2>
        <div id="advnews-growth-chart" style="height: 300px;">
            <canvas id="growth-canvas"></canvas>
        </div>
    </div>

    <div class="advnews-analytics-section">
        <h2><?php _e('Engagement', 'advanced-newsletter'); ?></h2>
        <div id="advnews-engagement-chart" style="height: 300px;">
            <canvas id="engagement-canvas"></canvas>
        </div>
    </div>

    <div class="advnews-analytics-section">
        <h2><?php _e('Top Campaigns', 'advanced-newsletter'); ?></h2>
        <div id="advnews-top-campaigns">
            <p><?php _e('Loading top campaigns...', 'advanced-newsletter'); ?></p>
        </div>
    </div>
</div>
