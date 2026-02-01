<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Hooks
{
    private $processed = [];

    public function init()
    {
        if (!FCRM_FB_Events_Admin::fluentcrm_active()) {
            return;
        }

        add_action('fluent_crm/contact_added_to_tags', [$this, 'handle_tags_added'], 10, 2);
        add_action('fluentcrm_contact_added_to_tags', [$this, 'handle_tags_added_legacy'], 10, 2);

        add_action('fluent_crm/contact_removed_from_tags', [$this, 'handle_tags_removed'], 10, 2);
        add_action('fluentcrm_contact_removed_from_tags', [$this, 'handle_tags_removed_legacy'], 10, 2);

        add_action('fluent_crm/contact_created', [$this, 'handle_contact_created'], 10, 1);
        add_action('fluentcrm_contact_created', [$this, 'handle_contact_created'], 10, 1);

        add_action('fluent_crm/email_opened', [$this, 'handle_email_opened'], 10, 1);
        add_action('fluent_crm/email_url_clicked', [$this, 'handle_email_clicked'], 10, 2);
    }

    private function integration_enabled()
    {
        $settings = FCRM_FB_Events_Admin::get_settings();
        return $settings['enabled'] === 'yes';
    }

    private function should_process($trigger)
    {
        $settings = FCRM_FB_Events_Admin::get_settings();

        if ($settings['enabled'] !== 'yes') {
            return false;
        }

        $mappings = $this->get_mappings_for_trigger($trigger);
        foreach ($mappings as $mapping) {
            if (!empty($mapping['enabled']) && $mapping['enabled'] === 'yes') {
                return true;
            }
        }

        return false;
    }

    public function handle_tags_added($subscriber, $tagIds)
    {
        $this->handle_contact_event('tag_applied', $subscriber, [
            'tag_ids' => (array) $tagIds,
        ]);
    }

    public function handle_tags_added_legacy($tagIds, $subscriber)
    {
        $this->handle_contact_event('tag_applied', $subscriber, [
            'tag_ids' => (array) $tagIds,
        ]);
    }

    public function handle_tags_removed($subscriber, $tagIds)
    {
        $this->handle_contact_event('tag_removed', $subscriber, [
            'tag_ids' => (array) $tagIds,
        ]);
    }

    public function handle_tags_removed_legacy($tagIds, $subscriber)
    {
        $this->handle_contact_event('tag_removed', $subscriber, [
            'tag_ids' => (array) $tagIds,
        ]);
    }

    public function handle_contact_created($subscriber)
    {
        $this->handle_contact_event('contact_created', $subscriber, []);
    }

    public function handle_email_opened($campaignEmail)
    {
        if (!$this->should_process('email_opened')) {
            return;
        }

        $subscriber = $this->resolve_campaign_email_subscriber($campaignEmail);
        if (!$subscriber) {
            return;
        }

        $this->handle_contact_event('email_opened', $subscriber, [
            'campaign_email_id' => isset($campaignEmail->id) ? (int) $campaignEmail->id : 0,
        ]);
    }

    public function handle_email_clicked($campaignEmail, $urlData)
    {
        if (!$this->should_process('email_clicked')) {
            return;
        }

        $subscriber = $this->resolve_campaign_email_subscriber($campaignEmail);
        if (!$subscriber) {
            return;
        }

        $this->handle_contact_event('email_clicked', $subscriber, [
            'campaign_email_id' => isset($campaignEmail->id) ? (int) $campaignEmail->id : 0,
            'url' => is_array($urlData) ? ($urlData['url'] ?? '') : '',
        ]);
    }

    private function resolve_campaign_email_subscriber($campaignEmail)
    {
        if (is_object($campaignEmail) && !empty($campaignEmail->subscriber)) {
            return $campaignEmail->subscriber;
        }

        if (is_object($campaignEmail) && !empty($campaignEmail->subscriber_id)) {
            if (class_exists('FluentCrm\\App\\Models\\Subscriber')) {
                return FluentCrm\App\Models\Subscriber::find($campaignEmail->subscriber_id);
            }
        }

        return null;
    }

    private function handle_contact_event($trigger, $subscriber, array $context)
    {
        if (!$this->integration_enabled()) {
            return;
        }

        if (!is_object($subscriber) || empty($subscriber->id)) {
            return;
        }

        $action_time = current_time('timestamp', true);
        $mappings = $this->get_mappings_for_trigger($trigger);
        if (empty($mappings)) {
            return;
        }

        $sender = new FCRM_FB_Events_Facebook_CAPI();
        $queue = new FCRM_FB_Events_Queue();

        foreach ($mappings as $mapping) {
            if (empty($mapping['enabled']) || $mapping['enabled'] !== 'yes') {
                continue;
            }

            if (in_array($trigger, ['tag_applied', 'tag_removed'], true) && !$this->tag_event_matches_selection($mapping, $context)) {
                continue;
            }

            $dedupe_key = md5($trigger . '|' . $subscriber->id . '|' . wp_json_encode($context) . '|' . $action_time . '|' . wp_json_encode($mapping));
            if (isset($this->processed[$dedupe_key])) {
                continue;
            }
            $this->processed[$dedupe_key] = true;

            $payload = $sender->build_event_payload($trigger, $subscriber, $context, $action_time, $mapping);
            if (!$payload) {
                continue;
            }

            $queue->dispatch($payload, $trigger, $subscriber);
        }
    }

    private function tag_event_matches_selection(array $mapping, array $context)
    {
        $selected_tags = array_filter(array_map('absint', (array) ($mapping['tag_ids'] ?? [])));
        if (empty($selected_tags)) {
            return false;
        }

        $event_tags = array_filter(array_map('absint', (array) ($context['tag_ids'] ?? [])));
        if (empty($event_tags)) {
            return false;
        }

        return (bool) array_intersect($selected_tags, $event_tags);
    }

    private function get_mappings_for_trigger($trigger)
    {
        $settings = FCRM_FB_Events_Admin::get_settings();
        $mappings = $settings['mappings'][$trigger] ?? [];
        if (!is_array($mappings)) {
            return [];
        }
        if (isset($mappings['enabled']) || isset($mappings['event_name']) || isset($mappings['send_custom_event'])) {
            return [$mappings];
        }

        return array_values($mappings);
    }
}
