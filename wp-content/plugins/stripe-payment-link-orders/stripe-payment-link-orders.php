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
  const OPT_EMAIL_TEMPLATE_MAP_JSON = 'spplo_email_template_map_json';

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

  public static function init() {
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);

    add_action('rest_api_init', [__CLASS__, 'register_webhook_route']);

    add_filter('manage_' . self::CPT . '_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_' . self::CPT . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);

    add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);

    add_action('spplo_order_created', [__CLASS__, 'sync_fluentcrm_contact'], 10, 4);

    add_action('admin_post_spplo_send_mapped_email', [__CLASS__, 'handle_admin_send_mapped_email']);
    add_action('admin_post_spplo_send_custom_email', [__CLASS__, 'handle_admin_send_custom_email']);
    add_action('admin_notices', [__CLASS__, 'admin_notices']);
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
    register_setting('spplo_settings_group', self::OPT_EMAIL_TEMPLATE_MAP_JSON);

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
    $default_template_map = wp_json_encode([
      "prod_ABC123" => [
        "subject" => "Your downloads for Order #{order_id}",
        "body" => "Hi {customer_name},\n\nThanks for purchasing {item_name}! Your download link is below:\n{download_url}\n\nNeed help? Reply to this email.",
        "links" => [
          ["label" => "Download", "url" => "https://example.com/downloads/logo-pack"],
          ["label" => "Documentation", "url" => "https://example.com/docs/logo-pack"]
        ]
      ],
      "price_123" => [
        "subject" => "Access details for {item_name}",
        "body" => "Thanks for your order #{order_id}.\n\nAccess your purchase here:\n{download_url}",
        "links" => ["label" => "Member Area", "url" => "https://example.com/members/pro"]
      ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

        <h2>Email Template Mapping (Per Product/Price)</h2>
        <p>Paste JSON mapping here. Keys can be Stripe <code>product_id</code> (<code>prod_...</code>) or <code>price_id</code> (<code>price_...</code>).</p>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="<?php echo esc_attr(self::OPT_EMAIL_TEMPLATE_MAP_JSON); ?>">Template Mapping JSON</label></th>
            <td>
              <?php $template_map_val = get_option(self::OPT_EMAIL_TEMPLATE_MAP_JSON, $default_template_map); ?>
              <textarea class="large-text code" rows="14"
                id="<?php echo esc_attr(self::OPT_EMAIL_TEMPLATE_MAP_JSON); ?>"
                name="<?php echo esc_attr(self::OPT_EMAIL_TEMPLATE_MAP_JSON); ?>"
              ><?php echo esc_textarea($template_map_val); ?></textarea>
              <p class="description">
                Value formats supported:
                <br>• <code>"prod_XXX": {"subject":"...","body":"...","links":"https://your-link"}</code>
                <br>• <code>"prod_XXX": {"subject":"...","body":"...","links":{"label":"Download","url":"https://your-link"}}</code>
                <br>• <code>"prod_XXX": {"subject":"...","body":"...","links":[{"label":"Link 1","url":"..."},{"label":"Link 2","url":"..."}]}</code>
                <br>Placeholders: <code>{order_id}</code>, <code>{customer_name}</code>, <code>{item_name}</code>, <code>{download_url}</code>, <code>{links_html}</code>, <code>{links_text}</code>
              </p>
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

    $customer_details    = $session['customer_details'] ?? [];
    $name                = $customer_details['name']  ?? '';
    $email               = $customer_details['email'] ?? '';
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

    $customer_details = $session['customer_details'] ?? [];
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

      $links = self::normalize_links_value($val);
      if (!empty($links)) {
        $map[$k] = $links;
      }
    }

    return ['map' => $map, 'error' => ''];
  }

  /**
   * Parse template mapping JSON into normalized array.
   * Normalized:
   * [
   *   "prod_XXX" => [
   *     "subject" => "...",
   *     "body" => "...",
   *     "links" => [ ["label"=>"...", "url"=>"..."], ... ],
   *   ],
   * ]
   */
  private static function get_email_template_map_normalized() {
    $raw = (string)get_option(self::OPT_EMAIL_TEMPLATE_MAP_JSON, '');
    $raw = trim($raw);

    if ($raw === '') return ['map' => [], 'error' => 'Email template map JSON is empty.'];

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return ['map' => [], 'error' => 'Email template map JSON is invalid.'];
    }

    $map = [];

    foreach ($decoded as $key => $val) {
      if (!is_array($val)) {
        continue;
      }

      $subject = trim((string)($val['subject'] ?? ''));
      $body = trim((string)($val['body'] ?? ''));
      $links = self::normalize_links_value($val['links'] ?? []);

      if ($subject === '' && $body === '' && empty($links)) {
        continue;
      }

      $map[(string)$key] = [
        'subject' => $subject,
        'body' => $body,
        'links' => $links,
      ];
    }

    return ['map' => $map, 'error' => ''];
  }

  private static function normalize_links_value($val) {
    $links = [];

    if (is_string($val)) {
      $u = trim($val);
      if ($u !== '') {
        $links[] = ['label' => 'Link', 'url' => $u];
      }
    } elseif (is_array($val) && isset($val['url'])) {
      $u = trim((string)$val['url']);
      if ($u !== '') {
        $links[] = [
          'label' => trim((string)($val['label'] ?? 'Link')) ?: 'Link',
          'url'   => $u
        ];
      }
    } elseif (is_array($val) && array_keys($val) === range(0, count($val) - 1)) {
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
      $uniq = [];
      foreach ($links as $lnk) {
        $uniq[$lnk['url']] = $lnk;
      }
      return array_values($uniq);
    }

    return [];
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

    $email_context = self::build_mapped_email_context($post_id, $resolved_links, []);
    if (is_wp_error($email_context)) {
      self::log_error($email_context->get_error_message(), ['post_id' => $post_id]);
      update_post_meta($post_id, '_spplo_email_error', sanitize_text_field($email_context->get_error_message()));
      return;
    }

    $result = self::send_customer_email($post_id, $email_context, 'automatic');
    if (!$result['success']) {
      update_post_meta($post_id, '_spplo_email_error', sanitize_text_field($result['message']));
    }
  }

  private static function build_mapped_email_context($post_id, array $resolved_links, array $override) {
    $to = get_post_meta($post_id, '_spplo_customer_email', true);
    $customer_name = get_post_meta($post_id, '_spplo_customer_name', true);

    if (!is_email($to)) {
      return new WP_Error('spplo_invalid_email', 'Customer email is invalid or missing.');
    }

    $template_map = self::get_email_template_map_normalized();
    $map = $template_map['map'];
    if (empty($map) && $template_map['error']) {
      self::log_error($template_map['error'], ['post_id' => $post_id]);
    }

    $selected = [
      'key' => '',
      'item_name' => '',
      'links' => [],
      'subject' => '',
      'body' => '',
    ];

    foreach ($resolved_links as $row) {
      $product_id = (string)($row['product_id'] ?? '');
      $price_id = (string)($row['price_id'] ?? '');
      $item_name = (string)($row['item_name'] ?? '');

      if ($product_id && isset($map[$product_id])) {
        $selected['key'] = $product_id;
        $selected['item_name'] = $item_name;
        $selected['subject'] = $map[$product_id]['subject'];
        $selected['body'] = $map[$product_id]['body'];
        $selected['links'] = !empty($map[$product_id]['links']) ? $map[$product_id]['links'] : ($row['links'] ?? []);
        break;
      }

      if ($price_id && isset($map[$price_id])) {
        $selected['key'] = $price_id;
        $selected['item_name'] = $item_name;
        $selected['subject'] = $map[$price_id]['subject'];
        $selected['body'] = $map[$price_id]['body'];
        $selected['links'] = !empty($map[$price_id]['links']) ? $map[$price_id]['links'] : ($row['links'] ?? []);
        break;
      }

      if ($item_name && isset($map[$item_name])) {
        $selected['key'] = $item_name;
        $selected['item_name'] = $item_name;
        $selected['subject'] = $map[$item_name]['subject'];
        $selected['body'] = $map[$item_name]['body'];
        $selected['links'] = !empty($map[$item_name]['links']) ? $map[$item_name]['links'] : ($row['links'] ?? []);
        break;
      }
    }

    $subject_tpl = $selected['subject'];
    $body_tpl = $selected['body'];
    $item_name = $selected['item_name'];
    $template_key = $selected['key'];
    $template_type = $template_key ? 'mapped' : 'default';

    $links = $selected['links'];
    if (empty($links)) {
      $links = [];
      foreach ($resolved_links as $row) {
        if (!empty($row['links'])) {
          $links = array_merge($links, $row['links']);
        }
      }
    }

    $links = apply_filters('spplo_download_links', $links, [
      'post_id' => $post_id,
      'template_key' => $template_key,
      'template_type' => $template_type,
    ]);

    $download_url = '';
    if (!empty($links[0]['url'])) {
      $download_url = (string)$links[0]['url'];
    }
    $download_url = apply_filters('spplo_download_url', $download_url, [
      'post_id' => $post_id,
      'template_key' => $template_key,
      'template_type' => $template_type,
      'links' => $links,
    ]);

    $subject_tpl = $subject_tpl !== '' ? $subject_tpl : (string)get_option(self::OPT_EMAIL_SUBJECT, 'Your links for Order #{order_id}');
    $body_tpl = $body_tpl !== '' ? $body_tpl : '';

    $replacements = [
      '{order_id}' => (string)$post_id,
      '{customer_name}' => (string)$customer_name,
      '{item_name}' => (string)$item_name,
      '{download_url}' => (string)$download_url,
    ];

    $subject = strtr($subject_tpl, $replacements);

    $intro = (string)get_option(self::OPT_EMAIL_INTRO, "Thanks for your purchase!\nHere are your links:");

    $has_any_link = !empty($links);

    $links_html = self::build_links_html($links);
    $links_text = self::build_links_text($links);

    $body_has_links_placeholders = (strpos($body_tpl, '{links_html}') !== false) || (strpos($body_tpl, '{links_text}') !== false);

    $body_html = '';
    $body_text = '';

    if ($body_tpl !== '') {
      $body_tpl = strtr($body_tpl, $replacements);
      $body_html = wp_kses_post(str_replace(
        ['{links_html}', '{links_text}'],
        [$links_html, nl2br(esc_html($links_text))],
        $body_tpl
      ));
      if ($body_has_links_placeholders) {
        $body_text = str_replace('{links_text}', $links_text, wp_strip_all_tags($body_tpl));
      } else {
        $body_text = wp_strip_all_tags($body_tpl);
        if ($links_text !== '') {
          $body_text .= "\n\n" . $links_text;
        }
      }
    } else {
      $intro_html = nl2br(esc_html($intro));
      $body_html  = '';
      $body_html .= '<div style="font-family:Arial, sans-serif; font-size:14px; line-height:1.5;">';
      $body_html .= '<p>Hi ' . esc_html($customer_name ? $customer_name : 'there') . ',</p>';
      $body_html .= '<p>' . $intro_html . '</p>';

      $body_html .= self::build_links_table_html($resolved_links);
      $body_html .= '<p style="margin-top:16px;">Order ID: <strong>' . esc_html((string)$post_id) . '</strong></p>';

      if (!$has_any_link) {
        $body_html .= '<p><em>Note:</em> We could not find links for your items. Please contact support.</p>';
      }

      $body_html .= '<p>If you have any questions, reply to this email.</p>';
      $body_html .= '</div>';

      $body_text = "Hi " . ($customer_name ? $customer_name : 'there') . ",\n\n" . $intro . "\n\n";
      foreach ($resolved_links as $row) {
        $item = (string)($row['item_name'] ?? 'Item');
        $qty = (int)($row['quantity'] ?? 1);
        $body_text .= $item . ($qty > 1 ? ' (x' . $qty . ')' : '') . "\n";
        $body_text .= self::build_links_text((array)($row['links'] ?? [])) . "\n\n";
      }
      $body_text .= "Order ID: " . $post_id . "\n";
      if (!$has_any_link) {
        $body_text .= "\nNote: We could not find links for your items. Please contact support.\n";
      }
      $body_text .= "\nIf you have any questions, reply to this email.\n";
    }

    if (!$body_has_links_placeholders && $body_tpl !== '' && $links_html !== '') {
      $body_html .= '<br><br>' . $links_html;
    }

    $context = [
      'post_id' => $post_id,
      'to' => $to,
      'customer_name' => $customer_name,
      'template_key' => $template_key,
      'template_type' => $template_type,
      'item_name' => $item_name,
      'links' => $links,
      'resolved_links' => $resolved_links,
      'download_url' => $download_url,
    ];

    $subject = apply_filters('spplo_email_subject', $subject, $context);
    $body_html = apply_filters('spplo_email_html', $body_html, $context);
    $body_text = apply_filters('spplo_email_text', $body_text, $context);

    $context['subject'] = $subject;
    $context['html'] = $body_html;
    $context['text'] = $body_text;

    if (!empty($override)) {
      $context = array_merge($context, $override);
    }

    return $context;
  }

  private static function send_customer_email($post_id, array $context, $source = 'manual') {
    $to = $context['to'];
    $subject = $context['subject'];
    $html = $context['html'];
    $text = $context['text'];

    $from_name  = (string)get_option(self::OPT_EMAIL_FROM_NAME, get_bloginfo('name'));
    $from_email = (string)get_option(self::OPT_EMAIL_FROM_EMAIL, get_option('admin_email'));
    if (!is_email($from_email)) $from_email = get_option('admin_email');

    $headers = [];
    $boundary = 'spplo-' . wp_generate_password(24, false);
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $headers[] = 'From: ' . self::sanitize_email_header_name($from_name) . ' <' . $from_email . '>';

    $body = '';
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    do_action('spplo_before_email_send', $post_id, $context, $source);
    $ok = wp_mail($to, $subject, $body, $headers);

    $history_entry = [
      'timestamp' => gmdate('c'),
      'user_id' => get_current_user_id(),
      'subject' => sanitize_text_field($subject),
      'template_key' => sanitize_text_field((string)($context['template_key'] ?? '')),
      'template_type' => sanitize_text_field((string)($context['template_type'] ?? '')),
      'source' => sanitize_text_field($source),
      'success' => $ok ? 1 : 0,
      'message' => $ok ? 'sent' : 'wp_mail() failed',
    ];

    self::add_email_history($post_id, $history_entry);

    if ($ok) {
      update_post_meta($post_id, '_spplo_email_sent_at', gmdate('c'));
      update_post_meta($post_id, '_spplo_email_to', sanitize_email($to));
      update_post_meta($post_id, '_spplo_email_subject', sanitize_text_field($subject));
      update_post_meta($post_id, '_spplo_email_template_key', sanitize_text_field((string)($context['template_key'] ?? '')));
      update_post_meta($post_id, '_spplo_email_template_type', sanitize_text_field((string)($context['template_type'] ?? '')));
      do_action('spplo_email_sent', $post_id, $context, $source);
      return ['success' => true, 'message' => 'Email sent.'];
    }

    $message = 'wp_mail() failed. Check server mail configuration / SMTP.';
    self::log_error($message, ['post_id' => $post_id, 'source' => $source]);
    do_action('spplo_email_failed', $post_id, $context, $source, $message);
    return ['success' => false, 'message' => $message];
  }

  private static function build_links_html(array $links) {
    if (empty($links)) {
      return '';
    }

    $parts = [];
    foreach ($links as $lnk) {
      $label = trim((string)($lnk['label'] ?? 'Link')) ?: 'Link';
      $url = trim((string)($lnk['url'] ?? ''));
      if ($url === '') {
        continue;
      }
      $parts[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
    }

    return implode('<br>', $parts);
  }

  private static function build_links_text(array $links) {
    if (empty($links)) {
      return '';
    }

    $parts = [];
    foreach ($links as $lnk) {
      $label = trim((string)($lnk['label'] ?? 'Link')) ?: 'Link';
      $url = trim((string)($lnk['url'] ?? ''));
      if ($url === '') {
        continue;
      }
      $parts[] = $label . ': ' . $url;
    }

    return implode("\n", $parts);
  }

  private static function build_links_table_html(array $resolved_links) {
    $html = '';
    $html .= '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; width:100%; max-width:700px;">';
    $html .= '<thead><tr>';
    $html .= '<th align="left">Item</th>';
    $html .= '<th align="left">Links</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($resolved_links as $row) {
      $item = (string)($row['item_name'] ?? 'Item');
      $qty  = (int)($row['quantity'] ?? 1);
      $item_display = esc_html($item . ($qty > 1 ? ' (x' . $qty . ')' : ''));

      $links_html = self::build_links_html((array)($row['links'] ?? []));
      $links_html = $links_html !== '' ? $links_html : '—';

      $html .= '<tr>';
      $html .= '<td>' . $item_display . '</td>';
      $html .= '<td>' . $links_html . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
  }

  private static function add_email_history($post_id, array $entry) {
    $history = get_post_meta($post_id, '_spplo_email_history', true);
    if (!is_array($history)) {
      $history = [];
    }
    $history[] = $entry;
    update_post_meta($post_id, '_spplo_email_history', $history);
  }

  private static function log_error($message, array $context = []) {
    $context_json = $context ? wp_json_encode($context) : '';
    error_log('[SPPLO] ' . $message . ($context_json ? ' | ' . $context_json : ''));
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
    $customer_email = get_post_meta($post->ID, '_spplo_customer_email', true);
    $sent_at = get_post_meta($post->ID, '_spplo_email_sent_at', true);
    $history = get_post_meta($post->ID, '_spplo_email_history', true);
    if (!is_array($history)) {
      $history = [];
    }

    echo '<p><strong>Customer:</strong> ' . esc_html($customer_email ?: '—') . '</p>';
    echo '<p><strong>Last sent:</strong> ' . esc_html($sent_at ?: '—') . '</p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="spplo_send_mapped_email" />';
    echo '<input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '" />';
    wp_nonce_field('spplo_send_mapped_email_' . $post->ID, 'spplo_send_mapped_email_nonce');
    submit_button('Resend Download Email', 'primary', 'submit', false);
    echo '</form>';

    echo '<hr>';
    echo '<h4>Send Custom Email</h4>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="spplo_send_custom_email" />';
    echo '<input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '" />';
    wp_nonce_field('spplo_send_custom_email_' . $post->ID, 'spplo_send_custom_email_nonce');
    echo '<p><label for="spplo_custom_subject"><strong>Subject</strong></label></p>';
    echo '<p><input type="text" class="widefat" name="spplo_custom_subject" id="spplo_custom_subject" /></p>';
    echo '<p><label for="spplo_custom_body"><strong>Body</strong></label></p>';
    echo '<p><textarea class="widefat" rows="6" name="spplo_custom_body" id="spplo_custom_body"></textarea></p>';
    echo '<p><label for="spplo_custom_download"><strong>Optional download link</strong></label></p>';
    echo '<p><input type="url" class="widefat" name="spplo_custom_download" id="spplo_custom_download" /></p>';
    submit_button('Send Custom Email', 'secondary', 'submit', false);
    echo '</form>';

    echo '<hr>';
    echo '<h4>Send History</h4>';
    if (empty($history)) {
      echo '<p>—</p>';
    } else {
      echo '<div style="max-height:220px; overflow:auto;">';
      echo '<table class="widefat striped" style="font-size:12px;">';
      echo '<tr><th>Time</th><th>User</th><th>Type</th><th>Status</th></tr>';
      $history = array_reverse($history);
      foreach ($history as $entry) {
        $time = esc_html((string)($entry['timestamp'] ?? ''));
        $user_id = (int)($entry['user_id'] ?? 0);
        $user = $user_id ? get_user_by('id', $user_id) : null;
        $user_name = $user ? $user->display_name : 'System';
        $type = esc_html((string)($entry['template_type'] ?? ''));
        $status = !empty($entry['success']) ? 'Sent' : 'Failed';
        echo '<tr>';
        echo '<td>' . $time . '</td>';
        echo '<td>' . esc_html($user_name) . '</td>';
        echo '<td>' . $type . '</td>';
        echo '<td>' . esc_html($status) . '</td>';
        echo '</tr>';
      }
      echo '</table>';
      echo '</div>';
    }
  }

  public static function handle_admin_send_mapped_email() {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    if (!$post_id) {
      wp_die('Invalid order.');
    }

    check_admin_referer('spplo_send_mapped_email_' . $post_id, 'spplo_send_mapped_email_nonce');

    if (!current_user_can('edit_post', $post_id)) {
      wp_die('Insufficient permissions.');
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== self::CPT) {
      wp_die('Invalid order.');
    }

    $rate_limit = self::check_rate_limit($post_id, 'mapped');
    if (is_wp_error($rate_limit)) {
      self::redirect_with_notice($post_id, 'error', $rate_limit->get_error_message());
    }

    $resolved_json = (string)get_post_meta($post_id, '_spplo_resolved_links', true);
    $resolved_links = json_decode($resolved_json, true);
    if (!is_array($resolved_links)) {
      $resolved_links = [];
    }

    $email_context = self::build_mapped_email_context($post_id, $resolved_links, [
      'template_type' => 'mapped',
    ]);

    if (is_wp_error($email_context)) {
      self::log_error($email_context->get_error_message(), ['post_id' => $post_id]);
      self::redirect_with_notice($post_id, 'error', $email_context->get_error_message());
    }

    $result = self::send_customer_email($post_id, $email_context, 'manual');
    self::redirect_with_notice($post_id, $result['success'] ? 'success' : 'error', $result['message']);
  }

  public static function handle_admin_send_custom_email() {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    if (!$post_id) {
      wp_die('Invalid order.');
    }

    check_admin_referer('spplo_send_custom_email_' . $post_id, 'spplo_send_custom_email_nonce');

    if (!current_user_can('edit_post', $post_id)) {
      wp_die('Insufficient permissions.');
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== self::CPT) {
      wp_die('Invalid order.');
    }

    $rate_limit = self::check_rate_limit($post_id, 'custom');
    if (is_wp_error($rate_limit)) {
      self::redirect_with_notice($post_id, 'error', $rate_limit->get_error_message());
    }

    $subject = isset($_POST['spplo_custom_subject']) ? sanitize_text_field(wp_unslash($_POST['spplo_custom_subject'])) : '';
    $body = isset($_POST['spplo_custom_body']) ? wp_kses_post(wp_unslash($_POST['spplo_custom_body'])) : '';
    $download = isset($_POST['spplo_custom_download']) ? esc_url_raw(wp_unslash($_POST['spplo_custom_download'])) : '';

    if ($subject === '' || $body === '') {
      self::redirect_with_notice($post_id, 'error', 'Custom subject and body are required.');
    }

    $to = get_post_meta($post_id, '_spplo_customer_email', true);
    $customer_name = get_post_meta($post_id, '_spplo_customer_name', true);

    if (!is_email($to)) {
      self::redirect_with_notice($post_id, 'error', 'Customer email is invalid or missing.');
    }

    $links = [];
    if ($download) {
      $links[] = [
        'label' => 'Download',
        'url' => $download,
      ];
    }

    $links_html = self::build_links_html($links);
    $links_text = self::build_links_text($links);

    $body_html = $body;
    if ($links_html !== '') {
      $body_html .= '<br><br>' . $links_html;
    }

    $body_text = wp_strip_all_tags($body);
    if ($links_text !== '') {
      $body_text .= "\n\n" . $links_text;
    }

    $context = [
      'post_id' => $post_id,
      'to' => $to,
      'customer_name' => $customer_name,
      'template_key' => 'custom',
      'template_type' => 'custom',
      'item_name' => '',
      'links' => $links,
      'resolved_links' => [],
      'download_url' => $download,
      'subject' => $subject,
      'html' => $body_html,
      'text' => $body_text,
    ];

    $context['subject'] = apply_filters('spplo_email_subject', $context['subject'], $context);
    $context['html'] = apply_filters('spplo_email_html', $context['html'], $context);
    $context['text'] = apply_filters('spplo_email_text', $context['text'], $context);

    $result = self::send_customer_email($post_id, $context, 'manual');
    self::redirect_with_notice($post_id, $result['success'] ? 'success' : 'error', $result['message']);
  }

  private static function check_rate_limit($post_id, $type) {
    $history = get_post_meta($post_id, '_spplo_email_history', true);
    if (!is_array($history) || empty($history)) {
      return true;
    }

    $last = end($history);
    $timestamp = isset($last['timestamp']) ? strtotime((string)$last['timestamp']) : 0;
    $last_type = (string)($last['template_type'] ?? '');

    if ($timestamp && (time() - $timestamp) < 60 && $last_type === $type) {
      return new WP_Error('spplo_rate_limit', 'Please wait before sending another email.');
    }

    return true;
  }

  private static function redirect_with_notice($post_id, $type, $message) {
    $url = add_query_arg([
      'post' => $post_id,
      'action' => 'edit',
      'spplo_notice' => rawurlencode($message),
      'spplo_notice_type' => $type,
    ], admin_url('post.php'));
    wp_safe_redirect($url);
    exit;
  }

  public static function admin_notices() {
    if (!isset($_GET['spplo_notice'], $_GET['spplo_notice_type'])) {
      return;
    }

    $message = sanitize_text_field(wp_unslash($_GET['spplo_notice']));
    $type = sanitize_text_field(wp_unslash($_GET['spplo_notice_type']));

    $class = ($type === 'success') ? 'notice notice-success' : 'notice notice-error';
    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
  }
}

SPPLO_Stripe_Payment_Link_Orders::init();
