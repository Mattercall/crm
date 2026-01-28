<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatUtmTermTable extends StatValueTable {

    function getName() {
        return $this->wpdb->prefix . "pys_stat_utm_term";
    }
    function getColName()
    {
        return 'UTM term';
    }
}