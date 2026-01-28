<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatTrafficTable extends StatValueTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_traffic";
    }
    function getColName()
    {
        return 'Traffic source';
    }
}