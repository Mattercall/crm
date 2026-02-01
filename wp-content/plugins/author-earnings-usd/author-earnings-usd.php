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
    const OPT_RATE       = 'aeusd_rate';                // option
    const OPT_TYPES      = 'aeusd_enabled_post_types';  // option

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);

        add_action('transition_post_status', [__CLASS__, 'on_transition_post_status'], 10, 3);

        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        add_action('admin_menu', [__CLASS__, 'add_earnings_menu']);

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

        // only when it becomes published (first time)
        if ($new_status !== 'publish' || $old_status === 'publish') return;

        // skip revisions/autosaves
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;

        $enabled_types = self::get_enabled_types();
        if (!in_array($post->post_type, $enabled_types, true)) return;

        // prevent double-pay if publish -> draft -> publish, etc.
        if (get_post_meta($post->ID, self::PAID_FLAG_META, true)) return;

        $rate = self::get_rate();
        if ($rate <= 0) return;

        $author_id = (int)$post->post_author;
        if ($author_id <= 0) return;

        $current = (float) get_user_meta($author_id, self::BALANCE_META, true);
        $new     = $current + $rate;

        update_user_meta($author_id, self::BALANCE_META, $new);
        update_post_meta($post->ID, self::PAID_FLAG_META, 1);
    }

    public static function get_balance(int $user_id): float {
        return (float) get_user_meta($user_id, self::BALANCE_META, true);
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

        echo '<p><strong>Current balance:</strong> ' . esc_html(self::format_usd($balance)) . '</p>';
        echo '<p><strong>Rate:</strong> ' . esc_html(self::format_usd($rate)) . ' per first-time publish (eligible post types set by admin).</p>';
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
    }

    public static function render_earnings_page() {
        $user_id = get_current_user_id();
        if (!$user_id) wp_die('Please log in.');

        $balance = self::get_balance($user_id);
        $rate    = self::get_rate();
        $types   = self::get_enabled_types();

        echo '<div class="wrap">';
        echo '<h1>Earnings (USD)</h1>';
        echo '<p><strong>Current balance:</strong> ' . esc_html(self::format_usd($balance)) . '</p>';
        echo '<p><strong>Rate:</strong> ' . esc_html(self::format_usd($rate)) . ' per first-time publish.</p>';
        echo '<p><strong>Eligible post types:</strong> ' . esc_html(implode(', ', $types)) . '</p>';
        echo '</div>';
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

AEUSD_Author_Earnings::init();
