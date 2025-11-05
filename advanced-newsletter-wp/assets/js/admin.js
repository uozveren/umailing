/**
 * Advanced Newsletter - Admin JavaScript
 */

(function($) {
    'use strict';

    // Common functions
    window.advNewsletterUtils = {
        showNotice: function(message, type) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        confirmAction: function(message) {
            return confirm(message || advNewsletterAdmin.strings.confirmDelete);
        },

        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    };

    // Dashboard
    if ($('.advnews-dashboard').length > 0) {
        // Initialize dashboard charts if needed
        console.log('Dashboard loaded');
    }

    // Subscribers page
    if ($('.advnews-subscribers').length > 0) {
        // Select all checkbox
        $(document).on('change', '#select-all', function() {
            $('.subscriber-checkbox').prop('checked', $(this).is(':checked'));
            toggleBulkActions();
        });

        // Individual checkbox
        $(document).on('change', '.subscriber-checkbox', function() {
            toggleBulkActions();
        });

        function toggleBulkActions() {
            var checked = $('.subscriber-checkbox:checked').length;
            $('#advnews-bulk-delete').toggle(checked > 0);
        }

        // Bulk delete
        $('#advnews-bulk-delete').on('click', function() {
            if (!advNewsletterUtils.confirmAction()) {
                return;
            }

            var ids = $('.subscriber-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            $.ajax({
                url: advNewsletterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'advnews_bulk_delete_subscribers',
                    nonce: advNewsletterAdmin.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        advNewsletterUtils.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        advNewsletterUtils.showNotice(response.data.message, 'error');
                    }
                }
            });
        });

        // Export
        $('#advnews-export-subscribers').on('click', function() {
            var status = $('#advnews-filter-status').val();
            var listId = $('#advnews-filter-list').val();

            $.ajax({
                url: advNewsletterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'advnews_export_subscribers',
                    nonce: advNewsletterAdmin.nonce,
                    status: status,
                    list_id: listId
                },
                success: function(response) {
                    if (response.success) {
                        var blob = new Blob([response.data.csv], { type: 'text/csv' });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = response.data.filename;
                        link.click();
                    } else {
                        advNewsletterUtils.showNotice(response.data.message, 'error');
                    }
                }
            });
        });

        // Import
        $('#advnews-import-subscribers').on('click', function() {
            $('#advnews-import-modal').fadeIn();
        });

        $('#advnews-import-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData();
            formData.append('action', 'advnews_import_subscribers');
            formData.append('nonce', advNewsletterAdmin.nonce);
            formData.append('file', $(this).find('[name="file"]')[0].files[0]);

            $.ajax({
                url: advNewsletterAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        advNewsletterUtils.showNotice(response.data.message, 'success');
                        $('#advnews-import-modal').fadeOut();
                        location.reload();
                    } else {
                        advNewsletterUtils.showNotice(response.data.message, 'error');
                    }
                }
            });
        });
    }

    // Campaigns page
    if ($('.advnews-campaigns').length > 0) {
        // Create campaign
        $('#advnews-create-campaign').on('click', function() {
            // Redirect to campaign editor or open modal
            window.location.href = advNewsletterAdmin.adminUrl + 'admin.php?page=advanced-newsletter-campaigns&action=new';
        });
    }

    // Analytics page
    if ($('.advnews-analytics').length > 0) {
        var currentPeriod = 'month';

        $('.advnews-period-selector .button').on('click', function() {
            $('.advnews-period-selector .button').removeClass('button-primary');
            $(this).addClass('button-primary');
            currentPeriod = $(this).data('period');
            loadAnalytics(currentPeriod);
        });

        function loadAnalytics(period) {
            $.ajax({
                url: advNewsletterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'advnews_get_analytics',
                    nonce: advNewsletterAdmin.nonce,
                    period: period
                },
                success: function(response) {
                    if (response.success) {
                        renderAnalytics(response.data);
                    }
                }
            });
        }

        function renderAnalytics(data) {
            // Render overview stats
            var html = '<div class="advnews-stats-grid">';
            html += '<div class="advnews-stat-box">';
            html += '<div class="advnews-stat-content">';
            html += '<h3>' + advNewsletterUtils.formatNumber(data.overview.total_subscribers) + '</h3>';
            html += '<p>Total Subscribers</p>';
            html += '</div></div>';
            html += '<div class="advnews-stat-box">';
            html += '<div class="advnews-stat-content">';
            html += '<h3>' + advNewsletterUtils.formatNumber(data.overview.total_campaigns) + '</h3>';
            html += '<p>Total Campaigns</p>';
            html += '</div></div>';
            html += '<div class="advnews-stat-box">';
            html += '<div class="advnews-stat-content">';
            html += '<h3>' + data.overview.avg_open_rate + '%</h3>';
            html += '<p>Avg Open Rate</p>';
            html += '</div></div>';
            html += '<div class="advnews-stat-box">';
            html += '<div class="advnews-stat-content">';
            html += '<h3>' + data.overview.avg_click_rate + '%</h3>';
            html += '<p>Avg Click Rate</p>';
            html += '</div></div>';
            html += '</div>';
            $('#advnews-overview-stats').html(html);

            // Render top campaigns
            if (data.top_campaigns && data.top_campaigns.length > 0) {
                var tableHtml = '<table class="wp-list-table widefat fixed striped">';
                tableHtml += '<thead><tr><th>Campaign</th><th>Sent</th><th>Opens</th><th>Open Rate</th><th>Clicks</th><th>Click Rate</th></tr></thead><tbody>';

                data.top_campaigns.forEach(function(campaign) {
                    tableHtml += '<tr>';
                    tableHtml += '<td><strong>' + campaign.name + '</strong></td>';
                    tableHtml += '<td>' + advNewsletterUtils.formatNumber(campaign.total_sent) + '</td>';
                    tableHtml += '<td>' + advNewsletterUtils.formatNumber(campaign.total_opens) + '</td>';
                    tableHtml += '<td>' + campaign.open_rate + '%</td>';
                    tableHtml += '<td>' + advNewsletterUtils.formatNumber(campaign.total_clicks) + '</td>';
                    tableHtml += '<td>' + campaign.click_rate + '%</td>';
                    tableHtml += '</tr>';
                });

                tableHtml += '</tbody></table>';
                $('#advnews-top-campaigns').html(tableHtml);
            }

            // TODO: Render charts using Chart.js or similar library
        }

        // Initial load
        loadAnalytics(currentPeriod);
    }

})(jQuery);
