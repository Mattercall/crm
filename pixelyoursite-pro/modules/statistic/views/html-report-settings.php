<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
/**
 * @var String $type // edd or woo
 */
$statistic = $type == "woo" ? PysStatistic()->wooStatistic : PysStatistic()->eddStatistic;
$allStatus = $type == "woo" ? wc_get_order_statuses() : edd_get_payment_statuses();
?>

<div class="card card-style6 card-static">
    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
        <h4 class="secondary_heading_type2">
            <?php _e( 'Settings', 'pys' ); ?>
        </h4>
    </div>
    <div class="card-body">
        <h4 class="primary_heading mb-4">Active orders status:</h4>
        <div class="select_stat">
            <select class="pys-pysselect2"
                    data-placeholder="Select Order status"
                    id="<?=$type?>_stat_order_statuses"  style="width: 100%;"
                    multiple>

                <?php
                $selected = $statistic::getSelectedOrderStatus();
                foreach ( $allStatus as $option_key => $option_value ) : ?>
                    <option value="<?php echo esc_attr( $option_key ); ?>"
                        <?php selected( in_array( $option_key, $selected ) ); ?>
                    >
                        <?php echo esc_attr( $option_value ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-block btn-sm btn-save btn-save-<?=$type?>-stat orange_button">Save Settings</button>
        </div>

    </div>
</div>
