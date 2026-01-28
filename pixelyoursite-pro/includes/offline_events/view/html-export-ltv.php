<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
function render_export_ltv_section($context = 'woo') {
    if ($context === 'edd') {
        $export_title = 'EDD Custom Audience Export file';
    } else {
        $export_title = 'WooCommerce Custom Audience Export file';
    }

    // Your HTML output here
    ?>
    <div class="card card-style5 customer_export_card woo-export">
        <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php echo $export_title;?></h4>
            </div>

            <?php cardCollapseSettings(); ?>
        </div>

        <div class="card-body">
            <div class="gap-24">
                <div class="woo-export-actions">
                    <p>
                        Export a customer file with lifetime value. Use it to create a
                        Custom Audience and a Value-Based Lookalike Audience. More details <a class="link" href="https://www.pixelyoursite.com/value-based-facebook-lookalike-audiences?utm_source=pro&utm_medium=plugin&utm_campaign=right-column-pro"
                                                                                              target="_blank">here</a>.
                    </p>
                </div>
                <div class="woo-export-actions-buttons">
                    <button type="submit" name="pys[export_custom_audiences]" value="<?php echo $context;?>" class="btn btn-primary btn-primary-type2"><?php _e( 'Export clients LTV file', 'pys' ); ?></button>
                </div>
            </div>

        </div>
    </div>
    <?php
}

?>


