<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
include_once 'class-order-statistic.php';
include_once 'class-order-woo-statistic.php';
include_once 'class-order-edd-statistic.php';

class PysStatistic {

    private static $_instance;
    private $tables = [];
    public $wooStatistic;
    public $eddStatistic;

	public $delete_time = 30;
	public $delete_timeStart = "";
	public $delete_timeEnd = "";
    /**
     * @return PysStatistic
     */

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'adminEnqueueScripts' ) );
        add_action( 'init', array($this, 'init'), 1 );
    }

    public function init()
    {
        global $wpdb;

        $orderTable = new StatOrdersTable($wpdb);
        $productTable = new StatProductsTable($wpdb);
        $landingTable = new StatLandingTable($wpdb);
        $trafficTable = new StatTrafficTable($wpdb);
        $utmCampaing = new StatUtmCampaingTable($wpdb);
        $utmContent = new StatUtmContentTable($wpdb);
        $utmMedium = new StatUtmMediumTable($wpdb);
        $utmSource = new StatUtmSourceTable($wpdb);
        $utmTerm = new StatUtmTermTable($wpdb);

        // Security check: verify user capability and nonce before processing delete requests
        if((isset($_POST['delete_time']) && $_POST['delete_time'] != "") || (isset($_POST['delete_type']) && $_POST['delete_type'] != "")){
            if ( current_user_can( 'manage_pys' )
                && isset( $_POST['_wpnonce'] )
                && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pys_delete_statistic' )
            ) {
                $orderTable->deleteDataBasedOnTime($_POST);
                $productTable->deleteDataBasedOnTime($_POST);
            }
        }


        $this->wooStatistic = new WooOrderStatistic($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm);
        $this->eddStatistic = new EddOrderStatistic($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm);
        $this->tables = [$orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm];

        add_filter("pys_db_tables",function ($items) {
            return array_merge($items,$this->tables);
        });
    }


    function adminMenu() {
        if(isWooCommerceActive()) {
            add_submenu_page( 'pixelyoursite', 'WooCommerce Reports', 'WooCommerce Reports',
                'manage_pys', 'pixelyoursite_woo_reports', array( $this, 'wooReport' ),5 );
        }
        if(isEddActive()) {
            add_submenu_page( 'pixelyoursite', 'EDD Reports', 'EDD Reports',
                'manage_pys', 'pixelyoursite_edd_reports', array( $this, 'eddReport' ) ,6);
        }
    }

    function adminEnqueueScripts() {
        if(isset($_GET['page']) && ($_GET['page'] == 'pixelyoursite_woo_reports' || $_GET['page'] == 'pixelyoursite_edd_reports')) {



            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_style( 'pys_calendar', PYS_URL . '/dist/styles/calendar.css', array(  ), PYS_VERSION );
            wp_enqueue_script( 'pys_chart', PYS_URL . '/dist/scripts/chart.min.js' );
            wp_enqueue_script( 'chart_adapter', PYS_URL .'/dist/scripts/chartjs-adapter-date-fns.js',
                array('pys_chart'),
                PYS_VERSION
            );

            wp_register_script( 'js-cookie-admin', PYS_URL . '/dist/scripts/js.cookie-2.1.3.min.js', array( 'jquery',  'pys_chart',
                'bootstrap' ), '2.1.3' );

            wp_enqueue_script( 'js-cookie-admin' );
            wp_enqueue_script( 'pys_chart_js', PYS_URL . '/dist/scripts/admin_stat.js', array( 'jquery',  'pys_chart',
                'bootstrap' ), PYS_VERSION );

        }
    }

    public function wooReport() {
        $user_id = get_current_user_id();
        include __DIR__.'/../../includes/views/html-report-woo.php';
    }

    public function eddReport() {
        include __DIR__.'/../../includes/views/html-report-edd.php';
    }
    function globalWooReport() {
        ob_clean();

        $stat = $this->wooStatistic;
        $base_url = home_url();
        $base_url_without_http = str_replace(array('http://', 'https://'), '', $base_url);
        if($_REQUEST["export_type"] == 'all'){
            $name = $base_url_without_http."-chatgptfile-woo-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename='.$name );
            $stat->exportGPT(
                $_REQUEST["label"],
                $_REQUEST["start_date"],
                $_REQUEST["end_date"],
                $_REQUEST["filter_type"],
                $_REQUEST["model"],
                $_REQUEST["cog"]
            );
        }
        elseif($_REQUEST["export_type"] == 'current'){
            $name = $base_url_without_http."-export-current-data-woo-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename='.$name );
            $stat->exportFullCurrent(
                $_REQUEST["label"],
                $_REQUEST["start_date"],
                $_REQUEST["end_date"],
                $_REQUEST["filter_type"],
                $_REQUEST["model"],
                $_REQUEST["cog"]
            );
        }

    }

    function singleWooReport() {
        ob_clean();
        $name = "export-".$_REQUEST["filter_type"]."-".$_REQUEST["filter_id"]."-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename='.$name );
        $stat = $this->wooStatistic;
        $stat->exportSingle(
            $_REQUEST["start_date"],
            $_REQUEST["end_date"],
            $_REQUEST["filter_id"],
            $_REQUEST['single_table_type'],
            $_REQUEST["filter_type"],
            $_REQUEST["model"],
            $_REQUEST["cog"]
        );
    }

    function globalEddReport() {
        ob_clean();

        $stat = $this->eddStatistic;
        $base_url = home_url();
        $base_url_without_http = str_replace(array('http://', 'https://'), '', $base_url);
        if($_REQUEST["export_type"] == 'all'){
            $name = $base_url_without_http."-chatgptfile-edd-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename='.$name );
            $stat->exportGPT(
                $_REQUEST["label"],
                $_REQUEST["start_date"],
                $_REQUEST["end_date"],
                $_REQUEST["filter_type"],
                $_REQUEST["model"],
                $_REQUEST["cog"]
            );
        }
        elseif($_REQUEST["export_type"] == 'current'){
            $name = $base_url_without_http."-export-current-data-edd-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename='.$name );
            $stat->exportFullCurrent(
                $_REQUEST["label"],
                $_REQUEST["start_date"],
                $_REQUEST["end_date"],
                $_REQUEST["filter_type"],
                $_REQUEST["model"],
                $_REQUEST["cog"]
            );
        }
    }

    function singleEddReport() {
        ob_clean();
        $name = "export-".$_REQUEST["filter_type"]."-".$_REQUEST["filter_id"]."-".$_REQUEST["start_date"]."_to_".$_REQUEST["end_date"].".csv";
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename='.$name );
        $stat = $this->eddStatistic;

        $stat->exportSingle(
            $_REQUEST["start_date"],
            $_REQUEST["end_date"],
            $_REQUEST["filter_id"],
            $_REQUEST['single_table_type'],
            $_REQUEST["filter_type"],
            $_REQUEST["model"],
            $_REQUEST["cog"]
        );
    }
}


/**
 * @return PysStatistic
 */
function PysStatistic() {
    return PysStatistic::instance();
}

PysStatistic();