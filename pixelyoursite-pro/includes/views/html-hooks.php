<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 hooks-wrapper">
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_by_gdpr - Disable send all pixels events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Disable send all pixels events, can by used for custom gdpr</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>

                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_{pixel}_by_gdpr - Disable send pixel events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>{pixel} - facebook, google_ads, ga, tiktok, pinterest, bing</p>
                    <p>Disable some pixel events, can by used for custom gdpr</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_facebook_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_gdpr_ajax_enabled - Update gdpr pixel status</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Load latest gdpr pixel status before load web pixel. Can by used when server use page caching</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_gdpr_ajax_enabled',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_check_consent_by_gdpr - Consent status for GDPR</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Allows developers to programmatically override consent status for GDPR compliance. Receives the current consent value, allowing you to customize logic to determine whether consent should be enabled or disabled. Useful for integrating with third-party consent management solutions or custom privacy workflows.</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_check_consent_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_event_data - Edit or add custom data to event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: array $data, string $slug ,any $context</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_event_data',function ($data,$slug,$context) {
    if(get_current_user_id() == 0 ) {
        $data['params']['total'] = 0;
    }
    return $data;
},10,3);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_currencies_list - Add new currency in list, for custom events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: array $currencies</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_currencies_list',function ($currencies) {
    $currencies['PTH'] = 'Test';
    return $currencies;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{edd or woo}_checkout_order_id - Use custom order id for purchase event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>pys_edd_checkout_order_id - Edd plugin<br>pys_woo_checkout_order_id - WooCommerce plugin</p>
                    <p>Can by user for custom checkout page</p>
                    <p>Param: int $order_id</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_woo_checkout_order_id',function ($order_id) {
    if(isset($_GET['custom_order_param_with_id'])) {
        return $_GET['custom_order_param_with_id'];
    }
    return $order_id;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_validate_pixel_event - Disable some events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>You can disable some events depend on your logic</p>
                    <p>Param: bool $isActive, \PixelYourSite\PYSEvent $event, \PixelYourSite\Settings $pixel</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_validate_pixel_event',function ($isActive,$event,$pixel) {
    if($pixel->getSlug() == "facebook"
    && $event->getId() == "woo_purchase"
    && get_current_user_id() == 0
    ) {
        return false;
    }
    return $isActive;
},10,3);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_server_event_filter - Conditionally disable server-side events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Allows you to conditionally prevent sending server-side events to Facebook CAPI, TikTok Events API, Pinterest Conversions API, and GA4 Measurement Protocol.</p>
                    <p><strong>Parameters:</strong></p>
                    <ul>
                        <li><code>$disable</code> (bool) - Whether to disable the event (default: false). Return true to block the event.</li>
                        <li><code>$event_name</code> (string) - Event slug, e.g., 'woo_purchase', 'automatic_event_scroll', 'init_event', 'edd_purchase', etc.</li>
                        <li><code>$tag_slug</code> (string) - Platform slug: 'facebook', 'tiktok', 'pinterest', or 'ga4'.</li>
                        <li><code>$order_id</code> (int|null) - Order ID if available, or null for non-order events.</li>
                    </ul>
                    <p><strong>Common event slugs:</strong></p>
                    <ul>
                        <li><strong>WooCommerce:</strong> <i><code>woo_purchase, woo_add_to_cart_on_button_click, woo_view_content, woo_initiate_checkout, woo_view_category, etc.</code></i></li>
                        <li><strong>EDD:</strong> <i><code>edd_purchase, edd_add_to_cart_on_button_click, edd_view_content, edd_initiate_checkout, etc.</code></i></li>
                        <li><strong>Automatic:</strong> <i><code>automatic_event_scroll, automatic_event_video, automatic_event_form, automatic_event_download, etc.</code></i></li>
                        <li><strong>Other:</strong> <i><code>init_event (page view)</code></i></li>
                    </ul>
                </div>
                <div class="example-block">
                    <label>Example 1 - Block Purchase events for API-created orders:</label>
                    <pre class="copy_text">
add_filter('pys_disable_server_event_filter', function($disable, $event_name, $tag_slug, $order_id) {
    // Block Purchase events for orders created via REST API
    if ( $event_name === 'woo_purchase' && $order_id ) {
        $order = wc_get_order($order_id);
        if ($order && $order->get_created_via() === 'rest-api') {
            return true; // Block this event
        }
    }
    return $disable;
}, 10, 4);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="example-block">
                    <label>Example 2 - Block all events for specific order status:</label>
                    <pre class="copy_text">
add_filter('pys_disable_server_event_filter', function($disable, $event_name, $tag_slug, $order_id) {
    // Block all events for pending orders
    if ($order_id) {
        $order = wc_get_order($order_id);
        if ($order && $order->get_status() === 'pending') {
            return true; // Block this event
        }
    }
    return $disable;
}, 10, 4);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="example-block">
                    <label>Example 3 - Block events only for specific platform:</label>
                    <pre class="copy_text">
add_filter('pys_disable_server_event_filter', function($disable, $event_name, $tag_slug, $order_id) {
    // Block Purchase events only for Facebook
    if ($event_name === 'woo_purchase' && $tag_slug === 'facebook') {
        return true; // Block Facebook Purchase events
    }
    return $disable;
}, 10, 4);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>

                <!-- Complete list of event slugs -->
                <div class="card card-style3" style="margin-top: 20px;">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2">📋 Complete List of All Event Slugs</h4>
                        <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body" style="display: none;">
                        <div class="flex-column-24gap">

                            <div style="margin-top: 20px;">
                                <h5 style="color: #0073aa; margin-bottom: 10px;">🔧 Other Events</h5>
                                <ul>
                                    <li><code>init_event</code> - Page view / initialization event</li>
                                    <li><code>custom</code> - Custom Events</li>
                                </ul>
                            </div>

                            <div>
                                <h5 style="color: #0073aa; margin-bottom: 10px;">🛒 WooCommerce Events</h5>
                                <ul style="column-count: 2; column-gap: 20px;">
                                    <li><code>woo_purchase</code> - Purchase event</li>
                                    <li><code>woo_view_content</code> - View product page</li>
                                    <li><code>woo_view_category</code> - View category page</li>
                                    <li><code>woo_view_cart</code> - View cart page</li>
                                    <li><code>woo_view_item_list</code> - View product list</li>
                                    <li><code>woo_view_item_list_single</code> - View single item list</li>
                                    <li><code>woo_view_item_list_search</code> - View search results</li>
                                    <li><code>woo_view_item_list_shop</code> - View shop page</li>
                                    <li><code>woo_view_item_list_tag</code> - View tag page</li>
                                    <li><code>woo_add_to_cart_on_button_click</code> - Add to cart button click</li>
                                    <li><code>woo_add_to_cart_on_cart_page</code> - Add to cart on cart page</li>
                                    <li><code>woo_add_to_cart_on_checkout_page</code> - Add to cart on checkout</li>
                                    <li><code>woo_initiate_checkout</code> - Initiate checkout</li>
                                    <li><code>woo_remove_from_cart</code> - Remove from cart</li>
                                    <li><code>woo_FirstTimeBuyer</code> - First time buyer</li>
                                    <li><code>woo_ReturningCustomer</code> - Returning customer</li>
                                    <li><code>woo_frequent_shopper</code> - Frequent shopper</li>
                                    <li><code>woo_vip_client</code> - VIP client</li>
                                    <li><code>woo_big_whale</code> - Big whale customer</li>
                                    <li><code>woo_affiliate</code> - Affiliate event</li>
                                    <li><code>woo_paypal</code> - PayPal event</li>
                                    <li><code>woo_select_content_category</code> - Select category</li>
                                    <li><code>woo_select_content_single</code> - Select single product</li>
                                    <li><code>woo_select_content_search</code> - Select from search</li>
                                    <li><code>woo_select_content_shop</code> - Select from shop</li>
                                    <li><code>woo_select_content_tag</code> - Select from tag</li>
                                    <li><code>woo_complete_registration</code> - Complete registration</li>
                                </ul>
                            </div>

                            <div style="margin-top: 20px;">
                                <h5 style="color: #0073aa; margin-bottom: 10px;">💳 Easy Digital Downloads (EDD) Events</h5>
                                <ul style="column-count: 2; column-gap: 20px;">
                                    <li><code>edd_purchase</code> - Purchase event</li>
                                    <li><code>edd_view_content</code> - View download page</li>
                                    <li><code>edd_view_category</code> - View category page</li>
                                    <li><code>edd_add_to_cart_on_button_click</code> - Add to cart button click</li>
                                    <li><code>edd_add_to_cart_on_checkout_page</code> - Add to cart on checkout</li>
                                    <li><code>edd_initiate_checkout</code> - Initiate checkout</li>
                                    <li><code>edd_remove_from_cart</code> - Remove from cart</li>
                                    <li><code>edd_frequent_shopper</code> - Frequent shopper</li>
                                    <li><code>edd_vip_client</code> - VIP client</li>
                                    <li><code>edd_big_whale</code> - Big whale customer</li>
                                </ul>
                            </div>

                            <div style="margin-top: 20px;">
                                <h5 style="color: #0073aa; margin-bottom: 10px;">🤖 Automatic Events</h5>
                                <ul style="column-count: 2; column-gap: 20px;">
                                    <li><code>automatic_event_scroll</code> - Page scroll event</li>
                                    <li><code>automatic_event_time_on_page</code> - Time on page</li>
                                    <li><code>automatic_event_video</code> - Video interaction</li>
                                    <li><code>automatic_event_form</code> - Form submission</li>
                                    <li><code>automatic_event_download</code> - File download</li>
                                    <li><code>automatic_event_comment</code> - Comment posted</li>
                                    <li><code>automatic_event_signup</code> - User signup</li>
                                    <li><code>automatic_event_login</code> - User login</li>
                                    <li><code>automatic_event_search</code> - Search performed</li>
                                    <li><code>automatic_event_internal_link</code> - Internal link click</li>
                                    <li><code>automatic_event_outbound_link</code> - Outbound link click</li>
                                    <li><code>automatic_event_tel_link</code> - Phone link click</li>
                                    <li><code>automatic_event_email_link</code> - Email link click</li>
                                    <li><code>automatic_event_adsense</code> - AdSense event</li>
                                    <li><code>automatic_event_404</code> - 404 page view</li>
                                </ul>
                            </div>

                            <div style="margin-top: 20px;">
                                <h5 style="color: #0073aa; margin-bottom: 10px;">🎯 CartFlows (WCF) Events (if CartFlows plugin is active)</h5>
                                <ul style="column-count: 2; column-gap: 20px;">
                                    <li><code>wcf_view_content</code> - View CartFlows page (each step)</li>
                                    <li><code>wcf_add_to_cart_on_next_step_click</code> - Add to cart on next step click</li>
                                    <li><code>wcf_add_to_cart_on_bump_click</code> - Add to cart on bump offer accepted</li>
                                    <li><code>wcf_remove_from_cart_on_bump_click</code> - Remove from cart on bump click</li>
                                    <li><code>wcf_page</code> - CartFlows page event (each step)</li>
                                    <li><code>wcf_step_page</code> - CartFlows step page (landing page)</li>
                                    <li><code>wcf_bump</code> - Order bump accepted</li>
                                    <li><code>wcf_lead</code> - CartFlows lead event (optin page)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_before_send_fb_server_event - Add custom data to  Facebook server event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: FacebookAds\Object\ServerSide\Event $event,string $pixel_Id, string $eventId</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_before_send_fb_server_event',function ($event,$pixel_Id,$eventId) {
    if(get_current_user_id() == 0 ) {
        $event->setActionSource("not_registered");
    }
    return $event;
},10,3);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_pixel_disabled - Disable Pixel</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $isActive,string $pixelSlug</p>
                    <p>Return: Array</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_pixel_disabled',function ($isActive,$pixelSlug) {
    if(get_current_user_id() == 0 && $pixelSlug == 'facebook') {
        return ['all']; // Disable all pixels
    }
    return $isActive;
},11,2);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_pixel_disabled',function ($isActive,$pixelSlug) {
    if(get_current_user_id() == 0 && $pixelSlug == 'facebook') {
        return ['1123450378576095', '1300447800692613']; // Disables pixels that are in the array
    }
    return $isActive;
},11,2);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{pixel}_ids - Add custom Pixel id</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p> {pixel} - facebook, google_ads, ga, tiktok, pinterest, bing</p>
                    <p>Param: array $ids</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_facebook_ids',function ($ids) {
    if(get_current_user_id() == 0) {
        $ids[]='CUSTOM_PIXEL_ID';
    }
    return $ids;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_fb_advanced_matching - Add or edit facebook advanced matching params</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: array $params</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_fb_advanced_matching',function ($params) {
    if(get_current_user_id() == 0) {
        $params['fn'] = "not_registered";
        $params['ln'] = "not_registered";
    }
    return $params;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_fb_server_user_data - Add or edit facebook server user data</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: \PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\UserData $userData</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_fb_server_user_data',function ($userData) {
    if(get_current_user_id() == 0) {
        $userData->setFirstName("undefined");
        $userData->setLastName("undefined");
        $userData->setEmail("undefined");
    }
    return $userData;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_all_cookie</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>disable all PYS cookies</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_all_cookie',function ($status) {
    $user = wp_get_current_user();
    $roles = ( array ) $user->roles;
    if(in_array('administrator', $roles) ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>there are also filters to disable certain groups of cookies that work on the same principle</p>
                    <p><code>pys_disabled_start_session_cookie</code> - disable start_session & session_limit cookie</p>
                    <p><code>pys_disable_first_visit_cookie</code> - disable pys_first_visit cookie</p>
                    <p><code>pys_disable_landing_page_cookie</code> - disable pys_landing_page & last_pys_landing_page cookies</p>
                    <p><code>pys_disable_trafficsource_cookie</code> - disable pysTrafficSource & last_pysTrafficSource cookies</p>
                    <p><code>pys_disable_utmTerms_cookie</code> - disable ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ,'utm_term'] with prefix <code>pys_</code> and <code>last_pys_</code> cookies</p>
                    <p><code>pys_disable_utmId_cookie</code> - disable ['fbadid', 'gadid', 'padid', 'bingid'] with prefix <code>pys_</code> and <code>last_pys_</code> cookies</p>
                    <p><code>pys_disable_advance_data_cookie</code> - disable pys_advanced_data cookies</p>
                    <p><code>pys_disable_externalID_by_gdpr</code> - disable pbid(external_id) cookie</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_google_alternative_id - Disable Google alternative GCLID cookie creation</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Disable Google alternative GCLID cookie creation for Safari compatibility</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_google_alternative_id',function ($status) {
    $user = wp_get_current_user();
    $roles = ( array ) $user->roles;
    if(in_array('administrator', $roles) ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{mode name}_mode - Fire pixel with Google consent mode</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p> {mode name} - analytics_storage, ad_storage, ad_user_data, ad_personalization</p>
                    <p>Param: bool $mode</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_analytics_storage_mode',function ($mode) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $mode;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>Fire the pixel with consent mode "analytics_storage": "granted"</p>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_bing_ad_storage_mode - Fire the Bing with consent mode</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $mode</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_bing_ad_storage_mode',function ($mode) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $mode;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>Fire the Bing with consent mode "ad_storage": "granted"</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_url_passthrough_mode - The filter turn ON/OFF the url_passthrough option</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_url_passthrough_mode',function ($status) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_meta_ldu_mode - The filter turn ON/OFF Meta Limited Data Use option</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_meta_ldu_mode',function ($status) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_send_meta_id - The filter allow/disallow sending the fb_login_id parameter from Social connect plugin</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_send_meta_id',function ($status) {
    if(get_current_user_id() == 1) {
        return false;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_reddit_ldu_mode - The filter turn ON/OFF Reddit Limited Data Use option</h4>
            </div>
			<?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_reddit_ldu_mode',function ($status) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
</div>

