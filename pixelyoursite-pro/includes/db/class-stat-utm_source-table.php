<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatUtmSourceTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_utm_source";
    }
    function getColName()
    {
        return 'UTM source';
    }
}