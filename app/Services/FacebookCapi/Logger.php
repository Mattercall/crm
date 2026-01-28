<?php

namespace FluentCrm\App\Services\FacebookCapi;

class Logger
{
    public static function log($data)
    {
        $defaults = [
            'contact_id'    => null,
            'contact_email' => '',
            'tag_id'        => null,
            'rule_id'       => '',
            'event_name'    => '',
            'status'        => 'fail',
            'response_code' => null,
            'response_body' => '',
            'retry_count'   => 0,
            'created_at'    => current_time('mysql')
        ];

        $payload = wp_parse_args($data, $defaults);

        fluentCrmDb()->table('fc_facebook_capi_logs')->insert($payload);
    }

    public static function getLogs($limit = 50, $offset = 0)
    {
        return fluentCrmDb()->table('fc_facebook_capi_logs')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public static function purgeOlderThan($days)
    {
        $days = absint($days);
        if (!$days) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days);

        return fluentCrmDb()->table('fc_facebook_capi_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
