<?php

namespace LM\Booking\Admin;

class Columns
{
    public function init(): void
    {
        add_filter('manage_lm_booking_posts_columns', [$this, 'addColumns']);
        add_action('manage_lm_booking_posts_custom_column', [$this, 'renderColumns'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'addFilters']);
        add_action('pre_get_posts', [$this, 'filterPosts']);
    }

    public function addColumns(array $columns): array
    {
        $newColumns = [];
        $newColumns['cb'] = $columns['cb'];
        $newColumns['title'] = $columns['title'];
        $newColumns['customer'] = __('Customer', 'lm-booking');
        $newColumns['service'] = __('Service', 'lm-booking');
        $newColumns['extras'] = __('Extras', 'lm-booking');
        $newColumns['total'] = __('Total', 'lm-booking');
        $newColumns['date'] = $columns['date'];

        return $newColumns;
    }

    public function renderColumns(string $column, int $postId): void
    {
        switch ($column) {
            case 'customer':
                $name = get_post_meta($postId, '_lm_booking_customer_name', true);
                $email = get_post_meta($postId, '_lm_booking_customer_email', true);
                if ($name) {
                    echo esc_html($name);
                    if ($email) {
                        echo '<br><small>' . esc_html($email) . '</small>';
                    }
                }
                break;

            case 'service':
                $service = get_post_meta($postId, '_lm_booking_service', true);
                if ($service) {
                    echo esc_html($service);
                }
                break;

            case 'extras':
                $extras = get_post_meta($postId, '_lm_booking_extras', true);
                $service = get_post_meta($postId, '_lm_booking_service', true);
                if ($extras) {
                    $extrasArray = json_decode($extras, true);
                    if (is_array($extrasArray) && !empty($extrasArray)) {
                        $extrasList = implode('; ', array_map(function($extra) use ($service) {
                            // Get settings to determine package index
                            $settings = get_option('lm_booking_settings', []);
                            $services = $settings['services'] ?? [];
                            $selectedPackageIndex = null;
                            
                            foreach ($services as $index => $s) {
                                if ($s['label'] === $service) {
                                    $selectedPackageIndex = $index;
                                    break;
                                }
                            }
                            
                            // Check if this extra is actually inclusive for the selected package
                            $includedPackages = $extra['included_packages'] ?? [];
                            
                            // Convert included_packages to integers to ensure proper comparison
                            $includedPackages = array_map('intval', $includedPackages);
                            $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                            
                            // Debug logging
                            error_log('Columns Debug - Extra: ' . $extra['label'] . ', Service: ' . $service . ', PackageIndex: ' . $selectedPackageIndex . ', IncludedPackages: ' . print_r($includedPackages, true) . ', IsInclusive: ' . ($isInclusive ? 'YES' : 'NO'));
                            
                            if ($isInclusive) {
                                return '<span style="background-color: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' . esc_html($extra['label']) . ' (inkl.)</span>';
                            } else {
                                $price = $extra['price'] == floor($extra['price']) ? 
                                    $extra['price'] : 
                                    number_format($extra['price'], 2, ',', '');
                                return esc_html($extra['label']) . ' (+€' . $price . ')';
                            }
                        }, $extrasArray));
                        echo $extrasList;
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                break;

            case 'total':
                $total = get_post_meta($postId, '_lm_booking_total', true);
                if ($total) {
                    echo esc_html(number_format($total, 2)) . '€';
                }
                break;
        }
    }

    public function addFilters(): void
    {
        global $typenow;
        
        if ($typenow !== 'lm_booking') {
            return;
        }

        $currentMonth = $_GET['lm_month'] ?? '';
        $currentSearch = $_GET['lm_email_search'] ?? '';

        echo '<select name="lm_month">';
        echo '<option value="">' . esc_html__('All Months', 'lm-booking') . '</option>';
        echo '<option value="this_month"' . selected($currentMonth, 'this_month', false) . '>' . esc_html__('This Month', 'lm-booking') . '</option>';
        echo '<option value="last_month"' . selected($currentMonth, 'last_month', false) . '>' . esc_html__('Last Month', 'lm-booking') . '</option>';
        echo '</select>';

        echo '<input type="text" name="lm_email_search" placeholder="' . esc_attr__('Search by email...', 'lm-booking') . '" value="' . esc_attr($currentSearch) . '">';
    }

    public function filterPosts(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'lm_booking') {
            return;
        }

        $monthFilter = $_GET['lm_month'] ?? '';
        $emailSearch = $_GET['lm_email_search'] ?? '';

        if ($monthFilter) {
            $dateQuery = [];
            
            if ($monthFilter === 'this_month') {
                $dateQuery = [
                    'year' => date('Y'),
                    'month' => date('n'),
                ];
            } elseif ($monthFilter === 'last_month') {
                $lastMonth = date('n') - 1;
                $year = date('Y');
                if ($lastMonth < 1) {
                    $lastMonth = 12;
                    $year--;
                }
                $dateQuery = [
                    'year' => $year,
                    'month' => $lastMonth,
                ];
            }

            if (!empty($dateQuery)) {
                $query->set('date_query', [$dateQuery]);
            }
        }

        if ($emailSearch) {
            $metaQuery = [
                'key' => '_lm_booking_customer_email',
                'value' => sanitize_text_field($emailSearch),
                'compare' => 'LIKE',
            ];
            $query->set('meta_query', [$metaQuery]);
        }
    }
}
