<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<div class="card card-style6 card-static deleting_form">
    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
        <h4 class="secondary_heading_type2">
            <?php _e( 'Clearing statistics', 'pys' ); ?>
        </h4>
    </div>
    <div class="card-body">
        <form method="post" id="dataDeletionForm">
            <?php wp_nonce_field( 'pys_delete_statistic' ); ?>
            <h4 class="primary_heading mb-4">Select period for data deletion:</h4>

            <div class="select_stat">
                <div class="select-standard-wrap">
                    <select name="delete_time" class="select-standard  pys_stat_delete_time">
                        <option value="all" <?=selected($this->delete_time,"all")?>>All</option>
                        <option value="yesterday" <?=selected($this->delete_time,"yesterday")?>>Yesterday</option>
                        <option value="today" <?=selected($this->delete_time,"today")?>>Today</option>
                        <option value="7" <?=selected($this->delete_time,"7")?>>Last 7 days</option>
                        <option value="30" <?=selected($this->delete_time,"30")?>>Last 30 days</option>
                        <option value="current_month" <?=selected($this->delete_time,"current_month")?>>Current month</option>
                        <option value="last_month" <?=selected($this->delete_time,"last_month")?>>Last month</option>
                        <option value="year_to_date" <?=selected($this->delete_time,"year_to_date")?>>Year to date</option>
                        <option value="last_year" <?=selected($this->delete_time,"last_year")?>>Last year</option>
                        <option value="custom" <?=selected($this->delete_time,"custom")?>>Custom dates</option>
                    </select>
                </div>
                <div class="pys_stat_delete_time_custom" style="display: none;" >
                    <input type="text" name="delete_time_start" class="datepicker datepicker_start input-standard" placeholder="From">
                    <input type="text" name="delete_time_end" class="datepicker datepicker_end input-standard" placeholder="To">
                </div>
                <input type="hidden" name="type" value="<?= $type;?>">
                <button type="submit" value="delete_statistic" class="btn btn-block btn-sm btn-danger btn-delete-stat"><?= __('Delete the data', 'pixelyoursite');?></button>
            </div>
        </form>
    </div>
</div>


