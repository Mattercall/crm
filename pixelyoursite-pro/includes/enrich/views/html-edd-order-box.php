<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
use function PixelYourSite\getEddCustomerTotals;
use function PixelYourSite\PYS;
$payment      = new \EDD_Payment( $payment_id );

if(!PYS()->getOption('edd_enabled_display_data_to_orders') || !isset($payment)) return;
include_once "function-helper.php";



$data = array();
$dataAnalytics = array();
if($payment->get_meta())
{
    $data = $payment->get_meta();
}
if(isset($data['user_info'])){
    $dataAnalytics = getEddCustomerTotals($data['user_info']['id']);
    if(empty($dataAnalytics) || $dataAnalytics['orders_count'] == 0) {
        $dataAnalytics = array(
            'orders_count' => '0',
            'avg_order_value' => '0',
            'ltv' => '0',
        );
    }
}

if($dataAnalytics && is_array($dataAnalytics) && is_array($data)) {
    $data = array_merge($data,$dataAnalytics);
}


if($data && is_array($data)) :
    if (isset($data['pys_enrich_data'])) :
        $meta = isset($data['pys_enrich_data']) ? $data['pys_enrich_data'] : array();
    endif;
?>
<style>
    table.pys_order_meta {
        width: 100%;text-align:left
    }
    table.pys_order_meta td.border span {
        border-top: 1px solid #f1f1f1;
        display: block;
    }
    table.pys_order_meta th,
    table.pys_order_meta td {
        padding:10px
    }
</style>

    <div class="inside">
            <table class="pys_order_meta">
                <tr>
                    <td colspan="2" ><strong>FIRST VISIT</strong></td>
                </tr>
                <tr>
                    <td colspan="2" class="border"><span></span></td>
                </tr>
                <tr >
                    <th>Landing Page:</th>
                    <?php
                    $landingPage = !empty($meta['pys_landing']) ? $meta['pys_landing'] : "No Landing Page";
                    if (filter_var($landingPage, FILTER_VALIDATE_URL)) {
                        echo '<td><a href="' . $landingPage . '" target="_blank">' . $landingPage . '</a></td>';
                    } else {
                        echo '<td>' . $landingPage . '</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <th>Traffic source:</th>
                    <td><?=!empty($meta['pys_source']) ? $meta['pys_source'] : "No Traffic source"?></td>
                </tr>
                <?php

                if(!empty($meta['pys_utm'])) {
                    $utms = explode("|",$meta['pys_utm']);
                    \PixelYourSite\Enrich\printUtm($utms);
                }

                ?>
                <tr>
                    <td colspan="2" class="border"><span></span></td>
                </tr>
                <tr>
                    <td colspan="2" ><strong>LAST VISIT</strong></td>
                </tr>
                <tr>
                    <td colspan="2" class="border"><span></span></td>
                </tr>
                <tr >
                    <th>Landing Page:</th>
                    <?php
                    $lastLanding = !empty($meta['last_pys_landing']) ? $meta['last_pys_landing'] : "No Landing Page";
                    if (filter_var($lastLanding, FILTER_VALIDATE_URL)) {
                        echo '<td><a href="' . $lastLanding . '" target="_blank">' . $lastLanding . '</a></td>';
                    } else {
                        echo '<td>' . $lastLanding . '</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <th>Traffic source:</th>
                    <td><?= !empty($meta['last_pys_source']) ? $meta['last_pys_source'] : "No Traffic source"?></td>
                </tr>
                <?php
                if(!empty($meta['last_pys_utm'])) {
                    $utms = explode("|",$meta['last_pys_utm']);
                    \PixelYourSite\Enrich\printUtm($utms);
                }

                ?>
                <tr>
                    <td colspan="2" class="border"><span></span></td>
                </tr>
                <?php
                if(!empty($meta['pys_browser_time'])) :
                $userTime = explode("|",$meta['pys_browser_time']);
                ?>
                <tr >
                    <th>Client's browser time</th>
                    <td></td>
                </tr>
                <tr >
                    <th>Hour:</th>
                    <td><?=$userTime[0]?></td>
                </tr>
                <tr >
                    <th>Day:</th>
                    <td><?=$userTime[1]?></td>
                </tr>
                <tr >
                    <th>Month:</th>
                    <td><?=$userTime[2]?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="2" class="border"<td><span></span></td>
                </tr>
                <tr >
                    <th>Number of orders:</th>
                    <td><?=!empty($data['orders_count']) ? $data['orders_count'] : ""?></td>
                </tr>
                <tr >
                    <th>Lifetime value:</th>
                    <td><?=!empty($data['ltv']) ? $data['ltv'] : ""?></td>
                </tr>
                <tr >
                    <th>Average order value:</th>
                    <td><?=!empty($data['avg_order_value']) ? $data['avg_order_value'] : ""?></td>
                </tr>

            </table>


    </div>
<?php else: ?>
<div class="inside">
    <h2>No data</h2>
</div>
<?php endif; ?>
