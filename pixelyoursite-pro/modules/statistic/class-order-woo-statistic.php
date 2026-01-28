<?php
namespace PixelYourSite;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WooOrderStatistic  extends OrderStatistics {
    static $db_sync_status = 'pys_sync_statistic_db';
    static $stat_order_imported_page = 'pys_woo_stat_order_imported_page';
    static $stat_order_statuses = 'pys_woo_stat_order_statuses';

    static function getSelectedOrderStatus() {
        return get_option(WooOrderStatistic::$stat_order_statuses,["wc-completed"]);
    }

    public function __construct($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm) {
        parent::__construct($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm);
        add_action("wp_ajax_pys_woo_stat_sync",[$this,"runSyncs"]);
        add_action("wp_ajax_pys_woo_stat_change_orders_status",[$this,"changeOrderStatus"]);

        // Load Stat Data
        add_action("wp_ajax_pys_woo_stat_data",[$this,"loadStatData"]);
        add_action("wp_ajax_pys_woo_stat_single_data",[$this,"loadStatSingleData"]);

        // woo
        if(PYS()->getOption("woo_enabled_save_data_to_orders")) {
            add_action("woocommerce_payment_complete", [$this, "addNewOrderAfterPayment"]);
            add_action("woocommerce_order_status_changed", [$this, "addNewOrderAfterChangeStatus"], 10, 4);
            add_action("woocommerce_order_refunded", [$this, "updateWooOrderSale"], 10, 2);
        }
    }


    /**
     * @param $statuses
     * @return int
     */
    function getOrdersCount($statuses) {
        global $wpdb;

        $sql = [];

        if(isWooUseHPStorage()) {
            foreach($statuses as $status){
                $sql[] = "status ='".$status."'";
            }
            $sql = "SELECT count(ID)  FROM {$wpdb->prefix}wc_orders WHERE ".implode(" OR ", $sql)." ";
        } else {
            foreach($statuses as $status){
                $sql[] = "post_status ='".$status."'";
            }
            $sql = "SELECT count(ID)  FROM {$wpdb->prefix}posts WHERE ".implode(" OR ", $sql)." AND `post_type` = 'shop_order'";
        }

        return $wpdb->get_var($sql);
    }


    function setImportPage($page) {
        update_option(WooOrderStatistic::$stat_order_imported_page, $page);
    }

    function setImportStatus($status) {
        update_option(WooOrderStatistic::$db_sync_status,$status);
    }
    function setOrdersStatus($status) {
        update_option(WooOrderStatistic::$stat_order_statuses,$status);
    }

    function getTypeId() {
        return 0;
    }

    function getSyncStatus() {
        return get_option(WooOrderStatistic::$db_sync_status,OrderStatistics::$SYNC_STATUS_START);
    }

    function getSyncPage() {
        return get_option(WooOrderStatistic::$stat_order_imported_page,1);
    }

    function importOrders($page,$perPage) {
        $args = array(
            'status' => WooOrderStatistic::getSelectedOrderStatus(),
            'limit' => $perPage,
            'paged' => $page,
        );

        $orders = wc_get_orders( $args );
        foreach ($orders as $order) {

            if($order instanceof \WC_Order) {
                if(!$this->isOrderExist($order->get_id())) {
                    $this->addNewOrder($order);
                   }
            }
        }
        return count($orders);
    }


    function addNewOrderAfterChangeStatus($order_id, $from, $to,$order) {


        $activeStatuses = $this->getSelectedOrderStatus();
        $oldStatus = "wc-".$from;
        $newStatus = "wc-".$to;

        if(in_array($oldStatus,$activeStatuses) && !in_array($newStatus,$activeStatuses)) {
            $this->deleteOrderWithProduct($order_id);
        }

        if(in_array($newStatus,$activeStatuses) && !in_array($oldStatus,$activeStatuses)) {
            if($this->isOrderExist($order_id)) {
                $sale = $this->getOrderSale($order);
                $this->updateOrder($order_id,$sale['gross'],$sale['net'],$sale['total']);
            } else {
                $this->addNewOrder($order);
            }
        }
    }

    function addNewOrderAfterPayment($orderId) {
        $order = wc_get_order($orderId);
        $activeStatuses = $this->getSelectedOrderStatus();
        $orderStatus = "wc-".$order->get_status();
        if(in_array($orderStatus,$activeStatuses)) {
            if($this->isOrderExist($orderId)) {
                $sale = $this->getOrderSale($order);
                $this->updateOrder($orderId,$sale['gross'],$sale['net'],$sale['total']);
            } else {
                $this->addNewOrder($order);
            }
        }
    }

    /**
     * @param \WC_Order $order
     */
    function addNewOrder($order) {
        $total = $this->getOrderSale($order);
        $tableParams = ["order_id" => $order->get_id(),
            "gross_sale" => $total['gross'],
            "net_sale" => $total['net'],
            "total_sale" => $total['total'],
            "type" => $this->getTypeId(),
            "date" => $order->get_date_created()->date('Y-m-d H:i:s')
        ];
        $orderItems = $this->getOrderProductItems($order);
        $enrichData = $order->get_meta('pys_enrich_data');
        $this->addOrder($order->get_id(),$tableParams,$orderItems,$enrichData);
    }

    /**
     * Update Gross Sale for order
     *
     * @param int $orderId
     * @param int $refundId
     */
    function updateWooOrderSale($orderId,$refundId) {
        $order = wc_get_order($orderId);
        if($order) {
            $sale = $this->getOrderSale($order);
            $this->updateOrder($orderId,$sale['gross'],$sale['net'],$sale['total']);
        }
    }

    /**
     * Gross Sales = Sale price of product(s) x quantity ordered. Does not include refunds, coupons, taxes or shipping
     * Net Sales = Gross Sales – Returns – Coupons
     * TOTAL = Gross Sales – Returns – Coupons + Taxes + Shipping
     * @param \WC_Order $order
     * @return array{gross: float,net:float,total:float}
     */
    function getOrderSale($order) {
        $subtotal = 0;
        $items = $order->get_items();
        foreach ($items as $item) {
            $subtotal += $order->get_item_subtotal($item,false,false);
        }
        $refund = floatval($order->get_total_refunded());
        $refundTax = $order->get_total_tax_refunded();
        $gross = $subtotal;
        $net = $subtotal - $order->get_total_discount(true) - ($refund - $refundTax);
        $total = $order->get_total() - $refund;
        return [
            "gross" => $gross,
            "net" => $net,
            "total" => $total,
        ];
    }

    /**
     * @param \WC_Order $order
     */
    function getOrderProductItems($order) {
        $items = $order->get_items();
        $orderItems = [];
        foreach ($items as $item) {
            if($item instanceof \WC_Order_Item_Product) {
                $grossSale = $order->get_item_subtotal($item,false,false);

                $product =  wc_get_product($item->get_product_id());
                if(!$product) continue;
                $orderItems[] = [
                    'order_id' => $order->get_id(),
                    'product_id' => $product->get_id(),
                    'product_name' => $this->filterCharValue($product->get_name(),99),

                    'qty' => $item->get_quantity(),
                    'gross_sale' => $grossSale,

                    'type' => $this->getTypeId(),
                    'date' => $order->get_date_created()->date('Y-m-d H:i:s')
                ];
            }
        }
        return $orderItems;
    }
}
