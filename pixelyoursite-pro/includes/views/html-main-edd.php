<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\Ads\Helpers as AdsHelpers;

?>



<!-- Enable EDD -->
<div class="cards-wrapper cards-wrapper-style1 gap-24">

    <!-- Advanced Purchase Tracking-->
    <div class="card card-style5">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e( 'Purchase and Refund Tracking Settings', 'pys' ); ?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-24">
                <div class="d-flex align-items-center">
                    <?php PYS()->render_switcher_input( 'edd_advance_purchase_fb_enabled' ); ?>
                    <h4 class="switcher-label secondary_heading"><?php _e( 'Facebook backup and renewals Purchase tracking via API', 'pys' ); ?></h4>
                </div>
                <div class="d-flex align-items-center">
                    <?php PYS()->render_switcher_input( 'edd_advance_purchase_ga_enabled' ); ?>
                    <h4 class="switcher-label secondary_heading"><?php _e( 'Google Analytics backup and renewals Purchase tracking via API', 'pys' ); ?></h4>
                </div>
                <?php if ( Tiktok()->enabled() ) : ?>
                    <div class="d-flex align-items-center">
                        <?php Tiktok()->render_switcher_input( 'edd_advance_purchase_enabled' ); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e( 'TikTok backup and renewals Purchase tracking via API', 'pys' ); ?></h4>
                    </div>
                <?php endif; ?>
                <?php if ( Pinterest()->enabled() ) : ?>
                    <div class="d-flex align-items-center">
                        <?php Pinterest()->render_switcher_input( 'edd_advance_purchase_enabled' ); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e( 'Pinterest backup and renewals Purchase tracking via API', 'pys' ); ?></h4>
                    </div>
                <?php endif; ?>
                <p class="text-gray">
                    <?php _e('The plugin will send a Purchase event to Meta and Google using API when auto-renewals take place or when a new order is placed by an admin on the backend. Meta Conversion API token and GA4 Measurement Protocol secret are required.', 'pys');?>
                </p>
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php PYS()->render_switcher_input( 'edd_track_refunds_GA' ); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e( 'Track refunds on Goolge Analytics', 'pys' ); ?></h4>
                    </div>
                    <p class="text-gray">
                        <?php _e('A "Refund" event will be sent to Google via the API when the order status changes to "Refund". GA4 measurement protocol secret required.', 'pys');?>
                    </p>
                </div>

            </div>
        </div>
    </div>
    <!-- Advanced Purchase Tracking-->
    <div class="card card-style5">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e( 'General', 'pys' ); ?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-24">
                <p>
                    <?php _e('Fire e-commerce related events. Meta, TikTok, Google Ads, Bing (paid add-on), and Pinterest (paid add-on) events are Dynamic Ads Ready. Monetization data is sent to Google Analytics.', 'pys');?>
                </p>
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php PYS()->render_switcher_input( 'edd_enabled_save_data_to_orders' ); ?>
                        <h4 class="switcher-label">Enable Easy Digital Downloads Reports</h4>
                    </div>
                    <p class="text-gray">
                        Save the <i>landing page, UTMs, client's browser's time, day, and month, the number of orders, lifetime value, and average order</i>.
                        You can view this data when you open an order, or on the <a class="link" href="<?=admin_url("admin.php?page=pixelyoursite_edd_reports")?>">Easy Digital Downloads Reports</a> page
                    </p>
                </div>
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php PYS()->render_switcher_input( 'edd_enabled_display_data_to_orders' ); ?>
                        <h4 class="switcher-label"><?php _e('Display the tracking data on the order\'s page', 'pys');?></h4>
                    </div>
                    <p class="text-gray">
                        Show the <i>landing page, traffic source,</i> and <i>UTMs</i> on the order's edit page.
                    </p>
                </div>
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php PYS()->render_switcher_input( 'edd_enabled_save_data_to_user' ); ?>
                        <h4 class="switcher-label"><?php _e('Display data to the user\'s profile', 'pys');?></h4>
                    </div>
                    <p class="text-gray">
                        Display <i>the number of orders, lifetime value, and average order</i>.
                    </p>
                </div>
                <?php if(isEddRecurringActive()) :?>
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            <?php PYS()->render_switcher_input( 'edd_enabled_purchase_recurring' ); ?>
                            <h4 class="switcher-label"><?php _e('Renewal Tracking', 'pys');?></h4>
                        </div>
                        <p class="text-gray"><?php _e('Send the Purchase event to Facebook and Google using API for automatic renewals.', 'pys');?></p>
                    </div>
                <?php endif; ?>
                <hr>
                <h4 class="primary_heading"><?php _e('New customer parameter', 'pys');?></h4>
                <p>
                    <?php _e('The new_customer parameter is added to the purchase event for our Google native tags and for GTM. It\'s use by Google for new customer acquisition. We always send it with true or false values for logged-in users. We will use these options for guest checkout.', 'pys');?>
                </p>
                <div class="d-flex align-items-center">
                    <div class="radio-inputs-wrap">
                        <?php PYS()->render_radio_input( 'edd_purchase_new_customer_guest', 'yes', 'Send it for guest checkout' ); ?>
                        <?php PYS()->render_radio_input( 'edd_purchase_new_customer_guest', 'no', 'Don\'t send it for guest checkout' ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- video -->
    <?php
    $videos = array(
        array(
            'url'   => 'https://www.youtube.com/watch?v=-bN5D_HJyuA',
            'title' => 'Enhanced Conversions for Google Ads with PixelYourSite',
            'time'  => '9:14',
        ),
        array(
            'url'   => 'https://www.youtube.com/watch?v=v3TfmX5H1Ts',
            'title' => 'Track Facebook (META) Ads results with Google Analytics 4 (GA4) using UTMs',
            'time'  => '10:13',
        ),
    );

    renderRecommendedVideo( $videos );
    ?>

    <!--  Transaction ID -->
    <div class="card card-style5">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e( 'Transaction ID', 'pys' ); ?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-24">
                <div>
                    <div class="mb-8">
                        <label class="primary_heading">Prefix:</label>
                    </div>
                    <?php PYS()->render_text_input( "edd_order_id_prefix","Prefix", false, false, false, 'short' ); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Consider adding a prefix for transactions IDs if you use the same tags on multiple websites.', 'pys');?>
                </p>
            </div>
        </div>
    </div>
    <!-- AddToCart -->
    <div class="card card-style5">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e('When to fire the add to cart event', 'pys');?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-24">
                <div class="woo_add_to_cart_event gap-24">
                    <?php PYS()->render_checkbox_input( 'edd_add_to_cart_on_button_click', __('On Add To Cart button clicks', 'pys')); ?>
                    <?php PYS()->render_checkbox_input( 'edd_add_to_cart_on_checkout_page', __('On Checkout Page', 'pys')); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ID Settings -->
    <div class="card card-style5 woo-id-settings">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2">ID Settings</h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-22">

                <!-- Facebook for WooCommerce -->

                <?php if ( Facebook()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Facebook ID settings</h4>
                            </div>
                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">content_id</label>
                                    </div>
                                    <?php Facebook()->render_select_input( 'edd_content_id', array(
                                        'download_id' => 'Download ID',
                                        'download_sku' => 'Download SKU',
                                    ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">content_id prefix</label>
                                    </div>
                                    <?php Facebook()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">content_id suffix</label>
                                    </div>
                                    <?php Facebook()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( GATags()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Google Tags ID settings</h4>
                            </div>

                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid</label>
                                    </div>
                                    <?php GATags()->render_select_input( 'edd_content_id', array(
                                        'download_id' => 'Download ID',
                                        'download_sku'   => 'Download SKU',
                                    ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid prefix</label>
                                    </div>
                                    <?php GATags()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid suffix</label>
                                    </div>
                                    <?php GATags()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( Pinterest()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Pinterest Tag ID settings</h4>
                            </div>

                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID</label>
                                    </div>
                                    <?php Pinterest()->render_select_input( 'edd_content_id', array(
                                        'download_id' => 'Download ID',
                                        'download_sku'   => 'Download SKU',
                                    ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID prefix</label>
                                    </div>
                                    <?php Pinterest()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>

                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID suffix</label>
                                    </div>
                                    <?php Pinterest()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Pinterest Tag ID settings</h4>&nbsp;
                                <a class="link"
                                   href="https://www.pixelyoursite.com/pinterest-tag?utm_source=pys-free-plugin&utm_medium=pinterest-badge&utm_campaign=requiere-free-add-on"
                                   target="_blank">The paid add-on is required</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( Bing()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Bing Tag ID settings</h4>
                            </div>

                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID</label>
                                    </div>
                                    <?php Bing()->render_select_input( 'edd_content_id', array(
                                        'download_id' => 'Download ID',
                                        'download_sku'   => 'Download SKU',
                                    ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID prefix</label>
                                    </div>
                                    <?php Bing()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID suffix</label>
                                    </div>
                                    <?php Bing()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Bing Tag ID settings</h4>&nbsp;
                                <a class="link"
                                   href="https://www.pixelyoursite.com/bing-tag?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-bing"
                                   target="_blank">The paid add-on is required</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

	            <?php if ( Reddit()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Reddit Tag ID settings</h4>
                            </div>

				            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID</label>
                                    </div>
						            <?php Reddit()->render_select_input( 'edd_content_id', array(
							            'download_id' => 'Download ID',
							            'download_sku'   => 'Download SKU',
						            ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID prefix</label>
                                    </div>
						            <?php Reddit()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ID suffix</label>
                                    </div>
						            <?php Reddit()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>

                            </div>
                        </div>
                    </div>
	            <?php else: ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Reddit Tag ID settings</h4>&nbsp;
                                <a class="link"
                                   href="https://www.pixelyoursite.com/plugins/pixelyoursite-professional/reddit-wordpress-plugin"
                                   target="_blank">The paid add-on is required</a>
                            </div>
                        </div>
                    </div>
	            <?php endif; ?>

                <?php if ( GTM()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">GTM tag settings</h4>
                            </div>

                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="gap-24">
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid</label>
                                    </div>
                                    <?php GTM()->render_select_input( 'edd_content_id', array(
                                        'download_id' => 'Download ID',
                                        'download_sku'   => 'Download SKU',
                                    ) ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid prefix</label>
                                    </div>
                                    <?php GTM()->render_text_input( 'edd_content_id_prefix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                                <div>
                                    <div class="mb-8">
                                        <label class="primary_heading">ecomm_prodid suffix</label>
                                    </div>
                                    <?php GTM()->render_text_input( 'edd_content_id_suffix', '(optional)', false, false, false, 'short' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Google Dynamic Remarketing Vertical -->
                <?php if ( GA()->enabled() || Ads()->enabled() ) : ?>
                    <div class="card card-style6">
                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h4 class="secondary_heading_type2">Google Dynamic Remarketing Vertical</h4>
                            </div>

                            <?php cardCollapseSettings(); ?>
                        </div>

                        <div class="card-body">
                            <div class="radio-inputs-wrap">
                                <div class="d-flex">
                                    <?php PYS()->render_radio_input( 'google_retargeting_logic', 'ecomm', 'Use Retail Vertical  (select this if you have access to Google Merchant)' ); ?>
                                    <?php renderPopoverButton( 'google_dynamic_remarketing_vertical' ); ?>
                                </div>

                                <?php PYS()->render_radio_input( 'google_retargeting_logic', 'dynx', 'Use Custom Vertical (select this if Google Merchant is not available for your country)' ); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Event Value -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h4 class="secondary_heading_type2">Value Settings</h4>
                        </div>

                        <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <div class="radio-inputs-wrap mb-24">
                            <?php PYS()->render_radio_input( 'edd_event_value', 'price', 'Use EasyDigitalDownloads price settings' ); ?>
                            <?php PYS()->render_radio_input( 'edd_event_value', 'custom', 'Customize Tax' ); ?>
                        </div>

                        <div class="edd-event-value-option mb-24" style="display: none;">
                            <div class="woo-event-value-option-item">
                                <?php PYS()->render_select_input( 'edd_tax_option',
                                    array(
                                        'included' => 'Include Tax',
                                        'excluded' => 'Exclude Tax',
                                    )
                                ); ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="primary_heading"><?php _e('Lifetime Customer Value', 'pys');?></label>
                        </div>
                        <?php PYS()->render_multi_select_input( 'edd_ltv_order_statuses', edd_get_payment_statuses() ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style5 woo-recommended-events">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e('Recommended events', 'pys');?></h4>
            </div>
        </div>

        <div class="card-body" style="display: block;">
            <!-- Purchases -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track Purchases</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>
                <div class="card-body">
                    <div class="gap-24">
                        <div>
                            <?php PYS()->renderValueOptionsBlock('edd_purchase', false);?>
                        </div>
                        <div class="d-flex">
                            <?php PYS()->render_checkbox_input( 'edd_purchase_on_transaction', 'Fire the event only once for each order (disable when testing)' ); ?>
                            <?php renderPopoverButton( 'edd_purchase_on_transaction', 'top' ); ?>
                        </div>
                        <div>
                            <?php PYS()->render_checkbox_input( 'edd_purchase_not_fire_for_zero', "Don't fire the event for 0 value transactions" ); ?>
                        </div>
                        <div>
                            <?php PYS()->render_checkbox_input( 'edd_purchase_not_fire_for_zero_items', "Don't fire the event when the number of items is 0" ); ?>
                        </div>

                        <?php if ( Facebook()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the Purchase event on Facebook
                                    (required for DPA)</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the purchase event on Google
                                    Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the purchase event on Google
                                    Ads</h4>
                            </div>
                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_purchase' ); ?>

                            <div class="woo-conversion-track">
                                <div class="mb-12">
                                    <label class="primary_heading">EDD Purchase event, Google Ads lables:</label>
                                </div>

                                <div class="radio-inputs-wrap">
                                    <?php Ads()->render_radio_input( 'edd_purchase_conversion_track', 'conversion', 'Fire a conversion event along with the default Purchase event' ); ?>
                                    <?php Ads()->render_radio_input( 'edd_purchase_conversion_track', 'current_event', 'Add the conversion label to the Purchase event' ); ?>
                                </div>
                            </div>

                            <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                                <div class="line"></div>
                            <?php endif; ?>

                        <?php endif; ?>
                        <!-- Pinterest -->
                        <?php
                        if(Pinterest()->enabled()) : ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input('edd_checkout_enabled'); ?>
                                <h4 class="switcher-label secondary_heading">Enable the Checkout event on Pinterest</h4>
                            </div>
                        <?php endif; ?>
                        <!-- Bing -->
                        <?php
                        if(Bing()->enabled()) : ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input('edd_purchase_enabled'); ?>
                                <h4 class="switcher-label secondary_heading">Enable the Purchase event on Bing</h4>
                                <?php renderPopoverButton('woo_bing_enable_purchase'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ( Tiktok()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the PlaceAnOrder event on
                                    TikTok</h4>
                            </div>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_complete_payment_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the CompletePayment event on
                                    TikTok</h4>
                            </div>
                        <?php endif; ?>
                        <?php if(Reddit()->enabled()) : ?>
                        <div class="d-flex align-items-center">
		                    <?php Reddit()->render_switcher_input('edd_purchase_enabled'); ?>
                            <h4 class="switcher-label secondary_heading">Enable the Purchase event on Reddit</h4>
                        </div>
                        <?php endif; ?>
                        <?php if ( GTM()->enabled() ) : ?>
                            <div class="line"></div>
                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_purchase_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the purchase event on GTM dataLayer</h4>
                            </div>
                        <?php endif; ?>

                        <div class="line"></div>

                        <?php
                        $message = '*This event will be fired on the order-received, the default Easy Digital Downloads
                                    "thank you page". If you use PayPal, make sure that auto-return is ON. If you want to use "custom
                                    thank you pages", you must configure them with our <a href="https://www.pixelyoursite.com/super-pack"
                                                                    target="_blank" class="link">Super Pack</a>.';
                        renderWarningMessage( $message ); ?>
                    </div>
                </div>
            </div>
            <!-- InitiateCheckout -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track the Checkout Page</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>
                <div class="card-body">
                    <div class="gap-24">
                        <div>
                            <?php PYS()->renderValueOptionsBlock( 'edd_initiate_checkout', false ); ?>
                        </div>
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the InitiateCheckout event on Facebook</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( GA()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the begin_checkout event on Google Analytics</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( Ads()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the begin_checkout event on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_initiate_checkout' ); ?>

                            <div class="woo-conversion-track">
                                <div class="mb-12">
                                    <label class="primary_heading">EDD begin_checkout event, Google Ads lables:</label>
                                </div>

                                <div class="radio-inputs-wrap">
                                    <?php Ads()->render_radio_input( 'edd_initiate_checkout_conversion_track', 'conversion', 'Fire a conversion event along with the default begin_checkout event' ); ?>
                                    <?php Ads()->render_radio_input( 'edd_initiate_checkout_conversion_track', 'current_event', 'Add the conversion label to the begin_checkout event' ); ?>
                                </div>
                            </div>

                            <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                                <div class="line"></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the InitiateCheckout on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) : $configured = true;?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the InitiateCheckout on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) : $configured = true;?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the InitiateCheckout on TikTok</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( GTM()->enabled() ) : ?>
                            <div>
                                <?php if ( $configured ) : ?>
                                    <div class="line mb-24"></div>
                                <?php endif; ?>

                                <div class="d-flex align-items-center">
                                    <?php GTM()->render_switcher_input( 'edd_initiate_checkout_enabled' ); ?>
                                    <h4 class="switcher-label secondary_heading">Enable the begin_checkout event on GTM dataLayer</h4>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- AddToCart -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track add to cart</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>
                <div class="card-body">
                    <div class="gap-24">
                        <div>
                            <?php PYS()->renderValueOptionsBlock('edd_add_to_cart', false);?>
                        </div>

                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the AddToCart event on Facebook (required for DPA)</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the add_to_cart event on Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the add_to_cart event on Google Ads</h4>
                            </div>
                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_add_to_cart' ); ?>
                            <div class="woo-conversion-track">
                                <div class="mb-12">
                                    <label class="primary_heading">EDD add_to_cart event, Google Ads lables:</label>
                                </div>

                                <div class="radio-inputs-wrap">
                                    <?php Ads()->render_radio_input( 'edd_add_to_cart_conversion_track', 'conversion', 'Fire a conversion event along with the default add_to_cart event' ); ?>
                                    <?php Ads()->render_radio_input( 'edd_add_to_cart_conversion_track', 'current_event', 'Add the conversion label to the add_to_cart event' ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the AddToCart event on Pinterest</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( Bing()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the AddToCart event on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the AddToCart event on TikTok</h4>
                            </div>
                        <?php endif; ?>
	                    <?php if ( Reddit()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
			                    <?php Reddit()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the AddToCart event on Reddit</h4>
                            </div>
	                    <?php endif; ?>
                        <?php if ( GTM()->enabled() ) : ?>
                            <div>
                                <?php if ( $configured ) : ?>
                                    <div class="line mb-24"></div>
                                <?php endif; ?>
                                <div class="d-flex align-items-center">
                                    <?php GTM()->render_switcher_input( 'edd_add_to_cart_enabled' ); ?>
                                    <h4 class="switcher-label secondary_heading">Enable the add_to_cart event on GTM dataLayer</h4>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- ViewContent -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track product pages</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>
                <div class="card-body">
                    <div class="gap-24">
                        <div>
                            <?php PYS()->renderValueOptionsBlock('edd_view_content', false);?>
                        </div>
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewContent on Facebook (required for DPA)</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( GA()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the view_item event on Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the view_item event on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_view_content' ); ?>

                            <div class="woo-conversion-track">
                                <div class="mb-12">
                                    <label class="primary_heading">EDD view_item event, Google Ads lables:</label>
                                </div>

                                <div class="radio-inputs-wrap">
                                    <?php Ads()->render_radio_input( 'edd_view_content_conversion_track', 'conversion', 'Fire a conversion event along with the default view_item event' ); ?>
                                    <?php Ads()->render_radio_input( 'edd_view_content_conversion_track', 'current_event', 'Add the conversion label to the view_item event' ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_page_visit_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the PageVisit event on
                                    Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the PageVisit event on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewContent event on TikTok</h4>
                            </div>
                        <?php endif; ?>

	                    <?php if ( Reddit()->enabled() ) :
		                    $configured = true; ?>
                            <div class="d-flex align-items-center">
			                    <?php Reddit()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewContent event on Reddit</h4>
                            </div>
	                    <?php endif; ?>

                        <?php if ( GTM()->enabled() ) : ?>
                            <div>
                                <?php if ( $configured ) : ?>
                                    <div class="line mb-24"></div>
                                <?php endif; ?>

                                <div class="d-flex align-items-center">
                                    <?php GTM()->render_switcher_input( 'edd_view_content_enabled' ); ?>
                                    <h4 class="switcher-label secondary_heading">Enable the view_item event on GTM
                                        dataLayer</h4>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- ViewCategory -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track product category pages</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>

                <div class="card-body">
                    <div class="gap-24">
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewCategory event on Facebook Analytics (used for DPA)</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the view_item_list event on Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the view_item_list event on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'woo_view_category' ); ?>

                            <div class="woo-conversion-track">
                                <div class="mb-12">
                                    <label class="primary_heading">EDD view_item_list event, Google Ads lables:</label>
                                </div>

                                <div class="radio-inputs-wrap">
                                    <?php Ads()->render_radio_input( 'edd_view_category_conversion_track', 'conversion', 'Fire a conversion event along with the default view_item_list event' ); ?>
                                    <?php Ads()->render_radio_input( 'edd_view_category_conversion_track', 'current_event', 'Add the conversion label to the view_item_list event' ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewCategory event on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewCategory event on Bing</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( GTM()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_view_category_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the ViewCategory event on GTM dataLayer</h4>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Marketing Events -->
    <div class="card card-style5 woo-advanced-events">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php _e('Advanced Marketing Events', 'pys');?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <!-- FrequentShopper -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">FrequentShopper Event</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>

                <div class="card-body">
                    <div class="gap-24">
                        <?php if ( Facebook()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Facebook</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_frequent_shopper' ); ?>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on TikTok</h4>
                            </div>
                        <?php endif; ?>

	                    <?php if ( Reddit()->enabled() ) : ?>
                            <div class="d-flex align-items-center">
			                    <?php Reddit()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Reddit</h4>
                            </div>
	                    <?php endif; ?>

                        <?php if ( GTM()->enabled() ) : ?>
                            <?php if ( $configured ) : ?>
                                <div class="line"></div>
                            <?php endif;
                            $configured = true; ?>

                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_frequent_shopper_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to GTM dataLayer</h4>
                            </div>

                            <div class="line"></div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center">
                            <label class="primary_heading mr-16">Fire this event when the client has at least</label>
                            <?php PYS()->render_number_input( 'woo_frequent_shopper_transactions' ); ?>
                            <label class="ml-20">transactions</label>
                        </div>
                    </div>
                </div>
            </div>
            <!-- VipClient -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">VIPClient Event</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>

                <div class="card-body">
                    <div class="gap-24">
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Facebook</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_vip_client' ); ?>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on TikTok</h4>
                            </div>
                        <?php endif; ?>

	                    <?php if ( Reddit()->enabled() ) :
		                    $configured = true; ?>
                            <div class="d-flex align-items-center">
			                    <?php Reddit()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Reddit</h4>
                            </div>
	                    <?php endif; ?>

                        <?php if ( GTM()->enabled() ) : ?>
                            <?php if ( $configured ) : ?>
                                <div class="line"></div>
                            <?php endif;
                            $configured = true; ?>

                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_vip_client_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to GTM dataLayer</h4>
                            </div>

                            <div class="line"></div>
                        <?php endif; ?>

                        <?php if ( $configured ) : ?>
                            <div class="woo-adv-events-condition">
                                <label class="primary_heading">Fire this event when the client has at least</label>
                                <?php PYS()->render_number_input( 'edd_vip_client_transactions' ); ?>
                                <label class="primary_heading">transactions and average order is at least</label>
                                <?php PYS()->render_number_input( 'edd_vip_client_average_value' ); ?>
                            </div>
                        <?php else : ?>
                            <div class="critical_message">Error: No supported pixels are not configured</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- BigWhale -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">BigWhale Event</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>

                <div class="card-body">
                    <div class="gap-24">
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Facebook</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to Google Analytics</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Ads()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Ads()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Google Ads</h4>
                            </div>

                            <?php AdsHelpers\renderConversionLabelInputs( 'edd_big_whale' ); ?>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() || Bing()->enabled() || Tiktok()->enabled() ) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Bing()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Bing()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Bing</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( Tiktok()->enabled() ) :
                            $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Tiktok()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on TikTok</h4>
                            </div>
                        <?php endif; ?>

	                    <?php if ( Reddit()->enabled() ) :
		                    $configured = true; ?>
                            <div class="d-flex align-items-center">
			                    <?php Reddit()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable on Reddit</h4>
                            </div>
	                    <?php endif; ?>

                        <?php if ( GTM()->enabled() ) : ?>
                            <?php if ( $configured ) : ?>
                                <div class="line"></div>
                            <?php endif;
                            $configured = true; ?>

                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_big_whale_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Send the event to GTM dataLayer</h4>
                            </div>

                            <div class="line"></div>
                        <?php endif; ?>

                        <?php if ( $configured ) : ?>
                            <div class="woo-adv-events-condition">
                                <label class="primary_heading">Fire this event when the client has LTV at least</label>
                                <?php PYS()->render_number_input( 'edd_big_whale_ltv' ); ?>
                            </div>
                        <?php else : ?>
                            <div class="critical_message">Error: No supported pixels are not configured</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Extra events -->
    <div class="card card-style5 woo-extra-events">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2">Extra events</h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <!-- RemoveFromCart -->
            <div class="card card-style6">
                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                    <div class="disable-card d-flex align-items-center">
                        <?php PYS()->render_switcher_input( 'edd_remove_from_cart_enabled' ); ?>
                        <h4 class="secondary_heading_type2 switcher-label">Track remove from cart</h4>
                    </div>
                    <?php cardCollapseSettings(); ?>
                </div>
                <div class="card-body">
                    <div class="gap-24">
                        <?php $configured = false; ?>
                        <?php if ( Facebook()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Facebook()->render_switcher_input( 'edd_remove_from_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the RemoveFromCart event on Facebook</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GA()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GA()->render_switcher_input( 'edd_remove_from_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the remove_from_cart event on Google Analytics</h4>
                            </div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled()) : ?>
                            <div class="line"></div>
                        <?php endif; ?>
                        <?php if ( Pinterest()->enabled() ) : $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php Pinterest()->render_switcher_input( 'edd_remove_from_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the RemoveFromCart event on Pinterest</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( GTM()->enabled() ) :
                            if ( $configured ) : ?>
                                <div class="line"></div>
                            <?php endif; $configured = true; ?>
                            <div class="d-flex align-items-center">
                                <?php GTM()->render_switcher_input( 'edd_remove_from_cart_enabled' ); ?>
                                <h4 class="switcher-label secondary_heading">Enable the RemoveFromCart event on GTM dataLayer</h4>
                            </div>
                        <?php endif; ?>

                        <?php if ( !$configured ) : ?>
                            <div class="critical_message">Error: No supported pixels are not configured</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
        include PYS_PATH . '/includes/offline_events/view/html-export-ltv.php';
        render_export_ltv_section('edd');
    ?>

    <!-- EDD Parameters -->
    <div class="card card-style5 woo-params-block">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2">EDD Parameters</h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body woo-params-list">

            <!-- Control the EDD Parameters -->
            <div class="card about-params card-style3">
                <div class="card-header card-header-style2">
                    <div class="disable-card d-flex align-items-center">
                        <h4 class="secondary_heading_type2">Control the EDD Parameters</h4>
                    </div>
                </div>

                <div class="card-body" style="display: block">
                    <div class="gap-24">
                        <p>
                            You can use these parameters to create audiences, custom conversions, or goals. We recommend keeping them active. If you get privacy warnings about some of these parameters, you can turn them OFF.
                        </p>

                        <div class="woo-control-parameters">
                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_edd_category_name_param' ); ?>
                                <h4 class="switcher-label secondary_heading">category_name</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_edd_num_items_param' ); ?>
                                <h4 class="switcher-label secondary_heading">num_items</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_edd_tags_param' ); ?>
                                <h4 class="switcher-label secondary_heading">tags</h4>
                            </div>


                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_woo_total_param' ); ?>
                                <h4 class="switcher-label secondary_heading">total (PRO)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_woo_tax_param' ); ?>
                                <h4 class="switcher-label secondary_heading">tax (PRO)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_edd_coupon_param' ); ?>
                                <h4 class="switcher-label secondary_heading">coupon (PRO)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->render_switcher_input( 'enable_edd_payment_method' ); ?>
                                <h4 class="switcher-label secondary_heading">payment_method for GA4 (PRO)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->renderDummySwitcher( true ); ?>
                                <h4 class="switcher-label secondary_heading">content_ids (mandatory for DPA)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->renderDummySwitcher( true ); ?>
                                <h4 class="switcher-label secondary_heading">content_type (mandatory for DPA)</h4>
                            </div>

                            <div class="woo-control-parameter-item">
                                <?php PYS()->renderDummySwitcher( true ); ?>
                                <h4 class="switcher-label secondary_heading">value (mandatory for purchase, you have
                                    more options on event level)</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- About params -->
            <div class="card about-params card-style3">
                <div class="card-header card-header-style2">
                    <div class="d-flex align-items-center">
                        <i class="icon-Info"></i>
                        <h4 class="heading-with-icon bold-heading">About EDD Events Parameters</h4>
                    </div>
                </div>

                <div class="card-body" style="display: block;">
                    <p class="mb-24">All events get the following parameters for all the tags:
                        <span class="parameters-list">page_title, post_type, post_id, landing_page, event_URL, user_role, plugin, event_time (pro),
                            event_day (pro), event_month (pro), traffic_source (pro), UTMs (pro).</span>
                    </p>
                    <p>The Meta Pixel events are Dynamic Ads ready.</p>
                    <p>The Google Analytics events track Monetization data (GA4).</p>
                    <p>The Google Ads events have the required data for Dynamic Remarketing (<a class="link" href = "https://support.google.com/google-ads/answer/7305793" target="_blank">official help</a>). </p>
                    <p class="mb-24">The Pinterest events have the required data for Dynamic Remarketing.</p>

                    <p>The Purchase event will have the following extra-parameters:
                        <span class="parameters-list">category_name, num_items, tags, total (pro), transactions_count (pro), tax (pro),
                            predicted_ltv (pro), average_order (pro), coupon_used (pro), coupon_code (pro), shipping (pro),
                            shipping_cost (pro), fee (pro)</span>.
                </div>
            </div>
        </div>
    </div>
</div>


