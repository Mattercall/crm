<?php
namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$pluginName = "";
if( isWooCommerceActive() && PYS()->getOption('woo_enabled_save_data_to_user')) {
    $pluginName = "WooCommerce:";
    $totals = getWooCustomerTotals($user->ID);
} elseif (isEddActive() && PYS()->getOption('edd_enabled_save_data_to_user')) {
    $pluginName = "Easy Digital Downloads:";
    $totals = getEddCustomerTotals($user->ID);
}
if (empty($totals) || $totals['orders_count'] == 0) {
	$totals = array(
		'orders_count' => 'Guest order',
		'avg_order_value' => 'Guest order',
		'ltv' => 'Guest order',
	);
}
if($pluginName == "") {
    return;
}
?>
    <h3><?php _e('PixelYourSite Pro'); ?></h3>
    <div style="margin:20px 10px">You can see more data on the
        <a href='<?=admin_url("admin.php?page=pixelyoursite_woo_reports")?>' target='_blank'>WooCommerce Reports</a> page.
        You can stop showing this data from the plugin's <a href='<?=admin_url("admin.php?page=pixelyoursite&tab=woo")?>' target='_blank'>WooCommerce page</a>.
        Find out more about how WooCommerce reports work by watching this
        <a href="https://www.youtube.com/watch?v=4VpVf9llfkU" target='_blank'>video</a>.</div>
    <table class="form-table">

        <tr >
            <th><?=$pluginName?></th>
            <td></td>
        </tr>
        <tr >
            <th>Number of orders:</th>
            <td><?=$totals['orders_count'] ?></td>
        </tr>
        <tr >
            <th>Lifetime value:</th>
            <td><?=$totals['ltv'] ?></td>
        </tr>
        <tr >
            <th>Average order value:</th>
            <td><?=$totals['avg_order_value'] ?></td>
        </tr>

    </table>
<?php
