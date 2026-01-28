<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\FacebookCapi\EventBuilder;
use FluentCrm\App\Services\FacebookCapi\Logger;
use FluentCrm\App\Services\FacebookCapi\Rules;
use FluentCrm\App\Services\FacebookCapi\Sender;
use FluentCrm\App\Services\FacebookCapi\Settings;
use FluentCrm\Framework\Support\Arr;

class FacebookCapi
{
    public function register()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_fluentcrm_save_facebook_capi_settings', [$this, 'saveSettings']);
        add_action('admin_post_fluentcrm_save_facebook_capi_rules', [$this, 'saveRules']);
        add_action('admin_post_fluentcrm_facebook_capi_test_event', [$this, 'sendTestEvent']);
        add_action('admin_post_fluentcrm_facebook_capi_purge_logs', [$this, 'purgeLogs']);
        add_action('fluentcrm_contact_added_to_tags', [$this, 'handleTagApplied'], 10, 2);
        add_action('fluentcrm_facebook_capi_send_event', [$this, 'handleAsyncEvent'], 10, 2);
        add_action('fluent_crm_ascheduler_runs_daily', [$this, 'runDailyCleanup']);
    }

    public function registerMenu()
    {
        add_submenu_page(
            'fluentcrm-admin',
            __('Facebook CAPI', 'fluent-crm'),
            __('Facebook CAPI', 'fluent-crm'),
            'manage_options',
            'fluentcrm-facebook-capi',
            [$this, 'renderPage']
        );
    }

    public function renderPage()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fluent-crm'));
        }

        $settings = Settings::getSettings();
        $rules = Rules::getRules();
        $tags = Tag::orderBy('title', 'ASC')->get();
        $lists = Lists::orderBy('title', 'ASC')->get();
        $statuses = fluentcrm_subscriber_statuses(true);
        $logs = Logger::getLogs(50, 0);
        $maskedToken = Settings::getMaskedToken($settings);

        $messages = [
            'settings_saved' => __('Settings saved successfully.', 'fluent-crm'),
            'rules_saved'    => __('Rules saved successfully.', 'fluent-crm'),
            'test_sent'      => __('Test event sent. Please check the response below.', 'fluent-crm'),
            'logs_purged'    => __('Logs have been purged.', 'fluent-crm')
        ];

        $noticeKey = sanitize_text_field(Arr::get($_GET, 'fb_capi_message', ''));
        $notice = Arr::get($messages, $noticeKey, '');
        $responseNotice = Arr::get($_GET, 'fb_capi_response');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Facebook CAPI', 'fluent-crm') . '</h1>';

        if ($notice) {
            echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
        }

        if ($responseNotice) {
            echo '<div class="notice notice-info"><p>' . esc_html(wp_unslash($responseNotice)) . '</p></div>';
        }

        echo '<h2>' . esc_html__('Settings', 'fluent-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fluentcrm_facebook_capi_settings');
        echo '<input type="hidden" name="action" value="fluentcrm_save_facebook_capi_settings" />';
        echo '<table class="form-table">';

        $this->renderCheckboxRow('enabled', __('Enable Integration', 'fluent-crm'), $settings['enabled'] === 'yes');
        $this->renderTextRow('pixel_id', __('Facebook Pixel ID', 'fluent-crm'), $settings['pixel_id']);
        $this->renderPasswordRow('access_token', __('Conversions API Access Token', 'fluent-crm'), $maskedToken);
        $this->renderTextRow('test_event_code', __('Test Event Code', 'fluent-crm'), $settings['test_event_code']);
        $this->renderTextRow('api_version', __('API Version', 'fluent-crm'), $settings['api_version']);
        $this->renderTextRow('default_event_source', __('Default Event Source URL', 'fluent-crm'), $settings['default_event_source']);
        $this->renderTextRow('default_action_source', __('Default Action Source', 'fluent-crm'), $settings['default_action_source']);
        $this->renderCheckboxRow('background', __('Send events in background queue', 'fluent-crm'), $settings['background'] === 'yes');
        $this->renderCheckboxRow('debug', __('Enable debug logging', 'fluent-crm'), $settings['debug'] === 'yes');
        $this->renderCheckboxRow('consent_required', __('Only send when consent meta is true', 'fluent-crm'), $settings['consent_required'] === 'yes');
        $this->renderTextRow('consent_meta_key', __('Consent Meta Key', 'fluent-crm'), $settings['consent_meta_key']);
        $this->renderNumberRow('log_retention_days', __('Purge logs older than (days)', 'fluent-crm'), $settings['log_retention_days']);

        echo '</table>';
        submit_button(__('Save Settings', 'fluent-crm'));
        echo '</form>';

        echo '<h2>' . esc_html__('Rules: Tag Applied → Facebook Events', 'fluent-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fluentcrm_facebook_capi_rules');
        echo '<input type="hidden" name="action" value="fluentcrm_save_facebook_capi_rules" />';
        echo '<div id="fc-rules">';
        foreach ($rules as $index => $rule) {
            $this->renderRule($index, $rule, $tags, $lists, $statuses);
        }
        echo '</div>';
        echo '<p><button class="button fc-add-rule">' . esc_html__('Add Rule', 'fluent-crm') . '</button></p>';
        submit_button(__('Save Rules', 'fluent-crm'));
        echo '</form>';

        echo '<h2>' . esc_html__('Send Test Event', 'fluent-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fluentcrm_facebook_capi_test_event');
        echo '<input type="hidden" name="action" value="fluentcrm_facebook_capi_test_event" />';
        echo '<table class="form-table">';
        $currentUser = wp_get_current_user();
        $emailValue = $currentUser ? $currentUser->user_email : '';
        $this->renderTextRow('test_email', __('Test Email', 'fluent-crm'), $emailValue);
        echo '</table>';
        submit_button(__('Send Test Lead Event', 'fluent-crm'));
        echo '</form>';

        echo '<h2>' . esc_html__('Logs', 'fluent-crm') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fluentcrm_facebook_capi_purge_logs');
        echo '<input type="hidden" name="action" value="fluentcrm_facebook_capi_purge_logs" />';
        submit_button(__('Purge Old Logs', 'fluent-crm'), 'secondary');
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Date', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Contact', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Tag ID', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Event', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Status', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Response', 'fluent-crm') . '</th>';
        echo '<th>' . esc_html__('Retries', 'fluent-crm') . '</th>';
        echo '</tr></thead><tbody>';

        if (!$logs) {
            echo '<tr><td colspan="7">' . esc_html__('No logs yet.', 'fluent-crm') . '</td></tr>';
        } else {
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log->created_at) . '</td>';
                echo '<td>' . esc_html($log->contact_email ?: $log->contact_id) . '</td>';
                echo '<td>' . esc_html($log->tag_id) . '</td>';
                echo '<td>' . esc_html($log->event_name) . '</td>';
                echo '<td>' . esc_html($log->status) . '</td>';
                echo '<td><code>' . esc_html($log->response_code) . '</code> ' . esc_html($log->response_body) . '</td>';
                echo '<td>' . esc_html($log->retry_count) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        $this->renderTemplates($tags, $lists, $statuses);

        echo '</div>';
    }

    public function saveSettings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fluent-crm'));
        }

        check_admin_referer('fluentcrm_facebook_capi_settings');

        Settings::saveSettings($_POST);

        wp_safe_redirect(admin_url('admin.php?page=fluentcrm-facebook-capi&fb_capi_message=settings_saved'));
        exit;
    }

    public function saveRules()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fluent-crm'));
        }

        check_admin_referer('fluentcrm_facebook_capi_rules');

        $rules = Arr::get($_POST, 'rules', []);
        Rules::saveRules($rules);

        wp_safe_redirect(admin_url('admin.php?page=fluentcrm-facebook-capi&fb_capi_message=rules_saved'));
        exit;
    }

    public function sendTestEvent()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fluent-crm'));
        }

        check_admin_referer('fluentcrm_facebook_capi_test_event');

        $settings = Settings::getSettings();
        if ($settings['enabled'] !== 'yes' || !$settings['pixel_id'] || !$settings['access_token']) {
            wp_safe_redirect(admin_url('admin.php?page=fluentcrm-facebook-capi&fb_capi_response=' . urlencode(__('Please enable integration and configure Pixel ID + Access Token first.', 'fluent-crm'))));
            exit;
        }

        $email = sanitize_email(Arr::get($_POST, 'test_email'));
        $subscriber = (object)[
            'id' => 0,
            'email' => $email,
            'phone' => '',
            'first_name' => '',
            'last_name' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => ''
        ];

        $rule = [
            'id' => 'test-event',
            'fire_once' => 'no'
        ];
        $eventConfig = [
            'event_type' => 'Lead',
            'custom_name' => '',
            'parameters' => '',
            'value' => '',
            'currency' => ''
        ];

        $context = $this->buildContext();
        $event = EventBuilder::buildEvent($subscriber, 0, $rule, $eventConfig, $settings, $context);

        $sender = new Sender();
        $result = $sender->sendEvent($event, $settings, []);
        $responseBody = $this->formatResponseBody($result['response']);

        Logger::log([
            'contact_id' => null,
            'contact_email' => $email,
            'tag_id' => null,
            'rule_id' => 'test-event',
            'event_name' => 'Lead',
            'status' => $result['success'] ? 'success' : 'fail',
            'response_code' => $result['response_code'],
            'response_body' => $responseBody,
            'retry_count' => max(0, $result['attempts'] - 1)
        ]);

        $message = $result['success'] ? __('Test event sent successfully.', 'fluent-crm') : __('Test event failed. Please review logs.', 'fluent-crm');

        wp_safe_redirect(admin_url('admin.php?page=fluentcrm-facebook-capi&fb_capi_message=test_sent&fb_capi_response=' . urlencode($message)));
        exit;
    }

    public function purgeLogs()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'fluent-crm'));
        }

        check_admin_referer('fluentcrm_facebook_capi_purge_logs');

        $settings = Settings::getSettings();
        Logger::purgeOlderThan(Arr::get($settings, 'log_retention_days', 30));

        wp_safe_redirect(admin_url('admin.php?page=fluentcrm-facebook-capi&fb_capi_message=logs_purged'));
        exit;
    }

    public function handleTagApplied($tagIds, $subscriber)
    {
        $settings = Settings::getSettings();
        if ($settings['enabled'] !== 'yes' || !$settings['pixel_id'] || !$settings['access_token']) {
            return;
        }

        $context = $this->buildContext();
        $sender = new Sender();

        foreach ((array)$tagIds as $tagId) {
            $rules = Rules::getRulesForTag($tagId);

            foreach ($rules as $rule) {
                if (!$this->isRuleAllowed($rule, $subscriber, $settings)) {
                    continue;
                }

                foreach ((array)Arr::get($rule, 'events', []) as $eventConfig) {
                    $event = EventBuilder::buildEvent($subscriber, $tagId, $rule, $eventConfig, $settings, $context);
                    if (!$event) {
                        continue;
                    }

                    $meta = [
                        'contact_id' => $subscriber->id,
                        'contact_email' => $subscriber->email,
                        'tag_id' => $tagId,
                        'rule_id' => Arr::get($rule, 'id'),
                        'event_name' => Arr::get($event, 'event_name'),
                        'fire_once' => Arr::get($rule, 'fire_once')
                    ];

                    if ($settings['background'] === 'yes' && function_exists('as_enqueue_async_action')) {
                        as_enqueue_async_action('fluentcrm_facebook_capi_send_event', [$event, $meta], 'fluentcrm');
                    } else {
                        $result = $sender->sendEvent($event, $settings, $meta);
                        $this->handleSendResult($subscriber, $result, $meta, $settings);
                    }
                }
            }
        }
    }

    public function handleAsyncEvent($event, $meta)
    {
        $settings = Settings::getSettings();
        $sender = new Sender();
        $result = $sender->sendEvent($event, $settings, $meta);

        $subscriberId = Arr::get($meta, 'contact_id');
        if ($subscriberId) {
            $subscriber = Subscriber::find($subscriberId);
        } else {
            $subscriber = null;
        }

        $this->handleSendResult($subscriber, $result, $meta, $settings);
    }

    public function runDailyCleanup()
    {
        $settings = Settings::getSettings();
        Logger::purgeOlderThan(Arr::get($settings, 'log_retention_days', 30));
    }

    protected function handleSendResult($subscriber, $result, $meta, $settings)
    {
        $responseBody = $this->formatResponseBody($result['response']);

        Logger::log([
            'contact_id' => Arr::get($meta, 'contact_id'),
            'contact_email' => Arr::get($meta, 'contact_email'),
            'tag_id' => Arr::get($meta, 'tag_id'),
            'rule_id' => Arr::get($meta, 'rule_id'),
            'event_name' => Arr::get($meta, 'event_name'),
            'status' => $result['success'] ? 'success' : 'fail',
            'response_code' => $result['response_code'],
            'response_body' => $responseBody,
            'retry_count' => max(0, $result['attempts'] - 1)
        ]);

        if ($result['success'] && Arr::get($meta, 'fire_once') === 'yes' && $subscriber) {
            $ruleId = Arr::get($meta, 'rule_id');
            $metaKey = $this->getRuleMetaKey($ruleId);
            fluentcrm_update_subscriber_meta($subscriber->id, $metaKey, current_time('mysql'));
        }

        if (Arr::get($settings, 'debug') === 'yes') {
            \FluentCrm\App\Services\Helper::debugLog('Facebook CAPI Event', [
                'event' => Arr::get($meta, 'event_name'),
                'contact' => Arr::get($meta, 'contact_id'),
                'tag' => Arr::get($meta, 'tag_id'),
                'status' => $result['success'] ? 'success' : 'fail',
                'response_code' => $result['response_code'],
                'response_body' => $responseBody
            ]);
        }
    }

    protected function isRuleAllowed($rule, $subscriber, $settings)
    {
        $ruleId = Arr::get($rule, 'id');
        if (Arr::get($rule, 'fire_once') === 'yes') {
            $metaKey = $this->getRuleMetaKey($ruleId);
            $existing = fluentcrm_get_subscriber_meta($subscriber->id, $metaKey);
            if ($existing) {
                return false;
            }
        }

        if (Arr::get($rule, 'require_contact_data') === 'yes') {
            $hasEmail = !empty($subscriber->email);
            $hasPhone = !empty($subscriber->phone);
            if (!$hasEmail && !$hasPhone) {
                return false;
            }
        }

        $requiredStatus = Arr::get($rule, 'required_status');
        if ($requiredStatus && $requiredStatus !== $subscriber->status) {
            return false;
        }

        $requiredLists = Arr::get($rule, 'required_list_ids', []);
        if ($requiredLists && method_exists($subscriber, 'hasAnyListId')) {
            if (!$subscriber->hasAnyListId($requiredLists)) {
                return false;
            }
        }

        if (Arr::get($settings, 'consent_required') === 'yes') {
            $metaKey = Arr::get($settings, 'consent_meta_key');
            if ($metaKey) {
                $consent = fluentcrm_get_subscriber_meta($subscriber->id, $metaKey);
                if (!$this->isTruthy($consent)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function buildContext()
    {
        $clientIp = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $clientIp = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $userAgent = '';
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }

        return [
            'client_ip' => $clientIp,
            'client_user_agent' => $userAgent
        ];
    }

    protected function getRuleMetaKey($ruleId)
    {
        return '_facebook_capi_rule_' . sanitize_key($ruleId);
    }

    protected function isTruthy($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string)$value);
        return in_array($value, ['yes', '1', 'true'], true);
    }

    protected function formatResponseBody($response)
    {
        if (!$response || is_wp_error($response)) {
            return is_wp_error($response) ? $response->get_error_message() : '';
        }

        $body = wp_remote_retrieve_body($response);
        $body = wp_strip_all_tags($body);

        if (strlen($body) > 200) {
            $body = substr($body, 0, 200) . '...';
        }

        return $body;
    }

    protected function renderRule($index, $rule, $tags, $lists, $statuses)
    {
        $ruleId = esc_attr(Arr::get($rule, 'id', ''));
        $name = esc_attr(Arr::get($rule, 'name'));
        $tagId = (int)Arr::get($rule, 'tag_id');
        $enabled = Arr::get($rule, 'enabled', 'yes') === 'yes';
        $fireOnce = Arr::get($rule, 'fire_once') === 'yes';
        $requireContactData = Arr::get($rule, 'require_contact_data') === 'yes';
        $requiredListIds = (array)Arr::get($rule, 'required_list_ids', []);
        $requiredStatus = Arr::get($rule, 'required_status');
        $events = (array)Arr::get($rule, 'events', []);

        echo '<div class="fc-rule" data-index="' . esc_attr($index) . '">';
        echo '<h3>' . esc_html__('Rule', 'fluent-crm') . '</h3>';
        echo '<p><button class="button link-button fc-remove-rule">' . esc_html__('Remove Rule', 'fluent-crm') . '</button></p>';
        echo '<table class="form-table">';

        echo '<tr><th>' . esc_html__('Enabled', 'fluent-crm') . '</th><td>';
        echo '<input type="checkbox" name="rules[' . esc_attr($index) . '][enabled]" value="1" ' . checked(true, $enabled, false) . ' />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Rule Name', 'fluent-crm') . '</th><td>';
        echo '<input type="text" class="regular-text" name="rules[' . esc_attr($index) . '][name]" value="' . $name . '" />';
        echo '<input type="hidden" name="rules[' . esc_attr($index) . '][id]" value="' . $ruleId . '" />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Trigger Tag', 'fluent-crm') . '</th><td>';
        echo '<select name="rules[' . esc_attr($index) . '][tag_id]">';
        echo '<option value="">' . esc_html__('Select Tag', 'fluent-crm') . '</option>';
        foreach ($tags as $tag) {
            $selected = selected($tagId, $tag->id, false);
            echo '<option value="' . esc_attr($tag->id) . '" ' . $selected . '>' . esc_html($tag->title) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Only fire once per contact', 'fluent-crm') . '</th><td>';
        echo '<input type="checkbox" name="rules[' . esc_attr($index) . '][fire_once]" value="1" ' . checked(true, $fireOnce, false) . ' />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Require email or phone', 'fluent-crm') . '</th><td>';
        echo '<input type="checkbox" name="rules[' . esc_attr($index) . '][require_contact_data]" value="1" ' . checked(true, $requireContactData, false) . ' />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Required Lists', 'fluent-crm') . '</th><td>';
        echo '<select multiple="multiple" name="rules[' . esc_attr($index) . '][required_list_ids][]" style="min-width:240px;">';
        foreach ($lists as $list) {
            $selected = in_array($list->id, $requiredListIds, true) ? 'selected' : '';
            echo '<option value="' . esc_attr($list->id) . '" ' . $selected . '>' . esc_html($list->title) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Required Status', 'fluent-crm') . '</th><td>';
        echo '<select name="rules[' . esc_attr($index) . '][required_status]">';
        echo '<option value="">' . esc_html__('Any status', 'fluent-crm') . '</option>';
        foreach ($statuses as $statusKey => $statusLabel) {
            $selected = selected($requiredStatus, $statusKey, false);
            echo '<option value="' . esc_attr($statusKey) . '" ' . $selected . '>' . esc_html($statusLabel) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '</table>';

        echo '<div class="fc-events">';
        foreach ($events as $eventIndex => $eventConfig) {
            $this->renderEvent($index, $eventIndex, $eventConfig);
        }
        echo '</div>';

        echo '<p><button class="button fc-add-event">' . esc_html__('Add Event', 'fluent-crm') . '</button></p>';
        echo '<hr /></div>';
    }

    protected function renderEvent($ruleIndex, $eventIndex, $eventConfig)
    {
        $eventType = esc_attr(Arr::get($eventConfig, 'event_type'));
        $customName = esc_attr(Arr::get($eventConfig, 'custom_name'));
        $parameters = esc_textarea(Arr::get($eventConfig, 'parameters'));
        $value = esc_attr(Arr::get($eventConfig, 'value'));
        $currency = esc_attr(Arr::get($eventConfig, 'currency'));
        $actionSource = esc_attr(Arr::get($eventConfig, 'action_source'));
        $eventSourceUrl = esc_attr(Arr::get($eventConfig, 'event_source_url'));

        echo '<div class="fc-event">';
        echo '<p><button class="button link-button fc-remove-event">' . esc_html__('Remove Event', 'fluent-crm') . '</button></p>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Event Type', 'fluent-crm') . '</th><td>';
        echo '<select name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][event_type]">';
        foreach ($this->getEventTypes() as $valueKey => $label) {
            $selected = selected($eventType, $valueKey, false);
            echo '<option value="' . esc_attr($valueKey) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Custom Event Name', 'fluent-crm') . '</th><td>';
        echo '<input type="text" class="regular-text" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][custom_name]" value="' . $customName . '" />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Event Parameters (JSON)', 'fluent-crm') . '</th><td>';
        echo '<textarea class="large-text" rows="4" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][parameters]">' . $parameters . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Value', 'fluent-crm') . '</th><td>';
        echo '<input type="text" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][value]" value="' . $value . '" />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Currency', 'fluent-crm') . '</th><td>';
        echo '<input type="text" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][currency]" value="' . $currency . '" />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Action Source', 'fluent-crm') . '</th><td>';
        echo '<input type="text" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][action_source]" value="' . $actionSource . '" />';
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Event Source URL', 'fluent-crm') . '</th><td>';
        echo '<input type="text" class="regular-text" name="rules[' . esc_attr($ruleIndex) . '][events][' . esc_attr($eventIndex) . '][event_source_url]" value="' . $eventSourceUrl . '" />';
        echo '</td></tr>';

        echo '</table></div>';
    }

    protected function renderTemplates($tags, $lists, $statuses)
    {
        echo '<script type="text/template" id="fc-rule-template">';
        $this->renderRule('__index__', [
            'enabled' => 'yes',
            'name' => '',
            'tag_id' => '',
            'fire_once' => 'no',
            'require_contact_data' => 'no',
            'required_list_ids' => [],
            'required_status' => '',
            'events' => []
        ], $tags, $lists, $statuses);
        echo '</script>';

        echo '<script type="text/template" id="fc-event-template">';
        $this->renderEvent('__index__', '__event_index__', [
            'event_type' => 'Lead',
            'custom_name' => '',
            'parameters' => '',
            'value' => '',
            'currency' => '',
            'action_source' => '',
            'event_source_url' => ''
        ]);
        echo '</script>';

        echo '<script>
            jQuery(function($){
                var ruleIndex = ' . esc_js(count(Rules::getRules())) . ';

                $(document).on(' . "'" . 'click' . "'" . ', ".fc-add-rule", function(e){
                    e.preventDefault();
                    var template = $("#fc-rule-template").html().replace(/__index__/g, ruleIndex);
                    $("#fc-rules").append(template);
                    ruleIndex++;
                });

                $(document).on(' . "'" . 'click' . "'" . ', ".fc-remove-rule", function(e){
                    e.preventDefault();
                    $(this).closest(".fc-rule").remove();
                });

                $(document).on(' . "'" . 'click' . "'" . ', ".fc-add-event", function(e){
                    e.preventDefault();
                    var $rule = $(this).closest(".fc-rule");
                    var ruleIndex = $rule.data("index");
                    var eventIndex = $rule.find(".fc-event").length;
                    var template = $("#fc-event-template").html()
                        .replace(/__index__/g, ruleIndex)
                        .replace(/__event_index__/g, eventIndex);
                    $rule.find(".fc-events").append(template);
                });

                $(document).on(' . "'" . 'click' . "'" . ', ".fc-remove-event", function(e){
                    e.preventDefault();
                    $(this).closest(".fc-event").remove();
                });
            });
        </script>';
    }

    protected function renderCheckboxRow($name, $label, $checked)
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked(true, $checked, false) . ' />';
        echo '</td></tr>';
    }

    protected function renderTextRow($name, $label, $value)
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '</td></tr>';
    }

    protected function renderPasswordRow($name, $label, $value)
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<input type="password" class="regular-text" name="' . esc_attr($name) . '" value="" placeholder="' . esc_attr($value) . '" autocomplete="new-password" />';
        echo '<p class="description">' . esc_html__('Leave blank to keep existing token.', 'fluent-crm') . '</p>';
        echo '</td></tr>';
    }

    protected function renderNumberRow($name, $label, $value)
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        echo '<input type="number" min="0" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '</td></tr>';
    }

    protected function getEventTypes()
    {
        return [
            'Lead' => __('Lead', 'fluent-crm'),
            'CompleteRegistration' => __('CompleteRegistration', 'fluent-crm'),
            'Subscribe' => __('Subscribe', 'fluent-crm'),
            'Purchase' => __('Purchase', 'fluent-crm'),
            'AddToCart' => __('AddToCart', 'fluent-crm'),
            'InitiateCheckout' => __('InitiateCheckout', 'fluent-crm'),
            'ViewContent' => __('ViewContent', 'fluent-crm'),
            'custom' => __('Custom Event', 'fluent-crm')
        ];
    }
}
