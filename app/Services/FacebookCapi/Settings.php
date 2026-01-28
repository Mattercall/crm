<?php

namespace FluentCrm\App\Services\FacebookCapi;

use FluentCrm\Framework\Support\Arr;

class Settings
{
    const OPTION_NAME = 'facebook_capi_settings';

    public static function getSettings()
    {
        $defaults = [
            'enabled'               => 'no',
            'pixel_id'              => '',
            'access_token'          => '',
            'test_event_code'       => '',
            'api_version'           => 'v18.0',
            'default_event_source'  => '',
            'default_action_source' => 'website',
            'background'            => 'yes',
            'debug'                 => 'no',
            'consent_required'      => 'no',
            'consent_meta_key'      => '',
            'log_retention_days'    => 30
        ];

        $settings = fluentcrm_get_option(self::OPTION_NAME, []);

        return wp_parse_args($settings, $defaults);
    }

    public static function saveSettings($data)
    {
        $settings = self::getSettings();

        $settings['enabled'] = Arr::get($data, 'enabled') ? 'yes' : 'no';
        $settings['pixel_id'] = sanitize_text_field(Arr::get($data, 'pixel_id'));
        $token = trim((string)Arr::get($data, 'access_token'));
        if ($token !== '') {
            $settings['access_token'] = sanitize_text_field($token);
        }

        $settings['test_event_code'] = sanitize_text_field(Arr::get($data, 'test_event_code'));
        $settings['api_version'] = sanitize_text_field(Arr::get($data, 'api_version', 'v18.0'));
        $settings['default_event_source'] = esc_url_raw(Arr::get($data, 'default_event_source'));
        $settings['default_action_source'] = sanitize_text_field(Arr::get($data, 'default_action_source', 'website'));
        $settings['background'] = Arr::get($data, 'background') ? 'yes' : 'no';
        $settings['debug'] = Arr::get($data, 'debug') ? 'yes' : 'no';
        $settings['consent_required'] = Arr::get($data, 'consent_required') ? 'yes' : 'no';
        $settings['consent_meta_key'] = sanitize_key(Arr::get($data, 'consent_meta_key'));
        $settings['log_retention_days'] = absint(Arr::get($data, 'log_retention_days', 30));

        fluentcrm_update_option(self::OPTION_NAME, $settings);

        return $settings;
    }

    public static function getEndpointUrl($settings)
    {
        $pixelId = Arr::get($settings, 'pixel_id');
        $apiVersion = apply_filters('fluentcrm/facebook_capi_api_version', Arr::get($settings, 'api_version', 'v18.0'));
        $apiVersion = ltrim($apiVersion, '/');

        return sprintf('https://graph.facebook.com/%s/%s/events', $apiVersion, $pixelId);
    }

    public static function getMaskedToken($settings)
    {
        $token = Arr::get($settings, 'access_token');
        if (!$token) {
            return '';
        }

        return str_repeat('•', 12);
    }
}
