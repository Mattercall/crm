<?php

namespace FluentCrm\App\Services\FacebookCapi;

use FluentCrm\Framework\Support\Arr;

class EventBuilder
{
    public static function buildEvent($subscriber, $tagId, $rule, $eventConfig, $settings, $context = [])
    {
        $eventType = Arr::get($eventConfig, 'event_type');
        $eventName = $eventType;

        if ($eventType === 'custom') {
            $eventName = Arr::get($eventConfig, 'custom_name');
        }

        $eventName = sanitize_text_field($eventName);
        if (!$eventName) {
            return null;
        }

        $eventSourceUrl = Arr::get($eventConfig, 'event_source_url');
        if (!$eventSourceUrl) {
            $eventSourceUrl = Arr::get($settings, 'default_event_source');
        }

        if (!$eventSourceUrl) {
            $eventSourceUrl = home_url();
        }

        $actionSource = Arr::get($eventConfig, 'action_source');
        if (!$actionSource) {
            $actionSource = Arr::get($settings, 'default_action_source', 'website');
        }

        $eventTime = time();
        $eventId = self::buildEventId($subscriber->id, $tagId, $eventName, $rule, $eventTime);

        $event = [
            'event_name'       => $eventName,
            'event_time'       => $eventTime,
            'action_source'    => $actionSource ?: 'website',
            'event_source_url' => $eventSourceUrl,
            'event_id'         => $eventId,
            'user_data'        => self::buildUserData($subscriber, $context),
            'custom_data'      => self::buildCustomData($eventConfig)
        ];

        return $event;
    }

    public static function buildCustomData($eventConfig)
    {
        $customData = [];

        $params = Arr::get($eventConfig, 'parameters');
        if ($params) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $customData = $decoded;
            }
        }

        $value = Arr::get($eventConfig, 'value');
        $currency = Arr::get($eventConfig, 'currency');

        if ($value !== '') {
            $customData['value'] = is_numeric($value) ? (float)$value : $value;
        }

        if ($currency) {
            $customData['currency'] = $currency;
        }

        return $customData;
    }

    public static function buildUserData($subscriber, $context = [])
    {
        $userData = [];

        if (!empty($subscriber->email)) {
            $userData['em'] = self::hashValue($subscriber->email);
        }

        if (!empty($subscriber->phone)) {
            $userData['ph'] = self::hashValue(self::normalizePhone($subscriber->phone));
        }

        if (!empty($subscriber->first_name)) {
            $userData['fn'] = self::hashValue($subscriber->first_name);
        }

        if (!empty($subscriber->last_name)) {
            $userData['ln'] = self::hashValue($subscriber->last_name);
        }

        if (!empty($subscriber->city)) {
            $userData['ct'] = self::hashValue($subscriber->city);
        }

        if (!empty($subscriber->state)) {
            $userData['st'] = self::hashValue($subscriber->state);
        }

        if (!empty($subscriber->zip)) {
            $userData['zp'] = self::hashValue($subscriber->zip);
        }

        if (!empty($subscriber->country)) {
            $userData['country'] = self::hashValue($subscriber->country);
        }

        $clientIp = Arr::get($context, 'client_ip');
        if ($clientIp) {
            $userData['client_ip_address'] = $clientIp;
        }

        $userAgent = Arr::get($context, 'client_user_agent');
        if ($userAgent) {
            $userData['client_user_agent'] = $userAgent;
        }

        return $userData;
    }

    public static function buildEventId($contactId, $tagId, $eventName, $rule, $eventTime)
    {
        $ruleId = Arr::get($rule, 'id');
        $seed = implode('|', [$contactId, $tagId, $eventName, $ruleId]);

        if (Arr::get($rule, 'fire_once') !== 'yes') {
            $seed .= '|' . floor($eventTime / 300);
        }

        return substr(hash('sha256', $seed), 0, 32);
    }

    public static function hashValue($value)
    {
        $normalized = trim(mb_strtolower($value));
        return hash('sha256', $normalized);
    }

    public static function normalizePhone($phone)
    {
        return preg_replace('/\D+/', '', $phone);
    }
}
