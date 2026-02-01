<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Facebook_CAPI
{
    const API_VERSION = 'v17.0';

    public function build_event_payload($trigger, $subscriber, array $context, $action_time, array $mapping)
    {
        $settings = FCRM_FB_Events_Admin::get_settings();

        if (!$mapping || $mapping['enabled'] !== 'yes') {
            return null;
        }

        $pixel_id = trim($settings['pixel_id']);
        $access_token = trim($settings['access_token']);

        if ($pixel_id === '' || $access_token === '') {
            return null;
        }

        $event_name = $mapping['event_name'];
        if ($mapping['send_custom_event'] === 'yes' && !empty($mapping['custom_event_name'])) {
            $event_name = $mapping['custom_event_name'];
        }

        $event_source_url = $settings['event_source_url'] ? $settings['event_source_url'] : site_url();

        $user_data = $this->build_user_data($subscriber);
        $custom_data = $this->build_custom_data($mapping, $context);

        $mapping_fingerprint = wp_json_encode($mapping);
        $event_id = md5($trigger . '|' . $subscriber->id . '|' . wp_json_encode($context) . '|' . $action_time . '|' . $event_name . '|' . $mapping_fingerprint);

        $event = [
            'event_name' => $event_name,
            'event_time' => (int) $action_time,
            'event_id' => $event_id,
            'event_source_url' => $event_source_url,
            'action_source' => 'website',
            'user_data' => $user_data,
            'custom_data' => $custom_data,
        ];

        return [
            'pixel_id' => $pixel_id,
            'access_token' => $access_token,
            'test_event_code' => $settings['test_event_code'],
            'event' => $event,
            'trigger' => $trigger,
            'contact_id' => (int) $subscriber->id,
            'contact_email' => (string) $subscriber->email,
        ];
    }

    public function send_event(array $payload)
    {
        $endpoint = sprintf('https://graph.facebook.com/%s/%s/events', self::API_VERSION, rawurlencode($payload['pixel_id']));

        $body = [
            'data' => [$payload['event']],
            'access_token' => $payload['access_token'],
        ];

        if (!empty($payload['test_event_code'])) {
            $body['test_event_code'] = $payload['test_event_code'];
        }

        $attempts = 0;
        $max_attempts = 3;
        $backoff = [1, 2, 4];
        $response_body = '';
        $status_code = 0;
        $success = false;

        while ($attempts < $max_attempts && !$success) {
            $attempts++;
            $response = wp_remote_post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                $response_body = $response->get_error_message();
                $status_code = 0;
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                if ($status_code >= 200 && $status_code < 300) {
                    $success = true;
                }
            }

            if (!$success && isset($backoff[$attempts - 1])) {
                sleep($backoff[$attempts - 1]);
            }
        }

        $settings = FCRM_FB_Events_Admin::get_settings();
        $log_response = '';
        if ($settings['debug_logging'] === 'yes' || !$success) {
            $log_response = $this->trim_response($response_body);
        }

        FCRM_FB_Events_Logger::add_log([
            'trigger' => $payload['trigger'],
            'contact_id' => $payload['contact_id'],
            'email' => $payload['contact_email'],
            'event_name' => $payload['event']['event_name'],
            'status_code' => $status_code,
            'response' => $log_response,
            'success' => $success ? 1 : 0,
        ]);

        return $success;
    }

    private function build_user_data($subscriber)
    {
        $data = [];

        $data['em'] = $this->hash_if_present($subscriber->email ?? '');
        $data['ph'] = $this->hash_if_present($subscriber->phone ?? '', 'phone');
        $data['fn'] = $this->hash_if_present($subscriber->first_name ?? '');
        $data['ln'] = $this->hash_if_present($subscriber->last_name ?? '');
        $data['ct'] = $this->hash_if_present($subscriber->city ?? '');
        $data['st'] = $this->hash_if_present($subscriber->state ?? '');
        $data['zp'] = $this->hash_if_present($subscriber->zip ?? '');
        $data['country'] = $this->hash_if_present($subscriber->country ?? '');
        $data['external_id'] = $this->hash_if_present((string) ($subscriber->id ?? ''), 'external_id');

        return array_filter($data);
    }

    private function build_custom_data(array $mapping, array $context)
    {
        $custom = [];

        if (!empty($mapping['value'])) {
            $custom['value'] = is_numeric($mapping['value']) ? (float) $mapping['value'] : $mapping['value'];
        }

        if (!empty($mapping['currency'])) {
            $custom['currency'] = $mapping['currency'];
        }

        if (!empty($context['tag_ids'])) {
            $custom['tag_ids'] = array_values(array_map('intval', (array) $context['tag_ids']));
        }

        if (!empty($context['list_ids'])) {
            $custom['list_ids'] = array_values(array_map('intval', (array) $context['list_ids']));
        }

        if (!empty($context['url'])) {
            $custom['url'] = esc_url_raw($context['url']);
        }

        if (!empty($mapping['custom_event_name'])) {
            $custom['custom_event_name'] = $mapping['custom_event_name'];
        }

        if (!empty($mapping['custom_params'])) {
            $decoded = json_decode($mapping['custom_params'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $custom = array_merge($custom, $decoded);
            }
        }

        return $custom;
    }

    private function hash_if_present($value, $type = 'default')
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($type === 'phone') {
            $value = preg_replace('/[^0-9]/', '', $value);
        }

        $value = strtolower($value);

        return hash('sha256', $value);
    }

    private function trim_response($response)
    {
        $response = wp_strip_all_tags((string) $response);
        if (strlen($response) > 200) {
            return substr($response, 0, 200) . '...';
        }

        return $response;
    }
}
