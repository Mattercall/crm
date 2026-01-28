<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
abstract class OrderStatistics {

    static $SYNC_STATUS_START = "START";
    static $SYNC_STATUS_FINISH = "FINISH";
    static $MODEL_FIRST_VISIT = "first_visit";
    static $MODEL_LAST_VISIT = "last_visit";

    /**
     * @var StatOrdersTable $orderTable
     * @var StatProductsTable $productTable
     * @var StatLandingTable $landingTable
     * @var StatTrafficTable $trafficTable
     * @var StatUtmCampaingTable $utmCampaing
     */
    public $orderTable;
    public $productTable;
    public $landingTable;
    public $trafficTable;
    public $utmCampaing;
    public $utmContent;
    public $utmMedium;
    public $utmSource;
    public $utmTerme;
    public $perPage = 30;
    public function __construct($orderTable,$productTable,$landingTable,$trafficTable,$utmCampaing,$utmContent,$utmMedium,$utmSource,$utmTerm) {
        $this->orderTable = $orderTable;
        $this->productTable = $productTable;
        $this->landingTable = $landingTable;
        $this->trafficTable = $trafficTable;
        $this->utmCampaing = $utmCampaing;
        $this->utmContent = $utmContent;
        $this->utmMedium = $utmMedium;
        $this->utmSource = $utmSource;
        $this->utmTerme = $utmTerm;
    }

    abstract function setImportPage($page);
    abstract function setImportStatus($page);
    abstract function setOrdersStatus($status);
    abstract function getTypeId();
    abstract function importOrders($page,$perPage);
    abstract function getSyncStatus();
    abstract function getSyncPage();
    abstract function getOrdersCount($statuses);

    function runSyncs() {
        if ( ! PYS()->adminSecurityCheck()
            || !isset($_REQUEST['_wpnonce'])
            || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), "html_report_wpnonce" )
        ) {
            return;
        }
        $page = isset($_POST['page']) ? absint($_POST['page']) : 0;

        $imported = $this->importOrders($page,$this->perPage);
        $this->setImportPage($page);
        $isLastPage = $imported != $this->perPage;
        if($isLastPage) {
            $this->setImportStatus(OrderStatistics::$SYNC_STATUS_FINISH);
        }

        wp_send_json_success([
            "page" => $page,
            "isLastPage" => $isLastPage,
        ]);
    }

    function changeOrderStatus() {
        if ( ! PYS()->adminSecurityCheck()
            || !isset($_REQUEST['_wpnonce'])
            || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), "html_report_wpnonce" )
        ) {
            return;
        }
        $orders = isset($_POST['orders']) && is_array($_POST['orders']) ? array_map('sanitize_text_field', wp_unslash($_POST['orders'])) : array();
        if(count($orders)>0) {
            $this->setImportPage(1);
            $this->setImportStatus(OrderStatistics::$SYNC_STATUS_START);
            $this->setOrdersStatus($orders);

            $this->orderTable->clear($this->getTypeId());
            $this->productTable->clear($this->getTypeId());
            wp_send_json_success([
                // "pages" => ceil($allCount/$this->perPage)
            ]);
        } else {
            wp_send_json_error();
        }
    }

    function isOrderExist($orderId) {
        return $this->orderTable->isExistOrder($orderId,$this->getTypeId());
    }

    function exportGPT($label,$startDate,$endDate,$filter_type,$model,$cog) {

        $endDate = date('Y-m-d', strtotime($endDate. ' + 1 days'));

        $typeId = $this->getTypeId();
        $filterColName = $this->getCollNameByTag($filter_type,$model);
        $filterTable = $this->getTableByTag($filter_type);
        $rows = $this->orderTable->getDataFull($startDate,$endDate,$typeId);
        $row_items = array( 'order_id','gross_sale','net_sale','total_sale','cost','profit', 'event_time','currency','product_ids', 'product_names', 'payment_method', 'user_id', 'ct', 'st', 'country', 'zip' );

        $row_items[]='tracking_type';

        // Sanitize request inputs
        $change_data = isset($_REQUEST['change-data']) && is_array($_REQUEST['change-data'])
            ? array_map('sanitize_text_field', wp_unslash($_REQUEST['change-data']))
            : array();
        $gpt_visit_model = isset($_REQUEST['gpt_visit_model'])
            ? sanitize_text_field( wp_unslash( $_REQUEST['gpt_visit_model'] ) )
            : '';

        if(!empty($change_data)){
            $row_items = array_merge($row_items,$change_data);
        }
        if(!empty($gpt_visit_model)) {
            switch ($gpt_visit_model) :
                case 'first_visit' :
                    $row_items = array_merge($row_items, array('traffic_source','landing_page','utm_source','utm_medium','utm_campaign','utm_term','utm_content'));
                    break;
                case 'last_visit' :
                    $row_items = array_merge($row_items, array('last_traffic_source','last_landing_page','last_utm_source','last_utm_medium','last_utm_campaign','last_utm_term','last_utm_content'));
                    break;
                case 'all' :
                    $row_items = array_merge($row_items, array('traffic_source','landing_page','utm_source','utm_medium','utm_campaign','utm_term','utm_content','last_traffic_source','last_landing_page','last_utm_source','last_utm_medium','last_utm_campaign','last_utm_term','last_utm_content'));
                    break;
            endswitch;
        } else {
            $row_items = array_merge($row_items, array('traffic_source','landing_page','utm_source','utm_medium','utm_campaign','utm_term','utm_content'));
        }


        $exportedFile = new CSVWriterFile($row_items);
        $exportedFile->openFile("php://output");
        foreach ($rows as $row) {
            $data_line = [
                $row['order_id'],
                $row['gross_sale'],
                $row['net_sale'],
                $row['total_sale'],
                $row['cost'],
                $row['profit'],

                $row['date_created'],
                $row['currency'],
                $row['ids'],
                $row['product_names'],
                $row['payment_method'],

                $row['user_id'],

                $row['city'],
                $row['state'],
                $row['country'],
                $row['postcode']
            ];

            $tracking_type = $this->getTrackingType($row['order_id']);
            $data_line[] = $tracking_type;


            if(!empty($change_data)){
                foreach ($change_data as $change_data_item){
                    $data_line[] = isset($row[$change_data_item]) ? $row[$change_data_item] : '';
                }
            }
            if(!empty($gpt_visit_model)) {
                switch ($gpt_visit_model) :
                    case 'first_visit' :
                        $data_line = array_merge($data_line, array(
                            $row['traffic_source'],
                            $row['landing'],
                            $row['utm_source'],
                            $row['utm_medium'],
                            $row['utm_campaing'],
                            $row['utm_term'],
                            $row['utm_content']
                        ));
                        break;
                    case 'last_visit' :
                        $data_line = array_merge($data_line, array(
                            $row['last_traffic_source'],
                            $row['last_landing'],
                            $row['last_utm_source'],
                            $row['last_utm_medium'],
                            $row['last_utm_campaing'],
                            $row['last_utm_term'],
                            $row['last_utm_content'],
                        ));
                        break;
                    case 'all' :
                        $data_line = array_merge($data_line, array(
                            $row['traffic_source'],
                            $row['landing'],
                            $row['utm_source'],
                            $row['utm_medium'],
                            $row['utm_campaing'],
                            $row['utm_term'],
                            $row['utm_content'],
                            $row['last_traffic_source'],
                            $row['last_landing'],
                            $row['last_utm_source'],
                            $row['last_utm_medium'],
                            $row['last_utm_campaing'],
                            $row['last_utm_term'],
                            $row['last_utm_content'],
                        ));
                        break;
                endswitch;
            } else {
                $data_line = array_merge($data_line, array(
                    $row['traffic_source'],
                    $row['landing'],
                    $row['utm_source'],
                    $row['utm_medium'],
                    $row['utm_campaing'],
                    $row['utm_term'],
                    $row['utm_content']
                ));
            }
            $exportedFile->writeLine($data_line);
        }

        $exportedFile->closeFile();

    }

    function exportFullCurrent($label,$startDate,$endDate,$filter_type,$model,$cog) {

        $endDate = date('Y-m-d', strtotime($endDate. ' + 1 days'));


        $typeId = $this->getTypeId();
        $filterColName = $this->getCollNameByTag($filter_type,$model);
        $filterTable = $this->getTableByTag($filter_type);
        if($cog != 'cog')
        {
            $rows = $this->orderTable->getDataAll($filterTable->getName(),$filterColName,$startDate,$endDate,$typeId);
            $exportedFile = new CSVWriterFile(
                array( $label,'Orders','Gross sale', 'Net sale')
            );
        }
        else{
            $rows = $this->orderTable->getCOGDataAll($filterTable->getName(),$filterColName,$startDate,$endDate,$typeId);
            $exportedFile = new CSVWriterFile(
                array( $label,'Orders','Cost', 'Profit')
            );
        }


        $exportedFile->openFile("php://output");
        foreach ($rows as $row) {
            $exportedFile->writeLine([
                $row->item_value,
                $row->count,
                $row->gross,
                $row->net
            ]);
        }

        $exportedFile->closeFile();

    }

    function exportSingle($startDate,$endDate,$filterId,$dataType,$filter_type,$model,$cog) {

        $typeId = $this->getTypeId();
        $filterColName = $this->getCollNameByTag($filter_type,$model);
        $endDate = date('Y-m-d', strtotime($endDate. ' + 1 days'));

        switch ($dataType) {
            case 'dates': {
                if($cog != 'cog') {
                    $exportedFile = new CSVWriterFile(
                        array("Date", "Orders", 'Gross sale', 'Net sale', 'Total sale')
                    );
                    $data = $this->orderTable->getDatesForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                }
                else
                {
                    $exportedFile = new CSVWriterFile(
                        array("Date", "Orders", 'Cost', 'Profit', 'Total sale')
                    );
                    $data = $this->orderTable->getCOGDatesForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                }

                $exportedFile->openFile("php://output");
                foreach ($data as $row) {
                    $exportedFile->writeLine([
                        $row['x'],
                        $row['count'],
                        $row['gross'],
                        $row['net'],
                        $row['total']
                    ]);
                }
                $exportedFile->closeFile();
            }break;
            case 'orders': {
                if($cog != 'cog') {
                    $exportedFile = new CSVWriterFile(
                        array( "Order ID",'Gross sale', 'Net sale', 'Total sale')
                    );
                    $data = $this->orderTable->getOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                }
                else
                {
                    $exportedFile = new CSVWriterFile(
                        array( "Order ID",'Cost', 'Profit', 'Total sale')
                    );
                    $data = $this->orderTable->getCOGOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                }

                $exportedFile->openFile("php://output");
                foreach ($data as $row) {
                    $exportedFile->writeLine([
                        $row['order_id'],
                        $row['gross'],
                        $row['net'],
                        $row['total']
                    ]);
                }
                $exportedFile->closeFile();
            }break;
            case 'products': {
                if($cog != 'cog') {
                    $exportedFile = new CSVWriterFile(
                        array( "Product Name",'Qty', 'Orders', 'Gross sale')
                    );
                    $data = $this->orderTable->getProductsForSingle($this->productTable->getName(),$filterColName,$filterId,$startDate,$endDate,$typeId);
                }
                else
                {
                    $exportedFile = new CSVWriterFile(
                        array( "Product Name",'Qty', 'Orders', 'Profit')
                    );
                    $data = $this->orderTable->getCOGProductsForSingle($this->productTable->getName(),$filterColName,$filterId,$startDate,$endDate,$typeId);
                }

                $exportedFile->openFile("php://output");
                foreach ($data as $row) {
                    $exportedFile->writeLine([
                        $row['name'],
                        $row['qty'],
                        $row['orders'],
                        $row['gross'],
                    ]);
                }
                $exportedFile->closeFile();
            }break;

        }
    }


    private function getCollNameByTag($tag,$model) {
        switch ($tag) {
            case "traffic_source": {
                $name = 'traffic_source_id';
            }break;
            case "traffic_landing": {
                $name = 'landing_id';
            }break;
            case "utm_source": {
                $name = 'utm_source_id';
            }break;
            case "utm_medium": {
                $name = 'utm_medium_id';
            }break;
            case "utm_campaign": {
                $name = 'utm_campaing_id';
            }break;
            case "utm_term": {
                $name = 'utm_term_id';
            }break;
            case "utm_content": {
                $name = 'utm_content_id';
            }break;
            default : $name = "";
        }

        if($model == self::$MODEL_FIRST_VISIT) {
            return $name;
        }
        return "last_".$name;
    }

    /**
     * @param $tag
     * @return StatValueTable | null
     */
    private function getTableByTag($tag) {
        switch ($tag) {
            case "traffic_source": {
                return $this->trafficTable;
            }break;
            case "traffic_landing": {
                return $this->landingTable;
            }break;
            case "utm_source": {
                return $this->utmSource;

            }break;
            case "utm_medium": {
                return $this->utmMedium;

            }break;
            case "utm_campaign": {
                return $this->utmCampaing;

            }break;
            case "utm_term": {
                return $this->utmTerme;

            }break;
            case "utm_content": {
                return $this->utmContent;
            }break;

            default: return null;
        }
    }
    /**
     * Ajax response
     */
    function loadStatSingleData() {
        if ( ! PYS()->adminSecurityCheck()
            || !isset($_REQUEST['_wpnonce'])
            || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), "html_report_wpnonce" )
        ) {
            return;
        }
        $model = isset($_POST["model"]) ? sanitize_text_field( wp_unslash( $_POST["model"] ) ) : ''; // first_visit or last_visit
        $startDate = isset($_POST["start_date"]) ? sanitize_text_field( wp_unslash( $_POST["start_date"] ) ) : '';
        $endDate = isset($_POST["end_date"]) ? sanitize_text_field( wp_unslash( $_POST["end_date"] ) ) : '';
        $endDate = date('Y-m-d', strtotime($endDate. ' + 1 days'));
        $filterId = isset($_POST["filter_id"]) ? sanitize_text_field( wp_unslash( $_POST["filter_id"] ) ) : '';
        $typeId = $this->getTypeId();
        $dataType = isset($_POST['single_table_type']) ? sanitize_text_field( wp_unslash( $_POST['single_table_type'] ) ) : '';
        $filter_type = isset($_POST["filter_type"]) ? sanitize_text_field( wp_unslash( $_POST["filter_type"] ) ) : '';
        $filterColName = $this->getCollNameByTag($filter_type,$model);
        $cog = isset($_POST["cog"]) ? sanitize_text_field( wp_unslash( $_POST["cog"] ) ) : '';
        $data = [];
        $chart = [];
        $max = 0;
        switch ($dataType) {
            case 'dates': {
                if($cog!='cog' || !isPixelCogActive())
                {
                    $data = $this->orderTable->getDatesForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                    $total = $this->orderTable->getFilterSingleTotal($dataType,$filterColName,$filterId,$typeId,$startDate,$endDate);
                }
                else
                {
                    $data = $this->orderTable->getCOGDatesForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                    $total = $this->orderTable->getCOGFilterSingleTotal($dataType,$filterColName,$filterId,$typeId,$startDate,$endDate);
                }
                $max = $total[0]["value"];
            }break;
            case 'orders': {
                if($cog!='cog' || !isPixelCogActive())
                {
                    $data = $this->orderTable->getOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                    $total = $this->orderTable->getFilterSingleTotal($dataType,$filterColName,$filterId,$typeId,$startDate,$endDate);
                }
                else
                {
                    $data = $this->orderTable->getCOGOrdersForSingle($filterColName,$filterId,$startDate,$endDate,$typeId);
                    $total = $this->orderTable->getCOGFilterSingleTotal($dataType,$filterColName,$filterId,$typeId,$startDate,$endDate);
                }
                $max = $total[0]["value"];
            }break;
            case 'products': {
                $orderBy = isset($_POST["order_by"]) ? sanitize_text_field( wp_unslash( $_POST["order_by"] ) ) : '';
                $order = isset($_POST["sort"]) ? sanitize_text_field( wp_unslash( $_POST["sort"] ) ) : '';
                $perPage = isset($_POST["perPage"]) ? absint($_POST["perPage"]) : 30;
                $page = isset($_POST["page"]) ? absint($_POST["page"]) : 1;

                $orders = $this->orderTable->getProductsOrders($filterColName,$filterId,$startDate,$endDate,$typeId);
                if($cog!='cog' || !isPixelCogActive())
                {
                    $data = $this->productTable->getProductsList($orders,$orderBy,$order,$typeId,$perPage,$page);
                    $productsId = array_map(function ($item) {return $item['id'];},$data);
                    $chart = $this->productTable->getProductsStat($productsId,$orders,$typeId,$orderBy,$order);
                    $total = $this->productTable->getProductsTotal($orders,$typeId);
                }
                else
                {
                    $data = $this->productTable->getCOGProductsList($orders,$orderBy,$order,$typeId,$perPage,$page);
                    $productsId = array_map(function ($item) {return $item['id'];},$data);
                    $chart = $this->productTable->getCOGProductsStat($productsId,$orders,$typeId,$orderBy,$order);
                    $total = $this->productTable->getCOGProductsTotal($orders,$typeId);
                }



                $max = $total[0]["value"];
            } break;
        }

        wp_send_json_success([
            "data" => $data,
            "chart" => $chart,
            "total" => $total,
            "max"   => $max,
            'cog' => $cog,
            'cogActive' => isPixelCogActive()
        ]);
        wp_die();
    }
    function loadStatData() {
        if ( ! PYS()->adminSecurityCheck()
            || !isset($_REQUEST['_wpnonce'])
            || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), "html_report_wpnonce" )
        ) {
            return;
        }
        $model = isset($_POST["model"]) ? sanitize_text_field( wp_unslash( $_POST["model"] ) ) : ''; // first_visit or last_visit
        $orderBy = isset($_POST["order_by"]) ? sanitize_text_field( wp_unslash( $_POST["order_by"] ) ) : '';
        $sort = isset($_POST["sort"]) ? sanitize_text_field( wp_unslash( $_POST["sort"] ) ) : '';
        $startDate = isset($_POST["start_date"]) ? sanitize_text_field( wp_unslash( $_POST["start_date"] ) ) : '';
        $endDate = isset($_POST["end_date"]) ? sanitize_text_field( wp_unslash( $_POST["end_date"] ) ) : '';
        $endDate = date('Y-m-d', strtotime($endDate. ' + 1 days'));

        $page = isset($_POST["page"]) ? absint($_POST["page"]) - 1 : 0;
        $perPage = isset($_POST["perPage"]) ? absint($_POST["perPage"]) : 30;
        $typeId = $this->getTypeId();
        $filter_type = isset($_POST["filter_type"]) ? sanitize_text_field( wp_unslash( $_POST["filter_type"] ) ) : '';
        $filterColName = $this->getCollNameByTag($filter_type,$model);
        $filterTable = $this->getTableByTag($filter_type);
        $cog = isset($_POST["cog"]) ? sanitize_text_field( wp_unslash( $_POST["cog"] ) ) : '';

        $items = [];
        $max = 0;



            if($cog!='cog' || !isPixelCogActive()){
                $total = ["total_sale"=>0,"net_sale"=>0,"gross_sale"=>0,"count"=>0];
                $filterItems = $this->orderTable->getSumForFilter($filterTable->getName(),$filterColName,$startDate,$endDate,$page * $perPage,$perPage,$typeId,$orderBy,$sort);
                if(count($filterItems["ids"]) > 0) {
                    $data = $this->orderTable->getData($filterTable->getName(), $filterColName, $filterItems["ids"], $startDate, $endDate, $typeId, $orderBy, $sort);
                    $total = $this->orderTable->getFilterTotal($filterColName, $typeId, $startDate, $endDate, $model == OrderStatistics::$MODEL_FIRST_VISIT);
                    $items = array_values($data);
                    $max = $this->orderTable->getFilterCount($filterColName,$typeId,$startDate,$endDate);
                }
            }
            else
            {
                $total = ["total_sale"=>0,"profit"=>0,"cost"=>0,"count"=>0];
                $filterItems = $this->orderTable->getCOGSumForFilter($filterTable->getName(),$filterColName,$startDate,$endDate,$page * $perPage,$perPage,$typeId,$orderBy,$sort);
                if(count($filterItems["ids"]) > 0) {
                    $data = $this->orderTable->getCOGData($filterTable->getName(), $filterColName, $filterItems["ids"], $startDate, $endDate, $typeId, $orderBy, $sort);
                    $total = $this->orderTable->getCOGFilterTotal($filterColName, $typeId, $startDate, $endDate, $model == OrderStatistics::$MODEL_FIRST_VISIT);
                    $items = array_values($data);
                    $max = $this->orderTable->getFilterCount($filterColName,$typeId,$startDate,$endDate);
                }
            }





        $symbol = "";
        if($this->getTypeId() == 0) { // woo
            $symbol = get_woocommerce_currency_symbol();
        }
        if($this->getTypeId() == 1) { // woo
            $symbol = edd_currency_symbol();
        }
        if($cog!='cog' || !isPixelCogActive()){
            $label = array(
                ["name"=>"Orders: ","value"=>$total['count']],
                ["name"=>"Gross Sale: ","value"=>$symbol.$total['gross_sale']],
                ["name"=>"Net Sale: ","value"=>$symbol.$total['net_sale']],
                ["name"=>"Total Sale: ","value"=>$symbol.$total['total_sale']]
            );
        }
        else
        {
            $label = array(
                ["name"=>"Orders: ","value"=>$total['count']],
                ["name"=>"Cost: ","value"=>$symbol.$total['cost']],
                ["name"=>"Profit: ","value"=>$symbol.$total['profit']],
                ["name"=>"Total Sale: ","value"=>$symbol.$total['total_sale']]
            );
        }
        wp_send_json_success([
            "items_sum" => $filterItems,
            "items" => $items,
            "max" => $max,
            "total" => $label,
            'cog' => $cog,
            'cogActive' => isPixelCogActive(),
            'colName' => $filterTable->getColName()
        ]);


        wp_die();
    }





    function getUtmIds($utmData) {
        $data = [];
        if(!empty($utmData)) {
            $utms = explode("|", $utmData);
            foreach ($utms as $utm) {
                $item = explode(":", $utm);
                $name = $item[0];
                if (isset($item[1]) && $item[1] !== "undefined") {
                    switch ($name) {
                        case "utm_source":
                            $data[$name] = $this->utmSource->insert($item[1]);
                            break;
                        case "utm_medium":
                            $data[$name] = $this->utmMedium->insert($item[1]);
                            break;
                        case "utm_campaign":
                            $data[$name] = $this->utmCampaing->insert($item[1]);
                            break;
                        case "utm_term":
                            $data[$name] = $this->utmTerme->insert($item[1]);
                            break;
                        case "utm_content":
                            $data[$name] = $this->utmContent->insert($item[1]);
                            break;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Hook for woocommerce_checkout_order_created
     * Create new stat order
     */
    function addOrder($orderId,$params,$orderItems,$enrichData) {


        $landing = isset($enrichData['pys_landing']) ? $enrichData['pys_landing'] : "";
        $source = isset($enrichData['pys_source']) ? $enrichData['pys_source']: "";
        $utmData = isset($enrichData['pys_utm']) ? $enrichData['pys_utm']: "";

        $lastLanding = isset($enrichData['last_pys_landing']) ? $enrichData['last_pys_landing'] : "";
        $lastSource = isset($enrichData['last_pys_source']) ? $enrichData['last_pys_source']: "";
        $lastUtmData = isset($enrichData['last_pys_utm']) ? $enrichData['last_pys_utm']: "";

        $utmIds = $this->getUtmIds($utmData);
        $lastUtmIds = $this->getUtmIds($lastUtmData);

        $tableParams = ["traffic_source_id" => $this->trafficTable->insert($source),
            "landing_id" => $this->landingTable->insert($landing),
            "utm_source_id" => isset($utmIds['utm_source']) ? $utmIds['utm_source'] : null,
            "utm_medium_id" => isset($utmIds['utm_medium']) ? $utmIds['utm_medium'] : null,
            "utm_campaing_id" => isset($utmIds['utm_campaign']) ? $utmIds['utm_campaign'] : null,
            "utm_term_id" => isset($utmIds['utm_term']) ? $utmIds['utm_term'] : null,
            "utm_content_id" => isset($utmIds['utm_content']) ? $utmIds['utm_content'] : null,

            "last_traffic_source_id" => $this->trafficTable->insert($lastSource),
            "last_landing_id" => $this->landingTable->insert($lastLanding),
            "last_utm_source_id" => isset($lastUtmIds['utm_source']) ? $lastUtmIds['utm_source'] : null,
            "last_utm_medium_id" => isset($lastUtmIds['utm_medium']) ? $lastUtmIds['utm_medium'] : null,
            "last_utm_campaing_id" => isset($lastUtmIds['utm_campaign']) ? $lastUtmIds['utm_campaign'] : null,
            "last_utm_term_id" => isset($lastUtmIds['utm_term']) ? $lastUtmIds['utm_term'] : null,
            "last_utm_content_id" => isset($lastUtmIds['utm_content']) ? $lastUtmIds['utm_content'] : null
        ];

        $this->orderTable->insertOrder(array_merge($params,$tableParams));
        $this->productTable->addOrderProducts($orderItems);
    }

    /**
     * Update Gross Sale for order
     *
     * @param int $orderId
     * @param $gross_sale
     * @param $net_sale
     * @param $total_sale
     */
    function updateOrder($orderId,$gross_sale,$net_sale,$total_sale) {
        $this->orderTable->updateOrder($orderId,$gross_sale,$net_sale,$total_sale,$this->getTypeId());
    }

    /**
     * Delete payment from stat
     * @param $order_id
     */
    function deleteOrderWithProduct($order_id) {
        $this->orderTable->deleteOrder($order_id,$this->getTypeId());
        $this->productTable->deleteOrderProduct($order_id,$this->getTypeId());
    }

    function filterCharValue($value,$max) {
        if(strlen($value)>$max) {
            return substr($value,0,$max);
        }
        return $value;
    }

    function getTrackingType($order_id){
        $tracking_type = 'Not tracked';
        if (isWooUseHPStorage()) {
            // WooCommerce >= 3.0
            $order = wc_get_order($order_id);
            if ($order) {
                if($order->get_meta('_pys_advance_purchase_event_fired', true)){
                    $tracking_type = 'Advanced Purchase Tracking (APT)';
                }elseif($order->get_meta('_pys_purchase_event_fired', true)){
                    $tracking_type = 'Tag or API';
                }
            }

        } else {
            // WooCommerce < 3.0
            if(get_post_meta($order_id,'_pys_advance_purchase_event_fired', true)){
                $tracking_type = 'Advanced Purchase Tracking (APT)';
            }elseif(get_post_meta($order_id,'_pys_purchase_event_fired', true)){
                $tracking_type = 'Tag or API';
            }
        }
        return $tracking_type;
    }
}
