<?php
/**
 * Admin Vouchers Page Template
 * 
 * @package LM\Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_POST) {
    // Debug: Log all POST data
    error_log('LM Booking Debug: POST data received: ' . print_r($_POST, true));
    
    if (isset($_POST['action']) && wp_verify_nonce($_POST['lm_voucher_nonce'], 'lm_voucher_action')) {
        $action = sanitize_text_field($_POST['action']);
        error_log('LM Booking Debug: Processing action: ' . $action);
        
        switch ($action) {
                
            case 'delete_voucher':
                $voucher_id = sanitize_text_field($_POST['voucher_id']);
                
                // Find the voucher to get the email
                $vouchers = get_option('lm_booking_vouchers', []);
                $voucher_email = '';
                foreach ($vouchers as $voucher) {
                    if ($voucher['id'] === $voucher_id) {
                        $voucher_email = $voucher['email'];
                        break;
                    }
                }
                
                // Use comprehensive deletion
                require_once LM_BOOKING_DIR . 'includes/Infra/Repo.php';
                $repo = new \LM\Booking\Infra\Repo();
                $deleted = $repo->deleteVoucherCompletely($voucher_id, $voucher_email);
                
                if ($deleted) {
                    echo '<div class="notice notice-success"><p>' . __('Voucher and all related data deleted successfully!', 'lm-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Error deleting voucher. Please try again.', 'lm-booking') . '</p></div>';
                }
                break;
                
            case 'delete_vouchers_by_email':
                $email = sanitize_email($_POST['email']);
                if (!empty($email)) {
                    require_once LM_BOOKING_DIR . 'includes/Infra/Repo.php';
                    $repo = new \LM\Booking\Infra\Repo();
                    $deleted = $repo->deleteVoucherByEmail($email);
                    
                    if ($deleted) {
                        echo '<div class="notice notice-success"><p>' . sprintf(__('All vouchers for %s deleted successfully!', 'lm-booking'), $email) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . __('Error deleting vouchers. Please try again.', 'lm-booking') . '</p></div>';
                    }
                }
                break;
                
            case 'toggle_voucher':
                $voucher_id = sanitize_text_field($_POST['voucher_id']);
                $vouchers = get_option('lm_booking_vouchers', []);
                foreach ($vouchers as &$voucher) {
                    if ($voucher['id'] === $voucher_id) {
                        $voucher['used'] = !$voucher['used'];
                        break;
                    }
                }
                update_option('lm_booking_vouchers', $vouchers);
                echo '<div class="notice notice-success"><p>' . __('Voucher status updated!', 'lm-booking') . '</p></div>';
                break;
                
            case 'update_settings':
                $signup_discount = floatval($_POST['signup_discount']);
                $signup_expiry_days = intval($_POST['signup_expiry_days']);
                
                error_log('LM Booking Debug: Saving settings - discount: ' . $signup_discount . ', expiry_days: ' . $signup_expiry_days);
                
                $settings = get_option('lm_booking_settings', []);
                $settings['voucher_signup_discount'] = $signup_discount;
                $settings['voucher_signup_expiry_days'] = $signup_expiry_days;
                
                error_log('LM Booking Debug: Settings array before save: ' . print_r($settings, true));
                
                $result = update_option('lm_booking_settings', $settings);
                
                error_log('LM Booking Debug: Update result: ' . ($result ? 'success' : 'failed'));
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Voucher settings updated successfully!', 'lm-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update voucher settings. Please try again.', 'lm-booking') . '</p></div>';
                }
                break;
                
            case 'export_csv':
                // Get filtered vouchers for export
                $all_vouchers = get_option('lm_booking_vouchers', []);
                $export_vouchers = [];
                
                // Apply same filtering logic as display
                if (!empty($date_from) || !empty($date_to)) {
                    foreach ($all_vouchers as $voucher) {
                        $voucher_date = strtotime($voucher['created']);
                        $include_voucher = true;
                        
                        if (!empty($date_from)) {
                            $from_date = strtotime($date_from);
                            if ($voucher_date < $from_date) {
                                $include_voucher = false;
                            }
                        }
                        
                        if (!empty($date_to)) {
                            $to_date = strtotime($date_to . ' 23:59:59');
                            if ($voucher_date > $to_date) {
                                $include_voucher = false;
                            }
                        }
                        
                        if ($include_voucher) {
                            $export_vouchers[] = $voucher;
                        }
                    }
                } else {
                    $export_vouchers = $all_vouchers;
                }
                
                exportVouchersToCSV($export_vouchers);
                break;
        }
    }
}

$vouchers = get_option('lm_booking_vouchers', []);
$settings = get_option('lm_booking_settings', []);
$signup_discount = $settings['voucher_signup_discount'] ?? 15;
$signup_expiry_days = $settings['voucher_signup_expiry_days'] ?? 30;

// Debug: Log current settings
error_log('LM Booking Debug: Current settings loaded - discount: ' . $signup_discount . ', expiry_days: ' . $signup_expiry_days);
error_log('LM Booking Debug: Full settings array: ' . print_r($settings, true));

// Handle date filtering
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Filter vouchers by date if filters are applied
if (!empty($date_from) || !empty($date_to)) {
    $filtered_vouchers = [];
    foreach ($vouchers as $voucher) {
        $voucher_date = strtotime($voucher['created']);
        $include_voucher = true;
        
        if (!empty($date_from)) {
            $from_date = strtotime($date_from);
            if ($voucher_date < $from_date) {
                $include_voucher = false;
            }
        }
        
        if (!empty($date_to)) {
            $to_date = strtotime($date_to . ' 23:59:59');
            if ($voucher_date > $to_date) {
                $include_voucher = false;
            }
        }
        
        if ($include_voucher) {
            $filtered_vouchers[] = $voucher;
        }
    }
    $vouchers = $filtered_vouchers;
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$total_vouchers = count($vouchers);
$total_pages = ceil($total_vouchers / $per_page);
$offset = ($current_page - 1) * $per_page;
$paginated_vouchers = array_slice($vouchers, $offset, $per_page);

// CSV Export Function
function exportVouchersToCSV($filtered_vouchers = []) {
    // Use filtered vouchers if provided, otherwise get all vouchers
    $vouchers = !empty($filtered_vouchers) ? $filtered_vouchers : get_option('lm_booking_vouchers', []);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gutscheine-' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV header
    fputcsv($output, ['E-Mail-Adresse', 'Gutscheincode', 'Rabatt (%)', 'Status', 'Erstellungsdatum', 'Ablaufdatum']);
    
    // Add voucher data
    foreach ($vouchers as $voucher) {
        $status = $voucher['used'] ? 'Verwendet' : 'Aktiv';
        if (!$voucher['used'] && strtotime($voucher['expiry']) < time()) {
            $status = 'Abgelaufen';
        }
        
        fputcsv($output, [
            $voucher['email'],
            $voucher['code'],
            $voucher['discount'],
            $status,
            date('Y-m-d H:i:s', strtotime($voucher['created'])),
            $voucher['expiry']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Gutschein-Verwaltung', 'lm-booking'); ?></h1>
    
    <div class="lm-vouchers-container">
        <!-- Voucher Settings -->
        <div class="lm-voucher-settings">
            <h2><?php esc_html_e('Gutschein-Anmeldeeinstellungen', 'lm-booking'); ?></h2>
            <p><?php esc_html_e('Konfigurieren Sie den Standard-Rabattsatz und die Ablaufzeit für Gutscheine, die über das Anmeldeformular generiert werden.', 'lm-booking'); ?></p>
            
            <form method="post" class="lm-form" id="voucher-settings-form">
                <?php wp_nonce_field('lm_voucher_action', 'lm_voucher_nonce'); ?>
                <input type="hidden" name="action" value="update_settings">
                
                <div class="lm-form-row">
                    <div class="lm-form-group">
                        <label for="signup_discount"><?php esc_html_e('Standard-Rabattsatz (%)', 'lm-booking'); ?></label>
                        <input type="number" id="signup_discount" name="signup_discount" 
                               min="0" max="100" step="0.1" value="<?php echo esc_attr($signup_discount); ?>" required>
                        <p class="description"><?php esc_html_e('Der Rabattprozentsatz, der auf Gutscheine angewendet wird, die über das Anmeldeformular generiert werden.', 'lm-booking'); ?></p>
                    </div>
                    
                    <div class="lm-form-group">
                        <label for="signup_expiry_days"><?php esc_html_e('Standard-Ablaufzeit (Tage)', 'lm-booking'); ?></label>
                        <input type="number" id="signup_expiry_days" name="signup_expiry_days" 
                               min="1" max="365" value="<?php echo esc_attr($signup_expiry_days); ?>" required>
                        <p class="description"><?php esc_html_e('Anzahl der Tage bis der Gutschein abläuft.', 'lm-booking'); ?></p>
                    </div>
                </div>
                
                <div class="lm-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Einstellungen speichern', 'lm-booking'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        
        <!-- Vouchers List -->
        <div class="lm-vouchers-list">
            <div class="lm-vouchers-header">
                <h2><?php esc_html_e('Vorhandene Gutscheine', 'lm-booking'); ?></h2>
                <div class="lm-vouchers-actions">
                    <?php if (!empty($vouchers)): ?>
                        <form method="post" style="display: inline-block;">
                            <?php wp_nonce_field('lm_voucher_action', 'lm_voucher_nonce'); ?>
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                            <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e('Als CSV exportieren', 'lm-booking'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Date Filter -->
            <div class="lm-voucher-filters">
                <form method="get" class="lm-filter-form">
                    <input type="hidden" name="page" value="lm-booking-vouchers">
                    <div class="lm-filter-row">
                        <div class="lm-filter-group">
                            <label for="date_from"><?php esc_html_e('Von Datum', 'lm-booking'); ?></label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        <div class="lm-filter-group">
                            <label for="date_to"><?php esc_html_e('Bis Datum', 'lm-booking'); ?></label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        <div class="lm-filter-group">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Filtern', 'lm-booking'); ?>
                            </button>
                            <?php if (!empty($date_from) || !empty($date_to)): ?>
                                <a href="<?php echo admin_url('admin.php?page=lm-booking-vouchers'); ?>" class="button button-secondary">
                                    <?php esc_html_e('Filter löschen', 'lm-booking'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (empty($vouchers)): ?>
                <p><?php esc_html_e('Keine Gutscheine gefunden.', 'lm-booking'); ?></p>
            <?php else: ?>
                <div class="lm-vouchers-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('E-Mail', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Code', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Rabatt', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Ablauf', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Erstellt', 'lm-booking'); ?></th>
                                <th><?php esc_html_e('Aktionen', 'lm-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_vouchers as $voucher): ?>
                                <tr class="<?php echo $voucher['used'] ? 'lm-voucher-used' : 'lm-voucher-active'; ?>">
                                    <td><?php echo esc_html($voucher['email']); ?></td>
                                    <td><code><?php echo esc_html($voucher['code']); ?></code></td>
                                    <td><?php echo esc_html($voucher['discount']); ?>%</td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($voucher['expiry']))); ?></td>
                                    <td>
                                        <span class="lm-status <?php echo $voucher['used'] ? 'lm-status-used' : 'lm-status-active'; ?>">
                                            <?php echo $voucher['used'] ? __('Verwendet', 'lm-booking') : __('Aktiv', 'lm-booking'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($voucher['created']))); ?></td>
                                    <td>
                                        <form method="post" style="display: inline-block;">
                                            <?php wp_nonce_field('lm_voucher_action', 'lm_voucher_nonce'); ?>
                                            <input type="hidden" name="action" value="toggle_voucher">
                                            <input type="hidden" name="voucher_id" value="<?php echo esc_attr($voucher['id']); ?>">
                                            <button type="submit" class="button button-small lm-action-btn" 
                                                    title="<?php echo $voucher['used'] ? esc_attr__('Als aktiv markieren', 'lm-booking') : esc_attr__('Als verwendet markieren', 'lm-booking'); ?>">
                                                <?php if ($voucher['used']): ?>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M9 12l2 2 4-4"/>
                                                        <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                                                        <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                                                        <path d="M12 3v6"/>
                                                        <path d="M12 15v6"/>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M9 12l2 2 4-4"/>
                                                        <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                                                        <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                                                        <path d="M12 3v6"/>
                                                        <path d="M12 15v6"/>
                                                    </svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="post" style="display: inline-block; margin-left: 5px;">
                                            <?php wp_nonce_field('lm_voucher_action', 'lm_voucher_nonce'); ?>
                                            <input type="hidden" name="action" value="delete_voucher">
                                            <input type="hidden" name="voucher_id" value="<?php echo esc_attr($voucher['id']); ?>">
                                            <button type="submit" class="button button-small button-link-delete lm-action-btn" 
                                                    title="<?php esc_attr_e('Löschen', 'lm-booking'); ?>"
                                                    onclick="return confirm('<?php esc_attr_e('Sind Sie sicher, dass Sie diesen Gutschein löschen möchten?', 'lm-booking'); ?>')">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M3 6h18"/>
                                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.lm-vouchers-container {
    max-width: 1200px;
}

.lm-voucher-settings {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.lm-voucher-settings h2 {
    margin-top: 0;
    color: #23282d;
}

.lm-voucher-settings p {
    color: #666;
    margin-bottom: 20px;
}

.lm-voucher-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.lm-voucher-filters h3 {
    margin-top: 0;
    color: #23282d;
}

.lm-filter-form {
    margin: 0;
}

.lm-filter-row {
    display: flex;
    gap: 20px;
    align-items: end;
}

.lm-filter-group {
    flex: 1;
}

.lm-filter-group:last-child {
    flex: 0 0 auto;
    display: flex;
    gap: 10px;
}

.lm-filter-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #23282d;
}

.lm-filter-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.lm-filter-group input:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}


.lm-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.lm-form-group {
    flex: 1;
}

.lm-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #23282d;
}

.lm-form-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.lm-form-group input:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}

.lm-form-actions {
    margin-top: 20px;
}

.lm-vouchers-list h2 {
    color: #23282d;
    margin-bottom: 15px;
}

.lm-vouchers-table {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}

.lm-vouchers-table table {
    margin: 0;
    border: none;
}

.lm-vouchers-table th {
    background: #f1f1f1;
    font-weight: 600;
    padding: 12px;
}

.lm-vouchers-table td {
    padding: 12px;
    vertical-align: middle;
}

.lm-voucher-used {
    opacity: 0.6;
}

.lm-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.lm-status-active {
    background: #d4edda;
    color: #155724;
}

.lm-status-used {
    background: #f8d7da;
    color: #721c24;
}

.lm-vouchers-table code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 13px;
}

.button-small {
    padding: 4px 8px;
    font-size: 12px;
    height: auto;
    line-height: 1.4;
}

/* Action buttons with SVG icons */
.lm-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 8px;
    min-width: 32px;
    height: 32px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.lm-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.lm-action-btn svg {
    pointer-events: none;
}

.lm-action-btn.button-link-delete {
    color: #dc3545;
}

.lm-action-btn.button-link-delete:hover {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Ensure voucher settings form submits properly
    $('#voucher-settings-form').on('submit', function(e) {
        console.log('Voucher settings form submitting...');
        console.log('Form data:', $(this).serialize());
        console.log('Form action:', $(this).find('input[name="action"]').val());
        console.log('Nonce:', $(this).find('input[name="lm_voucher_nonce"]').val());
        
        // Ensure form submits normally
        return true;
    });
    
});
</script>
