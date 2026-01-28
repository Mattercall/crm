<?php
namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class OfflineEventsDb {

    /**
     * @param int $page
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return array|object|null
     */
    private static function getPostIds($postType,$page,$exportType,$start,$end,$orderStatus) {
        global $wpdb;
        $startPage = ($page -1) * 100;
        $startDate = $start->format("Y-m-d H:i:s");
        $endDate = $end->format("Y-m-d H:i:s");
        $statusMask = implode(', ', array_fill(0, count($orderStatus), '%s'));
        $args = [$postType];


        if($exportType == "export_all") {
            $args = array_merge($args,$orderStatus);
            $args[] = $startPage;
            $query = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = %s 
                        AND post_status IN($statusMask)
                        LIMIT %d, 100",
                $args
            );
        } else {
            $args[] = $startDate;
            $args[] = $endDate;
            $args = array_merge($args,$orderStatus);
            $args[] = $startPage;
            $query = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = %s 
                       AND post_date >= %s 
                       AND post_date <= %s 
                       AND post_status IN($statusMask)
                       LIMIT %d, 100",
                $args
            );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * @param int $page
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return array|object|null
     */
    private static function getOrderIdsFromHp($page,$exportType,$start,$end,$orderStatus) {
        global $wpdb;
        $table = $wpdb->prefix."wc_orders";
        $startPage = ($page -1) * 100;
        $startDate = $start->format("Y-m-d H:i:s");
        $endDate = $end->format("Y-m-d H:i:s");
        $statusMask = implode(', ', array_fill(0, count($orderStatus), '%s'));



        if($exportType == "export_all") {
            $args = [$startPage];
            $args = array_merge($orderStatus,$args);

            $query = $wpdb->prepare(
                "SELECT ID FROM $table WHERE  status IN($statusMask)  LIMIT %d, 100",
                $args
            );
        } else {
            $args = [$startDate,$endDate];
            $args = array_merge($args,$orderStatus);
            $args[] = $startPage;
            $query = $wpdb->prepare(
                "SELECT ID FROM $table WHERE 
                        date_created_gmt >= %s 
                       AND date_created_gmt <= %s 
                       AND status IN($statusMask)
                       LIMIT %d, 100",
                $args
            );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * @param int $page
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return array|object|null
     */
    static function getOrderIds($page,$exportType,$start,$end,$orderStatus) {
        if(isWooUseHPStorage()) {
            return OfflineEventsDb::getOrderIdsFromHp($page,$exportType,$start,$end,$orderStatus);
        } else {
            return OfflineEventsDb::getPostIds("shop_order",$page,$exportType,$start,$end,$orderStatus);
        }
    }

    /**
     * @param String $postType
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return int
     */
    private static function getPostCount($postType,$exportType,$start,$end,$orderStatus) {
        global $wpdb;
        $startDate = $start->format("Y-m-d H:i:s");
        $endDate = $end->format("Y-m-d H:i:s");
        $statusMask = implode(', ', array_fill(0, count($orderStatus), '%s'));

        $args = [$postType];
        if($exportType == "export_all") {
            $args = array_merge($args,$orderStatus);
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM    $wpdb->posts WHERE   post_type = %s AND post_status IN($statusMask)",
                $args
            );
        } else {
            $args[] = $startDate;
            $args[] = $endDate;
            $args = array_merge($args,$orderStatus);
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM    $wpdb->posts  WHERE   post_type = %s 
                                 AND post_date >= %s 
                                 AND post_date <= %s
                                 AND post_status IN($statusMask)",
                $args
            );
        }

        return $wpdb->get_var( $query );
    }

    /**
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return int
     */
    private static function getOrderCountFromHP($exportType,$start,$end,$orderStatus) {
        global $wpdb;
        $table = $wpdb->prefix."wc_orders";
        $startDate = $start->format("Y-m-d H:i:s");
        $endDate = $end->format("Y-m-d H:i:s");
        $statusMask = implode(', ', array_fill(0, count($orderStatus), '%s'));


        if($exportType == "export_all") {

            $query = $wpdb->prepare("SELECT COUNT(*) FROM    $table WHERE   status IN($statusMask)",$orderStatus);

        } else {
            $args[] = $startDate;
            $args[] = $endDate;
            $args = array_merge($args,$orderStatus);
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM  $table  WHERE date_created_gmt >= %s 
                                 AND date_created_gmt <= %s
                                 AND status IN($statusMask)",
                $args
            );
        }
        return $wpdb->get_var( $query );
    }


    /**
     * @param String $exportType
     * @param \DateTime $start
     * @param \DateTime $end
     * @return int
     */
    static function getOrderCount($exportType,$start,$end,$orderStatus) {
        if(isWooUseHPStorage()) {
            return OfflineEventsDb::getOrderCountFromHP($exportType,$start,$end,$orderStatus);
        } else {
            return OfflineEventsDb::getPostCount("shop_order",$exportType, $start, $end,$orderStatus);
        }
    }
}