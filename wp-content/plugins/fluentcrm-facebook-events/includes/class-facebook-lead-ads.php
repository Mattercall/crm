<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Lead_Ads
{
    const REST_NAMESPACE = 'fcrm-facebook-events/v1';
    const WEBHOOK_ROUTE = '/leadgen';
    const LEADGEN_ACTION = 'fcrm_fb_events_process_leadgen';
    const LEADGEN_GROUP = 'fcrm_fb_events_lead_ads';
    const MAX_LEADGEN_ATTEMPTS = 3;
    const RETRY_DELAY_SECONDS = 300;

    public function init()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_pre_serve_request', [$this, 'maybe_serve_raw_challenge'], 10, 4);
        add_action(self::LEADGEN_ACTION, [$this, 'handle_leadgen_action'], 10, 1);
    }

    public function register_routes()
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::WEBHOOK_ROUTE,
            [
                'methods' => ['GET', 'POST'],
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        if ($request->get_method() === 'GET') {
            return $this->handle_verification($request);
        }

        if (!$this->lead_ads_enabled()) {
            return new WP_REST_Response(['message' => 'Lead Ads integration disabled.'], 200);
        }

        $settings = $this->get_lead_settings();
        $body = (string) $request->get_body();

        if (!empty($settings['app_secret']) && !$this->verify_signature($body, $request)) {
            $this->log_event('lead_ads', '', 403, 'Invalid webhook signature.', false);
            return new WP_REST_Response(['message' => 'Invalid signature.'], 403);
        }

        $payload = $request->get_json_params();
        if (empty($payload)) {
            $payload = json_decode($body, true);
        }

        if (empty($payload['entry'])) {
            return new WP_REST_Response(['message' => 'No entries found.'], 200);
        }

        foreach ($payload['entry'] as $entry) {
            $page_id = $entry['id'] ?? '';
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $leadgen_id = $value['leadgen_id'] ?? '';
                $form_id = $value['form_id'] ?? '';
                $created_time = !empty($value['created_time']) ? (int) $value['created_time'] : 0;
                $event_page_id = $value['page_id'] ?? $page_id;

                if (!$leadgen_id) {
                    continue;
                }

                $this->queue_leadgen($leadgen_id, $form_id, $event_page_id, $created_time, 'webhook');
            }
        }

        return new WP_REST_Response(['message' => 'Processed.'], 200);
    }

    private function handle_verification(WP_REST_Request $request)
    {
        $mode = $this->get_request_param($request, 'hub.mode');
        $verify_token = $this->get_request_param($request, 'hub.verify_token');
        $challenge = $this->get_request_param($request, 'hub.challenge');

        if ($mode !== 'subscribe') {
            return new WP_REST_Response(['message' => 'Invalid mode.'], 403);
        }

        $settings = $this->get_lead_settings();
        $expected = $settings['verify_token'] ?? '';

        if ($expected && $verify_token !== $expected) {
            return new WP_REST_Response(['message' => 'Invalid verify token.'], 403);
        }

        return new WP_REST_Response($challenge, 200);
    }

    public function maybe_serve_raw_challenge($served, $result, WP_REST_Request $request, WP_REST_Server $server)
    {
        if (!$result instanceof WP_REST_Response) {
            return $served;
        }

        if ($request->get_method() !== 'GET') {
            return $served;
        }

        if ($request->get_route() !== '/' . self::REST_NAMESPACE . self::WEBHOOK_ROUTE) {
            return $served;
        }

        if ((int) $result->get_status() !== 200) {
            return $served;
        }

        $data = $result->get_data();
        if (!is_string($data)) {
            return $served;
        }

        header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
        echo $data;
        return true;
    }

    private function get_request_param(WP_REST_Request $request, $key)
    {
        $value = $request->get_param($key);
        if ($value !== null) {
            return $value;
        }

        $normalized = str_replace('.', '_', $key);
        return $request->get_param($normalized);
    }

    private function verify_signature($body, WP_REST_Request $request)
    {
        $settings = $this->get_lead_settings();
        $secret = $settings['app_secret'] ?? '';
        if (!$secret) {
            return true;
        }

        $signature = $request->get_header('x-hub-signature-256');
        $algo = 'sha256';

        if (!$signature) {
            $signature = $request->get_header('x-hub-signature');
            $algo = 'sha1';
        }

        if (!$signature) {
            return false;
        }

        $expected = $algo . '=' . hash_hmac($algo, $body, $secret);

        return hash_equals($expected, $signature);
    }

    public function lead_ads_enabled()
    {
        $settings = $this->get_lead_settings();
        return !empty($settings['enabled']) && $settings['enabled'] === 'yes' && FCRM_FB_Events_Admin::fluentcrm_active();
    }

    public function get_lead_settings()
    {
        $settings = FCRM_FB_Events_Admin::get_settings();
        return $settings['lead_ads'] ?? [];
    }

    public function get_lead_field_mapping()
    {
        $settings = FCRM_FB_Events_Admin::get_settings();
        return $settings['lead_field_mapping'] ?? [];
    }

    public function get_pages()
    {
        $response = $this->api_request('/me/accounts', [
            'fields' => 'id,name,access_token',
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['data'] ?? [];
    }

    public function get_forms($page_id)
    {
        if (!$page_id) {
            return [];
        }

        $page_token = $this->get_page_access_token($page_id);
        $response = $this->api_request('/' . $page_id . '/leadgen_forms', [
            'fields' => 'id,name,created_time,status',
        ], $page_token);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['data'] ?? [];
    }

    public function fetch_leads($form_id, array $args = [])
    {
        if (!$form_id) {
            return new WP_Error('missing_form', __('Form ID is required.', 'fluentcrm-facebook-events'));
        }

        $params = array_filter([
            'fields' => 'id,created_time,field_data,form_id',
            'limit' => !empty($args['limit']) ? (int) $args['limit'] : 50,
            'after' => $args['after'] ?? null,
            'since' => $args['since'] ?? null,
            'until' => $args['until'] ?? null,
        ]);

        $page_token = '';
        if (!empty($args['page_id'])) {
            $page_token = $this->get_page_access_token($args['page_id']);
        }

        return $this->api_request('/' . $form_id . '/leads', $params, $page_token);
    }

    public function fetch_lead($leadgen_id, $page_id = '')
    {
        if (!$leadgen_id) {
            return new WP_Error('missing_lead', __('Lead ID is required.', 'fluentcrm-facebook-events'));
        }

        $page_token = $this->get_page_access_token($page_id);
        return $this->api_request('/' . $leadgen_id, [
            'fields' => 'id,created_time,field_data,form_id,ad_id,adgroup_id,campaign_id,platform',
        ], $page_token);
    }

    public function test_connection()
    {
        return $this->api_request('/me', [
            'fields' => 'id,name',
        ]);
    }

    public function subscribe_page_to_webhook($page_id)
    {
        if (!$page_id) {
            return new WP_Error('missing_page', __('Page ID is required.', 'fluentcrm-facebook-events'));
        }

        $page_token = $this->get_page_access_token($page_id);
        if (!$page_token) {
            return new WP_Error('missing_token', __('Page access token is required to subscribe the webhook.', 'fluentcrm-facebook-events'));
        }

        $response = $this->api_request('/' . $page_id . '/subscribed_apps', [
            'subscribed_fields' => 'leadgen',
        ], $page_token, 'POST');

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['success'])) {
            return new WP_Error('subscription_failed', __('Unable to subscribe the page to Lead Ads webhook.', 'fluentcrm-facebook-events'));
        }

        return $response;
    }

    public function import_lead_payload(array $lead, array $context)
    {
        $leadgen_id = $lead['id'] ?? '';
        $form_id = $lead['form_id'] ?? ($context['form_id'] ?? '');
        $page_id = $context['page_id'] ?? '';
        $lead_time = !empty($lead['created_time']) ? strtotime($lead['created_time']) : 0;

        if (!$leadgen_id) {
            return new WP_Error('missing_lead', __('Lead ID missing.', 'fluentcrm-facebook-events'));
        }

        if (FCRM_FB_Events_Lead_Store::has_lead($leadgen_id)) {
            return new WP_Error('duplicate', __('Lead already imported.', 'fluentcrm-facebook-events'));
        }

        $contact_data = $this->map_lead_to_contact($lead);
        if (is_wp_error($contact_data)) {
            $this->log_event('lead_ads', $leadgen_id, 422, $contact_data->get_error_message(), false);
            return $contact_data;
        }

        $contact = $this->upsert_contact($contact_data['contact'], $contact_data['custom_values']);
        if (is_wp_error($contact)) {
            $this->log_event('lead_ads', $contact_data['contact']['email'] ?? '', 500, $contact->get_error_message(), false);
            return $contact;
        }

        $this->apply_tags_and_lists($contact);
        $this->add_note($contact, $form_id);

        FCRM_FB_Events_Lead_Store::add_lead([
            'leadgen_id' => $leadgen_id,
            'form_id' => $form_id,
            'page_id' => $page_id,
            'contact_id' => $contact->id ?? null,
            'source' => $context['source'] ?? 'webhook',
            'lead_time' => $lead_time,
        ]);

        $identifier = $contact->email ?? ($contact->id ?? '');
        $this->log_event('lead_ads', $identifier, 200, 'Lead imported.', true);

        return $contact;
    }

    public function process_leadgen($leadgen_id, $form_id, $page_id, $created_time, $source)
    {
        if (FCRM_FB_Events_Lead_Store::has_lead($leadgen_id)) {
            return true;
        }

        if (!$leadgen_id) {
            return new WP_Error('missing_lead', __('Lead ID is required.', 'fluentcrm-facebook-events'));
        }

        $lead = $this->fetch_lead($leadgen_id, $page_id);
        if (is_wp_error($lead)) {
            $this->log_event('lead_ads', $leadgen_id, 500, $lead->get_error_message(), false);
            return $lead;
        }

        $lead['form_id'] = $lead['form_id'] ?? $form_id;
        if (!$lead['form_id'] && $form_id) {
            $lead['form_id'] = $form_id;
        }

        $result = $this->import_lead_payload($lead, [
            'form_id' => $form_id,
            'page_id' => $page_id,
            'created_time' => $created_time,
            'source' => $source,
        ]);

        return $result;
    }

    public function queue_leadgen($leadgen_id, $form_id, $page_id, $created_time, $source)
    {
        if (!$leadgen_id) {
            return;
        }

        if (FCRM_FB_Events_Lead_Store::has_lead($leadgen_id)) {
            return;
        }

        $settings = $this->get_lead_settings();
        if (empty($settings['access_token'])) {
            $this->log_event('lead_ads', $leadgen_id, 401, 'Missing access token.', false);
            return;
        }

        $payload = [
            'leadgen_id' => $leadgen_id,
            'form_id' => $form_id,
            'page_id' => $page_id,
            'created_time' => $created_time,
            'source' => $source,
            'attempt' => 1,
        ];

        if (function_exists('as_enqueue_async_action')) {
            if (function_exists('as_next_scheduled_action')) {
                $next = as_next_scheduled_action(self::LEADGEN_ACTION, [$payload], self::LEADGEN_GROUP);
                if ($next) {
                    return;
                }
            }
            as_enqueue_async_action(self::LEADGEN_ACTION, [$payload], self::LEADGEN_GROUP);
            return;
        }

        if (!wp_next_scheduled(self::LEADGEN_ACTION, [$payload])) {
            wp_schedule_single_event(time() + 5, self::LEADGEN_ACTION, [$payload]);
        }
    }

    public function handle_leadgen_action($payload)
    {
        if (!is_array($payload)) {
            return;
        }

        $leadgen_id = $payload['leadgen_id'] ?? '';
        if (!$leadgen_id) {
            return;
        }

        if (FCRM_FB_Events_Lead_Store::has_lead($leadgen_id)) {
            return;
        }

        $attempt = !empty($payload['attempt']) ? (int) $payload['attempt'] : 1;
        $result = $this->process_leadgen(
            $leadgen_id,
            $payload['form_id'] ?? '',
            $payload['page_id'] ?? '',
            $payload['created_time'] ?? 0,
            $payload['source'] ?? 'webhook'
        );

        if (is_wp_error($result)) {
            $non_retryable = ['missing_email', 'duplicate', 'missing_lead', 'missing_token'];
            if (!in_array($result->get_error_code(), $non_retryable, true)) {
                $this->schedule_leadgen_retry($payload, $attempt + 1, $result->get_error_message());
            }
        }
    }

    private function schedule_leadgen_retry(array $payload, $next_attempt, $reason)
    {
        if ($next_attempt > self::MAX_LEADGEN_ATTEMPTS) {
            $this->log_event('lead_ads', $payload['leadgen_id'] ?? '', 500, 'Max retry attempts reached: ' . $reason, false);
            return;
        }

        $payload['attempt'] = $next_attempt;
        $delay = self::RETRY_DELAY_SECONDS * $next_attempt;

        if (function_exists('as_schedule_single_action')) {
            if (function_exists('as_next_scheduled_action')) {
                $next = as_next_scheduled_action(self::LEADGEN_ACTION, [$payload], self::LEADGEN_GROUP);
                if ($next) {
                    return;
                }
            }
            as_schedule_single_action(time() + $delay, self::LEADGEN_ACTION, [$payload], self::LEADGEN_GROUP);
            return;
        }

        if (!wp_next_scheduled(self::LEADGEN_ACTION, [$payload])) {
            wp_schedule_single_event(time() + $delay, self::LEADGEN_ACTION, [$payload]);
        }
    }

    private function map_lead_to_contact(array $lead)
    {
        $settings = $this->get_lead_settings();
        $mapping = $this->get_lead_field_mapping();
        $lead_fields = $this->normalize_lead_fields($lead['field_data'] ?? []);

        $contact = [];
        $custom_values = [];

        foreach ($mapping as $fb_field => $fluent_field) {
            $fb_field = sanitize_key($fb_field);
            $fluent_field = sanitize_key($fluent_field);

            if (!$fb_field || !$fluent_field) {
                continue;
            }

            if (!isset($lead_fields[$fb_field])) {
                continue;
            }

            $value = $lead_fields[$fb_field];
            if (is_array($value)) {
                $value = implode(' ', array_filter($value));
            }

            if ($value === '') {
                continue;
            }

            if (in_array($fluent_field, ['email', 'first_name', 'last_name', 'phone', 'full_name'], true)) {
                $contact[$fluent_field] = $value;
            } else {
                $custom_values[$fluent_field] = $value;
            }
        }

        if (empty($contact['first_name']) && empty($contact['last_name']) && !empty($contact['full_name'])) {
            $parts = explode(' ', $contact['full_name'], 2);
            $contact['first_name'] = $parts[0];
            if (!empty($parts[1])) {
                $contact['last_name'] = $parts[1];
            }
        }

        if (!empty($contact['email'])) {
            $contact['email'] = sanitize_email($contact['email']);
        }

        if (!empty($contact['phone'])) {
            $contact['phone'] = sanitize_text_field($contact['phone']);
        }

        if (!empty($settings['contact_status'])) {
            $contact['status'] = $settings['contact_status'];
        }

        $contact['source'] = 'facebook_lead_ads';

        if (empty($contact['email'])) {
            $missing_action = $settings['missing_email_action'] ?? 'skip';
            if ($missing_action === 'phone_only' && !empty($contact['phone'])) {
                return [
                    'contact' => $contact,
                    'custom_values' => $custom_values,
                ];
            }

            return new WP_Error('missing_email', __('Lead does not contain an email address.', 'fluentcrm-facebook-events'));
        }

        return [
            'contact' => $contact,
            'custom_values' => $custom_values,
        ];
    }

    private function upsert_contact(array $contact_data, array $custom_values)
    {
        if (!class_exists('FluentCrm\\App\\Models\\Subscriber')) {
            return new WP_Error('missing_fluentcrm', __('FluentCRM is not active.', 'fluentcrm-facebook-events'));
        }

        $contact = null;
        if (class_exists('FluentCrm\\App\\Services\\Contacts\\ContactService')) {
            $service = new FluentCrm\App\Services\Contacts\ContactService();
            if (method_exists($service, 'createOrUpdate')) {
                $payload = $contact_data;
                if (!empty($custom_values)) {
                    $payload['custom_values'] = $custom_values;
                }
                $contact = $service->createOrUpdate($payload);
            }
        }

        if (!$contact) {
            $contact = $this->upsert_contact_fallback($contact_data);
        }

        if (!$contact || is_wp_error($contact)) {
            return new WP_Error('contact_error', __('Could not create or update contact.', 'fluentcrm-facebook-events'));
        }

        if (!empty($custom_values) && method_exists($contact, 'syncCustomFields')) {
            $contact->syncCustomFields($custom_values);
        }

        return $contact;
    }

    private function upsert_contact_fallback(array $contact_data)
    {
        $query = FluentCrm\App\Models\Subscriber::query();

        if (!empty($contact_data['email'])) {
            $query->where('email', $contact_data['email']);
        } elseif (!empty($contact_data['phone']) && $this->dedupe_by_phone()) {
            $query->where('phone', $contact_data['phone']);
        }

        $contact = $query->first();

        if ($contact) {
            $contact->fill($contact_data);
            $contact->save();
            return $contact;
        }

        return FluentCrm\App\Models\Subscriber::create($contact_data);
    }

    private function apply_tags_and_lists($contact)
    {
        $settings = $this->get_lead_settings();
        $tag_ids = array_filter(array_map('absint', (array) ($settings['tag_ids'] ?? [])));
        $list_ids = array_filter(array_map('absint', (array) ($settings['list_ids'] ?? [])));

        if (!empty($tag_ids) && method_exists($contact, 'attachTags')) {
            $contact->attachTags($tag_ids);
        }

        if (!empty($list_ids) && method_exists($contact, 'attachLists')) {
            $contact->attachLists($list_ids);
        }
    }

    private function add_note($contact, $form_id)
    {
        if (!class_exists('FluentCrm\\App\\Models\\SubscriberNote')) {
            return;
        }

        $message = __('Imported from Facebook Lead Ads', 'fluentcrm-facebook-events');
        if ($form_id) {
            $message .= sprintf(' (Form: %s)', $form_id);
        }

        FluentCrm\App\Models\SubscriberNote::create([
            'subscriber_id' => $contact->id,
            'title' => __('Facebook Lead Ads', 'fluentcrm-facebook-events'),
            'description' => $message,
            'type' => 'note',
        ]);
    }

    private function normalize_lead_fields(array $field_data)
    {
        $fields = [];

        foreach ($field_data as $field) {
            $name = isset($field['name']) ? sanitize_key($field['name']) : '';
            if (!$name) {
                continue;
            }

            $value = $field['values'] ?? '';
            if (is_array($value) && count($value) === 1) {
                $value = $value[0];
            }

            $fields[$name] = $value;
        }

        return $fields;
    }

    private function dedupe_by_phone()
    {
        $settings = $this->get_lead_settings();
        return !empty($settings['dedupe_by_phone']) && $settings['dedupe_by_phone'] === 'yes';
    }

    private function api_request($endpoint, array $params = [], $token_override = '', $method = 'GET')
    {
        $settings = $this->get_lead_settings();
        $token = $token_override ?: ($settings['access_token'] ?? '');
        if (!$token) {
            return new WP_Error('missing_token', __('Access token is required.', 'fluentcrm-facebook-events'));
        }

        $base = 'https://graph.facebook.com/v19.0';
        $params['access_token'] = $token;
        $method = strtoupper($method);

        if ($method === 'POST') {
            $url = $base . $endpoint;
            $response = wp_remote_post($url, [
                'timeout' => 20,
                'body' => $params,
            ]);
        } else {
            $url = add_query_arg($params, $base . $endpoint);
            $response = wp_remote_get($url, [
                'timeout' => 20,
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? __('Facebook API error.', 'fluentcrm-facebook-events');
            return new WP_Error('facebook_error', $message);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function get_page_access_token($page_id)
    {
        if (!$page_id) {
            return '';
        }

        $pages = $this->get_pages();
        if (is_wp_error($pages)) {
            return '';
        }

        foreach ($pages as $page) {
            if (!empty($page['id']) && $page['id'] === $page_id && !empty($page['access_token'])) {
                return $page['access_token'];
            }
        }

        return '';
    }

    private function log_event($trigger, $identifier, $status, $response, $success)
    {
        FCRM_FB_Events_Logger::add_log([
            'trigger' => $trigger,
            'contact_id' => 0,
            'email' => $identifier,
            'event_name' => 'lead_import',
            'status_code' => $status,
            'response' => $response,
            'success' => $success,
        ]);
    }
}
