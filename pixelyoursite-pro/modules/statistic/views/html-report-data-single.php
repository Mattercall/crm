<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @var String $activeFilter
 * @var String $filterId
 * @var String $visitModel
 * @var String $time
 * @var String $timeStart
 * @var String $timeEnd
 * @var String $title
 * @var String $perPage
 * @var String $type // edd or woo
 */
?>

<div class="stat_data gap-24">
    <div class="loading text-center">
        <span class="spinner is-active"></span>
    </div>
    <div class="single_data gap-24" data-filter="<?=$activeFilter?>" data-model="<?=$visitModel?>" data-filter_id="<?=$filterId?>" data-type="<?=$type?>">
        <div class="col text-center infoBlock">
            <div class="img-tools"><img class="cog_dont_install" alt="tools" src="<?php echo PYS_URL; ?>/dist/images/tools.svg"></div>
            <h3 class="text-center">Install the WooCommerce Cost of Goods plugin and get access to the Cost & Profit reports.</h3>
            <a class="orange_button" href="https://www.pixelyoursite.com/plugins/woocommerce-cost-of-goods?utm_source=plugin&utm_medium=plugin&utm_campaign=cost-profit-reports&utm_content=cost-profit-reports&utm_term=cost-profit-reports" target="_blank">Get the plugin</a>
        </div>
        <div class="total-block">
            <ul class="total">

            </ul>
        </div>
        <div class="d-flex align-items-center justify-content-between">
            <div class="select_stat">
                <div class="select-standard-wrap">
                    <select class="select-standard pys_stat_time">
                        <option value="yesterday" <?=selected($time,"yesterday")?>>Yesterday</option>
                        <option value="today" <?=selected($time,"today")?>>Today</option>
                        <option value="7" <?=selected($time,"7")?>>Last 7 days</option>
                        <option value="30" <?=selected($time,"30")?>>Last 30 days</option>
                        <option value="current_month" <?=selected($time,"current_month")?>>Current month</option>
                        <option value="last_month" <?=selected($time,"last_month")?>>Last month</option>
                        <option value="year_to_date" <?=selected($time,"year_to_date")?>>Year to date</option>
                        <option value="last_year" <?=selected($time,"last_year")?>>Last year</option>
                        <option value="custom" <?=selected($time,"custom")?>>Custom dates</option>
                    </select>
                </div>
                <div class="pys_stat_time_custom">
                    <?php
                    $calendarDateStart = $timeStart ? date_format(date_create($timeStart),'m/d/Y') : $timeStart;
                    $calendarDateEnd = $timeEnd ? date_format(date_create($timeEnd),'m/d/Y') : $timeEnd;
                    ?>
                    <input type="text" class="datepicker datepicker_start input-standard" placeholder="From" value="<?=$calendarDateStart?>"/>
                    <input type="text" class="datepicker datepicker_end input-standard" placeholder="To" value="<?=$calendarDateEnd?>"/>
                    <button class="btn btn-primary load">Load</button>
                </div>
            </div>
            <div class="single_back_flex">
                <span class="single_filter"><?=$title?></span>
                <button class="btn btn-primary single_back">< Back</button>
            </div>
        </div>


        <canvas id="pys_stat_single_graphics" width="400" height="100"></canvas>
        <div class="total-block">
            <ul class="total">

            </ul>
        </div>
        <div class="d-flex align-items-center justify-content-between ">
            <div class="per_page">
                <div class="select-standard-wrap">
                    <select class="select-standard  per_page_selector">
                        <option value="10" <?=selected($perPage,10)?>>10</option>
                        <option value="25" <?=selected($perPage,25)?>>25</option>
                        <option value="50" <?=selected($perPage,50)?>>50</option>
                        <option value="75" <?=selected($perPage,75)?>>75</option>
                        <option value="100" <?=selected($perPage,100)?>>100</option>
                    </select>
                </div>
                <div class="reload_table"></div>
            </div>
            <form class="report_form"  method="post" enctype="multipart/form-data">
                <input type="hidden" name="cog"/>
                <input type="hidden" name="start_date"/>
                <input type="hidden" name="end_date"/>
                <input type="hidden" name="filter_id"/>
                <input type="hidden" name="type"/>
                <input type="hidden" name="model"/>
                <input type="hidden" name="single_table_type"/>
                <input type="hidden" name="filter_type"/>
                <input type="hidden" name="export_csw" value="<?=$type?>_single_report"/>
                <button class="btn btn-primary report" >Download</button>
            </form>
        </div>

        <div class="line"></div>
        <div class="btn-group order_button" role="group" aria-label="Basic example">
            <button type="button" class="btn btn-primary" data-slug="dates">Date</button>
            <button type="button" class="btn btn-secondary" data-slug="orders">Order ID</button>
            <button type="button" class="btn btn-secondary" data-slug="products">Products</button>
        </div>

        <table class="pys_stat_single_info table">
        </table>
        <ul class="pys_stat_single_info_pagination pagination"></ul>
    </div>
</div>
