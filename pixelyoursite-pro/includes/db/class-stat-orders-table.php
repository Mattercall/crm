<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class StatOrdersTable extends DataBaseTable {

    function getName()
    {
        return $this->wpdb->prefix . "pys_stat_order";
    }
    function getColName()
    {
        return 'stat order';
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
              
              traffic_source_id BIGINT UNSIGNED NULL,
              landing_id BIGINT UNSIGNED NULL,
              utm_source_id BIGINT UNSIGNED NULL,
              utm_medium_id BIGINT UNSIGNED NULL,
              utm_campaing_id BIGINT UNSIGNED NULL,
              utm_term_id BIGINT UNSIGNED NULL,
              utm_content_id BIGINT UNSIGNED NULL,
              
              last_traffic_source_id BIGINT UNSIGNED NULL,
              last_landing_id BIGINT UNSIGNED NULL,
              last_utm_source_id BIGINT UNSIGNED NULL,
              last_utm_medium_id BIGINT UNSIGNED NULL,
              last_utm_campaing_id BIGINT UNSIGNED NULL,
              last_utm_term_id BIGINT UNSIGNED NULL,
              last_utm_content_id BIGINT UNSIGNED NULL,
              
              gross_sale FLOAT UNSIGNED NOT NULL,
              net_sale FLOAT UNSIGNED NOT NULL,
              total_sale FLOAT UNSIGNED NOT NULL,
              type TINYINT NOT NULL,
              date timestamp default current_timestamp, 
              PRIMARY KEY  (id)
            ) $collate;";
    }

    /**
     * @param $filterColName
     * @param $type
     * @return int
     */
    function getFilterCount($filterColName,$type,$dateStart,$dateEnd) {
        $sql = $this->wpdb->prepare("SELECT $filterColName FROM {$this->getName()} 
                                            WHERE type = %d AND $filterColName IS NOT NULL AND date BETWEEN %s AND %s
                                            GROUP BY $filterColName",
            $type,$dateStart,$dateEnd);

        return count($this->wpdb->get_results($sql));
    }

    /**
     * @param $filterColName
     * @param $type
     * @param $dateStart
     * @param $dateEnd
     * @param $isFirst
     * @return object
     */

    function getFilterTotal($filterColName,$type,$dateStart,$dateEnd,$isFirst) {
        $prefix = "";
        if(!$isFirst) {
            $prefix = "last_";
        }
        $sql = $this->wpdb->prepare("SELECT 
count(id) as count,
ROUND(SUM(total_sale),2) as total_sale,
ROUND(SUM(gross_sale),2) as gross_sale,
ROUND(SUM(net_sale),2) as net_sale
FROM {$this->getName()}
WHERE type = %d AND $filterColName IS NOT NULL AND date BETWEEN %s AND %s
",$type,$dateStart,$dateEnd);

        /*
          sum(case when {$prefix}traffic_source_id is null then 0 else 1 end) as traffic_source_count,
sum(case when {$prefix}landing_id is null then 0 else 1 end) as landing_count,
sum(case when {$prefix}utm_source_id is null then 0 else 1 end) as utm_source_count,
sum(case when {$prefix}utm_medium_id is null then 0 else 1 end) as utm_medium_count,
sum(case when {$prefix}utm_campaing_id is null then 0 else 1 end) as utm_campaing_count,
sum(case when {$prefix}utm_term_id is null then 0 else 1 end) as utm_term_count,
sum(case when {$prefix}utm_content_id is null then 0 else 1 end) as utm_content_count,
         */

        return $this->wpdb->get_row($sql,ARRAY_A);
    }
    public function getCOGFilterTotal($filterColName,$type,$dateStart,$dateEnd,$isFirst) {
        $prefix = "";
        if(!$isFirst) {
            $prefix = "last_";
        }
        $sql = $this->wpdb->prepare("SELECT 
count(id) as count,
ROUND(SUM(total_sale),2) as total_sale,
                    ROUND(SUM(meta1.meta_value),2) AS cost,
                    ROUND(SUM(meta2.meta_value),2) AS profit
                    FROM {$this->getName()} AS orders 
                    LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
	                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                    
WHERE type = %d AND $filterColName IS NOT NULL AND date BETWEEN %s AND %s
",$type,$dateStart,$dateEnd);

        return $this->wpdb->get_row($sql,ARRAY_A);
    }
    /**
     * @param $typeTable
     * @param $filterColName
     * @param $filterId
     * @param $type
     * @param $dateStart
     * @param $dateEnd
     * @return array|object|\stdClass|void|null
     */
    function getFilterSingleTotal($typeTable,$filterColName,$filterId,$type,$dateStart,$dateEnd) {
        $firstNam = "";
        switch ($typeTable) {
            case "dates": {
                $firstNam = "Dates: ";
                $select = "count(DISTINCT cast(`date` as date)) as count, count(DISTINCT order_id) as orders";}break;
            case "orders": {
                $firstNam = "Orders: ";
                $select = "count(id) as count";}break;
        }
        $data = [];
        $sql = $this->wpdb->prepare("SELECT 
                $select,
                ROUND(SUM(total_sale),2) as total_sale,
                ROUND(SUM(gross_sale),2) as gross_sale,
                ROUND(SUM(net_sale),2) as net_sale
                FROM {$this->getName()}
                WHERE type = %d AND $filterColName = $filterId AND date BETWEEN %s AND %s
                ",$type,$dateStart,$dateEnd);
        $row = $this->wpdb->get_row($sql);

        switch ($typeTable) {
            case "dates": {
                $data[] = ["name"=>"Dates: ","value"=>$row->count];
                $data[] = ["name"=>"Orders: ","value"=>$row->orders];
            }break;
            case "orders": {
                $data[] = ["name"=>"Orders: ","value"=>$row->count];
            }break;
        }
        $symbols = $type == 0 ? get_woocommerce_currency_symbol() : edd_currency_symbol();
        $data[] = ["name"=>"Gross Sale: ","value"=>$symbols.$row->gross_sale];
        $data[] = ["name"=>"Net Sale: ","value"=>$symbols.$row->net_sale];
        $data[] = ["name"=>"Total Sale: ","value"=>$symbols.$row->total_sale];
        return $data;
    }

    function getCOGFilterSingleTotal($typeTable,$filterColName,$filterId,$type,$dateStart,$dateEnd) {
        $firstNam = "";
        switch ($typeTable) {
            case "dates": {
                $firstNam = "Dates: ";
                $select = "count(DISTINCT cast(`date` as date)) as count, count(DISTINCT order_id) as orders";}break;
            case "orders": {
                $firstNam = "Orders: ";
                $select = "count(id) as count";}break;
        }
        $data = [];
        $sql = $this->wpdb->prepare("SELECT 
                $select,
                order_id,
                ROUND(SUM(total_sale),2) as total_sale,
                ROUND(SUM(meta1.meta_value),2) AS cost_sale,
                ROUND(SUM(meta2.meta_value),2) AS profit_sale
                FROM {$this->getName()} AS orders 
                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                WHERE type = %d AND $filterColName = $filterId AND date BETWEEN %s AND %s
                ",$type,$dateStart,$dateEnd);

        $row = $this->wpdb->get_row($sql);

        switch ($typeTable) {
            case "dates": {
                $data[] = ["name"=>"Dates: ","value"=>$row->count];
                $data[] = ["name"=>"Orders: ","value"=>$row->orders];
            }break;
            case "orders": {
                $data[] = ["name"=>"Orders: ","value"=>$row->count];
            }break;
        }

        $symbols = $type == 0 ? get_woocommerce_currency_symbol() : edd_currency_symbol();
        $data[] = ["name"=>"Cost: ","value"=>$symbols.$row->cost_sale];
        $data[] = ["name"=>"Profit: ","value"=>$symbols.$row->profit_sale];
        $data[] = ["name"=>"Total Sale: ","value"=>$symbols.$row->total_sale];
        return $data;
    }

    function getOrderByCol($slag) {
        switch ($slag) {
            case "order": return "count";
            case "gross_sale": return "gross";
            case "total_sale": return "total";
            default: return "net";
        }
    }

    function getSOrtType($slag) {
        if($slag == "desc") {
            return "DESC";
        }
        return "ASC";
    }

    function getSumForFilter($filterTableName,$filterColName,$startDate,$endDate,$from, $max,$type,$orderBy,$sort) {
        $orderBy = $this->getOrderByCol($orderBy);
        $sort = $this->getSOrtType($sort);
        $data = ["ids" => [],"filters" => []];
        $sql = $this->wpdb->prepare("SELECT count(order_id) as count, t2.id as item_id, t2.item_value, ROUND(SUM(gross_sale),2) as gross, ROUND(SUM(net_sale),2) as net, ROUND(SUM(total_sale),2) as total 
                                                FROM {$this->getName()} 
                                                LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
                                                WHERE type = %d AND $filterColName IS NOT NULL  AND date BETWEEN %s AND %s
                                                GROUP BY $filterColName
                                                ORDER BY $orderBy $sort
                                                LIMIT %d, %d
                                                ",$type,$startDate,$endDate,$from,$max);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data["ids"][] = $row->item_id;
            $data["filters"][] = ["id" => $row->item_id,"name" => $row->item_value,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"count" => $row->count];
        }
        return $data;
    }

    function getCOGSumForFilter($filterTableName,$filterColName,$startDate,$endDate,$from, $max,$type,$orderBy,$sort) {
        $orderBy = $this->getOrderByCol($orderBy);
        $sort = $this->getSOrtType($sort);
        $data = ["ids" => [],"filters" => []];
        $sql = $this->wpdb->prepare("SELECT count(order_id) as count, 
t2.id as item_id, 
t2.item_value, 
                        ROUND(SUM(total_sale),2) as total,
                        ROUND(SUM(meta1.meta_value),2) AS gross,
                        ROUND(SUM(meta2.meta_value),2) AS net
                        FROM {$this->getName()} AS orders 
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                        LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
                        WHERE type = %d AND $filterColName IS NOT NULL  AND date BETWEEN %s AND %s
                        GROUP BY $filterColName
                        ORDER BY $orderBy $sort
                        LIMIT %d, %d
                                                ",$type,$startDate,$endDate,$from,$max);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data["ids"][] = $row->item_id;
            $data["filters"][] = ["id" => $row->item_id,"name" => $row->item_value,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"count" => $row->count];
        }
        return $data;
    }

    function getDataFull($startDate,$endDate,$type){
        $data = array();

        $sql = $this->wpdb->prepare("SELECT orders.*,
                meta1.meta_value AS cost,
                meta2.meta_value AS profit
                FROM {$this->getName()} AS orders 
                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                WHERE type = %d and date BETWEEN %s AND %s
                ",$type,$startDate,$endDate);
        $results = $this->wpdb->get_results($sql);

        foreach ($results as $row) {
            $order_id = $row->order_id;
            switch ($type) {
                case 0 :
                    $order = wc_get_order($order_id);
                    break;
                case 1 :
                    $order = edd_get_payment($order_id);
                    break;
            }

            if ( $order == null ) {
                continue;
            }
            switch ($type) {
                case 0 :
                    $enrichData = $order->get_meta('pys_enrich_data');
                    break;
                case 1 :
                    $enrichData = edd_get_payment_meta($order_id)['pys_enrich_data'];
                    break;
            }
            $landing = isset($enrichData['pys_landing']) ? $enrichData['pys_landing'] : "";
            $source = isset($enrichData['pys_source']) ? $enrichData['pys_source'] : "";
            $utmData = isset($enrichData['pys_utm']) ? $enrichData['pys_utm'] : "";

            $lastLanding = isset($enrichData['last_pys_landing']) ? $enrichData['last_pys_landing'] : "";
            $lastSource = isset($enrichData['last_pys_source']) ? $enrichData['last_pys_source'] : "";
            $lastUtmData = isset($enrichData['last_pys_utm']) ? $enrichData['last_pys_utm'] : "";
            $utmIds = $this->getUtmValues($utmData);
            $lastUtmIds = $this->getUtmValues($lastUtmData);

            $tableParams = ["traffic_source" => $source,
                "landing" => $landing,
                "utm_source" => isset($utmIds['utm_source']) ? $utmIds['utm_source'] : null,
                "utm_medium" => isset($utmIds['utm_medium']) ? $utmIds['utm_medium'] : null,
                "utm_campaing" => isset($utmIds['utm_campaign']) ? $utmIds['utm_campaign'] : null,
                "utm_term" => isset($utmIds['utm_term']) ? $utmIds['utm_term'] : null,
                "utm_content" => isset($utmIds['utm_content']) ? $utmIds['utm_content'] : null,

                "last_traffic_source" => $lastSource,
                "last_landing" => $lastLanding,
                "last_utm_source" => isset($lastUtmIds['utm_source']) ? $lastUtmIds['utm_source'] : null,
                "last_utm_medium" => isset($lastUtmIds['utm_medium']) ? $lastUtmIds['utm_medium'] : null,
                "last_utm_campaing" => isset($lastUtmIds['utm_campaign']) ? $lastUtmIds['utm_campaign'] : null,
                "last_utm_term" => isset($lastUtmIds['utm_term']) ? $lastUtmIds['utm_term'] : null,
                "last_utm_content" => isset($lastUtmIds['utm_content']) ? $lastUtmIds['utm_content'] : null
            ];
            if (is_a($order, 'WC_Order')) {
                $total = $order->get_total();
                $args = ["products" => []];
                $ids = [];
                $product_names = [];

				$payment_method = $order->get_payment_method_title();

                foreach ($order->get_items() as $line_item) {
                    if (!($line_item instanceof \WC_Order_Item_Product)) continue;
                    $product_id = empty($line_item['variation_id']) ? $line_item['product_id'] : $line_item['variation_id'];
                    $product = wc_get_product($product_id);
                    if (!$product) continue;
                    $ids[] = $product->get_id();
                    $product_names[] = str_replace(array(',', ';'), ' ', $product->get_name());
                }


                //if(PYS()->getOption("woo_advance_purchase_fb_enabled") ) {//send fb server events
                $line = [
                    'order_id' => $order->get_id(),
                    'gross_sale' => $row->gross_sale,
                    'net_sale' => $row->net_sale,
                    'total_sale' => $row->total_sale,
                    'cost' => $row->cost,
                    'profit' => $row->profit,
	                'payment_method' => $payment_method,

                    'user_id' => $order->get_user_id(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'fn' => $order->get_billing_first_name(),
                    'ln' => $order->get_billing_last_name(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => $order->get_billing_country(),
                    'postcode' => $order->get_billing_postcode(),
                    'date_created' => $order->get_date_created()->date("Y-m-d\\TH:i:s\\Z"),
                    'currency' => $order->get_currency(),
                    'ids' => implode("|", $ids),
                    'product_names' => implode("|", $product_names)
                ];
            }elseif (is_a($order, 'EDD_Payment')) {
                $args = ["products" => []];
                $ids = [];
                $product_names = [];

                foreach ($order->downloads as $download) {
                    // Получаем ID продукта.
                    $product_id = $download['id'];

                    // Получаем объект продукта EDD.
                    $product = edd_get_download($product_id);

                    // Проверяем, существует ли продукт.
                    if ($product) {
                        $ids[] = $product->ID;
                        $product_names[] = str_replace(array(',', ';'), ' ', $product->post_title);
                    }
                }

                $line = [
                    'order_id' => $row->order_id,
                    'gross_sale' => $row->gross_sale,
                    'net_sale' => $row->net_sale,
                    'total_sale' => $row->total_sale,
                    'cost' => $row->cost,
                    'profit' => $row->profit,

                    'user_id' => $order->user_id,
                    'email' => $order->email,
                    'phone' => '',
                    'fn' => $order->first_name,
                    'ln' => $order->last_name,
                    'city' => $order->address['city'],
                    'state' => $order->address['state'],
                    'country' => $order->address['country'],
                    'postcode' => $order->address['zip'],
                    'date_created' => $order->date,
                    'currency' => edd_get_currency(),
                    'ids' => implode("|", $ids),
                    'product_names' => implode("|", $product_names)
                ];
            }
            $line = array_merge($line, $tableParams);
            $line = apply_filters("pys_offline_events_data",$line,$order);
            $data[] = $line;
        }
        return $data;
    }

    function getDataAll($filterTableName,$filterColName,$startDate,$endDate,$type) {
        $sql = $this->wpdb->prepare("SELECT count(order_id) as count, t2.id as item_id, t2.item_value, ROUND(SUM(gross_sale),2) as gross, ROUND(SUM(net_sale),2) as net, ROUND(SUM(total_sale),2) as total 
                                                FROM {$this->getName()} 
                                                LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
                                                WHERE type = %d AND $filterColName IS NOT NULL  AND date BETWEEN %s AND %s
                                                GROUP BY $filterColName
                                                ORDER BY total DESC
                                               
                                                ",$type,$startDate,$endDate);
        return $this->wpdb->get_results($sql);
    }

    function getCOGDataAll($filterTableName,$filterColName,$startDate,$endDate,$type) {
        $sql = $this->wpdb->prepare("SELECT count(order_id) as count, t2.id as item_id, t2.item_value, ROUND(SUM(total_sale),2) as total,
                        ROUND(SUM(meta1.meta_value),2) AS gross,
                        ROUND(SUM(meta2.meta_value),2) AS net
                                                FROM {$this->getName()} AS orders 
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                                                LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
                                                WHERE type = %d AND $filterColName IS NOT NULL  AND date BETWEEN %s AND %s
                                                GROUP BY $filterColName
                                                ORDER BY total DESC
                                               
                                                ",$type,$startDate,$endDate);
        return $this->wpdb->get_results($sql);
    }

    function getData($filterTableName,$filterColName,$ids,$startDate,$endDate,$type,$orderBy,$sort) {
        $data = [];
        $orderBy = $this->getOrderByCol($orderBy);
        $sort = $this->getSOrtType($sort);
        $in = '(' . implode(',', $ids) .')';
        //data: [{x:'2016-12-25', y:20}, {x:'2016-12-26', y:10},{x:'2016-12-27', y:15}]


        $sql = $this->wpdb->prepare("SELECT count(order_id) as count,t2.id as item_id, t2.item_value,CAST(date AS DATE) date ,ROUND(SUM(gross_sale),2) as gross, ROUND(SUM(net_sale),2) as net, ROUND(SUM(total_sale),2) as total 
FROM {$this->getName()} 
LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
WHERE type = %d AND $filterColName IN $in AND date BETWEEN %s AND %s
GROUP BY cast(`date` as date), $filterColName
ORDER BY $orderBy $sort
",$type,$startDate,$endDate);

        /**
         * @var {item_value:String,date:String,gross: float,net:float}[]$rows
         */
        $rows = $this->wpdb->get_results($sql);

        foreach ($rows as $row) {
            if(!key_exists($row->item_value,$data)) {
                $data[$row->item_value] = [
                    "item" => ["id" => $row->item_id,"name" => $row->item_value],
                    "data" => []
                ];
            }
            $data[$row->item_value]["data"][] = ["x"=>$row->date,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"count"=>$row->count];
        }

        return $data;
    }

    function getCOGData($filterTableName,$filterColName,$ids,$startDate,$endDate,$type,$orderBy,$sort) {
        $data = [];
        $orderBy = $this->getOrderByCol($orderBy);
        $sort = $this->getSOrtType($sort);
        $in = '(' . implode(',', $ids) .')';
        //data: [{x:'2016-12-25', y:20}, {x:'2016-12-26', y:10},{x:'2016-12-27', y:15}]


        $sql = $this->wpdb->prepare("SELECT order_id, count(order_id) as count,t2.id as item_id, t2.item_value,CAST(date AS DATE) date , ROUND(SUM(total_sale),2) as total, ROUND(SUM(meta1.meta_value),2) AS gross,
                        ROUND(SUM(meta2.meta_value),2) AS net
                        FROM {$this->getName()} AS orders 
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                        LEFT JOIN  $filterTableName as t2 ON  $filterColName = t2.id 
                        WHERE type = %d AND $filterColName IN $in AND date BETWEEN %s AND %s
                        GROUP BY cast(`date` as date), $filterColName
                        ORDER BY $orderBy $sort
                        ",$type,$startDate,$endDate);

        /**
         * @var {item_value:String,date:String,gross: float,net:float}[]$rows
         */
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            if(!key_exists($row->item_value,$data)) {
                $data[$row->item_value] = [
                    "item" => ["id" => $row->item_id,"name" => $row->item_value],
                    "data" => []
                ];
            }
            $data[$row->item_value]["data"][] = ["x"=>$row->date, "gross" => $row->gross,"net" => $row->net, "total" => $row->total, "count"=>$row->count];
        }

        return $data;
    }

    function isExistOrder($orderId,$type) {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT id FROM {$this->getName()} WHERE order_id = %d AND type = %d",$orderId,$type));
        return $row != null;
    }

    /**
     * @param $orderId
     * @return bool|int
     */
    function deleteOrder($orderId,$type) {
      return  $this->wpdb->delete($this->getName(),['order_id' => $orderId,'type' => $type],["%d","%d"]);
    }



    function getOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("SELECT  order_id, CAST(date AS DATE) date ,ROUND(gross_sale,2) as gross, ROUND(net_sale,2) as net, ROUND(total_sale,2) as total
                    FROM {$this->getName()} 
                    WHERE type = %d AND $filterColName = %d  AND date BETWEEN %s AND %s
                    ORDER BY total DESC
                    ",$type,$filterId,$startDate,$endDate);
        $rows = $this->wpdb->get_results($sql);

        foreach ($rows as $row) {
            $data[] = ["x"=>$row->date,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"order_id" => $row->order_id,"count" => 1];
        }
        return $data;
    }
    function getCOGOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("SELECT  order_id, CAST(date AS DATE) date , ROUND(total_sale,2) as total, ROUND(meta1.meta_value,2) AS gross,
                        ROUND(meta2.meta_value,2) AS net
                    FROM {$this->getName()} AS orders 
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
                        LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                    WHERE type = %d AND $filterColName = %d  AND date BETWEEN %s AND %s
                    ORDER BY total DESC
                    ",$type,$filterId,$startDate,$endDate);
        $rows = $this->wpdb->get_results($sql);

        foreach ($rows as $row) {
            $data[] = ["x"=>$row->date,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"order_id" => $row->order_id,"count" => 1];
        }
        return $data;
    }


    function getDatesForSingle($filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("SELECT count(order_id) as count ,order_id, CAST(date AS DATE) date ,ROUND(SUM(gross_sale),2) as gross, ROUND(SUM(net_sale),2) as net , ROUND(SUM(total_sale),2) as total
                    FROM {$this->getName()} 
                    WHERE type = %d AND $filterColName = %d  AND date BETWEEN %s AND %s
                    GROUP BY cast(`date` as date)
                    ORDER BY total DESC
                    ",$type,$filterId,$startDate,$endDate);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["x"=>$row->date,"gross" => $row->gross,"net" => $row->net,"total" => $row->total,"order_id" => $row->order_id,"count" => $row->count];
        }
        return $data;
    }

    function getCOGDatesForSingle($filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("SELECT 
                   count(order_id) as count ,
                   order_id, 
                   CAST(date AS DATE) date ,
                   ROUND(SUM(total_sale),2) as total,
                    ROUND(SUM(meta1.meta_value),2) AS cost,
                    ROUND(SUM(meta2.meta_value),2) AS profit
                    FROM {$this->getName()} AS orders 
                    LEFT JOIN {$this->wpdb->prefix}postmeta AS meta1 ON meta1.post_id = orders.order_id AND meta1.meta_key = '_pixel_cost_of_goods_order_cost'
	                LEFT JOIN {$this->wpdb->prefix}postmeta AS meta2 ON meta2.post_id = orders.order_id AND meta2.meta_key = '_pixel_cost_of_goods_order_profit'
                    WHERE type = %d AND $filterColName = %d  AND date BETWEEN %s AND %s
                    GROUP BY cast(`date` as date)
                    ORDER BY total DESC
                    ",$type,$filterId,$startDate,$endDate);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["x"=>$row->date,"gross" => $row->cost,"net" => $row->profit,"total" => $row->total,"order_id" => $row->order_id,"count" => $row->count];
        }
        return $data;
    }

    function getProductsOrders($filterColName,$filterId,$startDate,$endDate,$type) {
        $sql = $this->wpdb->prepare("
                    SELECT order_id 
                    FROM {$this->getName()} 
                    WHERE $filterColName = %d AND type = %d AND date BETWEEN %s AND %s
                    GROUP BY order_id
              "
            ,$filterId,$type,$startDate,$endDate);

        $col =  $this->wpdb->get_col($sql);

        return $col;
//        $data[] = ["name"=>"Products: ","value"=>$row->ids];
//        $data[] = ["name"=>"Orders: ","value"=>$row->orders];
//        $data[] = ["name"=>"Quantity: ","value"=>$row->qty];
//        $data[] = ["name"=>"Total Gross: ","value"=>$row->gross.get_woocommerce_currency_symbol()];

    }
    function getProductsForSingle($productTable,$filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("
 SELECT product.product_id, product.product_name, count(DISTINCT product.order_id) as count_order,  ROUND(SUM(product.gross_sale),2) as gross,SUM(product.qty) as qty 
                    FROM {$this->getName()} as orders
                    LEFT JOIN $productTable as product ON product.order_id = orders.order_id AND product.type = orders.type
                    WHERE $filterColName = %d AND orders.type = %d AND orders.date BETWEEN %s AND %s
                    GROUP BY product.product_id
                    ORDER BY gross DESC
                
                    "
        ,$filterId,$type,$startDate,$endDate);
        //error_log("getProductsForSingle ".$sql);
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["id"=>$row->product_id,"name"=>$row->product_name,"qty"=>$row->qty,"orders"=>$row->count_order,"gross"=>$row->gross];
        }
        return $data;

    }

    function getCOGProductsForSingle($productTable,$filterColName,$filterId,$startDate,$endDate,$type) {
        $data = [];
        $sql = $this->wpdb->prepare("
 SELECT product.product_id, product.product_name, count(DISTINCT product.order_id) as count_order,  IF(meta_goods_cost_type.meta_value = 'fix',ROUND(SUM((product.gross_sale - meta_goods_cost_val.meta_value)*qty),2),ROUND(SUM((product.gross_sale -(product.gross_sale/100*meta_goods_cost_val.meta_value)*qty)),2)) as profit,SUM(product.qty) as qty 
                    FROM {$this->getName()} AS orders 
                    LEFT JOIN $productTable as product ON product.order_id = orders.order_id AND product.type = orders.type
                    LEFT JOIN {$this->wpdb->prefix}postmeta AS meta_goods_cost_val ON meta_goods_cost_val.post_id = product.product_id AND meta_goods_cost_val.meta_key = '_pixel_cost_of_goods_cost_val' 
                    LEFT JOIN {$this->wpdb->prefix}postmeta AS meta_goods_cost_type ON meta_goods_cost_type.post_id = product.product_id AND meta_goods_cost_type.meta_key = '_pixel_cost_of_goods_cost_type'
                    WHERE $filterColName = %d AND orders.type = %d AND orders.date BETWEEN %s AND %s
                    GROUP BY product.product_id
                    ORDER BY profit DESC
                
                    "
            ,$filterId,$type,$startDate,$endDate);

        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $data[] = ["id"=>$row->product_id,"name"=>$row->product_name,"qty"=>$row->qty,"orders"=>$row->count_order,"gross"=>$row->profit];
        }
        return $data;

    }









    function updateOrder($orderId,$gross_sale,$net_sale,$total_sale,$type) {

        if($gross_sale < 0) $gross_sale = 0;
        if($net_sale < 0) $net_sale = 0;
        if($total_sale < 0) $total_sale = 0;

        return $this->wpdb->update($this->getName(),
            ["gross_sale" => $gross_sale, "net_sale" => $net_sale,"total_sale" => $total_sale],
            ["order_id" => $orderId,'type' => $type],
            ['%f','%f'],
            ['%d','%d']
        );
    }


    function insertOrder($params) {
        $status =  $this->wpdb->insert($this->getName(),$params,
            ['%d',
                '%f','%f','%f',
                '%d','%s',
                '%d','%d','%d','%d', '%d','%d','%d',
                '%d','%d','%d','%d', '%d','%d','%d']
        );
        if(!$status) {
            error_log("pys insertOrder error: ".$this->wpdb->last_error);
        }
        return $status;
    }


    /**
     * @param int $typeId
     */
    function clear($typeId) {
        $this->wpdb->delete($this->getName(),['type'=>$typeId],['%d']);
    }

    function getUtmValues($utms) {
        $utms = explode("|",$utms);
        $utmList = [];
        $data = [];
        $utmKeys = [
            "utm_source",
            "utm_medium",
            "utm_campaign",
            "utm_content",
            "utm_term",
        ];

        foreach($utms as $utm) {
            $item = explode(":",$utm);
            $name = $item[0];
            $value = !isset($item[1]) || $item[1] == "undefined" ? "" : $item[1];
            $utmList[$name] = $value;

        }
        foreach ($utmKeys as $key) {
            if(key_exists($key,$utmList)) {
                $data[$key] = $utmList[$key];
            }
        }
        return $data;
    }

}