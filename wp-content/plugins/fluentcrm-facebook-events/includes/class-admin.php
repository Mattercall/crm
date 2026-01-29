<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Admin
{
    const SETTINGS_SLUG = 'fcrm-fb-events';
    const LEAD_SETTINGS_SLUG = 'fcrm-fb-lead-settings';
    const LEAD_IMPORT_SLUG = 'fcrm-fb-lead-import';
    const LEAD_MAPPING_SLUG = 'fcrm-fb-lead-mapping';
    const LEAD_LOGS_SLUG = 'fcrm-fb-lead-logs';

    public function init()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_notices', [$this, 'maybe_show_notice']);
        add_action('admin_post_fcrm_fb_events_save_settings', [$this, 'handle_save']);
        add_action('admin_post_fcrm_fb_events_save_lead_settings', [$this, 'handle_save_lead_settings']);
        add_action('admin_post_fcrm_fb_events_save_lead_mapping', [$this, 'handle_save_lead_mapping']);
        add_action('admin_post_fcrm_fb_events_import_leads', [$this, 'handle_import_leads']);
        add_action('wp_ajax_fcrm_fb_events_import_leads_ajax', [$this, 'handle_import_leads_ajax']);
        add_action('admin_post_fcrm_fb_events_test_connection', [$this, 'handle_test_connection']);
        add_action('admin_init', [$this, 'maybe_redirect_legacy_slug']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public static function fluentcrm_active()
    {
        return defined('FLUENTCRM');
    }

    public function register_menu()
    {
        $capability = 'manage_options';

        add_menu_page(
            __('Facebook Events', 'fluentcrm-facebook-events'),
            __('Facebook Events', 'fluentcrm-facebook-events'),
            $capability,
            self::SETTINGS_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-facebook-alt'
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Events Settings', 'fluentcrm-facebook-events'),
            __('Events Settings', 'fluentcrm-facebook-events'),
            $capability,
            self::SETTINGS_SLUG,
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Lead Ads Settings', 'fluentcrm-facebook-events'),
            __('Lead Ads Settings', 'fluentcrm-facebook-events'),
            $capability,
            self::LEAD_SETTINGS_SLUG,
            [$this, 'render_lead_settings_page']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Lead Ads Import', 'fluentcrm-facebook-events'),
            __('Lead Ads Import', 'fluentcrm-facebook-events'),
            $capability,
            self::LEAD_IMPORT_SLUG,
            [$this, 'render_lead_import_page']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Lead Ads Mapping', 'fluentcrm-facebook-events'),
            __('Lead Ads Mapping', 'fluentcrm-facebook-events'),
            $capability,
            self::LEAD_MAPPING_SLUG,
            [$this, 'render_lead_mapping_page']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Lead Ads Logs', 'fluentcrm-facebook-events'),
            __('Lead Ads Logs', 'fluentcrm-facebook-events'),
            $capability,
            self::LEAD_LOGS_SLUG,
            [$this, 'render_lead_logs_page']
        );
    }

    public function enqueue_admin_assets($hook)
    {
        if (!is_admin()) {
            return;
        }

        $page = $_GET['page'] ?? '';
        if ($page !== self::LEAD_IMPORT_SLUG) {
            return;
        }

        wp_enqueue_script(
            'fcrm-fb-lead-import',
            FCRM_FB_EVENTS_URL . 'assets/lead-import.js',
            ['jquery'],
            FCRM_FB_EVENTS_VERSION,
            true
        );

        wp_enqueue_style(
            'fcrm-fb-lead-import',
            FCRM_FB_EVENTS_URL . 'assets/lead-import.css',
            [],
            FCRM_FB_EVENTS_VERSION
        );

        wp_localize_script('fcrm-fb-lead-import', 'FcrmFbLeadImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcrm_fb_events_import_leads_ajax'),
            'loadingText' => __('Fetching leads...', 'fluentcrm-facebook-events'),
            'doneText' => __('Import completed.', 'fluentcrm-facebook-events'),
            'errorText' => __('Import failed. Please check logs for details.', 'fluentcrm-facebook-events'),
        ]);
    }

    public function maybe_show_notice()
    {
        if (self::fluentcrm_active()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html__('FluentCRM is not active. FluentCRM Facebook Events will remain idle until FluentCRM is activated.', 'fluentcrm-facebook-events') . '</p></div>';
    }

    public function maybe_redirect_legacy_slug()
    {
        if (!is_admin()) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($request_uri === '') {
            return;
        }

        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $admin_path = wp_parse_url(admin_url(), PHP_URL_PATH);
        if (!$admin_path) {
            return;
        }

        $legacy_path = untrailingslashit(trailingslashit($admin_path) . self::SETTINGS_SLUG);
        if (untrailingslashit($path) !== $legacy_path) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SETTINGS_SLUG));
        exit;
    }

    public static function get_default_settings()
    {
        return [
            'enabled' => 'no',
            'pixel_id' => '',
            'access_token' => '',
            'test_event_code' => '',
            'event_source_url' => '',
            'send_mode' => 'immediate',
            'debug_logging' => 'no',
            'delete_data_on_uninstall' => 'no',
            'lead_ads' => [
                'enabled' => 'no',
                'app_id' => '',
                'app_secret' => '',
                'access_token' => '',
                'page_id' => '',
                'ad_account_id' => '',
                'verify_token' => '',
                'form_ids' => [],
                'missing_email_action' => 'skip',
                'contact_status' => 'subscribed',
                'dedupe_by_phone' => 'no',
                'tag_ids' => [],
                'list_ids' => [],
            ],
            'lead_field_mapping' => [
                'email' => 'email',
                'full_name' => 'full_name',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'phone_number' => 'phone',
                'phone' => 'phone',
                'lead_id' => '',
            ],
            'mappings' => [
                'tag_applied' => [
                    'label' => __('Tag Applied', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Lead',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'tag_ids' => [],
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'tag_removed' => [
                    'label' => __('Tag Removed', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Lead',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'tag_ids' => [],
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'contact_created' => [
                    'label' => __('Contact Created', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'CompleteRegistration',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'email_opened' => [
                    'label' => __('Email Opened', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'ViewContent',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'email_clicked' => [
                    'label' => __('Email Link Clicked', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'ViewContent',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
            ],
        ];
    }

    public static function get_settings()
    {
        $defaults = self::get_default_settings();
        $settings = get_option(FCRM_FB_EVENTS_OPTION_KEY, []);

        $settings = wp_parse_args($settings, $defaults);
        $settings['mappings'] = array_intersect_key($settings['mappings'], $defaults['mappings']);

        foreach ($defaults['mappings'] as $key => $mapping) {
            $settings['mappings'][$key] = wp_parse_args($settings['mappings'][$key] ?? [], $mapping);
        }

        $settings['lead_ads'] = wp_parse_args($settings['lead_ads'] ?? [], $defaults['lead_ads']);
        $settings['lead_field_mapping'] = is_array($settings['lead_field_mapping'] ?? null)
            ? $settings['lead_field_mapping']
            : $defaults['lead_field_mapping'];

        return $settings;
    }

    public function handle_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_save_settings');

        $input = wp_unslash($_POST);
        $defaults = self::get_default_settings();
        $existing = get_option(FCRM_FB_EVENTS_OPTION_KEY, []);

        $settings = wp_parse_args($existing, $defaults);
        $settings = array_merge($settings, [
            'enabled' => !empty($input['enabled']) ? 'yes' : 'no',
            'pixel_id' => sanitize_text_field($input['pixel_id'] ?? ''),
            'access_token' => sanitize_text_field($input['access_token'] ?? ''),
            'test_event_code' => sanitize_text_field($input['test_event_code'] ?? ''),
            'event_source_url' => esc_url_raw($input['event_source_url'] ?? ''),
            'send_mode' => in_array(($input['send_mode'] ?? 'immediate'), ['immediate', 'queue'], true) ? $input['send_mode'] : 'immediate',
            'debug_logging' => !empty($input['debug_logging']) ? 'yes' : 'no',
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']) ? 'yes' : 'no',
            'mappings' => $defaults['mappings'],
        ]);

        foreach ($defaults['mappings'] as $key => $mapping) {
            $map_input = $input['mappings'][$key] ?? [];
            $settings['mappings'][$key]['enabled'] = !empty($map_input['enabled']) ? 'yes' : 'no';
            $settings['mappings'][$key]['event_name'] = sanitize_text_field($map_input['event_name'] ?? $mapping['event_name']);
            $settings['mappings'][$key]['send_custom_event'] = !empty($map_input['send_custom_event']) ? 'yes' : 'no';
            $settings['mappings'][$key]['custom_event_name'] = sanitize_text_field($map_input['custom_event_name'] ?? '');
            if (in_array($key, ['tag_applied', 'tag_removed'], true)) {
                $tag_ids = array_map('absint', (array) ($map_input['tag_ids'] ?? []));
                $settings['mappings'][$key]['tag_ids'] = array_values(array_filter(array_unique($tag_ids)));
            }
            $settings['mappings'][$key]['value'] = sanitize_text_field($map_input['value'] ?? '');
            $settings['mappings'][$key]['currency'] = sanitize_text_field($map_input['currency'] ?? $mapping['currency']);
            $settings['mappings'][$key]['custom_params'] = $this->sanitize_custom_params($map_input['custom_params'] ?? '');
        }

        update_option(FCRM_FB_EVENTS_OPTION_KEY, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::SETTINGS_SLUG, 'updated' => 'true'], admin_url('admin.php')));
        exit;
    }

    public function handle_save_lead_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_save_lead_settings');

        $input = wp_unslash($_POST);
        $defaults = self::get_default_settings();
        $settings = self::get_settings();

        $lead_settings = [
            'enabled' => !empty($input['lead_enabled']) ? 'yes' : 'no',
            'app_id' => sanitize_text_field($input['app_id'] ?? ''),
            'app_secret' => sanitize_text_field($input['app_secret'] ?? ''),
            'access_token' => sanitize_text_field($input['lead_access_token'] ?? ''),
            'page_id' => sanitize_text_field($input['page_id'] ?? ''),
            'ad_account_id' => sanitize_text_field($input['ad_account_id'] ?? ''),
            'verify_token' => sanitize_text_field($input['verify_token'] ?? ''),
            'form_ids' => array_values(array_filter(array_map('sanitize_text_field', (array) ($input['form_ids'] ?? [])))),
            'missing_email_action' => in_array(($input['missing_email_action'] ?? 'skip'), ['skip', 'phone_only'], true) ? $input['missing_email_action'] : 'skip',
            'contact_status' => in_array(($input['contact_status'] ?? 'subscribed'), ['subscribed', 'pending'], true) ? $input['contact_status'] : 'subscribed',
            'dedupe_by_phone' => !empty($input['dedupe_by_phone']) ? 'yes' : 'no',
            'tag_ids' => array_values(array_filter(array_map('absint', (array) ($input['tag_ids'] ?? [])))),
            'list_ids' => array_values(array_filter(array_map('absint', (array) ($input['list_ids'] ?? [])))),
        ];

        $settings['lead_ads'] = wp_parse_args($lead_settings, $defaults['lead_ads']);

        update_option(FCRM_FB_EVENTS_OPTION_KEY, $settings, false);

        if ($settings['lead_ads']['enabled'] === 'yes' && !empty($settings['lead_ads']['page_id'])) {
            $lead_ads = fcrm_fb_events_lead_ads();
            $subscription = $lead_ads->subscribe_page_to_webhook($settings['lead_ads']['page_id']);
            if (is_wp_error($subscription)) {
                set_transient('fcrm_fb_events_lead_subscription_notice', $subscription->get_error_message(), 30);
            } else {
                set_transient('fcrm_fb_events_lead_subscription_notice', __('Lead Ads webhook subscription updated.', 'fluentcrm-facebook-events'), 30);
            }
        }

        wp_safe_redirect(add_query_arg(['page' => self::LEAD_SETTINGS_SLUG, 'updated' => 'true'], admin_url('admin.php')));
        exit;
    }

    public function handle_save_lead_mapping()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_save_lead_mapping');

        $input = wp_unslash($_POST);
        $settings = self::get_settings();
        $mapping_input = $input['mapping'] ?? [];
        $custom_keys = $input['mapping_custom_key'] ?? [];
        $custom_values = $input['mapping_custom_value'] ?? [];
        $mapping = [];

        foreach ($mapping_input as $fb_field => $fluent_field) {
            $fb_field = sanitize_key($fb_field);
            $fluent_field = sanitize_key($fluent_field);

            if ($fb_field && $fluent_field) {
                $mapping[$fb_field] = $fluent_field;
            }
        }

        if (!empty($custom_keys) && !empty($custom_values)) {
            foreach ($custom_keys as $index => $custom_key) {
                $custom_key = sanitize_key($custom_key);
                $custom_value = isset($custom_values[$index]) ? sanitize_key($custom_values[$index]) : '';
                if ($custom_key && $custom_value) {
                    $mapping[$custom_key] = $custom_value;
                }
            }
        }

        $settings['lead_field_mapping'] = $mapping;

        update_option(FCRM_FB_EVENTS_OPTION_KEY, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::LEAD_MAPPING_SLUG, 'updated' => 'true'], admin_url('admin.php')));
        exit;
    }

    public function handle_import_leads()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_import_leads');

        $result = $this->run_lead_import(wp_unslash($_POST));

        if (is_wp_error($result)) {
            set_transient('fcrm_fb_events_lead_import_notice', $result->get_error_message(), 30);
            wp_safe_redirect(add_query_arg(['page' => self::LEAD_IMPORT_SLUG], admin_url('admin.php')));
            exit;
        }

        set_transient('fcrm_fb_events_lead_import_notice', $result['message'], 30);

        wp_safe_redirect(add_query_arg(['page' => self::LEAD_IMPORT_SLUG, 'page_id' => $result['page_id'], 'form_id' => $result['form_id']], admin_url('admin.php')));
        exit;
    }

    public function handle_import_leads_ajax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'fluentcrm-facebook-events')], 403);
        }

        check_ajax_referer('fcrm_fb_events_import_leads_ajax', 'nonce');

        $result = $this->run_lead_import(wp_unslash($_POST));
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    private function run_lead_import(array $input)
    {
        $page_id = sanitize_text_field($input['page_id'] ?? '');
        $form_id = sanitize_text_field($input['form_id'] ?? '');
        $limit = !empty($input['limit']) ? absint($input['limit']) : 50;
        $after = sanitize_text_field($input['after'] ?? '');

        $since = sanitize_text_field($input['since'] ?? '');
        $until = sanitize_text_field($input['until'] ?? '');
        $since_timestamp = $since ? strtotime($since . ' 00:00:00') : null;
        $until_timestamp = $until ? strtotime($until . ' 23:59:59') : null;

        if (!$form_id) {
            return new WP_Error('missing_form', __('Form ID is required.', 'fluentcrm-facebook-events'));
        }

        $lead_ads = fcrm_fb_events_lead_ads();
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $next_cursor = '';
        $pages = 0;

        do {
            $response = $lead_ads->fetch_leads($form_id, [
                'limit' => $limit,
                'after' => $after ?: null,
                'since' => $since_timestamp,
                'until' => $until_timestamp,
                'page_id' => $page_id,
            ]);

            if (is_wp_error($response)) {
                $this->log_lead_import_error($response, $form_id, $page_id);
                return new WP_Error('fetch_error', $response->get_error_message());
            }

            $leads = $response['data'] ?? [];
            foreach ($leads as $lead) {
                $result = $lead_ads->import_lead_payload($lead, [
                    'form_id' => $form_id,
                    'page_id' => $page_id,
                    'source' => 'manual',
                ]);

                if (is_wp_error($result)) {
                    if ($result->get_error_code() === 'duplicate') {
                        $skipped++;
                    } else {
                        $errors++;
                    }
                } else {
                    $imported++;
                }
            }

            $next_cursor = $response['paging']['cursors']['after'] ?? '';
            $after = $next_cursor;
            $pages++;
        } while ($after && $pages < 10);

        $state = get_option(FCRM_FB_EVENTS_LEAD_IMPORT_OPTION, []);
        $state['forms'] = $state['forms'] ?? [];
        $state['forms'][$form_id] = current_time('mysql');
        update_option(FCRM_FB_EVENTS_LEAD_IMPORT_OPTION, $state, false);

        $message = sprintf(
            __('Imported %1$d lead(s), skipped %2$d, errors %3$d.', 'fluentcrm-facebook-events'),
            $imported,
            $skipped,
            $errors
        );

        if ($next_cursor) {
            $message .= ' ' . sprintf(__('Next cursor: %s', 'fluentcrm-facebook-events'), $next_cursor);
        }

        return [
            'page_id' => $page_id,
            'form_id' => $form_id,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'next_cursor' => $next_cursor,
            'message' => $message,
        ];
    }

    public function handle_test_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_test_connection');

        $lead_ads = fcrm_fb_events_lead_ads();
        $result = $lead_ads->test_connection();
        if (is_wp_error($result)) {
            set_transient('fcrm_fb_events_lead_test_notice', $result->get_error_message(), 30);
        } else {
            set_transient('fcrm_fb_events_lead_test_notice', __('Connection successful.', 'fluentcrm-facebook-events'), 30);
        }

        wp_safe_redirect(add_query_arg(['page' => self::LEAD_SETTINGS_SLUG], admin_url('admin.php')));
        exit;
    }

    private function sanitize_custom_params($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return wp_json_encode($decoded);
        }

        return '';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $logs = FCRM_FB_Events_Logger::get_logs(50);
        $updated = !empty($_GET['updated']);
        $tags = $this->get_available_tags();
        $event_options = [
            'Lead',
            'CompleteRegistration',
            'Subscribe',
            'Unsubscribe',
            'ViewContent',
            'Purchase',
            'Contact',
            'Custom',
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('FluentCRM → Facebook Events', 'fluentcrm-facebook-events') . '</h1>';

        if ($updated) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'fluentcrm-facebook-events') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fcrm_fb_events_save_settings" />';
        wp_nonce_field('fcrm_fb_events_save_settings');

        echo '<h2>' . esc_html__('General Settings', 'fluentcrm-facebook-events') . '</h2>';
        echo '<table class="form-table" role="presentation">';

        $this->render_checkbox_row('enabled', __('Enable Integration', 'fluentcrm-facebook-events'), $settings['enabled'] === 'yes');
        $this->render_text_row('pixel_id', __('Facebook Pixel ID', 'fluentcrm-facebook-events'), $settings['pixel_id']);
        $this->render_text_row('access_token', __('Facebook Access Token', 'fluentcrm-facebook-events'), $settings['access_token']);
        $this->render_text_row('test_event_code', __('Test Event Code (optional)', 'fluentcrm-facebook-events'), $settings['test_event_code']);
        $this->render_text_row('event_source_url', __('Default Event Source URL (optional)', 'fluentcrm-facebook-events'), $settings['event_source_url']);

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Send Mode', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        echo '<label><input type="radio" name="send_mode" value="immediate" ' . checked('immediate', $settings['send_mode'], false) . ' /> ' . esc_html__('Send immediately', 'fluentcrm-facebook-events') . '</label><br />';
        echo '<label><input type="radio" name="send_mode" value="queue" ' . checked('queue', $settings['send_mode'], false) . ' /> ' . esc_html__('Queue events', 'fluentcrm-facebook-events') . '</label>';
        echo '</td>';
        echo '</tr>';

        $this->render_checkbox_row('debug_logging', __('Enable Debug Logging', 'fluentcrm-facebook-events'), $settings['debug_logging'] === 'yes');
        $this->render_checkbox_row('delete_data_on_uninstall', __('Delete data on uninstall', 'fluentcrm-facebook-events'), $settings['delete_data_on_uninstall'] === 'yes');

        echo '</table>';

        echo '<h2>' . esc_html__('Event Mapping', 'fluentcrm-facebook-events') . '</h2>';
        echo '<p>' . esc_html__('Enable the FluentCRM triggers you want to send to Facebook and map them to a Facebook event name.', 'fluentcrm-facebook-events') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Trigger', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Tags', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Enable', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('FB Event Name', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Custom Event', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Value', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Currency', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Custom Params (JSON)', 'fluentcrm-facebook-events') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($settings['mappings'] as $key => $mapping) {
            echo '<tr>';
            echo '<td>' . esc_html($mapping['label']) . '</td>';
            echo '<td>';
            if (in_array($key, ['tag_applied', 'tag_removed'], true)) {
                if (empty($tags)) {
                    echo '<em>' . esc_html__('No tags available.', 'fluentcrm-facebook-events') . '</em>';
                } else {
                    echo '<select name="mappings[' . esc_attr($key) . '][tag_ids][]" multiple="multiple" size="4">';
                    foreach ($tags as $tag) {
                        $selected = in_array((int) $tag->id, (array) $mapping['tag_ids'], true);
                        echo '<option value="' . esc_attr($tag->id) . '" ' . selected(true, $selected, false) . '>' . esc_html($tag->title) . '</option>';
                    }
                    echo '</select>';
                    echo '<br /><span class="description">' . esc_html__('Select at least one tag to send this event.', 'fluentcrm-facebook-events') . '</span>';
                }
            } else {
                echo '&mdash;';
            }
            echo '</td>';
            echo '<td><input type="checkbox" name="mappings[' . esc_attr($key) . '][enabled]" value="1" ' . checked('yes', $mapping['enabled'], false) . ' /></td>';
            echo '<td><select name="mappings[' . esc_attr($key) . '][event_name]">';
            foreach ($event_options as $option) {
                echo '<option value="' . esc_attr($option) . '" ' . selected($option, $mapping['event_name'], false) . '>' . esc_html($option) . '</option>';
            }
            echo '</select></td>';
            echo '<td>';
            echo '<label><input type="checkbox" name="mappings[' . esc_attr($key) . '][send_custom_event]" value="1" ' . checked('yes', $mapping['send_custom_event'], false) . ' /> ' . esc_html__('Use custom event name', 'fluentcrm-facebook-events') . '</label><br />';
            echo '<input type="text" class="regular-text" name="mappings[' . esc_attr($key) . '][custom_event_name]" value="' . esc_attr($mapping['custom_event_name']) . '" placeholder="' . esc_attr__('CustomEventName', 'fluentcrm-facebook-events') . '" />';
            echo '</td>';
            echo '<td><input type="text" class="small-text" name="mappings[' . esc_attr($key) . '][value]" value="' . esc_attr($mapping['value']) . '" /></td>';
            echo '<td><input type="text" class="small-text" name="mappings[' . esc_attr($key) . '][currency]" value="' . esc_attr($mapping['currency']) . '" /></td>';
            echo '<td><textarea name="mappings[' . esc_attr($key) . '][custom_params]" rows="3" cols="30" placeholder="{\"param\":\"value\"}">' . esc_textarea($mapping['custom_params']) . '</textarea></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        submit_button(__('Save Settings', 'fluentcrm-facebook-events'));

        echo '</form>';

        echo '<h2>' . esc_html__('Recent Logs', 'fluentcrm-facebook-events') . '</h2>';
        echo '<p>' . esc_html__('Shows the most recent 50 attempts.', 'fluentcrm-facebook-events') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Time', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Trigger', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Contact', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Event Name', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Status', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Response', 'fluentcrm-facebook-events') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($logs)) {
            echo '<tr><td colspan="6">' . esc_html__('No log entries found.', 'fluentcrm-facebook-events') . '</td></tr>';
        } else {
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['created_at']) . '</td>';
                echo '<td>' . esc_html($log['trigger']) . '</td>';
                echo '<td>' . esc_html($log['contact_identifier']) . '</td>';
                echo '<td>' . esc_html($log['event_name']) . '</td>';
                echo '<td>' . esc_html($log['status_code']) . '</td>';
                echo '<td>' . esc_html($log['response']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_lead_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $lead_settings = $settings['lead_ads'];
        $lead_ads = fcrm_fb_events_lead_ads();
        $updated = !empty($_GET['updated']);
        $tags = $this->get_available_tags();
        $lists = $this->get_available_lists();
        $notice = get_transient('fcrm_fb_events_lead_test_notice');
        if ($notice) {
            delete_transient('fcrm_fb_events_lead_test_notice');
        }
        $subscription_notice = get_transient('fcrm_fb_events_lead_subscription_notice');
        if ($subscription_notice) {
            delete_transient('fcrm_fb_events_lead_subscription_notice');
        }

        $webhook_url = rest_url(FCRM_FB_Events_Lead_Ads::REST_NAMESPACE . FCRM_FB_Events_Lead_Ads::WEBHOOK_ROUTE);
        $pages = $lead_ads->get_pages();
        $forms = [];
        $selected_page_id = $lead_settings['page_id'] ?? '';
        if (!is_wp_error($pages) && $selected_page_id) {
            $forms = $lead_ads->get_forms($selected_page_id);
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Facebook Lead Ads Settings', 'fluentcrm-facebook-events') . '</h1>';

        if ($updated) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'fluentcrm-facebook-events') . '</p></div>';
        }

        if ($notice) {
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        }

        if ($subscription_notice) {
            echo '<div class="notice notice-info"><p>' . esc_html($subscription_notice) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fcrm_fb_events_save_lead_settings" />';
        wp_nonce_field('fcrm_fb_events_save_lead_settings');

        echo '<table class="form-table" role="presentation">';
        $this->render_checkbox_row('lead_enabled', __('Enable Lead Ads Sync', 'fluentcrm-facebook-events'), $lead_settings['enabled'] === 'yes');
        $this->render_text_row('app_id', __('Facebook App ID (optional)', 'fluentcrm-facebook-events'), $lead_settings['app_id']);
        $this->render_text_row('app_secret', __('Facebook App Secret (optional)', 'fluentcrm-facebook-events'), $lead_settings['app_secret']);
        $this->render_text_row('lead_access_token', __('Access Token', 'fluentcrm-facebook-events'), $lead_settings['access_token']);
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Facebook Page', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        if (!is_wp_error($pages) && !empty($pages)) {
            echo '<select name="page_id">';
            echo '<option value="">' . esc_html__('Select Page', 'fluentcrm-facebook-events') . '</option>';
            foreach ($pages as $page) {
                $selected = ($selected_page_id && $selected_page_id === $page['id']);
                echo '<option value="' . esc_attr($page['id']) . '" ' . selected(true, $selected, false) . '>' . esc_html($page['name']) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" class="regular-text" name="page_id" value="' . esc_attr($selected_page_id) . '" />';
            echo '<p class="description">' . esc_html__('Enter the Page ID if pages cannot be loaded.', 'fluentcrm-facebook-events') . '</p>';
        }
        echo '</td>';
        echo '</tr>';
        $this->render_text_row('ad_account_id', __('Ad Account ID (optional)', 'fluentcrm-facebook-events'), $lead_settings['ad_account_id']);
        $this->render_text_row('verify_token', __('Webhook Verify Token', 'fluentcrm-facebook-events'), $lead_settings['verify_token']);

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Webhook URL', 'fluentcrm-facebook-events') . '</th>';
        echo '<td><code>' . esc_html($webhook_url) . '</code></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Lead Forms', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        if (is_wp_error($pages)) {
            echo '<em>' . esc_html($pages->get_error_message()) . '</em>';
        } elseif (is_wp_error($forms)) {
            echo '<em>' . esc_html($forms->get_error_message()) . '</em>';
        } elseif (!empty($forms)) {
            $selected_forms = (array) ($lead_settings['form_ids'] ?? []);
            echo '<select name="form_ids[]" multiple="multiple" size="6">';
            foreach ($forms as $form) {
                $selected = in_array($form['id'], $selected_forms, true);
                echo '<option value="' . esc_attr($form['id']) . '" ' . selected(true, $selected, false) . '>' . esc_html($form['name']) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Select one or more forms to sync leads in real time. Leave blank to accept all forms.', 'fluentcrm-facebook-events') . '</p>';
        } else {
            echo '<em>' . esc_html__('Select a page and save to load available forms.', 'fluentcrm-facebook-events') . '</em>';
        }
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Missing Email Behavior', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        echo '<select name="missing_email_action">';
        echo '<option value="skip" ' . selected('skip', $lead_settings['missing_email_action'], false) . '>' . esc_html__('Skip lead', 'fluentcrm-facebook-events') . '</option>';
        echo '<option value="phone_only" ' . selected('phone_only', $lead_settings['missing_email_action'], false) . '>' . esc_html__('Create contact with phone only', 'fluentcrm-facebook-events') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Contact Status', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        echo '<select name="contact_status">';
        echo '<option value="subscribed" ' . selected('subscribed', $lead_settings['contact_status'], false) . '>' . esc_html__('Subscribed', 'fluentcrm-facebook-events') . '</option>';
        echo '<option value="pending" ' . selected('pending', $lead_settings['contact_status'], false) . '>' . esc_html__('Pending', 'fluentcrm-facebook-events') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        $this->render_checkbox_row('dedupe_by_phone', __('Dedupe by phone when email missing', 'fluentcrm-facebook-events'), $lead_settings['dedupe_by_phone'] === 'yes');

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Apply Tags', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        if (empty($tags)) {
            echo '<em>' . esc_html__('No tags available.', 'fluentcrm-facebook-events') . '</em>';
        } else {
            echo '<select name="tag_ids[]" multiple="multiple" size="5">';
            foreach ($tags as $tag) {
                $selected = in_array((int) $tag->id, (array) $lead_settings['tag_ids'], true);
                echo '<option value="' . esc_attr($tag->id) . '" ' . selected(true, $selected, false) . '>' . esc_html($tag->title) . '</option>';
            }
            echo '</select>';
        }
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Apply Lists', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        if (empty($lists)) {
            echo '<em>' . esc_html__('No lists available.', 'fluentcrm-facebook-events') . '</em>';
        } else {
            echo '<select name="list_ids[]" multiple="multiple" size="5">';
            foreach ($lists as $list) {
                $selected = in_array((int) $list->id, (array) $lead_settings['list_ids'], true);
                echo '<option value="' . esc_attr($list->id) . '" ' . selected(true, $selected, false) . '>' . esc_html($list->title) . '</option>';
            }
            echo '</select>';
        }
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        submit_button(__('Save Lead Ads Settings', 'fluentcrm-facebook-events'));
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:20px;">';
        echo '<input type="hidden" name="action" value="fcrm_fb_events_test_connection" />';
        wp_nonce_field('fcrm_fb_events_test_connection');
        submit_button(__('Test Connection', 'fluentcrm-facebook-events'), 'secondary', 'submit', false);
        echo '</form>';

        echo '</div>';
    }

    public function render_lead_import_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $lead_settings = $settings['lead_ads'];
        $lead_ads = fcrm_fb_events_lead_ads();
        $notice = get_transient('fcrm_fb_events_lead_import_notice');
        if ($notice) {
            delete_transient('fcrm_fb_events_lead_import_notice');
        }

        $selected_page = sanitize_text_field($_GET['page_id'] ?? $lead_settings['page_id']);
        $selected_form = sanitize_text_field($_GET['form_id'] ?? '');

        $pages = $lead_ads->get_pages();
        $forms = [];
        if (!is_wp_error($pages) && $selected_page) {
            $forms = $lead_ads->get_forms($selected_page);
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Facebook Lead Ads Import', 'fluentcrm-facebook-events') . '</h1>';

        if ($notice) {
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        }

        if (is_wp_error($pages)) {
            echo '<div class="notice notice-error"><p>' . esc_html($pages->get_error_message()) . '</p></div>';
        }

        echo '<div id="fcrm-fb-lead-import-status" class="notice" style="display:none;"><p></p></div>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::LEAD_IMPORT_SLUG) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Facebook Page', 'fluentcrm-facebook-events') . '</th>';
        echo '<td>';
        if (!is_wp_error($pages) && !empty($pages)) {
            echo '<select name="page_id">';
            echo '<option value="">' . esc_html__('Select Page', 'fluentcrm-facebook-events') . '</option>';
            foreach ($pages as $page) {
                $selected = ($selected_page && $selected_page === $page['id']);
                echo '<option value="' . esc_attr($page['id']) . '" ' . selected(true, $selected, false) . '>' . esc_html($page['name']) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<em>' . esc_html__('No pages found or invalid token.', 'fluentcrm-facebook-events') . '</em>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button(__('Load Forms', 'fluentcrm-facebook-events'), 'secondary', 'submit', false);
        echo '</form>';

        if (is_wp_error($forms)) {
            echo '<div class="notice notice-error"><p>' . esc_html($forms->get_error_message()) . '</p></div>';
        }

        if (!is_wp_error($forms) && !empty($forms)) {
            $last_imported = $selected_form ? FCRM_FB_Events_Lead_Store::get_last_import_time($selected_form) : null;
            echo '<form id="fcrm-fb-lead-import-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:20px;">';
            echo '<input type="hidden" name="action" value="fcrm_fb_events_import_leads" />';
            wp_nonce_field('fcrm_fb_events_import_leads');
            echo '<input type="hidden" name="page_id" value="' . esc_attr($selected_page) . '" />';

            echo '<table class="form-table"><tbody>';
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Lead Form', 'fluentcrm-facebook-events') . '</th>';
            echo '<td><select name="form_id">';
            foreach ($forms as $form) {
                $selected = ($selected_form && $selected_form === $form['id']);
                echo '<option value="' . esc_attr($form['id']) . '" ' . selected(true, $selected, false) . '>' . esc_html($form['name']) . '</option>';
            }
            echo '</select></td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Date Range', 'fluentcrm-facebook-events') . '</th>';
            echo '<td>';
            echo '<input type="date" name="since" /> ' . esc_html__('to', 'fluentcrm-facebook-events') . ' ';
            echo '<input type="date" name="until" />';
            if ($last_imported) {
                echo '<p class="description">' . sprintf(esc_html__('Last imported at %s', 'fluentcrm-facebook-events'), esc_html($last_imported)) . '</p>';
            }
            echo '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Limit Per Request', 'fluentcrm-facebook-events') . '</th>';
            echo '<td><input type="number" name="limit" value="50" min="1" max="200" /></td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Pagination Cursor (optional)', 'fluentcrm-facebook-events') . '</th>';
            echo '<td><input type="text" class="regular-text" name="after" value="" /></td>';
            echo '</tr>';

            echo '</tbody></table>';

            submit_button(__('Fetch Leads', 'fluentcrm-facebook-events'));
            echo '<div id="fcrm-fb-lead-import-progress" class="fcrm-fb-lead-import-progress" aria-live="polite" style="display:none;">';
            echo '<span class="spinner is-active"></span>';
            echo '<span class="fcrm-fb-lead-import-message">' . esc_html__('Preparing lead import...', 'fluentcrm-facebook-events') . '</span>';
            echo '<div class="fcrm-fb-lead-import-progress-bar"><span></span></div>';
            echo '</div>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function render_lead_mapping_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $mapping = $settings['lead_field_mapping'];
        $updated = !empty($_GET['updated']);

        $default_fields = [
            'email' => __('Email', 'fluentcrm-facebook-events'),
            'full_name' => __('Full Name', 'fluentcrm-facebook-events'),
            'first_name' => __('First Name', 'fluentcrm-facebook-events'),
            'last_name' => __('Last Name', 'fluentcrm-facebook-events'),
            'phone_number' => __('Phone Number', 'fluentcrm-facebook-events'),
            'phone' => __('Phone', 'fluentcrm-facebook-events'),
            'lead_id' => __('Lead ID', 'fluentcrm-facebook-events'),
            'company_name' => __('Company', 'fluentcrm-facebook-events'),
            'city' => __('City', 'fluentcrm-facebook-events'),
            'state' => __('State', 'fluentcrm-facebook-events'),
            'zip' => __('Zip', 'fluentcrm-facebook-events'),
            'country' => __('Country', 'fluentcrm-facebook-events'),
        ];

        $custom_fields = array_diff_key($mapping, $default_fields);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Facebook Lead Ads Field Mapping', 'fluentcrm-facebook-events') . '</h1>';

        if ($updated) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Mapping saved.', 'fluentcrm-facebook-events') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fcrm_fb_events_save_lead_mapping" />';
        wp_nonce_field('fcrm_fb_events_save_lead_mapping');

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Facebook Field', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('FluentCRM Field Key', 'fluentcrm-facebook-events') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($default_fields as $fb_key => $label) {
            $value = $mapping[$fb_key] ?? '';
            echo '<tr>';
            echo '<td>' . esc_html($label) . ' <code>' . esc_html($fb_key) . '</code></td>';
            echo '<td><input type="text" name="mapping[' . esc_attr($fb_key) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('e.g. email, first_name, phone, custom_key', 'fluentcrm-facebook-events') . '" /></td>';
            echo '</tr>';
        }

        if (!empty($custom_fields)) {
            foreach ($custom_fields as $fb_key => $fluent_key) {
                echo '<tr>';
                echo '<td><code>' . esc_html($fb_key) . '</code></td>';
                echo '<td><input type="text" name="mapping[' . esc_attr($fb_key) . ']" value="' . esc_attr($fluent_key) . '" /></td>';
                echo '</tr>';
            }
        }

        for ($i = 0; $i < 3; $i++) {
            echo '<tr>';
            echo '<td><input type="text" name="mapping_custom_key[]" value="" placeholder="' . esc_attr__('facebook_field', 'fluentcrm-facebook-events') . '" /></td>';
            echo '<td><input type="text" name="mapping_custom_value[]" value="" placeholder="' . esc_attr__('fluentcrm_field_key', 'fluentcrm-facebook-events') . '" /></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        submit_button(__('Save Mapping', 'fluentcrm-facebook-events'));
        echo '</form>';
        echo '</div>';
    }

    public function render_lead_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logs = FCRM_FB_Events_Logger::get_logs(50, 'lead_ads');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Facebook Lead Ads Logs', 'fluentcrm-facebook-events') . '</h1>';
        echo '<p>' . esc_html__('Shows the most recent 50 imports and webhook attempts.', 'fluentcrm-facebook-events') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Time', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Trigger', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Contact', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Event Name', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Status', 'fluentcrm-facebook-events') . '</th>';
        echo '<th>' . esc_html__('Response', 'fluentcrm-facebook-events') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($logs)) {
            echo '<tr><td colspan="6">' . esc_html__('No log entries found.', 'fluentcrm-facebook-events') . '</td></tr>';
        } else {
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['created_at']) . '</td>';
                echo '<td>' . esc_html($log['trigger']) . '</td>';
                echo '<td>' . esc_html($log['contact_identifier']) . '</td>';
                echo '<td>' . esc_html($log['event_name']) . '</td>';
                echo '<td>' . esc_html($log['status_code']) . '</td>';
                echo '<td>' . esc_html($log['response']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_checkbox_row($name, $label, $checked)
    {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td><label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html__('Enabled', 'fluentcrm-facebook-events') . '</label></td>';
        echo '</tr>';
    }

    private function render_text_row($name, $label, $value)
    {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td><input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" /></td>';
        echo '</tr>';
    }

    private function log_lead_import_error(WP_Error $error, $form_id, $page_id)
    {
        $details = $error->get_error_data();
        $response = $error->get_error_message();

        if (!empty($details)) {
            $response = wp_json_encode([
                'message' => $error->get_error_message(),
                'details' => $details,
            ]);
        }

        FCRM_FB_Events_Logger::add_log([
            'trigger' => 'lead_ads',
            'contact_id' => 0,
            'email' => $form_id ? ('form:' . $form_id) : '',
            'event_name' => 'lead_import',
            'status_code' => 500,
            'response' => $response,
            'success' => false,
        ]);
    }

    private function get_available_tags()
    {
        if (!class_exists('FluentCrm\\App\\Models\\Tag')) {
            return [];
        }

        return FluentCrm\App\Models\Tag::orderBy('title', 'ASC')->get();
    }

    private function get_available_lists()
    {
        if (!class_exists('FluentCrm\\App\\Models\\Lists')) {
            return [];
        }

        return FluentCrm\App\Models\Lists::orderBy('title', 'ASC')->get();
    }
}
