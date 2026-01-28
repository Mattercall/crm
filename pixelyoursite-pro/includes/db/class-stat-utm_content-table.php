<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatUtmContentTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_utm_content";
    }
    function getColName()
    {
        return 'UTM content';
    }
}