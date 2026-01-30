<?php
/**
 * Plugin Name: Stripe Payment Link Orders (Webhook -> WP Orders + Customer Email)
 * Description: Creates a private order record in WordPress when a Stripe Payment Link (Checkout) is paid, and emails download links per item purchased.
 * Version: 1.2.0
 * Author: Your Team
 */

if (!defined('ABSPATH')) exit;

class SPPLO_Stripe_Payment_Link_Orders {
  const OPT_SECRET_KEY        = 'spplo_stripe_secret_key';
  const OPT_WEBHOOK_SECRET    = 'spplo_stripe_webhook_secret';

  // Email settings
  const OPT_EMAIL_ENABLED     = 'spplo_email_enabled';
  const OPT_EMAIL_FROM_NAME   = 'spplo_email_from_name';
  const OPT_EMAIL_FROM_EMAIL  = 'spplo_email_from_email';
  const OPT_EMAIL_SUBJECT     = 'spplo_email_subject';
  const OPT_EMAIL_INTRO       = 'spplo_email_intro';

  // Mapping item -> link(s)
  // JSON object: keys can be Stripe product_id (prod_...), price_id (price_...), or fallback item name.
  // Values can be:
  // - "https://link"
  // - { "label": "Download", "url": "https://link" }
  // - [ { "label":"Link 1","url":"..." }, { "label":"Link 2","url":"..." } ]
  const OPT_DOWNLOAD_MAP_JSON = 'spplo_download_map_json';

  const OPT_FLUENTCRM_LIST_ID = 'spplo_fluentcrm_list_id';
  const OPT_FLUENTCRM_TAG_ID  = 'spplo_fluentcrm_tag_id';

  const CPT = 'stripe_order';
  const META_EMAIL_AUDIT_LOG = '_spplo_email_audit_log';
  const META_LAST_EMAIL_HASH = '_spplo_last_email_hash';

  const EMAIL_RATE_LIMIT_SECONDS = 60;
  const EMAIL_DUPLICATE_SECONDS = 600;

  public static function init() {
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);

    add_action('rest_api_init', [__CLASS__, 'register_webhook_route']);

    add_filter('manage_' . self::CPT . '_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_' . self::CPT . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);

    add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
    add_action('admin_post_spplo_send_order_email', [__CLASS__, 'handle_admin_send_order_email']);
    add_action('admin_notices', [__CLASS__, 'admin_notices']);

    add_action('spplo_order_created', [__CLASS__, 'sync_fluentcrm_contact'], 10, 4);
  }

  public static function register_cpt() {
    register_post_type(self::CPT, [
      'labels' => [
        'name'          => 'Stripe Orders',
        'singular_name' => 'Stripe Order',
      ],
      'public'              => false,
      'publicly_queryable'  => false,
      'exclude_from_search' => true,
      'show_ui'             => true,
      'show_in_menu'        => true,
      'menu_icon'           => 'dashicons-cart',
      'supports'            => ['title'],
      'capability_type'     => 'post',
      'show_in_rest'        => false,
    ]);
  }

  public static function admin_menu() {
    add_options_page(
      'Stripe Orders (Payment Links)',
      'Stripe Orders',
      'manage_options',
      'spplo-settings',
      [__CLASS__, 'settings_page']
    );
  }

  public static function register_settings() {
    register_setting('spplo_settings_group', self::OPT_SECRET_KEY);
    register_setting('spplo_settings_group', self::OPT_WEBHOOK_SECRET);

    register_setting('spplo_settings_group', self::OPT_EMAIL_ENABLED);
    register_setting('spplo_settings_group', self::OPT_EMAIL_FROM_NAME);
    register_setting('spplo_settings_group', self::OPT_EMAIL_FROM_EMAIL);
    register_setting('spplo_settings_group', self::OPT_EMAIL_SUBJECT);
    register_setting('spplo_settings_group', self::OPT_EMAIL_INTRO);

    register_setting('spplo_settings_group', self::OPT_DOWNLOAD_MAP_JSON);

    register_setting('spplo_settings_group', self::OPT_FLUENTCRM_LIST_ID, [
      'sanitize_callback' => 'absint',
    ]);
    register_setting('spplo_settings_group', self::OPT_FLUENTCRM_TAG_ID, [
      'sanitize_callback' => 'absint',
    ]);
  }

  public static function settings_page() {
    if (!current_user_can('manage_options')) return;

    $webhook_url = esc_html(rest_url('stripe-orders/v1/webhook'));

    $default_subject = 'Your links for Order #{order_id}';
    $default_intro = "Thanks for your purchase!\nHere are your links:";
    $default_map = wp_json_encode([
      // Example mappings — replace these with your Stripe product/price IDs and your links
      "prod_ABC123" => [
        ["label" => "Download Page", "url" => "https://example.com/downloads/logo-pack"],
        ["label" => "Documentation", "url" => "https://example.com/docs/logo-pack"]
      ],
      "price_123" => ["label" => "Member Area", "url" => "https://example.com/members/pro"],
      // Fallback by item name (only if product_id/price_id not available)
      "My Product Name" => "https://example.com/downloads/my-product"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    ?>
    <div class="wrap">
      <h1>Stripe Orders (Payment Links) Settings</h1>

      <p><strong>Webhook endpoint URL:</strong><br>
        <code><?php echo $webhook_url; ?></code>
      </p>

      <form method="post" action="options.php">
        <?php settings_fields('spplo_settings_group'); ?>

        <h2>Stripe API</h2>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_SECRET_KEY); ?>">Stripe Secret Key</label></th>
            <td>
              <input type="password" class="regular-text"
                id="<?php echo esc_attr(self::OPT_SECRET_KEY); ?>"
                name="<?php echo esc_attr(self::OPT_SECRET_KEY); ?>"
                value="<?php echo esc_attr(get_option(self::OPT_SECRET_KEY, '')); ?>"
                autocomplete="off"
              />
              <p class="description">
                Must match mode: <code>cs_test_</code> → <code>sk_test_</code>, <code>cs_live_</code> → <code>sk_live_</code>.
                Used to fetch line items (what customer bought).
              </p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_WEBHOOK_SECRET); ?>">Webhook Signing Secret</label></th>
            <td>
              <input type="password" class="regular-text"
                id="<?php echo esc_attr(self::OPT_WEBHOOK_SECRET); ?>"
                name="<?php echo esc_attr(self::OPT_WEBHOOK_SECRET); ?>"
                value="<?php echo esc_attr(get_option(self::OPT_WEBHOOK_SECRET, '')); ?>"
                autocomplete="off"
              />
              <p class="description">Stripe Dashboard → Developers → Webhooks → your endpoint → Signing secret.</p>
            </td>
          </tr>
        </table>

        <hr>

        <h2>Customer Email</h2>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Enable customer email</th>
            <td>
              <?php $enabled = get_option(self::OPT_EMAIL_ENABLED, '1'); ?>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(self::OPT_EMAIL_ENABLED); ?>" value="1" <?php checked($enabled, '1'); ?> />
                Send email when order is paid
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_EMAIL_FROM_NAME); ?>">From name</label></th>
            <td>
              <input type="text" class="regular-text"
                id="<?php echo esc_attr(self::OPT_EMAIL_FROM_NAME); ?>"
                name="<?php echo esc_attr(self::OPT_EMAIL_FROM_NAME); ?>"
                value="<?php echo esc_attr(get_option(self::OPT_EMAIL_FROM_NAME, get_bloginfo('name'))); ?>"
              />
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_EMAIL_FROM_EMAIL); ?>">From email</label></th>
            <td>
              <input type="email" class="regular-text"
                id="<?php echo esc_attr(self::OPT_EMAIL_FROM_EMAIL); ?>"
                name="<?php echo esc_attr(self::OPT_EMAIL_FROM_EMAIL); ?>"
                value="<?php echo esc_attr(get_option(self::OPT_EMAIL_FROM_EMAIL, get_option('admin_email'))); ?>"
              />
              <p class="description">Use a domain email for better deliverability (e.g. sales@yourdomain.com).</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_EMAIL_SUBJECT); ?>">Email subject</label></th>
            <td>
              <input type="text" class="large-text"
                id="<?php echo esc_attr(self::OPT_EMAIL_SUBJECT); ?>"
                name="<?php echo esc_attr(self::OPT_EMAIL_SUBJECT); ?>"
                value="<?php echo esc_attr(get_option(self::OPT_EMAIL_SUBJECT, $default_subject)); ?>"
              />
              <p class="description">Placeholders: <code>{order_id}</code>, <code>{customer_name}</code></p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_EMAIL_INTRO); ?>">Email intro text</label></th>
            <td>
              <textarea class="large-text" rows="4"
                id="<?php echo esc_attr(self::OPT_EMAIL_INTRO); ?>"
                name="<?php echo esc_attr(self::OPT_EMAIL_INTRO); ?>"
              ><?php echo esc_textarea(get_option(self::OPT_EMAIL_INTRO, $default_intro)); ?></textarea>
              <p class="description">This will appear above the links list.</p>
            </td>
          </tr>
        </table>

        <hr>

        <h2>FluentCRM</h2>
        <table class="form-table" role="presentation">
          <?php if (!class_exists('FluentCrm\\App\\Models\\Subscriber')) : ?>
            <tr>
              <th scope="row">FluentCRM Status</th>
              <td>
                <p class="description">FluentCRM is not active. Install/activate FluentCRM to map orders to a list or tag.</p>
              </td>
            </tr>
          <?php else : ?>
            <?php
            $selected_list_id = (int)get_option(self::OPT_FLUENTCRM_LIST_ID, 0);
            $selected_tag_id  = (int)get_option(self::OPT_FLUENTCRM_TAG_ID, 0);
            $lists = class_exists('FluentCrm\\App\\Models\\Lists')
              ? FluentCrm\App\Models\Lists::orderBy('title')->get()
              : [];
            $tags = class_exists('FluentCrm\\App\\Models\\Tag')
              ? FluentCrm\App\Models\Tag::orderBy('title')->get()
              : [];
            ?>
            <tr>
              <th scope="row"><label for="<?php echo esc_attr(self::OPT_FLUENTCRM_LIST_ID); ?>">FluentCRM List</label></th>
              <td>
                <select id="<?php echo esc_attr(self::OPT_FLUENTCRM_LIST_ID); ?>"
                        name="<?php echo esc_attr(self::OPT_FLUENTCRM_LIST_ID); ?>">
                  <option value="0"<?php selected($selected_list_id, 0); ?>>None</option>
                  <?php foreach ($lists as $list) : ?>
                    <option value="<?php echo esc_attr($list->id); ?>"<?php selected($selected_list_id, (int)$list->id); ?>>
                      <?php echo esc_html($list->title); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="description">New orders will add the contact to this list.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="<?php echo esc_attr(self::OPT_FLUENTCRM_TAG_ID); ?>">FluentCRM Tag</label></th>
              <td>
                <select id="<?php echo esc_attr(self::OPT_FLUENTCRM_TAG_ID); ?>"
                        name="<?php echo esc_attr(self::OPT_FLUENTCRM_TAG_ID); ?>">
                  <option value="0"<?php selected($selected_tag_id, 0); ?>>None</option>
                  <?php foreach ($tags as $tag) : ?>
                    <option value="<?php echo esc_attr($tag->id); ?>"<?php selected($selected_tag_id, (int)$tag->id); ?>>
                      <?php echo esc_html($tag->title); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="description">New orders will apply this tag to the contact.</p>
              </td>
            </tr>
          <?php endif; ?>
        </table>

        <hr>

        <h2>Download Links Mapping (Per Item)</h2>
        <p>Paste JSON mapping here. Keys can be Stripe <code>product_id</code> (<code>prod_...</code>) or <code>price_id</code> (<code>price_...</code>).</p>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_DOWNLOAD_MAP_JSON); ?>">Mapping JSON</label></th>
            <td>
              <?php $map_val = get_option(self::OPT_DOWNLOAD_MAP_JSON, $default_map); ?>
              <textarea class="large-text code" rows="14"
                id="<?php echo esc_attr(self::OPT_DOWNLOAD_MAP_JSON); ?>"
                name="<?php echo esc_attr(self::OPT_DOWNLOAD_MAP_JSON); ?>"
              ><?php echo esc_textarea($map_val); ?></textarea>
              <p class="description">
                Value formats supported:
                <br>• <code>"prod_XXX": "https://your-link"</code>
                <br>• <code>"prod_XXX": {"label":"Download","url":"https://your-link"}</code>
                <br>• <code>"prod_XXX": [{"label":"Link 1","url":"..."},{"label":"Link 2","url":"..."}]</code>
              </p>
            </td>
          </tr>
        </table>

        <?php submit_button(); ?>
      </form>

      <hr>
      <h2>Stripe Webhook Events to Enable</h2>
      <ul>
        <li><code>checkout.session.completed</code></li>
        <li><code>checkout.session.async_payment_succeeded</code> (optional for async payment methods)</li>
        <li><code>checkout.session.async_payment_failed</code> (optional)</li>
      </ul>
    </div>
    <?php
  }

  public static function register_webhook_route() {
    register_rest_route('stripe-orders/v1', '/webhook', [
      'methods'             => 'POST',
      'callback'            => [__CLASS__, 'handle_webhook'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function handle_webhook(\WP_REST_Request $request) {
    $payload    = file_get_contents('php://input');
    $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
    $wh_secret  = get_option(self::OPT_WEBHOOK_SECRET, '');

    if (empty($wh_secret)) {
      return new WP_REST_Response(['error' => 'Webhook secret not configured'], 500);
    }

    if (!self::verify_stripe_signature($payload, $sig_header, $wh_secret)) {
      return new WP_REST_Response(['error' => 'Invalid signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type']) || empty($event['data']['object'])) {
      return new WP_REST_Response(['error' => 'Invalid event payload'], 400);
    }

    $type = $event['type'];
    $obj  = $event['data']['object'];

    if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
      $created = self::create_order_from_checkout_session($event, $obj);
      return new WP_REST_Response(['received' => true, 'created' => $created], 200);
    }

    return new WP_REST_Response(['received' => true, 'ignored' => $type], 200);
  }

  /**
   * Verify Stripe-Signature header without the Stripe SDK.
   * Stripe signs: HMAC_SHA256(secret, "{timestamp}.{payload}")
   */
  private static function verify_stripe_signature($payload, $sig_header, $secret, $tolerance = 300) {
    if (empty($sig_header)) return false;

    $parts     = explode(',', $sig_header);
    $timestamp = null;
    $sigs_v1   = [];

    foreach ($parts as $p) {
      $kv = explode('=', trim($p), 2);
      if (count($kv) !== 2) continue;
      if ($kv[0] === 't')  $timestamp = $kv[1];
      if ($kv[0] === 'v1') $sigs_v1[] = $kv[1];
    }

    if (!$timestamp || empty($sigs_v1)) return false;
    if (!ctype_digit((string)$timestamp)) return false;

    if (abs(time() - (int)$timestamp) > (int)$tolerance) return false;

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);

    foreach ($sigs_v1 as $sig) {
      if (hash_equals($expected, $sig)) return true;
    }
    return false;
  }

  private static function create_order_from_checkout_session(array $event, array $session) {
    $session_id = $session['id'] ?? '';
    if (!$session_id) return false;

    $payment_status = $session['payment_status'] ?? '';
    if (!in_array($payment_status, ['paid', 'no_payment_required'], true)) {
      return false;
    }

    // Idempotency: don’t create twice for the same session
    $existing = get_posts([
      'post_type'      => self::CPT,
      'post_status'    => 'any',
      'meta_key'       => '_spplo_session_id',
      'meta_value'     => $session_id,
      'fields'         => 'ids',
      'posts_per_page' => 1,
    ]);
    if (!empty($existing)) return false;

    $customer_details    = self::get_customer_details($session);
    $name                = $customer_details['name'] ?? '';
    $email               = $customer_details['email'] ?? '';
    $phone               = $customer_details['phone'] ?? '';
    $amount_total        = $session['amount_total'] ?? null; // cents
    $currency            = strtoupper($session['currency'] ?? '');
    $mode                = $session['mode'] ?? '';
    $payment_intent      = $session['payment_intent'] ?? '';
    $client_reference_id = $session['client_reference_id'] ?? '';
    $metadata            = $session['metadata'] ?? [];
    $custom_fields_raw   = $session['custom_fields'] ?? [];

    // Fetch line items (with expanded product)
    $li_result = self::fetch_line_items($session_id);
    $items     = $li_result['items'];
    $li_code   = $li_result['http_code'];
    $li_error  = $li_result['error'];

    // Human readable summaries
    $items_summary       = self::summarize_items($items);
    $product_title       = self::get_title_product_name($items);
    $custom_fields_human = self::humanize_custom_fields($custom_fields_raw);

    // Create PRIVATE post
    $post_id = wp_insert_post([
      'post_type'   => self::CPT,
      'post_status' => 'private',
      'post_title'  => 'Order - ' . $session_id,
    ], true);

    if (is_wp_error($post_id)) return false;

    // Update title to: Order-(post id) - (Stripe product name)
    $new_title = 'Order-' . $post_id . ' - ' . $product_title;
    wp_update_post([
      'ID'         => $post_id,
      'post_title' => $new_title,
    ]);

    // Save meta
    update_post_meta($post_id, '_spplo_event_id', $event['id'] ?? '');
    update_post_meta($post_id, '_spplo_event_type', $event['type'] ?? '');
    update_post_meta($post_id, '_spplo_session_id', $session_id);

    update_post_meta($post_id, '_spplo_customer_name', sanitize_text_field((string)$name));
    update_post_meta($post_id, '_spplo_customer_email', sanitize_email((string)$email));
    update_post_meta($post_id, '_spplo_customer_phone', sanitize_text_field((string)$phone));
    update_post_meta($post_id, '_spplo_customer_details_source', sanitize_text_field((string)($customer_details['source'] ?? '')));
    update_post_meta($post_id, '_spplo_customer_fetch_http_code', (int)($customer_details['fetch_http_code'] ?? 0));
    update_post_meta($post_id, '_spplo_customer_fetch_error', sanitize_text_field((string)($customer_details['fetch_error'] ?? '')));

    update_post_meta($post_id, '_spplo_amount_total', is_null($amount_total) ? '' : (int)$amount_total);
    update_post_meta($post_id, '_spplo_currency', sanitize_text_field((string)$currency));
    update_post_meta($post_id, '_spplo_payment_status', sanitize_text_field((string)$payment_status));
    update_post_meta($post_id, '_spplo_mode', sanitize_text_field((string)$mode));
    update_post_meta($post_id, '_spplo_payment_intent', sanitize_text_field((string)$payment_intent));
    update_post_meta($post_id, '_spplo_client_reference_id', sanitize_text_field((string)$client_reference_id));

    update_post_meta($post_id, '_spplo_items', wp_json_encode($items));
    update_post_meta($post_id, '_spplo_items_summary', sanitize_text_field($items_summary));
    update_post_meta($post_id, '_spplo_product_title', sanitize_text_field($product_title));

    update_post_meta($post_id, '_spplo_metadata', wp_json_encode($metadata));

    // Store raw + human friendly custom fields
    update_post_meta($post_id, '_spplo_custom_fields', wp_json_encode($custom_fields_raw));
    update_post_meta($post_id, '_spplo_custom_fields_human', wp_json_encode($custom_fields_human));

    // Diagnostics for line item fetching
    update_post_meta($post_id, '_spplo_line_items_http_code', (int)$li_code);
    update_post_meta($post_id, '_spplo_line_items_error', sanitize_text_field((string)$li_error));

    // Resolve links per purchased item + email them
    $resolved = self::resolve_links_for_items($items);
    update_post_meta($post_id, '_spplo_resolved_links', wp_json_encode($resolved));

    self::maybe_send_customer_email($post_id, $resolved);

    do_action('spplo_order_created', $post_id, $session, $event, $items);

    return true;
  }

  public static function sync_fluentcrm_contact($post_id, array $session, array $event, array $items) {
    if (!class_exists('FluentCrm\\App\\Models\\Subscriber')) {
      return;
    }

    $customer_details = self::get_customer_details($session);
    $email = sanitize_email((string)($customer_details['email'] ?? ''));
    if (!$email || !is_email($email)) {
      return;
    }

    $name = sanitize_text_field((string)($customer_details['name'] ?? ''));
    $phone = sanitize_text_field((string)($customer_details['phone'] ?? ''));
    $name_parts = self::split_name($name);

    $contact_data = [
      'email' => $email,
      'first_name' => $name_parts['first_name'],
      'last_name' => $name_parts['last_name'],
      'full_name' => $name,
      'phone' => $phone,
      'source' => 'stripe_payment_link_orders',
    ];

    $contact = self::upsert_fluentcrm_contact($contact_data);

    if (!$contact || is_wp_error($contact)) {
      return;
    }

    update_post_meta($post_id, '_spplo_fluentcrm_contact_id', (int)$contact->id);

    $list_id = (int)get_option(self::OPT_FLUENTCRM_LIST_ID, 0);
    if ($list_id && method_exists($contact, 'attachLists')) {
      $contact->attachLists([$list_id]);
    }

    $tag_id = (int)get_option(self::OPT_FLUENTCRM_TAG_ID, 0);
    if ($tag_id && method_exists($contact, 'attachTags')) {
      $contact->attachTags([$tag_id]);
    }
  }

  private static function get_customer_details(array $session) {
    $details = is_array($session['customer_details'] ?? null) ? $session['customer_details'] : [];

    $name = sanitize_text_field((string)($details['name'] ?? ''));
    $email = sanitize_email((string)($details['email'] ?? ''));
    $phone = sanitize_text_field((string)($details['phone'] ?? ''));

    $sources = [];
    if ($name || $email || $phone) {
      $sources[] = 'session.customer_details';
    }

    $session_email = sanitize_email((string)($session['customer_email'] ?? ''));
    if (!$email && $session_email) {
      $email = $session_email;
      $sources[] = 'session.customer_email';
    }

    $fetch_http_code = 0;
    $fetch_error = '';

    $needs_fetch = (!$email || !$name || !$phone) && !empty($session['customer']);
    if ($needs_fetch) {
      $customer_id = (string)$session['customer'];
      $fetched = self::fetch_stripe_customer($customer_id);
      $fetch_http_code = (int)($fetched['http_code'] ?? 0);
      $fetch_error = (string)($fetched['error'] ?? '');
      $customer = $fetched['customer'] ?? [];
      if (is_array($customer)) {
        if (!$email && !empty($customer['email'])) {
          $email = sanitize_email((string)$customer['email']);
        }
        if (!$name && !empty($customer['name'])) {
          $name = sanitize_text_field((string)$customer['name']);
        }
        if (!$phone && !empty($customer['phone'])) {
          $phone = sanitize_text_field((string)$customer['phone']);
        }
      }
      $sources[] = 'stripe.customer';
    }

    return [
      'name' => $name,
      'email' => $email,
      'phone' => $phone,
      'source' => implode(',', array_unique($sources)),
      'fetch_http_code' => $fetch_http_code,
      'fetch_error' => $fetch_error,
    ];
  }

  private static function fetch_stripe_customer($customer_id) {
    $secret_key = (string)get_option(self::OPT_SECRET_KEY, '');
    if (empty($secret_key)) {
      return ['customer' => [], 'http_code' => 0, 'error' => 'Stripe secret key is missing in settings.'];
    }

    $url = 'https://api.stripe.com/v1/customers/' . rawurlencode($customer_id);

    $resp = wp_remote_get($url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $secret_key,
      ],
      'timeout' => 20,
    ]);

    if (is_wp_error($resp)) {
      return ['customer' => [], 'http_code' => 0, 'error' => $resp->get_error_message()];
    }

    $code = (int)wp_remote_retrieve_response_code($resp);
    $body = (string)wp_remote_retrieve_body($resp);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $msg = '';
      if (is_array($json) && isset($json['error']['message'])) {
        $msg = (string)$json['error']['message'];
      }
      if ($msg === '') $msg = 'Stripe API error while fetching customer.';
      return ['customer' => [], 'http_code' => $code, 'error' => $msg];
    }

    if (!is_array($json)) {
      return ['customer' => [], 'http_code' => $code, 'error' => 'Invalid Stripe customer response.'];
    }

    return ['customer' => $json, 'http_code' => $code, 'error' => ''];
  }

  private static function upsert_fluentcrm_contact(array $contact_data) {
    $contact = null;

    if (class_exists('FluentCrm\\App\\Services\\Contacts\\ContactService')) {
      $service = new FluentCrm\App\Services\Contacts\ContactService();
      if (method_exists($service, 'createOrUpdate')) {
        $contact = $service->createOrUpdate($contact_data);
      }
    }

    if ($contact) {
      return $contact;
    }

    $query = FluentCrm\App\Models\Subscriber::query();
    $query->where('email', $contact_data['email']);

    $existing = $query->first();
    if ($existing) {
      $existing->fill($contact_data);
      $existing->save();
      return $existing;
    }

    return FluentCrm\App\Models\Subscriber::create($contact_data);
  }

  private static function split_name($name) {
    $name = trim((string)$name);
    if ($name === '') {
      return ['first_name' => '', 'last_name' => ''];
    }

    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0] ?? '';
    $last = $parts[1] ?? '';

    return [
      'first_name' => $first,
      'last_name' => $last,
    ];
  }

  /**
   * Returns: ['items' => array, 'http_code' => int, 'error' => string]
   * Expands product so we can use product name reliably.
   */
  private static function fetch_line_items($session_id) {
    $secret_key = (string)get_option(self::OPT_SECRET_KEY, '');
    if (empty($secret_key)) {
      return ['items' => [], 'http_code' => 0, 'error' => 'Stripe secret key is missing in settings.'];
    }

    $url = 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($session_id) . '/line_items?limit=100&expand[]=data.price.product';

    $resp = wp_remote_get($url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $secret_key,
      ],
      'timeout' => 20,
    ]);

    if (is_wp_error($resp)) {
      return ['items' => [], 'http_code' => 0, 'error' => $resp->get_error_message()];
    }

    $code = (int)wp_remote_retrieve_response_code($resp);
    $body = (string)wp_remote_retrieve_body($resp);

    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $msg = '';
      if (is_array($json) && isset($json['error']['message'])) {
        $msg = (string)$json['error']['message'];
      }
      if ($msg === '') $msg = 'Stripe API error while fetching line items.';
      return ['items' => [], 'http_code' => $code, 'error' => $msg];
    }

    if (!is_array($json) || empty($json['data']) || !is_array($json['data'])) {
      return ['items' => [], 'http_code' => $code, 'error' => 'No line_items returned by Stripe.'];
    }

    $items = [];
    foreach ($json['data'] as $li) {
      $qty = isset($li['quantity']) ? (int)$li['quantity'] : 1;

      $desc = trim((string)($li['description'] ?? ''));

      $amount_total = $li['amount_total'] ?? null;
      $currency = strtoupper((string)($li['currency'] ?? ''));

      $price = $li['price'] ?? [];
      $price_id = (string)($price['id'] ?? '');
      $price_nickname = trim((string)($price['nickname'] ?? ''));

      $product_id = '';
      $product_name = '';

      if (isset($price['product'])) {
        if (is_array($price['product'])) {
          $product_id = (string)($price['product']['id'] ?? '');
          $product_name = trim((string)($price['product']['name'] ?? ''));
        } else {
          $product_id = (string)$price['product'];
        }
      }

      if ($desc === '') {
        $desc = $product_name ?: ($price_nickname ?: 'Item');
      }

      $items[] = [
        'description'    => $desc,
        'product_name'   => $product_name,
        'product_id'     => $product_id,
        'price_nickname' => $price_nickname,
        'price_id'       => $price_id,
        'quantity'       => $qty,
        'amount_total'   => $amount_total,
        'currency'       => $currency,
      ];
    }

    return ['items' => $items, 'http_code' => $code, 'error' => ''];
  }

  private static function summarize_items(array $items) {
    if (empty($items)) return '';
    $parts = [];
    foreach ($items as $it) {
      $desc = trim((string)($it['description'] ?? 'Item'));
      if ($desc === '') $desc = 'Item';
      $qty  = (int)($it['quantity'] ?? 1);
      $parts[] = $desc . ' x' . $qty;
    }
    return implode(', ', $parts);
  }

  private static function get_title_product_name(array $items) {
    if (empty($items)) return 'Stripe Payment';

    $first = trim((string)($items[0]['description'] ?? ''));
    if ($first === '') $first = trim((string)($items[0]['product_name'] ?? ''));
    if ($first === '') $first = trim((string)($items[0]['price_nickname'] ?? ''));
    if ($first === '') $first = 'Stripe Item';

    if (count($items) === 1) return $first;

    $more = count($items) - 1;
    return $first . ' +' . $more . ' more';
  }

  private static function humanize_custom_fields(array $custom_fields) {
    $out = [];

    foreach ($custom_fields as $cf) {
      $key  = (string)($cf['key'] ?? '');
      $type = (string)($cf['type'] ?? '');

      $label = $cf['label']['custom'] ?? $cf['label']['type'] ?? $key;
      $label = (string)$label;

      $value = '';

      if ($type === 'text') {
        $value = (string)($cf['text']['value'] ?? '');
      } elseif ($type === 'dropdown') {
        $value = (string)($cf['dropdown']['value'] ?? '');
      } elseif ($type === 'numeric') {
        $value = (string)($cf['numeric']['value'] ?? '');
      } else {
        foreach ($cf as $k => $v) {
          if (is_array($v) && isset($v['value'])) {
            $value = (string)$v['value'];
            break;
          }
        }
      }

      $out[] = [
        'key'   => $key,
        'label' => $label ?: $key,
        'type'  => $type,
        'value' => $value,
      ];
    }

    return $out;
  }

  /**
   * Parse mapping JSON from settings into a normalized array.
   * Normalized:
   * [
   *   "prod_XXX" => [ ["label"=>"...", "url"=>"..."], ... ],
   *   "price_YYY" => [ ... ],
   *   "Some Name" => [ ... ],
   * ]
   */
  private static function get_download_map_normalized() {
    $raw = (string)get_option(self::OPT_DOWNLOAD_MAP_JSON, '');
    $raw = trim($raw);

    if ($raw === '') return ['map' => [], 'error' => 'Download map JSON is empty.'];

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return ['map' => [], 'error' => 'Download map JSON is invalid.'];
    }

    $map = [];

    foreach ($decoded as $key => $val) {
      $k = (string)$key;

      $links = [];

      // string URL
      if (is_string($val)) {
        $u = trim($val);
        if ($u !== '') {
          $links[] = ['label' => 'Link', 'url' => $u];
        }
      }
      // object {label,url}
      elseif (is_array($val) && isset($val['url'])) {
        $u = trim((string)$val['url']);
        if ($u !== '') {
          $links[] = [
            'label' => trim((string)($val['label'] ?? 'Link')) ?: 'Link',
            'url'   => $u
          ];
        }
      }
      // array of objects
      elseif (is_array($val) && array_keys($val) === range(0, count($val) - 1)) {
        foreach ($val as $row) {
          if (!is_array($row)) continue;
          $u = trim((string)($row['url'] ?? ''));
          if ($u === '') continue;
          $links[] = [
            'label' => trim((string)($row['label'] ?? 'Link')) ?: 'Link',
            'url'   => $u
          ];
        }
      }

      if (!empty($links)) {
        // de-duplicate by URL
        $uniq = [];
        foreach ($links as $lnk) {
          $uniq[$lnk['url']] = $lnk;
        }
        $map[$k] = array_values($uniq);
      }
    }

    return ['map' => $map, 'error' => ''];
  }

  /**
   * Build resolved links per purchased item.
   * Output format:
   * [
   *   [
   *     "item_name" => "Logo Pack",
   *     "product_id" => "prod_...",
   *     "price_id" => "price_...",
   *     "quantity" => 1,
   *     "links" => [ ["label"=>"...","url"=>"..."], ... ],
   *   ],
   *   ...
   * ]
   */
  private static function resolve_links_for_items(array $items) {
    $parsed = self::get_download_map_normalized();
    $map = $parsed['map'];

    $resolved = [];

    foreach ($items as $it) {
      $item_name = trim((string)($it['description'] ?? ''));
      if ($item_name === '') $item_name = trim((string)($it['product_name'] ?? ''));
      if ($item_name === '') $item_name = trim((string)($it['price_nickname'] ?? ''));
      if ($item_name === '') $item_name = 'Item';

      $product_id = (string)($it['product_id'] ?? '');
      $price_id   = (string)($it['price_id'] ?? '');
      $qty        = (int)($it['quantity'] ?? 1);

      $links = [];

      // Priority: product_id -> price_id -> item_name
      if ($product_id && isset($map[$product_id])) {
        $links = $map[$product_id];
      } elseif ($price_id && isset($map[$price_id])) {
        $links = $map[$price_id];
      } elseif (isset($map[$item_name])) {
        $links = $map[$item_name];
      }

      $resolved[] = [
        'item_name'  => $item_name,
        'product_id' => $product_id,
        'price_id'   => $price_id,
        'quantity'   => $qty,
        'links'      => $links,
      ];
    }

    return $resolved;
  }

  /**
   * Send email once per order (stores meta to prevent duplicates).
   * Email content: list items + their links.
   */
  private static function maybe_send_customer_email($post_id, array $resolved_links) {
    $enabled = get_option(self::OPT_EMAIL_ENABLED, '1');
    if ($enabled !== '1') return;

    // prevent duplicate sends
    $already = get_post_meta($post_id, '_spplo_email_sent_at', true);
    if (!empty($already)) return;

    $send_result = self::send_download_email($post_id, $resolved_links, false);
    if (!$send_result['ok']) {
      update_post_meta($post_id, '_spplo_email_error', $send_result['message']);
    }
  }

  private static function send_download_email($post_id, array $resolved_links, $allow_duplicate) {
    $to = get_post_meta($post_id, '_spplo_customer_email', true);
    $customer_name = get_post_meta($post_id, '_spplo_customer_name', true);

    if (!is_email($to)) {
      return [
        'ok' => false,
        'message' => 'Customer email is invalid or missing.',
      ];
    }

    // If there are zero links for all items, we still send (optional). Change to "return" if you prefer.
    $has_any_link = false;
    foreach ($resolved_links as $row) {
      if (!empty($row['links'])) { $has_any_link = true; break; }
    }

    $from_name  = (string)get_option(self::OPT_EMAIL_FROM_NAME, get_bloginfo('name'));
    $from_email = (string)get_option(self::OPT_EMAIL_FROM_EMAIL, get_option('admin_email'));
    if (!is_email($from_email)) $from_email = get_option('admin_email');

    $built = self::build_download_email_content($post_id, $resolved_links, $customer_name, $has_any_link);
    $subject = $built['subject'];
    $html = $built['html'];

    // headers
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . self::sanitize_email_header_name($from_name) . ' <' . $from_email . '>';

    if (!$allow_duplicate) {
      $last_hash = (string)get_post_meta($post_id, self::META_LAST_EMAIL_HASH, true);
      $current_hash = md5($to . '|' . $subject . '|' . $html . '|download');
      if ($last_hash === $current_hash) {
        return [
          'ok' => false,
          'message' => 'Duplicate email detected.',
        ];
      }
    }

    $ok = wp_mail($to, $subject, $html, $headers);

    if ($ok) {
      update_post_meta($post_id, '_spplo_email_sent_at', gmdate('c'));
      update_post_meta($post_id, '_spplo_email_to', sanitize_email($to));
      update_post_meta($post_id, '_spplo_email_subject', sanitize_text_field($subject));
      update_post_meta($post_id, self::META_LAST_EMAIL_HASH, md5($to . '|' . $subject . '|' . $html . '|download'));
      return [
        'ok' => true,
        'message' => '',
        'subject' => $subject,
        'to' => $to,
      ];
    } else {
      return [
        'ok' => false,
        'message' => 'wp_mail() failed. Check server mail configuration / SMTP.',
      ];
    }
  }

  private static function build_download_email_content($post_id, array $resolved_links, $customer_name, $has_any_link) {
    $subject_tpl = (string)get_option(self::OPT_EMAIL_SUBJECT, 'Your links for Order #{order_id}');
    $subject = str_replace(
      ['{order_id}', '{customer_name}'],
      [(string)$post_id, (string)$customer_name],
      $subject_tpl
    );

    $intro = (string)get_option(self::OPT_EMAIL_INTRO, "Thanks for your purchase!\nHere are your links:");
    $intro_html = nl2br(esc_html($intro));

    // Build HTML body
    $html  = '';
    $html .= '<div style="font-family:Arial, sans-serif; font-size:14px; line-height:1.5;">';
    $html .= '<p>Hi ' . esc_html($customer_name ? $customer_name : 'there') . ',</p>';
    $html .= '<p>' . $intro_html . '</p>';

    $html .= '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; width:100%; max-width:700px;">';
    $html .= '<thead><tr>';
    $html .= '<th align="left">Item</th>';
    $html .= '<th align="left">Links</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($resolved_links as $row) {
      $item = $row['item_name'];
      $qty  = (int)($row['quantity'] ?? 1);
      $item_display = esc_html($item . ($qty > 1 ? ' (x' . $qty . ')' : ''));

      $links_html = '';
      if (!empty($row['links'])) {
        $parts = [];
        foreach ($row['links'] as $lnk) {
          $label = trim((string)($lnk['label'] ?? 'Link')) ?: 'Link';
          $url   = trim((string)($lnk['url'] ?? ''));
          if ($url === '') continue;
          $parts[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
        }
        $links_html = !empty($parts) ? implode('<br>', $parts) : '—';
      } else {
        $links_html = '—';
      }

      $html .= '<tr>';
      $html .= '<td>' . $item_display . '</td>';
      $html .= '<td>' . $links_html . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $html .= '<p style="margin-top:16px;">Order ID: <strong>' . esc_html((string)$post_id) . '</strong></p>';

    if (!$has_any_link) {
      $html .= '<p><em>Note:</em> We could not find links for your items. Please contact support.</p>';
    }

    $html .= '<p>If you have any questions, reply to this email.</p>';
    $html .= '</div>';

    return [
      'subject' => $subject,
      'html' => $html,
    ];
  }

  private static function sanitize_email_header_name($name) {
    $name = (string)$name;
    $name = wp_strip_all_tags($name);
    $name = preg_replace('/[\r\n]+/', ' ', $name);
    $name = trim($name);
    return $name === '' ? get_bloginfo('name') : $name;
  }

  public static function columns($cols) {
    $new = [];
    $new['cb']            = $cols['cb'] ?? '';
    $new['title']         = 'Order';
    $new['spplo_email']   = 'Email';
    $new['spplo_amount']  = 'Amount';
    $new['spplo_items']   = 'Items';
    $new['spplo_mailed']  = 'Email Sent';
    $new['date']          = $cols['date'] ?? 'Date';
    return $new;
  }

  public static function column_content($col, $post_id) {
    if ($col === 'spplo_email') {
      echo esc_html(get_post_meta($post_id, '_spplo_customer_email', true));
      return;
    }
    if ($col === 'spplo_amount') {
      $amt = get_post_meta($post_id, '_spplo_amount_total', true);
      $cur = get_post_meta($post_id, '_spplo_currency', true);
      if ($amt === '') { echo '—'; return; }
      $formatted = number_format(((int)$amt) / 100, 2);
      echo esc_html($formatted . ' ' . $cur);
      return;
    }
    if ($col === 'spplo_items') {
      $sum = get_post_meta($post_id, '_spplo_items_summary', true);
      echo esc_html($sum ?: '—');
      return;
    }
    if ($col === 'spplo_mailed') {
      $sent = get_post_meta($post_id, '_spplo_email_sent_at', true);
      if ($sent) {
        echo esc_html('Yes');
      } else {
        $err = get_post_meta($post_id, '_spplo_email_error', true);
        echo esc_html($err ? 'No (error)' : 'No');
      }
      return;
    }
  }

  public static function add_metaboxes() {
    add_meta_box(
      'spplo_order_details',
      'Stripe Order Details',
      [__CLASS__, 'metabox_order_details'],
      self::CPT,
      'normal',
      'high'
    );

    add_meta_box(
      'spplo_send_email',
      'Send Email',
      [__CLASS__, 'metabox_send_email'],
      self::CPT,
      'side',
      'default'
    );
  }

  public static function metabox_order_details($post) {
    $session_id = get_post_meta($post->ID, '_spplo_session_id', true);
    $name       = get_post_meta($post->ID, '_spplo_customer_name', true);
    $email      = get_post_meta($post->ID, '_spplo_customer_email', true);
    $status     = get_post_meta($post->ID, '_spplo_payment_status', true);

    $amt = get_post_meta($post->ID, '_spplo_amount_total', true);
    $cur = get_post_meta($post->ID, '_spplo_currency', true);
    $amount_display = ($amt !== '') ? number_format(((int)$amt)/100, 2) . ' ' . strtoupper((string)$cur) : '—';

    $items_json = (string)get_post_meta($post->ID, '_spplo_items', true);
    $items = json_decode($items_json, true);
    if (!is_array($items)) $items = [];

    $metadata_json = (string)get_post_meta($post->ID, '_spplo_metadata', true);
    $metadata = json_decode($metadata_json, true);
    if (!is_array($metadata)) $metadata = [];

    $cf_human_json = (string)get_post_meta($post->ID, '_spplo_custom_fields_human', true);
    $custom_fields_human = json_decode($cf_human_json, true);
    if (!is_array($custom_fields_human)) $custom_fields_human = [];

    $resolved_json = (string)get_post_meta($post->ID, '_spplo_resolved_links', true);
    $resolved = json_decode($resolved_json, true);
    if (!is_array($resolved)) $resolved = [];

    $li_code  = (int)get_post_meta($post->ID, '_spplo_line_items_http_code', true);
    $li_error = (string)get_post_meta($post->ID, '_spplo_line_items_error', true);

    $sent_at  = (string)get_post_meta($post->ID, '_spplo_email_sent_at', true);
    $mail_err = (string)get_post_meta($post->ID, '_spplo_email_error', true);

    echo '<h3 style="margin-top:0;">Summary</h3>';
    echo '<table class="widefat striped">';
    echo '<tr><th style="width:240px;">Order ID</th><td><code>' . esc_html($post->ID) . '</code></td></tr>';
    echo '<tr><th>Session ID</th><td><code>' . esc_html($session_id ?: '—') . '</code></td></tr>';
    echo '<tr><th>Customer</th><td>' . esc_html($name ?: '—') . '</td></tr>';
    echo '<tr><th>Email</th><td>' . esc_html($email ?: '—') . '</td></tr>';
    echo '<tr><th>Payment Status</th><td>' . esc_html($status ?: '—') . '</td></tr>';
    echo '<tr><th>Amount</th><td>' . esc_html($amount_display) . '</td></tr>';
    echo '<tr><th>Email Sent</th><td>' . esc_html($sent_at ? 'Yes (' . $sent_at . ')' : 'No') . ($mail_err ? ' — <code>' . esc_html($mail_err) . '</code>' : '') . '</td></tr>';
    echo '</table>';

    echo '<hr>';

    echo '<h3>Items Purchased</h3>';
    if (empty($items)) {
      echo '<p>—</p>';
      if (!empty($li_error) || $li_code !== 0) {
        echo '<p><strong>Line items fetch diagnostics:</strong></p>';
        echo '<ul>';
        echo '<li>HTTP Code: <code>' . esc_html((string)$li_code) . '</code></li>';
        echo '<li>Error: <code>' . esc_html($li_error ?: '—') . '</code></li>';
        echo '</ul>';
      }
    } else {
      echo '<table class="widefat striped">';
      echo '<tr><th>Item</th><th style="width:90px;">Qty</th><th style="width:160px;">Line Total</th></tr>';

      foreach ($items as $it) {
        $desc = trim((string)($it['description'] ?? ''));
        if ($desc === '') $desc = trim((string)($it['product_name'] ?? ''));
        if ($desc === '') $desc = trim((string)($it['price_nickname'] ?? ''));
        if ($desc === '') $desc = 'Item';

        $qty  = (int)($it['quantity'] ?? 1);

        $line_amt = $it['amount_total'] ?? null;
        $line_cur = strtoupper((string)($it['currency'] ?? ''));

        $line_display = '—';
        if ($line_amt !== null && $line_amt !== '') {
          $line_display = number_format(((int)$line_amt)/100, 2) . ' ' . $line_cur;
        }

        echo '<tr>';
        echo '<td>' . esc_html($desc) . '</td>';
        echo '<td>' . esc_html((string)$qty) . '</td>';
        echo '<td>' . esc_html($line_display) . '</td>';
        echo '</tr>';
      }

      echo '</table>';
    }

    echo '<hr>';

    echo '<h3>Links (Resolved Per Item)</h3>';
    if (empty($resolved)) {
      echo '<p>—</p>';
    } else {
      echo '<table class="widefat striped">';
      echo '<tr><th style="width:280px;">Item</th><th>Links</th></tr>';
      foreach ($resolved as $row) {
        $item = (string)($row['item_name'] ?? 'Item');
        $qty  = (int)($row['quantity'] ?? 1);
        $item_display = esc_html($item . ($qty > 1 ? ' (x' . $qty . ')' : ''));

        $links_html = '—';
        if (!empty($row['links']) && is_array($row['links'])) {
          $parts = [];
          foreach ($row['links'] as $lnk) {
            if (!is_array($lnk)) continue;
            $label = trim((string)($lnk['label'] ?? 'Link')) ?: 'Link';
            $url   = trim((string)($lnk['url'] ?? ''));
            if ($url === '') continue;
            $parts[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
          }
          if (!empty($parts)) $links_html = implode('<br>', $parts);
        }

        echo '<tr>';
        echo '<td>' . $item_display . '</td>';
        echo '<td>' . $links_html . '</td>';
        echo '</tr>';
      }
      echo '</table>';
    }

    echo '<hr>';

    echo '<h3>Custom Fields</h3>';
    if (empty($custom_fields_human)) {
      echo '<p>—</p>';
    } else {
      echo '<table class="widefat striped">';
      echo '<tr><th style="width:280px;">Field</th><th>Value</th></tr>';
      foreach ($custom_fields_human as $cf) {
        $label = (string)($cf['label'] ?? ($cf['key'] ?? 'Field'));
        $value = (string)($cf['value'] ?? '');
        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td>' . esc_html($value) . '</td>';
        echo '</tr>';
      }
      echo '</table>';
    }

    echo '<hr>';

    echo '<h3>Metadata</h3>';
    if (empty($metadata)) {
      echo '<p>—</p>';
    } else {
      echo '<table class="widefat striped">';
      echo '<tr><th style="width:280px;">Key</th><th>Value</th></tr>';
      foreach ($metadata as $k => $v) {
        if (is_array($v) || is_object($v)) $v = wp_json_encode($v);
        echo '<tr>';
        echo '<td><code>' . esc_html((string)$k) . '</code></td>';
        echo '<td>' . esc_html((string)$v) . '</td>';
        echo '</tr>';
      }
      echo '</table>';
    }

    echo '<hr>';
    echo '<details><summary><strong>Raw JSON (debug)</strong></summary>';
    echo '<p><strong>Items JSON</strong></p><pre style="white-space:pre-wrap;">' . esc_html($items_json) . '</pre>';
    echo '<p><strong>Resolved Links JSON</strong></p><pre style="white-space:pre-wrap;">' . esc_html($resolved_json) . '</pre>';
    echo '<p><strong>Custom Fields JSON</strong></p><pre style="white-space:pre-wrap;">' . esc_html((string)get_post_meta($post->ID, '_spplo_custom_fields', true)) . '</pre>';
    echo '<p><strong>Metadata JSON</strong></p><pre style="white-space:pre-wrap;">' . esc_html($metadata_json) . '</pre>';
    echo '</details>';
  }

  public static function metabox_send_email($post) {
    if (!current_user_can('edit_post', $post->ID)) {
      echo '<p>' . esc_html__('You do not have permission to send emails for this order.', 'spplo') . '</p>';
      return;
    }

    $email = get_post_meta($post->ID, '_spplo_customer_email', true);
    $default_subject = get_option(self::OPT_EMAIL_SUBJECT, 'Your links for Order #{order_id}');
    $default_subject = str_replace('{order_id}', (string)$post->ID, (string)$default_subject);

    wp_nonce_field('spplo_send_order_email', 'spplo_send_order_email_nonce');
    echo '<input type="hidden" name="action" value="spplo_send_order_email" />';
    echo '<input type="hidden" name="post_id" value="' . esc_attr((string)$post->ID) . '" />';

    echo '<p><strong>Customer Email:</strong><br>' . esc_html($email ?: '—') . '</p>';
    echo '<p>';
    echo '<button type="submit" name="spplo_send_action" value="resend" class="button button-secondary" formmethod="post" formaction="' . esc_url(admin_url('admin-post.php')) . '">';
    echo esc_html__('Resend Download Email', 'spplo');
    echo '</button>';
    echo '</p>';

    echo '<hr>';
    echo '<h4 style="margin:0 0 6px;">' . esc_html__('Send Custom Email', 'spplo') . '</h4>';
    echo '<p>';
    echo '<label for="spplo_custom_subject"><strong>' . esc_html__('Subject', 'spplo') . '</strong></label>';
    echo '<input type="text" class="widefat" id="spplo_custom_subject" name="spplo_custom_subject" value="' . esc_attr($default_subject) . '" />';
    echo '</p>';
    echo '<p>';
    echo '<label for="spplo_custom_body"><strong>' . esc_html__('Body', 'spplo') . '</strong></label>';
    echo '<textarea class="widefat" rows="5" id="spplo_custom_body" name="spplo_custom_body"></textarea>';
    echo '</p>';
    echo '<p>';
    echo '<label for="spplo_custom_download_link"><strong>' . esc_html__('Custom Download Link (optional)', 'spplo') . '</strong></label>';
    echo '<input type="url" class="widefat" id="spplo_custom_download_link" name="spplo_custom_download_link" placeholder="https://example.com/download" />';
    echo '</p>';
    echo '<p>';
    echo '<button type="submit" name="spplo_send_action" value="custom" class="button button-primary" formmethod="post" formaction="' . esc_url(admin_url('admin-post.php')) . '">';
    echo esc_html__('Send Custom Email', 'spplo');
    echo '</button>';
    echo '</p>';

    $history = self::get_email_audit_log($post->ID);
    echo '<hr>';
    echo '<h4 style="margin:0 0 6px;">' . esc_html__('Send History', 'spplo') . '</h4>';
    if (empty($history)) {
      echo '<p>' . esc_html__('No emails sent yet.', 'spplo') . '</p>';
    } else {
      echo '<div style="max-height:200px; overflow:auto;">';
      echo '<table class="widefat striped">';
      echo '<thead><tr>';
      echo '<th>' . esc_html__('Time (UTC)', 'spplo') . '</th>';
      echo '<th>' . esc_html__('Admin', 'spplo') . '</th>';
      echo '<th>' . esc_html__('Type', 'spplo') . '</th>';
      echo '<th>' . esc_html__('Subject', 'spplo') . '</th>';
      echo '<th>' . esc_html__('Status', 'spplo') . '</th>';
      echo '</tr></thead><tbody>';
      foreach ($history as $entry) {
        echo '<tr>';
        echo '<td>' . esc_html($entry['timestamp'] ?? '—') . '</td>';
        echo '<td>' . esc_html($entry['admin'] ?? '—') . '</td>';
        echo '<td>' . esc_html($entry['type'] ?? '—') . '</td>';
        echo '<td>' . esc_html($entry['subject'] ?? '—') . '</td>';
        echo '<td>' . esc_html($entry['status'] ?? '—') . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
      echo '</div>';
    }
  }

  public static function handle_admin_send_order_email() {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You are not allowed to send emails.', 'spplo'));
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || get_post_type($post_id) !== self::CPT) {
      wp_die(esc_html__('Invalid order.', 'spplo'));
    }

    if (!current_user_can('edit_post', $post_id)) {
      wp_die(esc_html__('You are not allowed to send emails for this order.', 'spplo'));
    }

    check_admin_referer('spplo_send_order_email', 'spplo_send_order_email_nonce');

    $action = isset($_POST['spplo_send_action']) ? sanitize_key(wp_unslash($_POST['spplo_send_action'])) : '';
    $email_to = get_post_meta($post_id, '_spplo_customer_email', true);
    $customer_name = get_post_meta($post_id, '_spplo_customer_name', true);

    if (!is_email($email_to)) {
      self::redirect_with_notice($post_id, 'error', 'Customer email is invalid or missing.');
    }

    $current_time = current_time('timestamp', true);
    $history = self::get_email_audit_log($post_id);
    $last_entry = !empty($history) ? $history[0] : null;
    if ($last_entry && !empty($last_entry['timestamp_unix'])) {
      $elapsed = $current_time - (int)$last_entry['timestamp_unix'];
      if ($elapsed < self::EMAIL_RATE_LIMIT_SECONDS) {
        self::redirect_with_notice($post_id, 'error', 'Please wait before sending another email.');
      }
    }

    $subject = '';
    $body = '';
    $download_link = '';
    $type = '';
    $send_result = ['ok' => false, 'message' => ''];
    $email_hash_source = '';

    if ($action === 'resend') {
      $type = 'download';
      $resolved_json = (string)get_post_meta($post_id, '_spplo_resolved_links', true);
      $resolved_links = json_decode($resolved_json, true);
      if (!is_array($resolved_links)) {
        $items_json = (string)get_post_meta($post_id, '_spplo_items', true);
        $items = json_decode($items_json, true);
        if (!is_array($items)) {
          $items = [];
        }
        $resolved_links = self::resolve_links_for_items($items);
      }

      $has_any_link = false;
      foreach ($resolved_links as $row) {
        if (!empty($row['links'])) { $has_any_link = true; break; }
      }
      $built = self::build_download_email_content($post_id, $resolved_links, $customer_name, $has_any_link);
      $subject = $built['subject'];
      $body = $built['html'];
      $email_hash_source = $email_to . '|' . $subject . '|' . $body . '|download';
    } elseif ($action === 'custom') {
      $type = 'custom';
      $subject = isset($_POST['spplo_custom_subject']) ? sanitize_text_field(wp_unslash($_POST['spplo_custom_subject'])) : '';
      $body = isset($_POST['spplo_custom_body']) ? wp_kses_post(wp_unslash($_POST['spplo_custom_body'])) : '';
      $download_link = isset($_POST['spplo_custom_download_link']) ? esc_url_raw(wp_unslash($_POST['spplo_custom_download_link'])) : '';

      if ($subject === '' || $body === '') {
        self::redirect_with_notice($post_id, 'error', 'Subject and body are required for a custom email.');
      }

      $email_hash_source = $email_to . '|' . $subject . '|' . $body . '|' . $download_link . '|custom';
    } else {
      self::redirect_with_notice($post_id, 'error', 'Invalid email action.');
    }

    $hash = md5($email_hash_source);
    if (!empty($last_entry['hash']) && $last_entry['hash'] === $hash) {
      $elapsed = $current_time - (int)($last_entry['timestamp_unix'] ?? 0);
      if ($elapsed < self::EMAIL_DUPLICATE_SECONDS) {
        self::redirect_with_notice($post_id, 'error', 'Duplicate email detected recently.');
      }
    }

    if ($action === 'resend') {
      $send_result = self::send_download_email($post_id, $resolved_links, true);
    } else {
      $send_result = self::send_custom_email($post_id, $email_to, $customer_name, $subject, $body, $download_link);
    }

    $log_entry = [
      'timestamp' => gmdate('c', $current_time),
      'timestamp_unix' => $current_time,
      'admin' => self::format_admin_user(get_current_user_id()),
      'admin_id' => get_current_user_id(),
      'subject' => $subject,
      'type' => $type,
      'status' => $send_result['ok'] ? 'success' : 'fail',
      'message' => $send_result['message'],
      'hash' => $hash,
    ];
    self::add_email_audit_log($post_id, $log_entry);

    if ($send_result['ok']) {
      self::redirect_with_notice($post_id, 'success', 'Email sent successfully.');
    }

    self::redirect_with_notice($post_id, 'error', $send_result['message'] ?: 'Email failed to send.');
  }

  private static function send_custom_email($post_id, $to, $customer_name, $subject, $body, $download_link) {
    $from_name  = (string)get_option(self::OPT_EMAIL_FROM_NAME, get_bloginfo('name'));
    $from_email = (string)get_option(self::OPT_EMAIL_FROM_EMAIL, get_option('admin_email'));
    if (!is_email($from_email)) {
      $from_email = get_option('admin_email');
    }

    $content = '<div style="font-family:Arial, sans-serif; font-size:14px; line-height:1.5;">';
    $content .= '<p>Hi ' . esc_html($customer_name ? $customer_name : 'there') . ',</p>';
    $content .= '<p>' . wp_kses_post(nl2br($body)) . '</p>';
    if ($download_link) {
      $content .= '<p><a href="' . esc_url($download_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Download Link', 'spplo') . '</a></p>';
    }
    $content .= '<p style="margin-top:16px;">Order ID: <strong>' . esc_html((string)$post_id) . '</strong></p>';
    $content .= '</div>';

    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . self::sanitize_email_header_name($from_name) . ' <' . $from_email . '>';

    $ok = wp_mail($to, $subject, $content, $headers);

    if ($ok) {
      update_post_meta($post_id, '_spplo_email_sent_at', gmdate('c'));
      update_post_meta($post_id, '_spplo_email_to', sanitize_email($to));
      update_post_meta($post_id, '_spplo_email_subject', sanitize_text_field($subject));
      update_post_meta($post_id, self::META_LAST_EMAIL_HASH, md5($to . '|' . $subject . '|' . $content . '|custom'));
      return [
        'ok' => true,
        'message' => '',
      ];
    }

    return [
      'ok' => false,
      'message' => 'wp_mail() failed. Check server mail configuration / SMTP.',
    ];
  }

  private static function get_email_audit_log($post_id) {
    $history = get_post_meta($post_id, self::META_EMAIL_AUDIT_LOG, true);
    if (!is_array($history)) {
      $history = [];
    }

    return $history;
  }

  private static function add_email_audit_log($post_id, array $entry) {
    $history = self::get_email_audit_log($post_id);
    array_unshift($history, $entry);
    $history = array_slice($history, 0, 50);
    update_post_meta($post_id, self::META_EMAIL_AUDIT_LOG, $history);
  }

  private static function format_admin_user($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
      return 'Unknown';
    }
    $name = $user->display_name ? $user->display_name : $user->user_login;
    return $name . ' (#' . $user->ID . ')';
  }

  private static function redirect_with_notice($post_id, $type, $message) {
    $url = admin_url('post.php?post=' . (int)$post_id . '&action=edit');
    $url = add_query_arg([
      'spplo_email_notice' => $type,
      'spplo_email_message' => rawurlencode($message),
    ], $url);
    wp_safe_redirect($url);
    exit;
  }

  public static function admin_notices() {
    if (empty($_GET['spplo_email_notice']) || empty($_GET['spplo_email_message'])) {
      return;
    }

    $type = sanitize_key(wp_unslash($_GET['spplo_email_notice']));
    $message = sanitize_text_field(wp_unslash($_GET['spplo_email_message']));
    $class = $type === 'success' ? 'notice notice-success' : 'notice notice-error';
    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
  }
}

SPPLO_Stripe_Payment_Link_Orders::init();
