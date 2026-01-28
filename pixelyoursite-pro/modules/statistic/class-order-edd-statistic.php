<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class EddOrderStatistic extends OrderStatistics {
    static $db_sync_status = 'pys_edd_sync_statistic_db';
    static $stat_order_imported_page = 'pys_edd_stat_order_imported_page';
    static $stat_order_statuses = 'pys_edd_stat_order_statuses';

    static function getSelectedOrderStatus() {
        return get_option(EddOrderStatistic::$stat_order_statuses,["publish","complete"]);
    }

    public function __construct($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm) {
        parent::__construct($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm);
        add_action("wp_ajax_pys_edd_stat_sync",[$this,"runSyncs"]);
        add_action("wp_ajax_pys_edd_stat_change_orders_status",[$this,"changeOrderStatus"]);

        // Load Stat Data
        add_action("wp_ajax_pys_edd_stat_data",[$this,"loadStatData"]);
        add_action("wp_ajax_pys_edd_stat_single_data",[$this,"loadStatSingleData"]);

        if(PYS()->getOption("edd_enabled_save_data_to_orders")) {
            add_action("edd_complete_purchase", [$this, "eddPaymentCreated"]);
            add_action('edd_update_payment_status', [$this, "eddPaymentStatusChange"], 10, 3);
            add_action('edd_payment_delete', [$this, "eddPaymentDelete"]);
            add_action('edd_payment_saved', [$this, "eddPaymentSaved"], 10, 2);
        }
    }

    function setImportPage($page) {
        update_option(EddOrderStatistic::$stat_order_imported_page, $page);
    }

    function setImportStatus($status) {
        update_option(EddOrderStatistic::$db_sync_status,$status);
    }

    function setOrdersStatus($status) {
        update_option(EddOrderStatistic::$stat_order_statuses,$status);
    }

    function getTypeId() {
        return 1;
    }

    function getSyncStatus() {
        return get_option(EddOrderStatistic::$db_sync_status,OrderStatistics::$SYNC_STATUS_START);
    }

    function getSyncPage() {
        return get_option(EddOrderStatistic::$stat_order_imported_page,1);
    }

    // EDD hooks
    /**
     * Add  payment if status is support
     * @param $payment_id
     */
    function eddPaymentCreated($payment_id) {
        $payment = edd_get_payment($payment_id);
        if($payment) {
            $status = $payment->status;
            $activeStatuses = EddOrderStatistic::getSelectedOrderStatus();
            if(in_array($status,$activeStatuses) && !$this->isOrderExist($payment_id)) {
                $this->addNewPayment($payment);
            }
        }
    }

    /**
     * Add or delete payment from stat if status isn't support
     * @param $payment_id
     * @param $new_status
     * @param $old_status
     */
    function eddPaymentStatusChange($payment_id, $new_status, $old_status) {
        $activeStatuses = EddOrderStatistic::getSelectedOrderStatus();
        $oldStatus = $old_status;
        $newStatus = $new_status;

        if(in_array($oldStatus,$activeStatuses) && !in_array($newStatus,$activeStatuses)) {
            $this->deleteOrderWithProduct($payment_id);
        }

        if(in_array($newStatus,$activeStatuses) && !in_array($oldStatus,$activeStatuses)) {
            if($this->isOrderExist($payment_id)) {
                // this update in save hook
            } else {
                $payment = edd_get_payment($payment_id);
                if($payment) {
                    $this->addNewPayment($payment);
                }
            }
        }
    }

    /**
     * Delete payment from stat
     * @param $payment_id
     */
    function eddPaymentDelete($payment_id) {
        $this->deleteOrderWithProduct($payment_id);
    }

    /**
     * Update payment sale
     * @param $payment_id
     * @param \EDD_Payment $payment
     */
    function eddPaymentSaved( $payment_id, $payment) {

        $order = edd_get_payment($payment_id);
        if($this->isOrderExist($payment_id)) {
            $sale = $this->getPaymentSale($order);
            $this->updateOrder($payment_id,$sale['gross'],$sale['net'],$sale['total']);
        } else {
            $this->addNewPayment($order);
        }

    }

    /**
     * @param \EDD_Payment $payment
     */
    function addNewPayment($payment)
    {
        $total = $this->getPaymentSale($payment);
        $tableParams = ["order_id" => $payment->ID,
            "gross_sale" => $total['gross'],
            "net_sale" => $total['net'],
            "total_sale" => $total['total'],
            "type" => $this->getTypeId(),
            "date" => $payment->date
        ];
        $orderItems = $this->getPaymentProductItems($payment);
        $meta = $payment->get_meta();
        $this->addOrder($payment->ID, $tableParams, $orderItems,isset($meta['pys_enrich_data']) ? $meta['pys_enrich_data'] : false);
    }



    /**
     * Gross Sales = Sale price of product(s) x quantity ordered. Does not include refunds, coupons, taxes or shipping
     * Net Sales = Gross Sales – Returns – Coupons
     * TOTAL = Gross Sales – Returns – Coupons + Taxes + Shipping
     * @param \EDD_Payment $payment
     * @return array
     */
    function getPaymentSale($payment) {
        $subtotal = 0;
        $tax = 0;
        $fees = 0;
        $discount = 0;
        // 'name','id' ,'item_number','item_price','quantity','discount','subtotal','tax' ,'fees' ,'price'
        $downloads = $payment->cart_details;
        //error_log("getPaymentSale ".print_r($payment,true)." downloads ".print_r($downloads,true));
        foreach ($downloads as $download) {
            $subtotal += $download['subtotal'];
            $tax += $download['tax'];
            //$fees += $download['fees'];
            $discount += $download['discount'];
        }
        $gross = $subtotal;
        $net = $gross - $discount ;
        $total = $net + $tax + $fees;
        return [
            "gross" => $gross,
            "net" => $net,
            "total" => $total,
        ];
    }

    /**
     * @param \EDD_Payment $payment
     * @return array
     */
    function getPaymentProductItems($payment) {
        $cart_details = $payment->cart_details;
        $orderItems = [];
        foreach ($cart_details as $cart_item_key => $cart_item) {
            $productId = (int)$cart_item['id'];
            $grossSale = $cart_item['subtotal'];

            $orderItems[] = [
                'order_id' => $payment->ID,
                'product_id' => $productId,
                'product_name' => $cart_item['name'],

                'qty' => $cart_item['quantity'],
                'gross_sale' => $grossSale,

                'type' => $this->getTypeId(),
                'date' => $payment->date
            ];
        }

        return $orderItems;
    }


    function importOrders($page, $perPage)
    {


        $payments = edd_get_payments( array(
            'status' => EddOrderStatistic::getSelectedOrderStatus(),
            'number' => $perPage,
            'page' => $page
        ) );

        $count = 0;
        if(is_array($payments)) {
            $count = count($payments);
            foreach ($payments as $payment) {
                $this->addNewPayment($payment);
            }
        }

        return $count;
    }


    function getOrdersCount($statuses) {
        $size = 0;
        $counts = edd_count_payments(array(
        ));
        foreach ($statuses as $status) {
            if(property_exists($counts,$status)) {
                $size += $counts->$status;
            }
        }
        return $size;
    }
}