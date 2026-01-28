<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @var String $visitModel
 * @var String $time
 * @var String $timeStart
 * @var String $timeEnd
 * @var String $perPage
 * @var String $type // edd or woo
 */
?>

<div class="stat_data gap-24">
    <div class="loading text-center">
        <span class="spinner is-active"></span>
    </div>
    <div class="global_data gap-24" data-type="<?=$type?>">
        <div class="col text-center infoBlock">
            <div class="img-tools"><img class="cog_dont_install" alt="tools" src="<?php echo PYS_URL; ?>/dist/images/tools.svg"></div>
            <h3 class="text-center">Install the WooCommerce Cost of Goods plugin and get access to the Cost & Profit reports.</h3>
            <a class="orange_button" href="https://www.pixelyoursite.com/plugins/woocommerce-cost-of-goods?utm_source=plugin&utm_medium=plugin&utm_campaign=cost-profit-reports&utm_content=cost-profit-reports&utm_term=cost-profit-reports" target="_blank">Get the plugin</a>
        </div>
        <div class="total-block">
            <ul class="total">

            </ul>
        </div>
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

            <div class="pys_stat_time_custom" style="display: <?=$timeStart?"flex":"none"?>">
                <?php
                $calendarDateStart = $timeStart ? date_format(date_create($timeStart),'m/d/Y') : $timeStart;
                $calendarDateEnd = $timeEnd ? date_format(date_create($timeEnd),'m/d/Y') : $timeEnd;
                ?>
                <input type="text" class="datepicker datepicker_start input-standard" placeholder="From" value="<?=$calendarDateStart?>"/>
                <input type="text" class="datepicker datepicker_end input-standard" placeholder="To" value="<?=$calendarDateEnd?>"/>
                <button class="btn btn-primary load">Load</button>
                <span class="vertical-line"></span>
            </div>

            <div class="select-standard-wrap">
                <select class="select-standard pys_visit_model" id="select_visit_model">
                    <option value="first_visit" <?=selected("first_visit",$visitModel)?>>First Visit</option>
                    <option value="last_visit" <?=selected("last_visit",$visitModel)?>>Last Visit</option>
                </select>
            </div>
        </div>

        <canvas id="pys_stat_graphics" width="400" height="100"></canvas>
        <div class="total-block">
            <ul class="total">

            </ul>
        </div>
        <div class="d-flex align-items-center justify-content-between ">
            <div class="per_page">
                <div class="select-standard-wrap">
                    <select class="select-standard per_page_selector">
                        <option value="5" <?=selected($perPage,5)?>>5</option>
                        <option value="25" <?=selected($perPage,25)?>>25</option>
                        <option value="50" <?=selected($perPage,50)?>>50</option>
                        <option value="75" <?=selected($perPage,75)?>>75</option>
                        <option value="100" <?=selected($perPage,100)?>>100</option>
                    </select>
                </div>
                <div class="reload_table"></div>
            </div>
            <div class="export-current-data-csv export-data-csv">
                <button class="btn btn-primary report">Export the current data set</button>
            </div>
        </div>
        <div class="line"></div>
        <div class="d-flex align-items-center justify-content-between report_form_block">
            <form class="report_form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="cog"/>
                <input type="hidden" name="label"/>
                <input type="hidden" name="model"/>
                <input type="hidden" name="start_date"/>
                <input type="hidden" name="end_date"/>
                <input type="hidden" name="type"/>
                <input type="hidden" name="filter_type"/>
                <input type="hidden" name="export_type"/>
                <input type="hidden" name="export_csw" value="<?=$type?>_report"/>


                <div class="export-all-data-csv export-data-csv">
                    <div class="change-data-export">
                        <h4 class="primary_heading">Select additional fields to be exported</h4>
                        <div class="change-data-block">
                            <div class="small-checkbox">
                                <input type="checkbox" id="fn" name="change-data[]"
                                       value="fn"
                                       class="small-control-input">
                                <label class="small-control small-checkbox-label" for="fn">
                                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                                    <span class="small-control-description">Fn</span>
                                </label>
                            </div>
                            <div class="small-checkbox">
                                <input type="checkbox" id="ln" name="change-data[]"
                                       value="ln"
                                       class="small-control-input">
                                <label class="small-control small-checkbox-label" for="ln">
                                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                                    <span class="small-control-description">Ln</span>
                                </label>
                            </div>
                            <div class="small-checkbox">
                                <input type="checkbox" id="email" name="change-data[]"
                                       value="email"
                                       class="small-control-input">
                                <label class="small-control small-checkbox-label" for="email">
                                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                                    <span class="small-control-description">Email</span>
                                </label>
                            </div>
                            <div class="small-checkbox">
                                <input type="checkbox" id="phone" name="change-data[]"
                                       value="phone"
                                       class="small-control-input">
                                <label class="small-control small-checkbox-label" for="phone">
                                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                                    <span class="small-control-description">Phone</span>
                                </label>
                            </div>
                        </div>

                        <div class="change_visit_model">
                            <div class="select-standard-wrap">
                                <select class="select-standard pys_visit_model" name="gpt_visit_model">
                                    <option value="first_visit" selected>First Visit</option>
                                    <option value="last_visit">Last Visit</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray">Learn how to use it: <a href="https://www.pixelyoursite.com/pixelyoursite-and-chatgpt?utm_source=pys_pro&utm_medium=pro_plugin_chatgpt_help_link" target="_blank">watch video here</a></p>
                    <button class="btn btn-primary all_report">Export ChatGPT file</button>
                </div>
            </form>
        </div>



        <table class="pys_stat_info table " data-filter_type="">
            <thead>
            <tr>
                <th class="title"></th>
                <th class="num_sale sortable"   data-order="order"  data-sort="desc">
                    Orders:<i class="fa fa-sort"></i>
                </th>
                <th class="sortable"            data-order="gross_sale" data-sort="desc">
                    Gross sales:<i class="fa fa-sort"></i>
                </th>
                <th class="active sortable"     data-order="net_sale"  data-sort="desc">
                    Net sales:<i class="fa fa-sort-desc"></i>
                </th>
                <th class="sortable"            data-order="total_sale"  data-sort="desc">
                    Total sales:<i class="fa fa-sort"></i>
                </th>
            </tr>
            </thead>
            <tbody>

            </tbody>
        </table>
        <ul class="pagination">

        </ul>
    </div>
</div>
