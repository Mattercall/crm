<?php

namespace FluentCrm\App\Services\FacebookCapi;

use FluentCrm\Framework\Support\Arr;

class Sender
{
    public function sendEvent($event, $settings, $meta = [])
    {
        $endpoint = Settings::getEndpointUrl($settings);
        $token = Arr::get($settings, 'access_token');
        $testEventCode = Arr::get($settings, 'test_event_code');

        $payload = [
            'data' => [$event],
            'access_token' => $token
        ];

        if ($testEventCode) {
            $payload['test_event_code'] = $testEventCode;
        }

        $attempts = 0;
        $lastResponse = null;
        $responseCode = 0;
        $success = false;

        $backoffs = [1, 3];
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            $attempts++;

            $lastResponse = wp_remote_post($endpoint, [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($payload)
            ]);

            if (is_wp_error($lastResponse)) {
                $responseCode = 0;
            } else {
                $responseCode = wp_remote_retrieve_response_code($lastResponse);
            }

            if (!is_wp_error($lastResponse) && $responseCode >= 200 && $responseCode < 300) {
                $success = true;
                break;
            }

            if ($attempts < $maxAttempts) {
                $sleep = $backoffs[$attempts - 1] ?? 5;
                sleep($sleep);
            }
        }

        return [
            'success' => $success,
            'response' => $lastResponse,
            'response_code' => $responseCode,
            'attempts' => $attempts
        ];
    }
}
