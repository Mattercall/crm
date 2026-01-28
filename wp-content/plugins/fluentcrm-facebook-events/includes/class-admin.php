<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Admin
{
    const SETTINGS_SLUG = 'fcrm-fb-events';

    public function init()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_notices', [$this, 'maybe_show_notice']);
        add_action('admin_post_fcrm_fb_events_save_settings', [$this, 'handle_save']);
    }

    public static function fluentcrm_active()
    {
        return defined('FLUENTCRM');
    }

    public function register_menu()
    {
        $capability = 'manage_options';

        if (self::fluentcrm_active()) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Facebook Events', 'fluentcrm-facebook-events'),
                __('Facebook Events', 'fluentcrm-facebook-events'),
                $capability,
                self::SETTINGS_SLUG,
                [$this, 'render_settings_page']
            );
        } else {
            add_options_page(
                __('Facebook Events', 'fluentcrm-facebook-events'),
                __('Facebook Events', 'fluentcrm-facebook-events'),
                $capability,
                self::SETTINGS_SLUG,
                [$this, 'render_settings_page']
            );
        }
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
            'mappings' => [
                'tag_applied' => [
                    'label' => __('Tag Applied', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Lead',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
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
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'list_subscribed' => [
                    'label' => __('List Subscribed', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Subscribe',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
                    'value' => '',
                    'currency' => 'USD',
                    'custom_params' => '',
                ],
                'list_unsubscribed' => [
                    'label' => __('List Unsubscribed', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Unsubscribe',
                    'send_custom_event' => 'no',
                    'custom_event_name' => '',
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
                'contact_updated' => [
                    'label' => __('Contact Updated', 'fluentcrm-facebook-events'),
                    'enabled' => 'no',
                    'event_name' => 'Lead',
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

        return wp_parse_args($settings, $defaults);
    }

    public function handle_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fluentcrm-facebook-events'));
        }

        check_admin_referer('fcrm_fb_events_save_settings');

        $input = wp_unslash($_POST);
        $defaults = self::get_default_settings();

        $settings = [
            'enabled' => !empty($input['enabled']) ? 'yes' : 'no',
            'pixel_id' => sanitize_text_field($input['pixel_id'] ?? ''),
            'access_token' => sanitize_text_field($input['access_token'] ?? ''),
            'test_event_code' => sanitize_text_field($input['test_event_code'] ?? ''),
            'event_source_url' => esc_url_raw($input['event_source_url'] ?? ''),
            'send_mode' => in_array(($input['send_mode'] ?? 'immediate'), ['immediate', 'queue'], true) ? $input['send_mode'] : 'immediate',
            'debug_logging' => !empty($input['debug_logging']) ? 'yes' : 'no',
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']) ? 'yes' : 'no',
            'mappings' => $defaults['mappings'],
        ];

        foreach ($defaults['mappings'] as $key => $mapping) {
            $map_input = $input['mappings'][$key] ?? [];
            $settings['mappings'][$key]['enabled'] = !empty($map_input['enabled']) ? 'yes' : 'no';
            $settings['mappings'][$key]['event_name'] = sanitize_text_field($map_input['event_name'] ?? $mapping['event_name']);
            $settings['mappings'][$key]['send_custom_event'] = !empty($map_input['send_custom_event']) ? 'yes' : 'no';
            $settings['mappings'][$key]['custom_event_name'] = sanitize_text_field($map_input['custom_event_name'] ?? '');
            $settings['mappings'][$key]['value'] = sanitize_text_field($map_input['value'] ?? '');
            $settings['mappings'][$key]['currency'] = sanitize_text_field($map_input['currency'] ?? $mapping['currency']);
            $settings['mappings'][$key]['custom_params'] = $this->sanitize_custom_params($map_input['custom_params'] ?? '');
        }

        update_option(FCRM_FB_EVENTS_OPTION_KEY, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::SETTINGS_SLUG, 'updated' => 'true'], admin_url('admin.php')));
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
        $logs = FCRM_FB_Events_Logger::get_logs(100);
        $updated = !empty($_GET['updated']);
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
        echo '<p>' . esc_html__('Shows the most recent 100 attempts.', 'fluentcrm-facebook-events') . '</p>';
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
}
