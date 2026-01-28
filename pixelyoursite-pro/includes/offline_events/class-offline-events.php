<?php

namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once PYS_PATH.'/includes/offline_events/class-offline-export-file.php';
require_once PYS_PATH.'/includes/offline_events/class-offline-db.php';
class OfflineEvents {
    private static $_instance;
    private $exportTypes = ['export_last_time','export_by_date','export_all'];
    private $exportedFile;

    private $seenEmails = [];
    private $seenPhones = [];

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {
// Fb offline export
        add_action( 'wp_ajax_pys_woo_get_order_count', array( $this, 'woo_get_order_count' ) );
        add_action( 'wp_ajax_pys_woo_generate_offline_events_report', array( $this, 'woo_generate_offline_events_report' ) );
        add_action( 'wp_ajax_pys_woo_generate_all_offline_events_report', array( $this, 'woo_generate_all_data_offline_events_report' ) );

        add_action( 'wp_ajax_pys_woo_generate_all_customers_report', array( $this, 'woo_generate_all_customers_report' ) );
    }

    /**
     * @param String $exportType
     * @param \DateTime $startDate
     * @param \DateTime$endDate
     * @return String
     */
    private function getFineName($exportType,$startDate,$endDate,$key) {
        if($exportType == "export_all") {
            return date("Y_m_d",time())."-".$exportType."-".$key;
        } else {
            return date("Y_m_d",time())."-".$exportType."-".$startDate->format("Y_m_d")."-".$endDate->format("Y_m_d")."-".$key;
        }
    }

    private function getFilePath($fileName) {
        return trailingslashit( PYS_PATH ).'tmp/'.$fileName.".csv";
    }

    public function getFileUrl($fileName) {
        return trailingslashit( PYS_URL ).'tmp/'.$fileName.".csv";
    }

    private function getCustomerFilePath($fileName) {
        return trailingslashit( PYS_PATH ).'tmp_customers/'.$fileName.".csv";
    }
    public function getCustomerFileUrl($fileName) {
        return trailingslashit( PYS_URL ).'tmp_customers/'.$fileName.".csv";
    }

    private function validateExportType($type) {

        if(!in_array($type, $this->exportTypes)) {
            return  "export_all";
        }
        return $type;
    }

    /**
     * @param String $type
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param int $page
     * @return int
     */
    public function wooExportPurchase($type,$startDate,$endDate,$page,$orderStatus,$fileName) {
        $this->exportedFile = new CSVWriterFile(
            array( 'order_id','email', 'phone', 'fn', 'ln', 'ct', 'st', 'country', 'zip','event_name','event_time', 'value','currency','content_ids' )
        );
        $filePath = $this->getFilePath($fileName);
        $results = OfflineEventsDb::getOrderIds($page,$type,$startDate,$endDate,$orderStatus);
        $this->exportedFile->openFile($filePath,$page);

        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $orderIdPrefix = PYS()->getOption("woo_order_id_prefix");

        foreach ($results as $row) {
            $order_id = $row->ID;

            $order = wc_get_order($order_id);
            if ( is_a( $order, 'WC_Order_Refund' ) ) {
                $order = wc_get_order( $order->get_parent_id() );
            }

            if ( $order == null ) {
                continue;
            }
            $total = $order->get_total();
            $args = ["products"=>[]];
            $ids = [];

            foreach($order->get_items() as $line_item) {
                if( !($line_item instanceof \WC_Order_Item_Product)) continue;
                $product_id = empty($line_item['variation_id']) ? $line_item['product_id'] : $line_item['variation_id'];
                $product = wc_get_product($product_id);
                if(!$product) continue;
                $price = getWooProductPriceToDisplay($product->get_id(),1,-1);
                $args["products"][] = [
                    'product_id'    => $product->get_id(),
                    'parent_id'     => $product->get_parent_id(),
                    'type'          => $product->get_type(),
                    'quantity'      => $line_item['qty'],
                    'price'         => $price, // price for single product
                    'total'         => \PixelYourSite\pys_round($line_item['total']),
                    'total_tax'     => pys_round($line_item['total_tax']),
                    'subtotal'      => pys_round($line_item['subtotal']),
                    'subtotal_tax'  => pys_round($line_item['subtotal_tax']),
                ];
                $ids[] =$product->get_id();
            }

            $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$args);

            //if(PYS()->getOption("woo_advance_purchase_fb_enabled") ) {//send fb server events
                $data = [
                    $orderIdPrefix.$order->get_id(),
                    $order->get_billing_email(),
                    $order->get_billing_phone(),
                    $order->get_billing_first_name(),
                    $order->get_billing_last_name(),
                    $order->get_billing_city(),
                    $order->get_billing_state(),
                    $order->get_billing_country(),
                    $order->get_billing_postcode(),
                    is_a( $order, 'WC_Order_Refund' ) || $order->get_status() == 'refunded' ? "Refund" : "Purchase",
                    $order->get_date_created()->date("Y-m-d\\TH:i:s\\Z"),
                    $value,
                    $order->get_currency(),
                    implode(",",$ids),
                ];
                $data = apply_filters("pys_offline_events_data",$data,$order);
                $this->exportedFile->writeLine($data);
            //}
        }

        $this->exportedFile->closeFile();
        return count($results);
    }

    /**
     * @param String $type
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param int $page
     * @return int
     */
    public function wooExporAllDataPurchase($type,$startDate,$endDate,$page,$orderStatus,$fileName) {

        $this->exportedFile = new CSVWriterFile(
            array( 'order_id','email', 'phone', 'fn', 'ln', 'ct', 'st', 'country', 'zip','event_name','event_time', 'value', 'cost', 'profit','currency','content_ids','traffic_source','landing','utm_source','utm_campaign','utm_term','utm_content','last_traffic_source','last_landing','last_utm_source','last_utm_campaign','last_utm_term','last_utm_content' )
        );
        $filePath = $this->getFilePath($fileName);
        $results = OfflineEventsDb::getOrderIds($page,$type,$startDate,$endDate,$orderStatus);
        $this->exportedFile->openFile($filePath,$page);


        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $orderIdPrefix = PYS()->getOption("woo_order_id_prefix");

        foreach ($results as $row) {
            $order_id = $row->ID;

            $order = wc_get_order($order_id);
            if ( is_a( $order, 'WC_Order_Refund' ) ) {
                $order = wc_get_order( $order->get_parent_id() );
            }
            if ( $order == null ) {
                continue;
            }
            $total = $order->get_total();
            $args = ["products"=>[]];
            $ids = [];
            $enrichData = $order->get_meta('pys_enrich_data');
            $landing = isset($enrichData['pys_landing']) ? $enrichData['pys_landing'] : "";
            $source = isset($enrichData['pys_source']) ? $enrichData['pys_source']: "";
            $utmData = isset($enrichData['pys_utm']) ? $enrichData['pys_utm']: "";

            $lastLanding = isset($enrichData['last_pys_landing']) ? $enrichData['last_pys_landing'] : "";
            $lastSource = isset($enrichData['last_pys_source']) ? $enrichData['last_pys_source']: "";
            $lastUtmData = isset($enrichData['last_pys_utm']) ? $enrichData['last_pys_utm']: "";
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
            foreach($order->get_items() as $line_item) {
                if( !($line_item instanceof \WC_Order_Item_Product)) continue;
                $product_id = empty($line_item['variation_id']) ? $line_item['product_id'] : $line_item['variation_id'];
                $product = wc_get_product($product_id);
                if(!$product) continue;
                $price = getWooProductPriceToDisplay($product->get_id(),1,-1);
                $args["products"][] = [
                    'product_id'    => $product->get_id(),
                    'parent_id'     => $product->get_parent_id(),
                    'type'          => $product->get_type(),
                    'quantity'      => $line_item['qty'],
                    'price'         => $price, // price for single product
                    'total'         => \PixelYourSite\pys_round($line_item['total']),
                    'total_tax'     => pys_round($line_item['total_tax']),
                    'subtotal'      => pys_round($line_item['subtotal']),
                    'subtotal_tax'  => pys_round($line_item['subtotal_tax']),
                ];
                $ids[] =$product->get_id();
            }

            $value = getProductCogOrder($args);
            $total = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$args);
            //if(PYS()->getOption("woo_advance_purchase_fb_enabled") ) {//send fb server events
            $data = [
                $orderIdPrefix.$order->get_id(),
                $order->get_billing_email(),
                $order->get_billing_phone(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_country(),
                $order->get_billing_postcode(),
                is_a( $order, 'WC_Order_Refund' ) || $order->get_status() == 'refunded' ? "Refund" : "Purchase",
                $order->get_date_created()->date("Y-m-d\\TH:i:s\\Z"),
                $total,
                $value['cost'],
                $value['profit'],
                $order->get_currency(),
                implode(",",$ids)
            ];
            foreach ($tableParams as $key => $value) {
                $data[$key] = $value;
            }
            $data = apply_filters("pys_offline_events_data",$data,$order);
            $this->exportedFile->writeLine($data);
            //}
        }

        $this->exportedFile->closeFile();
        return count($results);
    }

    public function getUtmValues($utms) {
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
    public function wooExportUniqueUsers($type, $use_crypto, $startDate, $endDate, $page, $orderStatus, $fileName) {
        $this->exportedFile = new CSVWriterFile(
            array('email', 'phone', 'first_name', 'last_name', 'country', 'zip')
        );
        $filePath = $this->getCustomerFilePath($fileName);
        $results = OfflineEventsDb::getOrderIds($page, $type, $startDate, $endDate, $orderStatus);
        $this->exportedFile->openCustomerFile($filePath, $page);

        $countUnique = 0;

        $this->seenEmails = get_transient('seenEmails_'.$type) ?? [];
        $this->seenPhones = get_transient('seenPhones_'.$type) ?? [];

        foreach ($results as $row) {
            $order_id = $row->ID;

            $order = wc_get_order($order_id);
            if (is_a($order, 'WC_Order_Refund')) {
                $order = wc_get_order($order->get_parent_id());
            }

            if ($order == null) {
                continue;
            }

            // We receive the user's email and phone number
            $email = $order->get_billing_email();
            $phone = $order->get_billing_phone();

            // We skip if the entry with this email or phone number has already been processed
	        if (empty($email) && empty($phone)) {
		        continue;
	        }
            if (!empty($email) && isset($this->seenEmails[$email])) {
                continue;
            }
            if (!empty($phone) && isset($this->seenPhones[$phone])) {
                continue;
            }

            // Add email and phone number to the corresponding arrays
            if (!empty($email)) {
                $this->seenEmails[$email] = true;
            }
            if (!empty($phone)) {
                $this->seenPhones[$phone] = true;
            }

            $email = $use_crypto ? $this->processAndHash($email, 'email') : $email;
            $phone = $use_crypto ? $this->processAndHash($phone, 'phone') : $phone;
            $firstName = $use_crypto ? $this->processAndHash($order->get_billing_first_name(), 'name' ) : $order->get_billing_first_name();
            $lastName = $use_crypto ?  $this->processAndHash($order->get_billing_last_name(), 'surname' ) : $order->get_billing_last_name();
            $country = $order->get_billing_country();
            $zip = $order->get_billing_postcode();
            $data = [
                $email,
                $phone,
                $firstName,
                $lastName,
                $country,
                $zip
            ];

            $this->exportedFile->writeLine($data);
            $countUnique++;
        }

        $this->exportedFile->closeFile();

        set_transient('seenEmails_'.$type, $this->seenEmails, 60*60);
        set_transient('seenPhones_'.$type, $this->seenPhones, 60*60);
        // Returning the number of unique records
        return $countUnique;
    }
    public function isSha256($str) {
        return preg_match('/^[a-f0-9]{64}$/', $str);
    }
    public function processAndHash($value, $type) {
        if(is_array($value)){
            $value = $value[0];
        }
        if (!empty($value) && !$this->isSha256($value)) {
            switch ($type) {
                case 'email':
	                $value = preg_replace('/\s+/', '', strtolower(trim($value)));
                    break;
                case 'phone':
	                $value = preg_replace('/\D/', '', $value);
	                if (substr($value, 0, 1) !== '+') {
		                $value = '+' . $value;
	                }
                    break;
                case 'name':
                case 'surname':
		            $value = preg_replace('/[\d\W]/u', '', $value); // Using `/u` to handle Unicode
		            $value = strtolower(trim($value));
                    $value = trim($value);
                    break;
                case 'street':
	                $value = preg_replace('/\s+/', '', strtolower($value));
                    break;
            }
            $value = hash('sha256', $value);
        }
        return $value;
    }

    public function woo_get_order_count() {
        if ( ! PYS()->adminSecurityCheck()
            || (!wp_verify_nonce($_REQUEST['_wpnonce'],"woo_generate_export_wpnonce")
                && !wp_verify_nonce($_REQUEST['_wpnonce'],"customer_generate_export_wpnonce"))
        ) {
            return;
        }
        $type = sanitize_text_field($_POST['type']);
        set_transient('seenEmails_'.$type, [], 60*60);
        set_transient('seenPhones_'.$type, [], 60*60);
        $start = date_create($_POST['start']);
        $end = date_create($_POST["end"]);
        $orderStatus = (array)$_POST['order_status'];

        $count = OfflineEventsDb::getOrderCount($type, $start, $end,$orderStatus);

        wp_send_json_success(['count' => $count],200);
    }

    public function woo_generate_offline_events_report() {
        if ( ! PYS()->adminSecurityCheck() ) {
            return;
        }

        $type = $this->validateExportType($_POST['type']);
        $page = absint($_POST['page']);
        $key = intval($_POST['key']);

        $start = isset($_POST['start']) ? $_POST['start'] : "now";
        $end = isset($_POST['end']) ? $_POST['end'] : "now";
        $orderStatus = (array)$_POST['order_status'];

        $startDate = date_create($start);
        $endDate = date_create($end);
        $name = $this->getFineName($type,date_create($start),date_create($end),$key);
        $fileUrl = $this->getFileUrl($name);


        PYS()->updateOptions(["woo_last_export_date" => $endDate->format("Y-m-d H:i:s")]);


        $count = $this->wooExportPurchase(
            $type,
            $startDate,
            $endDate,
            $page,
            $orderStatus,
            $name
        );

        wp_send_json_success(['count' => $count,'file_url'=>$fileUrl,"file_name"=>$name],200);
    }
    public function woo_generate_all_data_offline_events_report() {
        if ( ! PYS()->adminSecurityCheck() ) {
            return;
        }

        $type = $this->validateExportType($_POST['type']);
        $page = absint($_POST['page']);
        $key = (int)$_POST['key'];

        $start = isset($_POST['start']) ? $_POST['start'] : "now";
        $end = isset($_POST['end']) ? $_POST['end'] : "now";
        $orderStatus = (array)$_POST['order_status'];

        $startDate = date_create($start);
        $endDate = date_create($end);
        $name = $this->getFineName($type,date_create($start),date_create($end),$key);
        $fileUrl = $this->getFileUrl($name);


        PYS()->updateOptions(["woo_last_export_date" => $endDate->format("Y-m-d H:i:s")]);


        $count = $this->wooExporAllDataPurchase(
            $type,
            $startDate,
            $endDate,
            $page,
            $orderStatus,
            $name
        );

        wp_send_json_success(['count' => $count,'file_url'=>$fileUrl,"file_name"=>$name],200);
    }
    public function woo_generate_all_customers_report()
    {
        if ( ! PYS()->adminSecurityCheck() ) {
            return;
        }

        $type = $this->validateExportType($_POST['type']);
        $use_crypto = isset($_POST['use_crypto']) ? (bool)$_POST['use_crypto'] : false;
        $page = absint($_POST['page']);
        $key = (int) $_POST['key'];

        $start = isset($_POST['start']) ? $_POST['start'] : "now";
        $end = isset($_POST['end']) ? $_POST['end'] : "now";
        $orderStatus = (array)$_POST['order_status'];

        $startDate = date_create($start);
        $endDate = date_create($end);
        $name = $this->getFineName($type,date_create($start),date_create($end),$key);
        $fileUrl = $this->getCustomerFileUrl($name);
        PYS()->updateOptions(["woo_last_export_customer_date" => $endDate->format("Y-m-d H:i:s")]);


        $count = $this->wooExportUniqueUsers(
            $type,
            $use_crypto,
            $startDate,
            $endDate,
            $page,
            $orderStatus,
            $name
        );

        wp_send_json_success(['count' => $count,'file_url'=>$fileUrl,"file_name"=>$name],200);
    }
}

/**
 * @return OfflineEvents
 */
function OfflineEvents() {
    return OfflineEvents::instance();
}