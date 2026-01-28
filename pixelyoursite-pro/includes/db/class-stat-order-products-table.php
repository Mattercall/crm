<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class StatProductsTable extends DataBaseTable  {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_product_order";
    }
    function getColName()
    {
        return 'stat product order';
    }
    function getCreateSql()
    {
        $collate = '';
        $tableName = $this->getName();
        if ( $this->wpdb->has_cap( 'collation' ) && $this->wpdb->get_charset_collate() != false) {
            $collate = $this->wpdb->get_charset_collate();
        }
        return "CREATE TABLE $tableName (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              order_id BIGINT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NOT NULL,
              product_name  char(100)  NOT NULL,
              qty INT UNSIGNED NOT NULL, 
              gross_sale FLOAT UNSIGNED NOT NULL,
              type TINYINT NOT NULL,
              date timestamp default current_timestamp, 
              PRIMARY KEY  (id)
            ) $collate;";
    }

    /**
     * @param \EDD_Payment $payment
     */
    function addEddOrderProducts($payment) {
        $cart_details = $payment->cart_details;

        foreach ($cart_details as $cart_item_key => $cart_item) {
            $productId = (int)$cart_item['id'];
            $post = get_post($productId);
        }
    }


    function addOrderProducts($orderItems) {
        foreach ($orderItems as $item) {
            $this->wpdb->insert($this->getName(),$item,[
                "%d","%d","%s",
                "%d","%f",
                "%d",'%s'
            ]);
        }
    }

    function getProductsList($orderIds,$orderBy,$order,$type,$perPage,$page) {
        $start = ($page - 1) * $perPage;
        $data = [];
        $in = '(' . implode(',', $orderIds) .')';
        if($orderBy == "net") {
            $orderBy = "gross";
        }
        $sql = $this->wpdb->prepare("
                   SELECT product_id, product_name, count(DISTINCT order_id) as count_order,  ROUND(SUM(gross_sale),2) as gross,SUM(qty) as qty, ROUND(SUM(gross_sale*qty),2) as total
                    FROM {$this->getName()}
                    WHERE order_id in $in and type = %d
                    GROUP BY product_id
                    ORDER BY $orderBy $order
                    LIMIT %d, %d"
            ,$type,$start,$perPage);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["id"=>$row->product_id,"name"=>$row->product_name,"qty"=>$row->qty,"orders"=>$row->count_order,"gross"=>$row->gross, "total"=>$row->total];
        }
        return $data;
    }
    function getCOGProductsList($orderIds,$orderBy,$order,$type,$perPage,$page) {
        if($orderBy == "net") {
            $orderBy = "profit";
        }
        if($orderBy == "gross") {
            $orderBy = "cost";
        }
        $start = ($page - 1) * $perPage;
        $data = [];
        $in = '(' . implode(',', $orderIds) .')';
        $sql = $this->wpdb->prepare("
                   SELECT 
                          product_id, 
                          product_name, 
                          IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((gross_sale - meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale -(gross_sale/100*meta_goods_cost_val.meta_value)*qty)),2)) as profit, 
                          IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale/100*meta_goods_cost_val.meta_value)*qty),2)) as cost,
                          count(DISTINCT order_id) as count_order, 
                          ROUND(SUM(gross_sale*qty),2) as total, SUM(qty) as qty
                    FROM {$this->getName()} AS products 
                    LEFT JOIN wp_postmeta AS meta_goods_cost_val ON meta_goods_cost_val.post_id = products.product_id AND meta_goods_cost_val.meta_key = '_pixel_cost_of_goods_cost_val' 
                    LEFT JOIN wp_postmeta AS meta_goods_cost_type ON meta_goods_cost_type.post_id = products.product_id AND meta_goods_cost_type.meta_key = '_pixel_cost_of_goods_cost_type'
                    WHERE order_id in $in and type = %d 
                    GROUP BY product_id
                    ORDER BY $orderBy $order
                    LIMIT %d, %d"
            ,$type,$start,$perPage);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["id"=>$row->product_id,"name"=>$row->product_name,"qty"=>$row->qty,"orders"=>$row->count_order,"gross"=>$row->cost, "net"=>$row->profit,"total"=>$row->total];

        }
        return $data;
    }



    function getProductsStat($products,$orders,$typeId,$orderBy,$order) {
        $data = [];
        $in = '(' . implode(',', $products) .')';
        $inOrders = '(' . implode(',', $orders) .')';
        $col = "";
        if($orderBy == "net") {
            $orderBy = "gross";
        }
        if($orderBy == "qty") {
            $col = "sum(qty) as qty";
        }
        if($orderBy == "count_order") {
            $col = "count(DISTINCT order_id) as count_order";
        }
        if($orderBy == "gross") {
            $col = "ROUND(SUM(gross_sale),2) as gross";
        }

        if($orderBy == "total") {
            $col = "ROUND(SUM(gross_sale*qty),2) as total";
        }

        $sql = $this->wpdb->prepare("
                    SELECT $col,CAST(date AS DATE) createDate  
                    FROM {$this->getName()}
                    WHERE product_id in $in AND order_id in $inOrders AND type = %d 
                    GROUP BY createDate
                    ORDER BY $orderBy $order ",$typeId);

        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["y"=>$row->$orderBy,"x"=>$row->createDate];
        }

        return $data;

    }

    function getCOGProductsStat($products,$orders,$typeId,$orderBy,$order) {
        $data = [];
        $in = '(' . implode(',', $products) .')';
        $inOrders = '(' . implode(',', $orders) .')';
        $col = "";

        if($orderBy == "qty") {
            $col = "sum(qty) as qty";
        }
        if($orderBy == "count_order") {
            $col = "count(DISTINCT order_id) as count_order";
        }
        if($orderBy == "gross") {
            $col = "IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale/100*meta_goods_cost_val.meta_value)*qty),2)) as gross";
        }
        if($orderBy == "net") {
            $col = "IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((gross_sale - meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale -(gross_sale/100*meta_goods_cost_val.meta_value)*qty)),2)) as net";
        }
        if($orderBy == "total") {
            $col = "ROUND(SUM(gross_sale*qty),2) as total";
        }
        $sql = $this->wpdb->prepare("
                    SELECT $col,CAST(date AS DATE) createDate  
                    FROM {$this->getName()} AS products
                    LEFT JOIN wp_postmeta AS meta_goods_cost_val ON meta_goods_cost_val.post_id = products.product_id AND meta_goods_cost_val.meta_key = '_pixel_cost_of_goods_cost_val' 
                    LEFT JOIN wp_postmeta AS meta_goods_cost_type ON meta_goods_cost_type.post_id = products.product_id AND meta_goods_cost_type.meta_key = '_pixel_cost_of_goods_cost_type'
                    WHERE product_id in $in AND order_id in $inOrders AND type = %d 
                    GROUP BY createDate
                    ORDER BY $orderBy $order ",$typeId);

        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["y"=>$row->$orderBy,"x"=>$row->createDate];
        }

        return $data;

    }

    function getProductsTotal($orders,$typeId) {
        $data = [];
        $inOrders = '(' . implode(',', $orders) .')';
        $sql = $this->wpdb->prepare("
                    SELECT count(DISTINCT product_id) as ids,count(DISTINCT order_id) as orders, SUM(qty) as qty, ROUND(SUM(gross_sale),2) as gross, ROUND(SUM(gross_sale*qty),2) as total_sale
                    FROM {$this->getName()}
                    WHERE order_id in $inOrders AND type = %d 
                   
                    ",$typeId);

        $row = $this->wpdb->get_row($sql);
        $symbols = $typeId == 0 ? get_woocommerce_currency_symbol() : edd_currency_symbol();
        $data[] = ["name"=>"Products: ","value"=>$row->ids];
        $data[] = ["name"=>"Quantity: ","value"=>$row->qty];
        $data[] = ["name"=>"Orders: ","value"=>$row->orders];
        $data[] = ["name"=>"Gross Sale: ","value"=>$row->gross.$symbols];
        $data[] = ["name"=>"Total Sale: ","value"=>$row->total_sale.$symbols];
        return $data;
    }

    function getCOGProductsTotal($orders,$typeId) {
        $data = [];
        $inOrders = '(' . implode(',', $orders) .')';
        $sql = $this->wpdb->prepare("
                    SELECT count(DISTINCT product_id) as ids,count(DISTINCT order_id) as orders, SUM(qty) as qty, 
                        IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((gross_sale - meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale -(gross_sale/100*meta_goods_cost_val.meta_value)*qty)),2)) as profit,
                        IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((gross_sale/100*meta_goods_cost_val.meta_value)*qty),2)) as cost,
                       ROUND(SUM(gross_sale*qty),2) as total_sale
                    FROM {$this->getName()} AS products
                    LEFT JOIN wp_postmeta AS meta_goods_cost_val ON meta_goods_cost_val.post_id = products.product_id AND meta_goods_cost_val.meta_key = '_pixel_cost_of_goods_cost_val' 
                   LEFT JOIN wp_postmeta AS meta_goods_cost_type ON meta_goods_cost_type.post_id = products.product_id AND meta_goods_cost_type.meta_key = '_pixel_cost_of_goods_cost_type'
                    WHERE order_id in $inOrders AND type = %d 
                   
                    ",$typeId);
        $row = $this->wpdb->get_row($sql);
        $symbols = $typeId == 0 ? get_woocommerce_currency_symbol() : edd_currency_symbol();
        $data[] = ["name"=>"Products: ","value"=>$row->ids];
        $data[] = ["name"=>"Quantity: ","value"=>$row->qty];
        $data[] = ["name"=>"Orders: ","value"=>$row->orders];
        $data[] = ["name"=>"Cost: ","value"=>$row->cost.$symbols];
        $data[] = ["name"=>"Profit: ","value"=>$row->profit.$symbols];
        $data[] = ["name"=>"Total sale: ","value"=>$row->total_sale.$symbols];
        return $data;
    }

    function deleteOrderProduct($orderId,$typeId) {
        $this->wpdb->delete($this->getName(),['order_id' => $orderId,'type' => $typeId],["%d","%d"]);
    }
    /**
     * @param int $typeId
     */
    function clear($typeId) {
        $this->wpdb->delete($this->getName(),['type' => $typeId],["%d"]);
    }


}