<?php

namespace FluentCrm\App\Services\FacebookCapi;

use FluentCrm\Framework\Support\Arr;

class Rules
{
    const OPTION_NAME = 'facebook_capi_rules';

    public static function getRules()
    {
        $rules = fluentcrm_get_option(self::OPTION_NAME, []);

        if (!is_array($rules)) {
            return [];
        }

        return $rules;
    }

    public static function getRulesForTag($tagId)
    {
        $rules = self::getRules();
        $matched = [];

        foreach ($rules as $rule) {
            if ((int)Arr::get($rule, 'tag_id') === (int)$tagId && Arr::get($rule, 'enabled', 'yes') === 'yes') {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    public static function saveRules($postedRules)
    {
        $rules = [];

        if (!is_array($postedRules)) {
            fluentcrm_update_option(self::OPTION_NAME, []);
            return [];
        }

        foreach ($postedRules as $rule) {
            $ruleId = Arr::get($rule, 'id');
            if (!$ruleId) {
                $ruleId = wp_generate_uuid4();
            }

            $eventConfigs = [];
            $events = Arr::get($rule, 'events', []);
            if (is_array($events)) {
                foreach ($events as $event) {
                    $eventType = sanitize_text_field(Arr::get($event, 'event_type'));
                    $eventConfig = [
                        'event_type'       => $eventType,
                        'custom_name'      => sanitize_text_field(Arr::get($event, 'custom_name')),
                        'parameters'       => wp_kses_post(Arr::get($event, 'parameters')),
                        'value'            => sanitize_text_field(Arr::get($event, 'value')),
                        'currency'         => sanitize_text_field(Arr::get($event, 'currency')),
                        'action_source'    => sanitize_text_field(Arr::get($event, 'action_source')),
                        'event_source_url' => esc_url_raw(Arr::get($event, 'event_source_url'))
                    ];

                    if ($eventConfig['event_type'] || $eventConfig['custom_name']) {
                        $eventConfigs[] = $eventConfig;
                    }
                }
            }

            $rules[] = [
                'id'                   => $ruleId,
                'enabled'              => Arr::get($rule, 'enabled') ? 'yes' : 'no',
                'name'                 => sanitize_text_field(Arr::get($rule, 'name')),
                'tag_id'               => absint(Arr::get($rule, 'tag_id')),
                'fire_once'            => Arr::get($rule, 'fire_once') ? 'yes' : 'no',
                'require_contact_data' => Arr::get($rule, 'require_contact_data') ? 'yes' : 'no',
                'required_list_ids'    => array_map('absint', (array)Arr::get($rule, 'required_list_ids', [])),
                'required_status'      => sanitize_text_field(Arr::get($rule, 'required_status')),
                'events'               => $eventConfigs
            ];
        }

        fluentcrm_update_option(self::OPTION_NAME, $rules);

        return $rules;
    }
}
