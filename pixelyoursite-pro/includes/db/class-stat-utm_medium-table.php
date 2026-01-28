<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatUtmMediumTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_utm_medium";
    }
    function getColName()
    {
        return 'UTM medium';
    }
}