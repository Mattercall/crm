# FluentCRM → Facebook Events

A standalone WordPress plugin that listens to FluentCRM contact events and forwards them to the Facebook Conversions API (CAPI).

## Requirements

- WordPress 5.8+
- FluentCRM installed and active
- A Facebook Pixel ID and CAPI Access Token

## Installation

1. Copy this plugin folder to `wp-content/plugins/fluentcrm-facebook-events/`.
2. Activate **FluentCRM Facebook Events** from the WordPress Plugins screen.
3. Go to **FluentCRM → Facebook Events** (or **Settings → Facebook Events** if FluentCRM is inactive).
4. Enter your Pixel ID and Access Token.
5. Enable the integration and configure the event mappings.

## Event Mapping Examples

### Tag Applied → Lead

1. Enable the integration.
2. In the **Event Mapping** table, enable **Tag Applied**.
3. Choose **Lead** as the FB Event Name.
4. Save settings.

Now, when a tag is applied to a FluentCRM contact, the plugin sends a **Lead** event to Facebook CAPI.

### List Subscribed → Subscribe

1. Enable **List Subscribed**.
2. Choose **Subscribe** as the FB event.
3. (Optional) Provide `value` and `currency`.

## Queueing

- **Send immediately**: events are sent on the same request.
- **Queue events**: uses Action Scheduler if available; otherwise falls back to a WP-Cron queue.

## Logging

The **Recent Logs** table shows the last 100 delivery attempts, including the status code and response snippet. Enable **Debug Logging** to store response snippets for successful requests as well.

## Data Cleanup

Enable **Delete data on uninstall** if you want to remove the settings and log/queue tables when uninstalling the plugin.

## Facebook Lead Ads → FluentCRM Contact Sync

This plugin can also ingest Facebook Lead Ads and create/update FluentCRM contacts via webhook or manual import.

### Setup Steps

1. Create a Facebook App and enable **Lead Ads** in the App settings.
2. Generate a long-lived Page token or System User token with these permissions:
   - `leads_retrieval`
   - `pages_read_engagement`
   - `pages_manage_metadata`
3. In WordPress, open **Facebook Events → Lead Ads Settings** and save:
   - Access Token (required)
   - App Secret (required for signature verification)
   - Webhook Verify Token (used for the Facebook webhook handshake)
4. Copy the **Webhook URL** shown on the settings page and add it to your Facebook App webhook configuration.
5. Click **Test Connection** to confirm the token and permissions.
6. Configure **Lead Ads Mapping** to map Facebook fields to FluentCRM fields.
7. Optionally set default Tags/Lists and contact status.

### Manual Import

Use **Facebook Events → Lead Ads Import** to pick a Page and Form, set a date range, and fetch leads on demand. The plugin stores processed lead IDs to avoid duplicates and shows the next pagination cursor if more leads are available.

### Logging

Check **Facebook Events → Lead Ads Logs** for import status, webhook activity, and errors.
