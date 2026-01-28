<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 logs-wrapper">
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('General', 'pys');?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php Facebook()->render_switcher_input( 'remove_metadata' ); ?>
                        <h4 class="switcher-label secondary_heading">autoConfig: false</h4>
                    </div>
                    <p class="text-gray"><?php _e('Remove Facebook default events', 'pys');?> </p>
                </div>

                <div>
                    <div class="d-flex align-items-center">
                        <?php Facebook()->render_switcher_input( 'disable_noscript' ); ?>
                        <h4 class="switcher-label secondary_heading"> <?php _e('Disable noscript', 'pys');?> </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Medical Content', 'pys');?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div>
                    <div class="d-flex align-items-center mb-4">
                        <?php Facebook()->render_switcher_input( 'enabled_medical' ); ?>
                        <h4 class="switcher-label"><?php _e('Don\'t track parameters', 'pys');?></h4>
                    </div>
                </div>

                <div>
                    <p class="mb-8">
                        Meta imposes restrictions on tracking data for websites with medical-related content and products. Use this option to disable event parameters that might track such data. These settings apply to Meta Pixel and CAPI events. To disable parameters for all tags, use the default parameter controls.
                    </p>
                    <p>
                        If you need to replace the standard WooCommerce AddToCart or Purhcase events with custom events, you can do it on the <a class="link" href="<?= buildAdminUrl('pixelyoursite', 'events') ?>">Events Page</a>.
                    </p>
                </div>

                <div>
                    <h4 class="primary_heading mb-4"><?php _e('Don\'t track these parameters for the Meta pixel and CAPI events:', 'pys');?></h4>
                    <?php Facebook()->render_multi_select_input('do_not_track_medical_param', getAllMetaEventParamName()); ?>
                    <p class="text-gray mt-8"><?php _e('If you want to disable parameters for all tags, use the default options from the plugin\'s main page, or from the WooCommerce and EDD pages.', 'pys');?></p>
                </div>
            </div>
        </div>
    </div>
</div>