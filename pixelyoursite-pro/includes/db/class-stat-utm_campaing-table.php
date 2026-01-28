<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatUtmCampaingTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_utm_campaing";
    }
    function getColName()
    {
        return 'UTM campaign';
    }
}