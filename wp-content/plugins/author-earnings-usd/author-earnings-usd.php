<?php
/**
 * Plugin Name: Author Earnings (USD) - Per Publish
 * Description: Pay authors a configurable USD amount when content is first published, for admin-selected post types. Shows balance to users in wp-admin.
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) exit;

class AEUSD_Author_Earnings {
    const BALANCE_META   = 'aeusd_balance_usd';         // user meta
    const PAID_FLAG_META = '_aeusd_paid_on_publish';    // post meta flag
    const CREDIT_AMOUNT_META = '_aeusd_credit_amount';
    const CREDITED_AT_META   = '_aeusd_credited_at';
    const DEDUCTED_FLAG_META = '_aeusd_deducted_on_unpublish';
    const LAST_PAID_META     = 'aeusd_last_paid_at';
    const OPT_RATE       = 'aeusd_rate';                // option
    const OPT_TYPES      = 'aeusd_enabled_post_types';  // option

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);

        add_action('transition_post_status', [__CLASS__, 'on_transition_post_status'], 10, 3);
        add_action('wp_trash_post', [__CLASS__, 'on_trash_post'], 10, 1);
        add_action('before_delete_post', [__CLASS__, 'on_delete_post'], 10, 1);

        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        add_action('admin_menu', [__CLASS__, 'add_earnings_menu']);
        add_action('admin_post_aeusd_submit_request', [__CLASS__, 'handle_submit_request']);
        add_action('admin_post_aeusd_review_request', [__CLASS__, 'handle_review_request']);

        // Block editors from deleting content (keeps Editor role otherwise unchanged)
        add_filter('map_meta_cap', [__CLASS__, 'block_editor_delete_caps'], 10, 4);

        // Optional: hide Trash/Delete UI for editors
        add_filter('post_row_actions', [__CLASS__, 'hide_delete_row_actions'], 10, 2);
        add_filter('page_row_actions', [__CLASS__, 'hide_delete_row_actions'], 10, 2);
    }

    /* ---------------------------
     * Settings
     * --------------------------- */
    public static function register_settings() {
        register_setting('aeusd_settings', self::OPT_RATE, [
            'type'              => 'number',
            'sanitize_callback' => [__CLASS__, 'sanitize_rate'],
            'default'           => 0.15,
        ]);

        register_setting('aeusd_settings', self::OPT_TYPES, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_types'],
            'default'           => ['post'],
        ]);
    }

    public static function sanitize_rate($value) {
        $v = is_numeric($value) ? (float)$value : 0.0;
        if ($v < 0) $v = 0.0;
        // keep 4 decimal precision max
        return round($v, 4);
    }

    public static function sanitize_types($value) {
        if (!is_array($value)) return [];
        $value = array_map('sanitize_key', $value);
        $value = array_values(array_unique($value));

        // Only allow post types that exist and have UI
        $valid = array_keys(self::get_rewardable_post_types());
        return array_values(array_intersect($value, $valid));
    }

    private static function get_rate(): float {
        $rate = get_option(self::OPT_RATE, 0.15);
        return is_numeric($rate) ? (float)$rate : 0.0;
    }

    private static function get_enabled_types(): array {
        $types = get_option(self::OPT_TYPES, ['post']);
        return is_array($types) ? array_values(array_unique(array_filter($types, 'is_string'))) : [];
    }

    private static function get_rewardable_post_types(): array {
        // Show UI post types only
        $objects = get_post_types(['show_ui' => true], 'objects');

        // Return [post_type => label]
        $out = [];
        foreach ($objects as $pt => $obj) {
            $out[$pt] = $obj->labels->singular_name ?? $pt;
        }
        ksort($out);
        return $out;
    }

    private static function get_requests_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aeusd_payment_requests';
    }

    public static function activate() {
        global $wpdb;
        $table = self::get_requests_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            author_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            reviewed_at datetime DEFAULT NULL,
            admin_id bigint(20) unsigned DEFAULT NULL,
            admin_note text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY author_id (author_id),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function add_settings_page() {
        add_options_page(
            'Author Earnings (USD)',
            'Author Earnings (USD)',
            'manage_options',
            'aeusd-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) wp_die('Not allowed');

        $rate  = self::get_rate();
        $types = self::get_enabled_types();
        $catalog = self::get_rewardable_post_types();

        echo '<div class="wrap">';
        echo '<h1>Author Earnings (USD)</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('aeusd_settings');

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row"><label for="aeusd_rate">Rate per first publish (USD)</label></th><td>';
        echo '<input name="' . esc_attr(self::OPT_RATE) . '" id="aeusd_rate" type="number" step="0.01" min="0" value="' . esc_attr(number_format($rate, 2, '.', '')) . '" />';
        echo '<p class="description">Example: 0.15 means $0.15 each time content is published for the first time.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Post types that earn money</th><td>';
        echo '<fieldset>';
        foreach ($catalog as $pt => $label) {
            $checked = in_array($pt, $types, true);
            echo '<label style="display:block; margin: 4px 0;">';
            echo '<input type="checkbox" name="' . esc_attr(self::OPT_TYPES) . '[]" value="' . esc_attr($pt) . '" ' . checked($checked, true, false) . ' /> ';
            echo esc_html($label . " ({$pt})");
            echo '</label>';
        }
        echo '<p class="description">Only these post types will generate earnings on first publish.</p>';
        echo '</fieldset>';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Save Settings');
        echo '</form>';
        echo '</div>';
    }

    /* ---------------------------
     * Earnings logic
     * --------------------------- */
    public static function on_transition_post_status($new_status, $old_status, $post) {
        if (!($post instanceof WP_Post)) return;

        // skip revisions/autosaves
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;

        $enabled_types = self::get_enabled_types();
        if (!in_array($post->post_type, $enabled_types, true)) return;

        if ($new_status === 'publish' && $old_status !== 'publish') {
            $author_id = (int)$post->post_author;
            if ($author_id <= 0) return;

            $rate = self::get_rate();
            if ($rate <= 0) return;

            $paid_flag = get_post_meta($post->ID, self::PAID_FLAG_META, true);
            $deducted  = get_post_meta($post->ID, self::DEDUCTED_FLAG_META, true);

            if ($paid_flag && $deducted) {
                $amount = self::get_post_credit_amount($post->ID, $rate);
                self::adjust_user_balance($author_id, $amount);
                delete_post_meta($post->ID, self::DEDUCTED_FLAG_META);
                return;
            }

            if ($paid_flag) return;

            self::adjust_user_balance($author_id, $rate);
            update_post_meta($post->ID, self::PAID_FLAG_META, 1);
            update_post_meta($post->ID, self::CREDIT_AMOUNT_META, $rate);
            update_post_meta($post->ID, self::CREDITED_AT_META, current_time('mysql'));
            delete_post_meta($post->ID, self::DEDUCTED_FLAG_META);
            return;
        }

        if ($old_status === 'publish' && $new_status !== 'publish') {
            self::deduct_post_earnings($post);
        }
    }

    public static function get_balance(int $user_id): float {
        return (float) get_user_meta($user_id, self::BALANCE_META, true);
    }

    private static function adjust_user_balance(int $user_id, float $delta) {
        $current = (float) get_user_meta($user_id, self::BALANCE_META, true);
        $new     = max(0, $current + $delta);
        update_user_meta($user_id, self::BALANCE_META, $new);
    }

    private static function get_post_credit_amount(int $post_id, float $fallback): float {
        $stored = get_post_meta($post_id, self::CREDIT_AMOUNT_META, true);
        if (is_numeric($stored)) {
            return (float) $stored;
        }
        return $fallback;
    }

    private static function get_post_credited_at(int $post_id): ?int {
        $credited = get_post_meta($post_id, self::CREDITED_AT_META, true);
        if ($credited) {
            $timestamp = strtotime($credited);
            if ($timestamp) {
                return $timestamp;
            }
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return null;
        }

        $fallback_date = $post->post_date_gmt && $post->post_date_gmt !== '0000-00-00 00:00:00'
            ? $post->post_date_gmt
            : $post->post_date;
        $timestamp = strtotime($fallback_date);
        return $timestamp ? $timestamp : null;
    }

    private static function get_last_paid_at(int $user_id): ?int {
        $value = get_user_meta($user_id, self::LAST_PAID_META, true);
        if (!$value) {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp ? $timestamp : null;
    }

    private static function deduct_post_earnings(WP_Post $post) {
        $paid_flag = get_post_meta($post->ID, self::PAID_FLAG_META, true);
        if (!$paid_flag) return;

        if (get_post_meta($post->ID, self::DEDUCTED_FLAG_META, true)) return;

        $author_id = (int)$post->post_author;
        if ($author_id <= 0) return;

        $credited_at = self::get_post_credited_at($post->ID);
        $last_paid   = self::get_last_paid_at($author_id);

        if ($credited_at && $last_paid && $credited_at <= $last_paid) {
            return;
        }

        $rate = self::get_rate();
        $amount = self::get_post_credit_amount($post->ID, $rate);
        if ($amount <= 0) return;

        self::adjust_user_balance($author_id, -$amount);
        update_post_meta($post->ID, self::DEDUCTED_FLAG_META, 1);
    }

    public static function on_trash_post($post_id) {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        $enabled_types = self::get_enabled_types();
        if (!in_array($post->post_type, $enabled_types, true)) return;

        self::deduct_post_earnings($post);
    }

    public static function on_delete_post($post_id) {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        $enabled_types = self::get_enabled_types();
        if (!in_array($post->post_type, $enabled_types, true)) return;

        self::deduct_post_earnings($post);
    }

    private static function format_usd(float $amount): string {
        // Simple USD formatting (no locale dependencies)
        return '$' . number_format($amount, 2, '.', ',');
    }

    /* ---------------------------
     * UI for users
     * --------------------------- */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'aeusd_author_earnings_widget',
            'Your Earnings (USD)',
            [__CLASS__, 'render_dashboard_widget']
        );
    }

    public static function render_dashboard_widget() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>Please log in.</p>';
            return;
        }

        $balance = self::get_balance($user_id);
        $rate    = self::get_rate();
        $pending = self::get_pending_request($user_id);

        echo '<p><strong>Current balance:</strong> ' . esc_html(self::format_usd($balance)) . '</p>';
        if ($pending) {
            echo '<p><strong>Payment request:</strong> Pending approval for ' . esc_html(self::format_usd((float) $pending->amount)) . '</p>';
        }
        echo '<p><strong>Rate:</strong> ' . esc_html(self::format_usd($rate)) . ' per first-time publish (eligible post types set by admin).</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=aeusd-earnings')) . '">View earnings &amp; requests</a></p>';
    }

    public static function add_earnings_menu() {
        add_menu_page(
            'Earnings (USD)',
            'Earnings',
            'read',
            'aeusd-earnings',
            [__CLASS__, 'render_earnings_page'],
            'dashicons-money-alt',
            58
        );

        add_submenu_page(
            'aeusd-earnings',
            'Payment Requests',
            'Payment Requests',
            'manage_options',
            'aeusd-payment-requests',
            [__CLASS__, 'render_admin_requests_page']
        );
    }

    public static function render_earnings_page() {
        $user_id = get_current_user_id();
        if (!$user_id) wp_die('Please log in.');

        $balance = self::get_balance($user_id);
        $rate    = self::get_rate();
        $types   = self::get_enabled_types();
        $pending = self::get_pending_request($user_id);
        $history = self::get_user_requests($user_id);

        echo '<div class="wrap">';
        echo '<h1>Earnings (USD)</h1>';
        echo '<p><strong>Current payable balance:</strong> ' . esc_html(self::format_usd($balance)) . '</p>';
        echo '<p><strong>Rate:</strong> ' . esc_html(self::format_usd($rate)) . ' per first-time publish.</p>';
        echo '<p><strong>Eligible post types:</strong> ' . esc_html(implode(', ', $types)) . '</p>';

        echo '<hr />';
        echo '<h2>Request payment</h2>';
        if ($pending) {
            echo '<p><strong>Status:</strong> Pending approval for ' . esc_html(self::format_usd((float) $pending->amount)) . ' (requested ' . esc_html(self::format_datetime($pending->created_at)) . ')</p>';
        } elseif ($balance <= 0) {
            echo '<p>You have no payable balance available.</p>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('aeusd_submit_request', 'aeusd_nonce');
            echo '<input type="hidden" name="action" value="aeusd_submit_request" />';
            echo '<p>You can request payment for your current payable balance of <strong>' . esc_html(self::format_usd($balance)) . '</strong>.</p>';
            echo '<p><button class="button button-primary" type="submit">Submit Payment Request</button></p>';
            echo '</form>';
        }

        echo '<hr />';
        echo '<h2>Payment history</h2>';
        if (empty($history)) {
            echo '<p>No payment requests yet.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>Requested</th>';
            echo '<th>Amount</th>';
            echo '<th>Status</th>';
            echo '<th>Admin Note</th>';
            echo '<th>Reviewed</th>';
            echo '</tr></thead><tbody>';
            foreach ($history as $request) {
                echo '<tr>';
                echo '<td>' . esc_html(self::format_datetime($request->created_at)) . '</td>';
                echo '<td>' . esc_html(self::format_usd((float) $request->amount)) . '</td>';
                echo '<td>' . esc_html(self::format_status_label($request->status)) . '</td>';
                echo '<td>' . esc_html($request->admin_note ?: '—') . '</td>';
                echo '<td>' . esc_html($request->reviewed_at ? self::format_datetime($request->reviewed_at) : '—') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public static function render_admin_requests_page() {
        if (!current_user_can('manage_options')) wp_die('Not allowed');

        $requests = self::get_all_requests();

        echo '<div class="wrap">';
        echo '<h1>Payment Requests</h1>';
        if (empty($requests)) {
            echo '<p>No payment requests found.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Author</th>';
        echo '<th>Requested</th>';
        echo '<th>Amount</th>';
        echo '<th>Status</th>';
        echo '<th>Admin Note</th>';
        echo '<th>Review</th>';
        echo '</tr></thead><tbody>';

        foreach ($requests as $request) {
            $author = get_userdata((int) $request->author_id);
            $author_name = $author ? $author->display_name : 'Unknown';
            echo '<tr>';
            echo '<td>' . esc_html($author_name) . '</td>';
            echo '<td>' . esc_html(self::format_datetime($request->created_at)) . '</td>';
            echo '<td>' . esc_html(self::format_usd((float) $request->amount)) . '</td>';
            echo '<td>' . esc_html(self::format_status_label($request->status)) . '</td>';
            echo '<td>' . esc_html($request->admin_note ?: '—') . '</td>';
            echo '<td>';
            if ($request->status === 'pending') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('aeusd_review_request', 'aeusd_nonce');
                echo '<input type="hidden" name="action" value="aeusd_review_request" />';
                echo '<input type="hidden" name="request_id" value="' . esc_attr((int) $request->id) . '" />';
                echo '<p><textarea name="admin_note" rows="2" cols="30" placeholder="Optional note..."></textarea></p>';
                echo '<p>';
                echo '<button class="button button-primary" type="submit" name="decision" value="approve">Approve</button> ';
                echo '<button class="button" type="submit" name="decision" value="reject">Reject</button>';
                echo '</p>';
                echo '</form>';
            } else {
                echo esc_html($request->reviewed_at ? self::format_datetime($request->reviewed_at) : '—');
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function format_datetime($datetime): string {
        if (!$datetime) return '—';
        $timestamp = strtotime($datetime);
        if (!$timestamp) return '—';
        return date_i18n('M j, Y g:i a', $timestamp);
    }

    private static function format_status_label(string $status): string {
        $map = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    private static function get_pending_request(int $user_id) {
        global $wpdb;
        $table = self::get_requests_table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE author_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1", $user_id, 'pending')
        );
    }

    private static function get_user_requests(int $user_id): array {
        global $wpdb;
        $table = self::get_requests_table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE author_id = %d ORDER BY created_at DESC", $user_id)
        );
    }

    private static function get_all_requests(): array {
        global $wpdb;
        $table = self::get_requests_table();
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }

    public static function handle_submit_request() {
        if (!is_user_logged_in()) {
            wp_die('Please log in.');
        }

        check_admin_referer('aeusd_submit_request', 'aeusd_nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_die('Please log in.');
        }

        $balance = self::get_balance($user_id);
        if ($balance <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=aeusd-earnings&aeusd=empty'));
            exit;
        }

        if (self::get_pending_request($user_id)) {
            wp_safe_redirect(admin_url('admin.php?page=aeusd-earnings&aeusd=pending'));
            exit;
        }

        global $wpdb;
        $table = self::get_requests_table();
        $wpdb->insert(
            $table,
            [
                'author_id' => $user_id,
                'amount' => $balance,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%f', '%s', '%s']
        );

        wp_safe_redirect(admin_url('admin.php?page=aeusd-earnings&aeusd=requested'));
        exit;
    }

    public static function handle_review_request() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('aeusd_review_request', 'aeusd_nonce');

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $decision   = isset($_POST['decision']) ? sanitize_key($_POST['decision']) : '';
        $admin_note = isset($_POST['admin_note']) ? sanitize_textarea_field($_POST['admin_note']) : '';

        if (!$request_id || !in_array($decision, ['approve', 'reject'], true)) {
            wp_safe_redirect(admin_url('admin.php?page=aeusd-payment-requests'));
            exit;
        }

        global $wpdb;
        $table = self::get_requests_table();
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id));

        if (!$request || $request->status !== 'pending') {
            wp_safe_redirect(admin_url('admin.php?page=aeusd-payment-requests'));
            exit;
        }

        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $reviewed_at = current_time('mysql');

        $wpdb->update(
            $table,
            [
                'status' => $status,
                'reviewed_at' => $reviewed_at,
                'admin_id' => get_current_user_id(),
                'admin_note' => $admin_note,
            ],
            ['id' => $request_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($status === 'approved') {
            $current_balance = self::get_balance((int) $request->author_id);
            $approved_amount = (float) $request->amount;
            $new_balance = max(0, $current_balance - $approved_amount);
            update_user_meta((int) $request->author_id, self::BALANCE_META, $new_balance);
            update_user_meta((int) $request->author_id, self::LAST_PAID_META, $reviewed_at);
        }

        wp_safe_redirect(admin_url('admin.php?page=aeusd-payment-requests'));
        exit;
    }

    /* ---------------------------
     * Block Editors from deleting content
     * --------------------------- */

    /**
     * Hard-block Editors from deleting/trashing any content (any post type).
     * Editors can still edit/publish/update as usual.
     */
    public static function block_editor_delete_caps($caps, $cap, $user_id, $args) {
        if (!in_array($cap, ['delete_post', 'delete_page'], true)) {
            return $caps;
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->roles) || !in_array('editor', (array)$user->roles, true)) {
            return $caps;
        }

        $post_id = isset($args[0]) ? (int)$args[0] : 0;
        if ($post_id > 0) {
            // Deny delete/trash for editors
            return ['do_not_allow'];
        }

        return $caps;
    }

    /**
     * UI cleanup: Hide Trash/Delete links for editors in list tables.
     * (Even if hidden, the cap check above is the real security.)
     */
    public static function hide_delete_row_actions($actions, $post) {
        $user = wp_get_current_user();
        if ($user && in_array('editor', (array)$user->roles, true)) {
            unset($actions['trash'], $actions['delete']);
        }
        return $actions;
    }
}

register_activation_hook(__FILE__, ['AEUSD_Author_Earnings', 'activate']);
AEUSD_Author_Earnings::init();
