<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatLandingTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_landing";
    }

    function getColName()
    {
        return 'Landing page';
    }
}