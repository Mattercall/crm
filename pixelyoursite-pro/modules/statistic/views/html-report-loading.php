<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
/**
 * @var String $type // edd or woo
 */

$statistic = $type == "woo" ? PysStatistic()->wooStatistic : PysStatistic()->eddStatistic;
$lastPage = $statistic->getSyncPage();
$selected = $statistic::getSelectedOrderStatus();
$countPages = ceil($statistic->getOrdersCount($selected)/$statistic->perPage);

?>
<div class="gap-24 align-items-center">
        <div class="text-center h2">Preparing your new settings, don't close this page.</div>
        <span class="spinner is-active" style="float:none; vertical-align: bottom;"></span>
        <div class="progress stat_progress" data-page="<?=$lastPage?>" data-max_page="<?=$countPages?>" data-type="<?=$type?>">
            <div class="progress-bar" role="progressbar" style="width: 0;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>
</div>
