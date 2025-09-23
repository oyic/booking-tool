<?php

namespace LM\Booking\Admin;

class BookingsList
{
    public function init(): void
    {
        add_action('admin_init', [$this, 'handleBulkActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('admin_init', [$this, 'handleExport']);
        add_action('wp_ajax_lm_check_new_bookings', [$this, 'checkNewBookings']);
        add_action('wp_ajax_lm_get_booking_details', [$this, 'getBookingDetailsAjax']);
        add_action('wp_ajax_lm_update_booking_status', [$this, 'updateBookingStatusAjax']);
        add_action('wp_ajax_lm_complete_booking', [$this, 'completeBookingAjax']);
        add_action('wp_ajax_lm_delete_booking', [$this, 'deleteBookingAjax']);
    }

    public function enqueueAdminScripts($hook): void
    {
        if ($hook !== 'toplevel_page_lm-booking') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-admin');
    }

    public function handleBulkActions(): void
    {
        if (!isset($_POST['lm_booking_bulk_action']) || !wp_verify_nonce($_POST['_wpnonce'], 'lm_booking_bulk_action')) {
            return;
        }

        $action = sanitize_text_field($_POST['lm_booking_bulk_action']);
        $booking_ids = isset($_POST['booking_ids']) ? array_map('intval', $_POST['booking_ids']) : [];

        if (empty($booking_ids)) {
            return;
        }

        switch ($action) {
            case 'mark_completed':
                $this->bulkUpdateStatus($booking_ids, 'completed');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Selected bookings marked as completed.', 'lm-booking') . '</p></div>';
                });
                break;
                
            case 'mark_in_progress':
                $this->bulkUpdateStatus($booking_ids, 'in_progress');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Selected bookings marked as in progress.', 'lm-booking') . '</p></div>';
                });
                break;
                
            case 'delete':
                $this->bulkDelete($booking_ids);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Selected bookings deleted.', 'lm-booking') . '</p></div>';
                });
                break;
        }
    }

    private function bulkUpdateStatus(array $booking_ids, string $status): void
    {
        foreach ($booking_ids as $booking_id) {
            update_post_meta($booking_id, '_lm_booking_status', $status);
        }
    }

    private function bulkDelete(array $booking_ids): void
    {
        foreach ($booking_ids as $booking_id) {
            wp_delete_post($booking_id, true);
        }
    }

    public function getBookings(array $args = []): array
    {
        $defaults = [
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => 1,
            'meta_query' => [],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query_args = wp_parse_args($args, $defaults);

        // Handle sorting
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'date');
        $order = sanitize_text_field($_GET['order'] ?? 'DESC');
        
        switch ($orderby) {
            case 'customer_name':
                $query_args['meta_key'] = '_lm_booking_customer_name';
                $query_args['orderby'] = 'meta_value';
                $query_args['order'] = strtoupper($order);
                break;
            case 'total':
                $query_args['meta_key'] = '_lm_booking_total';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = strtoupper($order);
                break;
            case 'date':
            default:
                $query_args['orderby'] = 'date';
                $query_args['order'] = strtoupper($order);
                break;
        }

        // Handle search
        if (!empty($_GET['s'])) {
            $query_args['s'] = sanitize_text_field($_GET['s']);
        }

        // Handle status filter
        if (!empty($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            $query_args['meta_query'][] = [
                'key' => '_lm_booking_status',
                'value' => $status,
                'compare' => '='
            ];
        }

        // Handle package filter
        if (!empty($_GET['package'])) {
            $package = sanitize_text_field($_GET['package']);
            $query_args['meta_query'][] = [
                'key' => '_lm_booking_service',
                'value' => $package,
                'compare' => '='
            ];
        }

        // Handle delivery urgency filter
        if (!empty($_GET['delivery'])) {
            $delivery = sanitize_text_field($_GET['delivery']);
            $query_args['meta_query'][] = [
                'key' => '_lm_booking_delivery',
                'value' => $delivery,
                'compare' => '='
            ];
        }

        // Handle date range filter
        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $date_query = [];
            
            if (!empty($_GET['date_from'])) {
                $date_query['after'] = sanitize_text_field($_GET['date_from']);
            }
            
            if (!empty($_GET['date_to'])) {
                $date_query['before'] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
            }
            
            $date_query['inclusive'] = true;
            $query_args['date_query'] = [$date_query];
        }

        // Handle export - get all bookings
        if (!empty($_GET['export'])) {
            $query_args['posts_per_page'] = -1;
            $query_args['paged'] = 1;
        }

        $query = new \WP_Query($query_args);
        
        // Debug logging to help troubleshoot
        if (defined('WP_DEBUG') && WP_DEBUG) {
        }
        
        return $query->posts;
    }

    public function getBookingStats(): array
    {
        $total_bookings = wp_count_posts('lm_booking');
        $total_revenue = 0;
        $status_counts = [
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0
        ];

        // Get all bookings for stats
        $bookings = get_posts([
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => []
        ]);

        foreach ($bookings as $booking) {
            $status = get_post_meta($booking->ID, '_lm_booking_status', true) ?: 'pending';
            $status_counts[$status]++;
            
            $total = get_post_meta($booking->ID, '_lm_booking_total', true);
            if ($total) {
                $total_revenue += floatval($total);
            }
        }

        return [
            'total' => $total_bookings->publish,
            'revenue' => $total_revenue,
            'status_counts' => $status_counts
        ];
    }

    public function getBookingMeta(int $booking_id): array
    {
        $meta = [
            'customer_name' => get_post_meta($booking_id, '_lm_booking_customer_name', true),
            'customer_email' => get_post_meta($booking_id, '_lm_booking_customer_email', true),
            'service' => get_post_meta($booking_id, '_lm_booking_service', true),
            'words' => get_post_meta($booking_id, '_lm_booking_words', true),
            'norm_pages' => get_post_meta($booking_id, '_lm_booking_norm_pages', true),
            'delivery' => get_post_meta($booking_id, '_lm_booking_delivery', true),
            'delivery_date' => get_post_meta($booking_id, '_lm_booking_delivery_date', true),
            'extras' => get_post_meta($booking_id, '_lm_booking_extras', true),
            'total' => get_post_meta($booking_id, '_lm_booking_total', true),
            'status' => get_post_meta($booking_id, '_lm_booking_status', true) ?: 'pending',
            'breakdown' => get_post_meta($booking_id, '_lm_booking_breakdown', true),
            'file_uploaded' => get_post_meta($booking_id, '_lm_booking_file_uploaded', true),
            'document' => get_post_meta($booking_id, '_lm_booking_document', true),
            'country' => get_post_meta($booking_id, '_lm_booking_country', true),
            'program' => get_post_meta($booking_id, '_lm_booking_program', true),
            'voucher_code' => get_post_meta($booking_id, '_lm_booking_voucher_code', true),
            'voucher_discount' => get_post_meta($booking_id, '_lm_booking_voucher_discount', true),
            'original_total' => get_post_meta($booking_id, '_lm_booking_original_total', true),
            'discount_amount' => get_post_meta($booking_id, '_lm_booking_discount_amount', true),
            'note' => get_post_meta($booking_id, '_lm_booking_note', true)
        ];


        return $meta;
    }

    private function formatCountry(string $country): string
    {
        $countries = [
            'de' => __('Germany', 'lm-booking'),
            'at' => __('Austria', 'lm-booking'),
            'ch' => __('Switzerland', 'lm-booking'),
        ];
        return $countries[$country] ?? ucfirst($country);
    }

    private function formatProgram(string $program): string
    {
        $programs = [
            'bachelor_thesis' => __('Bachelor Thesis', 'lm-booking'),
            'master_thesis' => __('Master Thesis', 'lm-booking'),
            'phd_thesis' => __('PhD Thesis', 'lm-booking'),
            'bachelor' => __('Bachelor', 'lm-booking'),
            'master' => __('Master', 'lm-booking'),
            'phd' => __('PhD', 'lm-booking'),
            'other' => __('Other', 'lm-booking'),
        ];
        return $programs[$program] ?? ucfirst(str_replace('_', ' ', $program));
    }

    public function showBookingDetails(int $booking_id): void
    {
        $meta = $this->getBookingMeta($booking_id);
        $booking = get_post($booking_id);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Details', 'lm-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=lm-booking'); ?>" class="button"><?php esc_html_e('← Back to Bookings', 'lm-booking'); ?></a>
            
            <style>
            .file-download-container {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 2px solid #e1e1e1;
            }
            .file-download-container h2 {
                margin-bottom: 15px;
                color: #333;
                font-size: 20px;
            }
            .file-download-section {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-top: 10px;
            }
            .file-download-info {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            .file-details {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .file-icon {
                font-size: 24px;
            }
            .file-info {
                display: flex;
                flex-direction: column;
            }
            .file-name {
                font-weight: 600;
                font-size: 16px;
                color: #333;
            }
            .file-size {
                font-size: 12px;
                color: #666;
                margin-top: 2px;
            }
            .file-status {
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
            }
            .file-status.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .file-status.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .lm-extra-inclusive {
                background: #ffc107 !important;
                color: #212529 !important;
                padding: 4px 8px !important;
                border-radius: 4px !important;
                margin: 2px 0 !important;
                display: inline-block !important;
            }
            
            /* Modal Single Column Layout */
            .lm-modal-content {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .lm-detail-section {
                background: #f9f9f9;
                border: 1px solid #e1e1e1;
                border-radius: 8px;
                padding: 16px;
            }
            .lm-detail-section h4 {
                margin: 0 0 12px 0;
                color: #333;
                font-size: 16px;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 8px;
            }
            .lm-detail-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #e1e1e1;
            }
            .lm-detail-item:last-child {
                border-bottom: none;
            }
            .lm-detail-item strong {
                color: #555;
                font-weight: 600;
                min-width: 120px;
            }
            .lm-detail-item span {
                color: #333;
                text-align: right;
                flex: 1;
            }
            </style>
            
            <div class="booking-details">
                <h2><?php echo esc_html($booking->post_title); ?></h2>
                
                <!-- Customer Information -->
                <div class="booking-section">
                    <h3><?php esc_html_e('Customer Information', 'lm-booking'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Name', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Email', 'lm-booking'); ?></th>
                            <td>
                                <a href="mailto:<?php echo esc_attr($meta['customer_email']); ?>"><?php echo esc_html($meta['customer_email']); ?></a>
                                <a href="<?php echo admin_url('admin.php?page=lm-booking&s=' . urlencode($meta['customer_email'])); ?>" class="button button-small"><?php esc_html_e('Find Similar', 'lm-booking'); ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Country', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['country']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Program', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['program']); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Service Details -->
                <div class="booking-section">
                    <h3><?php esc_html_e('Service Details', 'lm-booking'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Package', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['service']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Word Count', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['words']); ?> words</td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Norm Pages', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['norm_pages']); ?> pages</td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Delivery Option', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['delivery']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Delivery Date', 'lm-booking'); ?></th>
                            <td><?php echo esc_html($meta['delivery_date']); ?></td>
                        </tr>
                    </table>
                </div>


                <!-- Pricing Information -->
                <div class="booking-section">
                    <h3><?php esc_html_e('Pricing Information', 'lm-booking'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Total Amount', 'lm-booking'); ?></th>
                            <td><strong class="total-amount">€<?php echo esc_html($meta['total']); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'lm-booking'); ?></th>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($meta['status']); ?>">
                                    <?php echo esc_html(ucfirst($meta['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if ($meta['extras']): ?>
                <h3><?php esc_html_e('Extras', 'lm-booking'); ?></h3>
                <ul>
                    <?php 
                    $extras = json_decode($meta['extras'], true);
                    if (is_array($extras)) {
                        foreach ($extras as $extra) {
                            $isInclusive = !empty($extra['included_packages']);
                            $cssClass = $isInclusive ? 'lm-extra-inclusive' : '';
                            $priceDisplay = $isInclusive ? '' : ' (+€' . esc_html($extra['price']) . ')';
                            echo '<li class="' . $cssClass . '">' . esc_html($extra['label']) . $priceDisplay . '</li>';
                        }
                    }
                    ?>
                </ul>
                <?php endif; ?>
                
                <?php if ($meta['breakdown']): ?>
                <h3><?php esc_html_e('Price Breakdown', 'lm-booking'); ?></h3>
                <div class="lm-breakdown-formatted">
                    <?php echo $this->formatPriceBreakdown($meta['breakdown']); ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- File Download Section - Separate Container -->
        <div class="file-download-container">
            <h2><?php esc_html_e('Document Download', 'lm-booking'); ?></h2>
            <div class="file-download-section">
                <?php 
                if ($meta['file_uploaded'] && !empty($meta['document'])) {
                    $document = json_decode($meta['document'], true);
                    if (is_array($document) && isset($document['original_name'], $document['url'])) {
                        echo '<div class="file-download-info">';
                        echo '<div class="file-details">';
                        echo '<span class="file-icon">📄</span>';
                        echo '<div class="file-info">';
                        echo '<div class="file-name">' . esc_html($document['original_name']) . '</div>';
                        if (isset($document['size'])) {
                            echo '<div class="file-size">' . esc_html($this->formatFileSize($document['size'])) . '</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                        echo '<a href="' . esc_url($document['url']) . '" target="_blank" class="button button-primary button-large">';
                        echo 'Download Document';
                        echo '</a>';
                        echo '</div>';
                        echo '<div class="file-status success">✅ ' . __('File Available for Download', 'lm-booking') . '</div>';
                    } else {
                        echo '<div class="file-status success">✅ ' . __('File Uploaded', 'lm-booking') . '</div>';
                    }
                } else {
                    echo '<div class="file-status error">❌ ' . __('No File Available', 'lm-booking') . '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function handleExport(): void
    {
        // Handle POST request with selected booking IDs
        if (isset($_POST['export']) && isset($_POST['selected_booking_ids']) && isset($_POST['page']) && $_POST['page'] === 'lm-booking') {
            if (!wp_verify_nonce($_POST['export_nonce'], 'lm_export_bookings')) {
                wp_die(__('Security check failed.', 'lm-booking'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to export data.', 'lm-booking'));
            }

            $format = sanitize_text_field($_POST['export']);
            if (!in_array($format, ['csv', 'xls'])) {
                wp_die(__('Invalid export format.', 'lm-booking'));
            }

            $selected_ids = array_map('intval', $_POST['selected_booking_ids']);
            if (empty($selected_ids)) {
                wp_die(__('No bookings selected for export.', 'lm-booking'));
            }

            $bookings = $this->getBookingsByIds($selected_ids);
            $this->exportBookings($bookings, $format);
            return;
        }

        // Handle GET request (legacy support)
        if (!isset($_GET['export']) || $_GET['page'] !== 'lm-booking') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to export data.', 'lm-booking'));
        }

        $format = sanitize_text_field($_GET['export']);
        if (!in_array($format, ['csv', 'xls'])) {
            wp_die(__('Invalid export format.', 'lm-booking'));
        }

        $bookings = $this->getBookings();
        $this->exportBookings($bookings, $format);
    }

    private function getBookingsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $args = [
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $ids,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ];

        return get_posts($args);
    }

    private function exportBookings(array $bookings, string $format): void
    {
        $filename = 'bookings_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        if ($format === 'csv') {
            $this->exportToCSV($bookings, $filename);
        } else {
            $this->exportToXLS($bookings, $filename);
        }
    }

    private function exportToCSV(array $bookings, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers
        fputcsv($output, [
            'ID',
            'Date',
            'Customer Name',
            'Email',
            'Service',
            'Words',
            'Norm Pages',
            'Delivery',
            'Delivery Date',
            'Extras',
            'Total',
            'Status',
            'Country',
            'Program',
            'File Uploaded'
        ]);

        // Data
        foreach ($bookings as $booking) {
            $meta = $this->getBookingMeta($booking->ID);
            $extras = json_decode($meta['extras'], true);
            $extrasList = '';
            if (is_array($extras)) {
                $extrasList = implode('; ', array_map(function($extra) {
                    $isInclusive = !empty($extra['included_packages']);
                    $priceDisplay = $isInclusive ? '' : ' (+€' . $extra['price'] . ')';
                    return $extra['label'] . $priceDisplay;
                }, $extras));
            }

            fputcsv($output, [
                $booking->ID,
                $booking->post_date,
                $meta['customer_name'],
                $meta['customer_email'],
                $meta['service'],
                $meta['words'],
                $meta['norm_pages'],
                $meta['delivery'],
                $meta['delivery_date'],
                $extrasList,
                $meta['total'],
                $meta['status'],
                $meta['country'],
                $meta['program'],
                $meta['file_uploaded'] ? 'Yes' : 'No'
            ]);
        }

        fclose($output);
        exit;
    }

    private function exportToXLS(array $bookings, string $filename): void
    {
        // Simple XLS export using HTML table format
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<table border="1">';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Date</th>';
        echo '<th>Customer Name</th>';
        echo '<th>Email</th>';
        echo '<th>Service</th>';
        echo '<th>Words</th>';
        echo '<th>Norm Pages</th>';
        echo '<th>Delivery</th>';
        echo '<th>Delivery Date</th>';
        echo '<th>Extras</th>';
        echo '<th>Total</th>';
        echo '<th>Voucher</th>';
        echo '<th>Status</th>';
        echo '<th>Country</th>';
        echo '<th>Program</th>';
        echo '<th>File Uploaded</th>';
        echo '</tr>';

        foreach ($bookings as $booking) {
            $meta = $this->getBookingMeta($booking->ID);
            $extras = json_decode($meta['extras'], true);
            $extrasList = '';
            if (is_array($extras)) {
                $extrasList = implode('; ', array_map(function($extra) {
                    $isInclusive = !empty($extra['included_packages']);
                    $priceDisplay = $isInclusive ? '' : ' (+€' . $extra['price'] . ')';
                    return $extra['label'] . $priceDisplay;
                }, $extras));
            }

            echo '<tr>';
            echo '<td>' . esc_html($booking->ID) . '</td>';
            echo '<td>' . esc_html($booking->post_date) . '</td>';
            echo '<td>' . esc_html($meta['customer_name']) . '</td>';
            echo '<td>' . esc_html($meta['customer_email']) . '</td>';
            echo '<td>' . esc_html($meta['service']) . '</td>';
            echo '<td>' . esc_html($meta['words']) . '</td>';
            echo '<td>' . esc_html($meta['norm_pages']) . '</td>';
            echo '<td>' . esc_html($meta['delivery']) . '</td>';
            echo '<td>' . esc_html($meta['delivery_date']) . '</td>';
            echo '<td>' . esc_html($extrasList) . '</td>';
            echo '<td>' . esc_html($meta['total']) . '</td>';
            
            // Voucher column
            if (!empty($meta['voucher_code'])) {
                $voucherInfo = $meta['voucher_code'] . ' (' . $meta['voucher_discount'] . '%)';
                if (!empty($meta['original_total']) && !empty($meta['discount_amount'])) {
                    $voucherInfo .= '<br><small>Original: €' . $meta['original_total'] . '<br>Saved: €' . $meta['discount_amount'] . '</small>';
                }
                echo '<td style="background-color: #d4edda; color: #155724;">' . $voucherInfo . '</td>';
            } else {
                echo '<td>-</td>';
            }
            
            echo '<td>' . esc_html($meta['status']) . '</td>';
            echo '<td>' . esc_html($meta['country']) . '</td>';
            echo '<td>' . esc_html($meta['program']) . '</td>';
            echo '<td>' . ($meta['file_uploaded'] ? 'Yes' : 'No') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        exit;
    }

    public function getAvailablePackages(): array
    {
        $settings = get_option('lm_booking_settings', []);
        $services = $settings['services'] ?? [];
        
        $packages = [];
        foreach ($services as $service) {
            $packages[] = $service['label'];
        }
        
        return $packages;
    }

    public function getAvailableDeliveryOptions(): array
    {
        $settings = get_option('lm_booking_settings', []);
        $deliveryOptions = $settings['delivery_options'] ?? [];
        
        $options = [];
        foreach ($deliveryOptions as $option) {
            $options[$option['days'] . 'd'] = $option['label'];
        }
        
        return $options;
    }

    public function getFilterQueryString(): string
    {
        $params = [];
        
        if (!empty($_GET['status'])) {
            $params[] = 'status=' . urlencode($_GET['status']);
        }
        if (!empty($_GET['package'])) {
            $params[] = 'package=' . urlencode($_GET['package']);
        }
        if (!empty($_GET['delivery'])) {
            $params[] = 'delivery=' . urlencode($_GET['delivery']);
        }
        if (!empty($_GET['date_from'])) {
            $params[] = 'date_from=' . urlencode($_GET['date_from']);
        }
        if (!empty($_GET['date_to'])) {
            $params[] = 'date_to=' . urlencode($_GET['date_to']);
        }
        if (!empty($_GET['s'])) {
            $params[] = 's=' . urlencode($_GET['s']);
        }
        
        return empty($params) ? '' : '&' . implode('&', $params);
    }

    public function refreshBookingsCache(): void
    {
        // Clear any WordPress object cache for bookings
        wp_cache_delete('lm_booking_posts', 'posts');
        
        // Clear any transients that might cache booking data
        delete_transient('lm_booking_stats');
        delete_transient('lm_booking_list');
    }

    public function getRecentBookings(int $limit = 5): array
    {
        $bookings = get_posts([
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => []
        ]);

        return $bookings;
    }

    public function checkNewBookings(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], 'lm_check_bookings')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Get the last checked timestamp from user meta
        $user_id = get_current_user_id();
        $last_checked = get_user_meta($user_id, 'lm_last_booking_check', true);
        
        if (!$last_checked) {
            $last_checked = time() - 300; // Default to 5 minutes ago
        }

        // Check for bookings created after the last check
        $recent_bookings = get_posts([
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [
                [
                    'after' => date('Y-m-d H:i:s', $last_checked),
                    'inclusive' => false
                ]
            ]
        ]);

        // Update the last checked timestamp
        update_user_meta($user_id, 'lm_last_booking_check', time());

        wp_send_json_success([
            'has_new' => !empty($recent_bookings),
            'count' => count($recent_bookings)
        ]);
    }

    public function getBookingDetailsAjax(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], 'lm_booking_details')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $booking_id = intval($_POST['booking_id']);
        if (!$booking_id) {
            wp_send_json_error(['message' => 'Invalid booking ID']);
        }

        $meta = $this->getBookingMeta($booking_id);
        $booking = get_post($booking_id);
        
        if (!$booking) {
            wp_send_json_error(['message' => 'Booking not found']);
        }

        // Generate HTML for modal
        ob_start();
        ?>
        <div class="lm-booking-details-modal">
            <div class="lm-modal-content">
                <div class="lm-detail-section">
                    <h4><?php esc_html_e('Customer Information', 'lm-booking'); ?></h4>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Name:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['customer_name']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Email:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['customer_email']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Country:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($this->formatCountry($meta['country'])); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Program:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($this->formatProgram($meta['program'])); ?></span>
                    </div>
                    <?php if (!empty($meta['note'])): ?>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Notes:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['note']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="lm-detail-section">
                    <h4><?php esc_html_e('Service Details', 'lm-booking'); ?></h4>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Package:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['service']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Word Count:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['words']); ?> words</span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Norm Pages:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['norm_pages']); ?> pages</span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Delivery Option:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['delivery']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Delivery Date:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['delivery_date']); ?></span>
                    </div>
                </div>

                <?php if ($meta['extras']): ?>
                <div class="lm-detail-section">
                    <h4><?php esc_html_e('Extras', 'lm-booking'); ?></h4>
                    <ul class="lm-extras-list">
                        <?php 
                        $extras = json_decode($meta['extras'], true);
                        if (is_array($extras)) {
                            foreach ($extras as $extra) {
                                $isInclusive = !empty($extra['included_packages']);
                                $cssClass = $isInclusive ? 'lm-extra-inclusive' : '';
                                $priceDisplay = $isInclusive ? '' : ' (+€' . esc_html($extra['price']) . ')';
                                echo '<li class="' . $cssClass . '">' . esc_html($extra['label']) . $priceDisplay . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="lm-detail-section">
                    <h4><?php esc_html_e('Pricing & Status', 'lm-booking'); ?></h4>
                    <?php if (!empty($meta['voucher_code'])): ?>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Original Amount:', 'lm-booking'); ?></strong>
                        <span>€<?php echo esc_html($meta['original_total']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Voucher Code:', 'lm-booking'); ?></strong>
                        <span style="background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($meta['voucher_code']); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Discount:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html($meta['voucher_discount']); ?>% (-€<?php echo esc_html($meta['discount_amount']); ?>)</span>
                    </div>
                    <?php endif; ?>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Total Amount:', 'lm-booking'); ?></strong>
                        <span><strong>€<?php echo esc_html($meta['total']); ?></strong></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Status:', 'lm-booking'); ?></strong>
                        <span class="status-badge status-<?php echo esc_attr($meta['status']); ?>"><?php echo esc_html(ucfirst($meta['status'])); ?></span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Uploaded File:', 'lm-booking'); ?></strong>
                        <span>
                            <?php 
                            if ($meta['file_uploaded'] && !empty($meta['document'])) {
                                $document = json_decode($meta['document'], true);
                                if (is_array($document) && isset($document['original_name'], $document['url'])) {
                                    echo '<a href="' . esc_url($document['url']) . '" target="_blank" class="button button-small" style="margin-right: 8px;">';
                                    echo '📄 ' . esc_html($document['original_name']);
                                    echo '</a>';
                                    if (isset($document['size'])) {
                                        echo '<span style="color: #666; font-size: 12px;">(' . esc_html($this->formatFileSize($document['size'])) . ')</span>';
                                    }
                                } else {
                                    echo '✅ ' . __('Yes', 'lm-booking');
                                }
                            } else {
                                echo '❌ ' . __('No', 'lm-booking');
                            }
                            ?>
                        </span>
                    </div>
                    <div class="lm-detail-item">
                        <strong><?php esc_html_e('Created:', 'lm-booking'); ?></strong>
                        <span><?php echo esc_html(date('M j, Y H:i', strtotime($booking->post_date))); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($meta['breakdown']): ?>
            <div class="lm-detail-section lm-full-width">
                <h4><?php esc_html_e('Price Breakdown', 'lm-booking'); ?></h4>
                <div class="lm-breakdown-formatted">
                    <?php echo $this->formatPriceBreakdown($meta['breakdown']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    private function formatPriceBreakdown(string $breakdown): string
    {
        $data = json_decode($breakdown, true);
        if (!$data) {
            return '<pre>' . esc_html($breakdown) . '</pre>';
        }

        $html = '<div class="lm-price-breakdown">';
        
        // Norm Pages
        if (isset($data['normPages'])) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Norm Pages:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">' . esc_html($data['normPages']) . '</span>';
            $html .= '</div>';
        }

        // Base Price
        if (isset($data['base'])) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Base Price:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">€' . number_format($data['base'], 2) . '</span>';
            $html .= '</div>';
        }

        // Extras
        if (isset($data['extras']) && is_array($data['extras']) && !empty($data['extras'])) {
            $html .= '<div class="lm-breakdown-section">';
            $html .= '<div class="lm-breakdown-label">' . __('Extras:', 'lm-booking') . '</div>';
            foreach ($data['extras'] as $extra) {
                $isInclusive = !empty($extra['included_packages']);
                $cssClass = $isInclusive ? 'lm-breakdown-row lm-breakdown-indent lm-extra-inclusive' : 'lm-breakdown-row lm-breakdown-indent';
                $html .= '<div class="' . $cssClass . '">';
                $html .= '<span class="lm-breakdown-label">' . esc_html($extra['label'] ?? 'Extra') . ':</span>';
                if ($isInclusive) {
                    $html .= '<span class="lm-breakdown-value">Included</span>';
                } else {
                    $html .= '<span class="lm-breakdown-value">€' . number_format($extra['price'] ?? 0, 2) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Extras Total
        if (isset($data['extrasTotal']) && $data['extrasTotal'] > 0) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Extras Total:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">€' . number_format($data['extrasTotal'], 2) . '</span>';
            $html .= '</div>';
        }

        // Subtotal
        if (isset($data['subtotal'])) {
            $html .= '<div class="lm-breakdown-row lm-breakdown-subtotal">';
            $html .= '<span class="lm-breakdown-label">' . __('Subtotal:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">€' . number_format($data['subtotal'], 2) . '</span>';
            $html .= '</div>';
        }

        // Surcharge
        if (isset($data['surcharge']) && $data['surcharge'] > 0) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Surcharge:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">€' . number_format($data['surcharge'], 2) . '</span>';
            $html .= '</div>';
        }

        // Total
        if (isset($data['total'])) {
            $html .= '<div class="lm-breakdown-row lm-breakdown-total">';
            $html .= '<span class="lm-breakdown-label">' . __('Total:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">€' . number_format($data['total'], 2) . '</span>';
            $html .= '</div>';
        }

        // Delivery
        if (isset($data['delivery'])) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Delivery:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">' . esc_html($data['delivery']) . '</span>';
            $html .= '</div>';
        }

        // Multiplier
        if (isset($data['multiplier']) && $data['multiplier'] != 1) {
            $html .= '<div class="lm-breakdown-row">';
            $html .= '<span class="lm-breakdown-label">' . __('Multiplier:', 'lm-booking') . '</span>';
            $html .= '<span class="lm-breakdown-value">' . esc_html($data['multiplier']) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function updateBookingStatusAjax(): void
    {
        // Debug logging
        
        if (!wp_verify_nonce($_POST['nonce'], 'lm_update_status')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $booking_id = intval($_POST['booking_id']);
        $status = sanitize_text_field($_POST['status']);
        
        
        if (!$booking_id || !in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'])) {
            wp_send_json_error(['message' => 'Invalid parameters - booking_id: ' . $booking_id . ', status: ' . $status]);
        }

        // Check if booking exists
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'lm_booking') {
            wp_send_json_error(['message' => 'Booking not found']);
        }

        $result = update_post_meta($booking_id, '_lm_booking_status', $status);

        // Send email notification to customer
        $email_result = $this->sendStatusNotificationEmail($booking_id, $status);

        wp_send_json_success(['message' => 'Status updated successfully']);
    }

    public function completeBookingAjax(): void
    {
        // Debug logging
        
        if (!wp_verify_nonce($_POST['nonce'], 'lm_complete_booking')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $booking_id = intval($_POST['booking_id']);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        
        if (!$booking_id) {
            wp_send_json_error(['message' => 'Invalid booking ID']);
        }

        // Check if booking exists
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'lm_booking') {
            wp_send_json_error(['message' => 'Booking not found']);
        }

        // Handle file upload
        $uploaded_file = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $this->handleCompletedFileUpload($_FILES['file'], $booking_id);
            if (!$uploaded_file) {
                wp_send_json_error(['message' => 'File upload failed']);
            }
        }

        // Update status to completed
        update_post_meta($booking_id, '_lm_booking_status', 'completed');

        // Save completion data
        if ($uploaded_file) {
            update_post_meta($booking_id, '_lm_booking_completed_file', wp_json_encode($uploaded_file));
        }
        if ($message) {
            update_post_meta($booking_id, '_lm_booking_completion_message', $message);
        }

        // Send completion email to customer
        $this->sendCompletionEmail($booking_id, $uploaded_file, $message);

        wp_send_json_success(['message' => 'Booking completed and email sent']);
    }

    public function deleteBookingAjax(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], 'lm_delete_booking')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $booking_id = intval($_POST['booking_id']);
        if (!$booking_id) {
            wp_send_json_error(['message' => 'Invalid booking ID']);
        }

        $result = wp_delete_post($booking_id, true);
        if (!$result) {
            wp_send_json_error(['message' => 'Failed to delete booking']);
        }

        wp_send_json_success(['message' => 'Booking deleted successfully']);
    }

    private function sendStatusNotificationEmail(int $booking_id, string $status): bool
    {
        
        $email = get_post_meta($booking_id, '_lm_booking_customer_email', true);
        $name = get_post_meta($booking_id, '_lm_booking_customer_name', true);
        
        
        if (!$email || !$name) {
            return false;
        }

        $subject = '';
        $message = '';
        
        switch ($status) {
            case 'in_progress':
                $subject = __('Your booking is now in progress', 'lm-booking');
                $message = sprintf(
                    '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                        <h2 style="color: #17a2b8;">Booking Status Update</h2>
                        <p>Hello %s,</p>
                        <p>Your booking request is now being processed by our team. We will work on your document and notify you once it\'s completed.</p>
                        <p>Thank you for choosing our services!</p>
                        <p>Best regards,<br>The Team</p>
                    </body></html>',
                    $name
                );
                break;
            case 'completed':
                $subject = __('Your booking has been completed', 'lm-booking');
                $message = sprintf(
                    '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                        <h2 style="color: #28a745;">Booking Completed</h2>
                        <p>Hello %s,</p>
                        <p>Great news! Your booking has been completed. Please check your email for the revised document.</p>
                        <p>Thank you for choosing our services!</p>
                        <p>Best regards,<br>The Team</p>
                    </body></html>',
                    $name
                );
                break;
        }

        if ($subject && $message) {
            
            // Set headers for HTML email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            $result = wp_mail($email, $subject, $message, $headers);
            return $result;
        }
        
        return false;
    }

    private function sendCompletionEmail(int $booking_id, ?array $file_data, string $message): void
    {
        $email = get_post_meta($booking_id, '_lm_booking_customer_email', true);
        $name = get_post_meta($booking_id, '_lm_booking_customer_name', true);
        
        if (!$email || !$name) {
            return;
        }

        $subject = __('Your document is ready for download', 'lm-booking');
        
        $email_message = sprintf(
            '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <h2 style="color: #28a745;">Document Ready for Download</h2>
                <p>Hello %s,</p>
                <p>Your document has been reviewed and is ready for download.</p>',
            $name
        );

        if ($message) {
            $email_message .= '<div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #17a2b8; margin: 20px 0;">
                <p style="margin: 0;"><strong>Message from our team:</strong></p>
                <p style="margin: 10px 0 0 0;">' . nl2br(esc_html($message)) . '</p>
            </div>';
        }

        $email_message .= '<p>You can download your revised document using the link below:</p>';

        if ($file_data) {
            $email_message .= sprintf(
                '<div style="background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0 0 10px 0;"><strong>Download Information:</strong></p>
                    <p style="margin: 5px 0;"><strong>File:</strong> %s</p>
                    <p style="margin: 5px 0;"><strong>Size:</strong> %s</p>
                    <p style="margin: 10px 0 0 0;">
                        <a href="%s" style="background-color: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Download Document</a>
                    </p>
                </div>',
                esc_html($file_data['original_name']),
                $this->formatFileSize($file_data['size']),
                esc_url($file_data['url'])
            );
        }

        $email_message .= '<p>Thank you for choosing our services!</p>
            <p>Best regards,<br>The Team</p>
        </body></html>';

        // Prepare attachments
        $attachments = [];
        if ($file_data && file_exists($file_data['path'])) {
            $attachments[] = $file_data['path'];
        }

        // Set headers for HTML email
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($email, $subject, $email_message, $headers, $attachments);
    }

    private function handleCompletedFileUpload(array $file, int $booking_id): ?array
    {
        $upload_dir = wp_upload_dir();
        $completed_dir = $upload_dir['basedir'] . '/lektorat-mac/completed/';
        
        // Create directory if it doesn't exist
        if (!file_exists($completed_dir)) {
            wp_mkdir_p($completed_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            file_put_contents($completed_dir . '.htaccess', $htaccess_content);
        }

        // Validate file
        $allowed_types = ['doc', 'docx', 'pdf', 'rtf', 'odt'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            return null;
        }

        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            return null;
        }

        // Generate unique filename
        $unique_filename = 'completed_' . $booking_id . '_' . uniqid() . '.' . $file_extension;
        $file_path = $completed_dir . $unique_filename;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'original_name' => $file['name'],
                'path' => $file_path,
                'url' => $upload_dir['baseurl'] . '/lektorat-mac/completed/' . $unique_filename,
                'size' => $file['size'],
                'type' => $file['type']
            ];
        }

        return null;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }

}
