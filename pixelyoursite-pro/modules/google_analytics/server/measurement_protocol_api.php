<?php
namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
require_once PYS_PATH.'/modules/google_analytics/server/server_event_helper.php';

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\UserAddress;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Service;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Request\BaseRequest;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\UserProperties;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\UserProperty;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\UserData;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\UserDataItem;

use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Dto\Common\ConsentProperty;
use PYS_PRO_GLOBAL\Br33f\Ga4\MeasurementProtocol\Enum\ConsentCode;


/**
 * The Measurement Protocol API wrapper class.
 *
 * A basic wrapper around the GA Measurement Protocol HTTP API used for making
 * server-side API calls to track events.
 *
 */
class GaMeasurementProtocolAPI
{

    static $uaMap = [
        'traffic_source',
        'event_category',
    ];
    /** @var string endpoint for GA API */
    public $ga_url = 'https://www.google-analytics.com/collect';
    private $access_token = '';
    //public $ga_url = 'https://www.google-analytics.com/debug/collect'; //debug

    private static $_instance;
    private $isEnabled;
    private $isDebug;
    /**
     * Get singleton instance
     */
    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        $this->isEnabled = GA()->isServerApiEnabled();
        $this->isDebug = PYS()->getOption( 'debug_enabled' );

        if($this->isEnabled) {
            // AJAX handlers for fallback
            //add_action( 'wp_ajax_pys_ga_api_event',array($this,"catchAjaxEvent"));
            //add_action( 'wp_ajax_nopriv_pys_ga_api_event', array($this,"catchAjaxEvent"));
        }
    }


    /**
     * Send event in shutdown hook (not work in ajax)
     * @param SingleEvent[] $events
     */
    public function sendEventsAsync($events)
    {
        // not use
    }

    /**
     * Send Event Now
     *
     * @param SingleEvent[] $events
     */
    public function sendEventsNow($events)
    {
        foreach ($events as $event) {
            if ($this->isEventDisabledByFilter($event)) {
                continue;
            }

            $ids = $event->payload['trackingIds'];
            $this->sendEvent($ids, $event);
        }
    }

    private function sendEvent($tags, $event)
    {
        if (!$this->access_token) {
            $this->access_token = GA()->getApiTokens();
        }
        $timestampMicros = (int) (microtime(true) * 1000000);
        // Filter only GA4 tags (G-*), exclude Google Ads (AW-*) and others
        $ga4Tags = array_filter($tags, function($tag) {
            return $this->isGaV4($tag);
        });

        // Log tag filtering
        GA()->getLog()->debug('GA4 Measurement Protocol - Original tags', $tags);
        GA()->getLog()->debug('GA4 Measurement Protocol - Filtered GA4 tags', $ga4Tags);

        foreach ($ga4Tags as $tag) {
            $data = $event->getData();
            $params = $data['params'];
            $order_id = null;
            $order = null;
            if(!empty($data['woo_order']))
            {
                $order_id = $data['woo_order'];
                $order = wc_get_order($order_id);
                $type = 'woo';
            }
            elseif (!empty($data['edd_order']))
            {
                $order_id = $data['edd_order'];
                if (function_exists('edd_get_order')) {
                    $order = edd_get_order($order_id);
                }
                $type = 'edd';
            }
            else
            {
                continue;
            }



            if (empty($this->access_token[$tag]) || empty($order_id)) {
                continue;
            }

            $clientId = GaServerEventHelper::getGAStatFromOrder('clientId', $order_id, $type);
            if (empty($clientId)) {
                continue;
            }

            // Get session_id for specific measurement_id
            $sessionId = GaServerEventHelper::getGAStatFromOrder('session_id', $order_id, $type, $tag);
            GA()->getLog()->debug('GA4 session_id for measurement_id ' . $tag, $sessionId);

            $ga4Service = new Service($this->access_token[$tag]);
            $ga4Service->setMeasurementId($tag);

            $baseRequest = new BaseRequest();
            $baseRequest->setClientId($clientId);

            $eventData = GaServerEventHelper::mapSingleEventToServerDataGA4($event);
            $baseRequest->setTimestampMicros($timestampMicros);
            // Add session_id as event parameter if available
            if (!empty($sessionId)) {
                $eventData->setParamValue('session_id', $sessionId);
                $sessionNumber = GaServerEventHelper::getGAStatFromOrder('session_number', $order_id, $type, $tag);
                $eventData->setParamValue('session_number', $sessionNumber);
                GA()->getLog()->debug('Added session_id to event', $sessionId);
            }
            // Add custom properties efficiently
            // Check if order is auto-renewal
            $isRenewalOrder = $this->isRenewalOrder($order, $type);
            $renewalName = 'auto-renewal'. (isset($params['payment_method']) && !empty($params['payment_method']) !== null ? '-' . $params['payment_method'] : '');

            // Get enrichment data from order meta
            $enrichData = $this->getOrderEnrichData($order, $type);
            $is_last_visit = PYS()->getOption('visit_data_model') === 'last_visit';

            if(PYS()->getOption( 'track_traffic_source' )){
                if ($isRenewalOrder) {
                    $eventData->setParamValue('traffic_source', $renewalName);
                } elseif (!empty($enrichData)) {
                    // Get traffic source from order meta
                    if ($is_last_visit && isset($enrichData['last_pys_source'])) {
                        $eventData->setParamValue('traffic_source', $enrichData['last_pys_source']);
                    } elseif (!$is_last_visit && isset($enrichData['pys_source'])) {
                        $eventData->setParamValue('traffic_source', $enrichData['pys_source']);
                    }
                } else {
                    $eventData->setParamValue('traffic_source', getTrafficSource($is_last_visit));
                }
            }

            if(PYS()->getOption( 'track_utms' )) {
                if ($isRenewalOrder) {
                    // Set default values for auto-renewal orders
                    $renewalUtms = [
                        'utm_source'   => $renewalName,
                        'utm_medium'   => $renewalName,
                        'utm_campaign' => $renewalName,
                        'utm_content'  => $renewalName,
                        'utm_term'     => $renewalName,
                        'landing_page' => $renewalName,
                    ];
                    foreach ($renewalUtms as $key => $value) {
                        $eventData->setParamValue($key, $value);
                    }
                } elseif (!empty($enrichData)) {
                    // Get UTMs from order meta
                    if ($is_last_visit && isset($enrichData['last_pys_utm'])) {
                        $utms = parseUtmString($enrichData['last_pys_utm']);
                        foreach ($utms as $key => $value) {
                            $eventData->setParamValue($key, $value);
                        }
                    } elseif (!$is_last_visit && isset($enrichData['pys_utm'])) {
                        $utms = parseUtmString($enrichData['pys_utm']);
                        foreach ($utms as $key => $value) {
                            $eventData->setParamValue($key, $value);
                        }
                    }

                    // Get landing page from order meta
                    if ($is_last_visit && isset($enrichData['last_pys_landing'])) {
                        $eventData->setParamValue('landing_page', $enrichData['last_pys_landing']);
                    } elseif (!$is_last_visit && isset($enrichData['pys_landing'])) {
                        $eventData->setParamValue('landing_page', $enrichData['pys_landing']);
                    }
                } else {
                    $utms = getUtms(false, $is_last_visit);
                    foreach ($utms as $key => $value) {
                        $eventData->setParamValue($key, $value);
                    }
                    if (!$is_last_visit) {
                        $eventData->setParamValue('landing_page', sanitize_url($_SESSION['LandingPage'] ?? $_COOKIE['pys_landing_page'] ?? 'undefined'));
                    } else {
                        $eventData->setParamValue('landing_page', sanitize_url($_COOKIE['last_pys_landing_page'] ?? 'undefined'));
                    }
                }
            }
            $event_additional_data = array(
                'plugin'         => 'PixelYourSite',
                'event_day'      => PYS()->getOption("enable_event_day_param") ? current_time( "l" ) : '',
                'event_month'    => PYS()->getOption("enable_event_month_param") ? current_time( "F" ) : '',
                'event_hour'     => PYS()->getOption("enable_event_time_param") ? $this->getHour() : '',
                'user_role'     => PYS()->getOption("enable_user_role_param") ? getUserRoles() : ''
            );

            foreach ($event_additional_data as $key => $value) {
                if($value === '') {
                    continue;
                }
                $eventData->setParamValue($key, $value);
            }


            GA()->getLog()->debug('Send for GA4', $tag);

            $consent = new ConsentProperty();
            $this->setConsentProperty($params, 'ad_user_data', $consent, 'setAdUserData');
            $this->setConsentProperty($params, 'ad_personalization', $consent, 'setAdPersonalization');

            $baseRequest->setConsent( $consent );

            $baseRequest->addEvent($eventData);
            if ( isset( $data[ 'woo_order' ] ) && !empty( $data[ 'woo_order' ] ) ) {
                $order = wc_get_order( $data[ 'woo_order' ] );
                if ($order) {
                    $street = $order->get_billing_address_1();
                    $city = $order->get_billing_city();
                    $zip = $order->get_billing_postcode();
                    $country = $order->get_billing_country();

                    $user_persistence_data = get_persistence_user_data($order->get_billing_email(), $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_billing_phone());

                    $userData = new UserData();
                    if (!empty($user_persistence_data['em'])) {
                        $email = $this->processAndHash($user_persistence_data['em'], 'email');
                        if (!empty($email)) {
                            $userData->addUserDataItem(new UserDataItem('sha256_email_address', $email));
                        }
                    }
                    if (!empty($user_persistence_data['tel'])) {
                        $phone = $this->processAndHash($user_persistence_data['tel'], 'phone');
                        if (!empty($phone)) {
                            $userData->addUserDataItem(new UserDataItem('sha256_phone_number', $phone));
                        }
                    }
                    $userAdress = new UserAddress();
                    if ( ! empty( $user_persistence_data['fn'] ) ) {
                        $name = $this->processAndHash( $user_persistence_data['fn'], 'name' );
                        if (!empty($name)) {
                            $userAdress->addUserAddressItem( new UserDataItem( 'sha256_first_name', $name ) );
                        }
                    }
                    if ( ! empty( $user_persistence_data['ln'] ) ) {
                        $surname = $this->processAndHash( $user_persistence_data['ln'], 'surname' );
                        if (!empty($surname)) {
                            $userAdress->addUserAddressItem( new UserDataItem( 'sha256_last_name', $surname ) );
                        }
                    }
                    if ( $country && $city ) {
                        $userAdress->addUserAddressItem( new UserDataItem( 'city', $city ) );
                        $userAdress->addUserAddressItem( new UserDataItem( 'country', $country ) );
                        if ( $zip ) {
                            $userAdress->addUserAddressItem( new UserDataItem( 'postal_code', $zip ) );
                        }
                        if ( $street ) {
                            $street = $this->processAndHash( $street, 'street' );
                            if (!empty($street)) {
                                $userAdress->addUserAddressItem( new UserDataItem( 'sha256_street', $street ) );
                            }
                        }
                    }
                    if($userAdress->getUserAddressItemList()){
                        $userData->addUserAddress( $userAdress );
                    }
                    if(GA()->getOption( "track_user_id" )) {
                        $user_id = $order->get_user_id();
                        if (is_numeric($user_id) && $user_id > 0) {
                            $baseRequest->setUserId((string)$user_id);
                        }
                    }

                    $baseRequest->setUserData($userData);
                }
            } elseif ( isset( $data[ 'edd_order' ] ) && !empty( $data[ 'edd_order' ] ) ) {
                $order = new \EDD_Payment( $data[ 'edd_order' ] );
                if ( $order ) {
                    $meta = $order->get_meta();
                    if ( isset( $meta[ 'user_info' ] ) ) {
                        //object UserProperties
                        $userData = new UserData();
                        $user_first_name = $meta[ 'user_info' ][ 'first_name' ] ?? '';
                        $user_last_name = $meta[ 'user_info' ][ 'last_name' ] ?? '';
                        $user_email = $meta[ 'user_info' ][ 'email' ] ?? '';
                        $user_persistence_data = get_persistence_user_data( $user_email, $user_first_name, $user_last_name, '' );


                        if (!empty($user_persistence_data['em'])) {
                            $email = $this->processAndHash($user_persistence_data['em'], 'email');
                            if (!empty($email)) {
                                $userData->addUserDataItem(new UserDataItem('sha256_email_address', $email));
                            }
                        }
                        $userAdress = new UserAddress();
                        if (!empty($user_persistence_data['fn'])) {
                            $first_name = $this->processAndHash($user_persistence_data['fn'], 'name');
                            if (!empty($first_name)) {
                                $userAdress->addUserAddressItem(new UserDataItem('sha256_first_name', $first_name));
                            }
                        }
                        if (!empty($user_persistence_data['ln'])) {
                            $last_name = $this->processAndHash($user_persistence_data['ln'], 'surname');
                            if (!empty($last_name)) {
                                $userAdress->addUserAddressItem(new UserDataItem('sha256_last_name', $last_name));
                            }
                        }
                        if (isset($meta['user_info']['address'])) {
                            $street = $this->processAndHash($meta['user_info']['address'], 'street');
                            if (!empty($street)) {
                                $userAdress->addUserAddressItem(new UserDataItem('sha256_street', $street));
                            }
                        }

                        // Setting user properties for purchaseEventData
                        if ($userAdress->getUserAddressItemList()) {
                            $userData->addUserAddress($userAdress);
                        }
                        if(GA()->getOption( "track_user_id" )){
                            $user_id = edd_get_payment_user_id($data[ 'edd_order' ]);
                            if (is_numeric($user_id) && $user_id > 0) {
                                $baseRequest->setUserId((string)$user_id);
                            }
                        }

                        $baseRequest->setUserData($userData);
                    }
                }
            }
            GA()->getLog()->debug('Send GA4 server event request', $baseRequest);

// We have all the data we need. Just send the request.
            $debugMode = $this->shouldUseDebugMode($tag);
            GA()->getLog()->debug('Debug mode for tag ' . $tag . ': ' . ($debugMode ? 'enabled' : 'disabled'));

            if ($debugMode) {
                $response = $ga4Service->sendDebug($baseRequest);
            } else {
                $response = $ga4Service->send($baseRequest);
                if(($event->getId() === 'edd_purchase' || $event->getId() === 'woo_purchase') && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300){
                    if ( isset( $data[ 'woo_order' ] ) && !empty( $data[ 'woo_order' ] ) ) {
                        if ( isWooCommerceVersionGte('3.0.0') ) {
                            $order = wc_get_order($order_id);
                            if($order) {
                                $order->update_meta_data( '_pys_ga_purchase_event_fired', true );
                                $order->save();
                            }
                        } else {
                            // WooCommerce < 3.0
                            update_post_meta( $order_id, '_pys_ga_purchase_event_fired', true );
                        }
                    } elseif ( isset( $data[ 'edd_order' ] ) && !empty( $data[ 'edd_order' ] ) ) {
                        edd_update_payment_meta( $order_id, '_pys_ga_purchase_event_fired', true );
                    }
                }
            }
            GA()->getLog()->debug('Send GA4 server event status code: ', $response->getStatusCode());

            GA()->getLog()->debug('Send GA4 server event response', $response);
            // Note: else block for Universal Analytics is no longer used,
            // as we filter only GA4 tags (G-*) at the beginning of the method
            // else
            // {
            //     $eventData = GaServerEventHelper::mapSingleEventToServerData($event);
            //     $eventData['v'] = '1';// API version
            //     $eventData['tid'] = $tag; // tracking ID
            //     $eventData['z'] = time();
            //
            //     $response = wp_safe_remote_request($this->ga_url, $this->prepareRequestArgs($eventData));
            //     if (is_wp_error($response)) {
            //         GA()->getLog()->debug('Send GA server event error', $response);
            //         return;
            //     }
            //     GA()->getLog()->debug('Send GA server event response', $response);
            // }

//            $response_code     = wp_remote_retrieve_response_code( $response );
//            $response_message  = wp_remote_retrieve_response_message( $response );
//            $raw_response_body = wp_remote_retrieve_body( $response );

        }
    }

    private function prepareRequestArgs($params)
    {
        $args = array(
            'method' => 'POST',
            'timeout' => MINUTE_IN_SECONDS,
            'redirection' => 0,
            // 'httpversion' => '1.0',
            'sslverify' => true,
            'blocking' => true,
            // 'user-agent'  => $this->get_request_user_agent(),
            'headers' => [],
            'body' => $this->paramsToString($params),
            'cookies' => array(),
        );

        return $args;
    }

    public function paramsToString($params)
    {

        return http_build_query($params, '', '&');
    }

    public function isGaV4($tag) {
        return strpos($tag, 'G') === 0;
    }
    function isSha256($str) {
        return preg_match('/^[a-f0-9]{64}$/', $str);
    }
    function processAndHash($value, $type) {
        if (is_array($value) && !empty($value)) {
            $value = reset($value);
        }
        if (!empty($value) && !$this->isSha256($value)) {
            switch ($type) {
                case 'email':
                    $value = strtolower($value);
                    if (preg_match('/@(gmail\.com|googlemail\.com)$/', $value)) {
                        $value = preg_replace('/\.(?=[^@]*@)/', '', $value);
                    }
                    $value = str_replace(' ', '', $value);
                    break;
                case 'phone':
                    $value = preg_replace('/\D/', '', $value);
                    $value = '+' . $value;
                    break;
                case 'name':
                case 'surname':
                    $value = preg_replace('/[\d\W]/', '', $value);
                    $value = strtolower($value);
                    $value = trim($value);
                    break;
                case 'street':
                    $value = strtolower($value);
                    $value = str_replace(' ', '', $value);
                    break;
            }
            // Do not hash empty values after processing
            if (empty($value) || $value === '+') {
                return '';
            }
            $value = hash('sha256', $value);
        }
        return $value;
    }

    function setConsentProperty($params, $key, $consent, $method) {
        if (isset($params[$key]) && !empty($params[$key])) {
            $consent->$method(strtoupper($params[$key]));
        } else {
            $consent->$method(ConsentCode::DENIED);
        }
    }

    /**
     * Check if debug mode should be used for specific tag
     *
     * @param string $tag GA4 measurement ID
     * @return bool
     */
    private function shouldUseDebugMode($tag) {
        $debugFlags = GA()->getPixelDebugMode();

        // Check if debug mode is enabled globally
        if (!empty($debugFlags)) {
            // If we have specific pixel indices, check if this tag matches
            foreach ($debugFlags as $flag) {
                if (strpos($flag, 'index_') === 0) {
                    // This is a specific pixel index, we need to check if this tag matches
                    $index = str_replace('index_', '', $flag);
                    $pixelIndex = $this->getPixelIndex($tag);
                    if ($pixelIndex !== null && $pixelIndex == $index) {
                        return true;
                    }
                } else {
                    // Global debug mode enabled
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get pixel index for specific measurement ID
     *
     * @param string $tag GA4 measurement ID
     * @return int|null
     */
    private function getPixelIndex($tag) {
        $allPixels = GA()->getAllPixels();
        $index = 0;

        foreach ($allPixels as $pixel) {
            if ($pixel === $tag) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    /**
     * Check if order is auto-renewal (subscription renewal)
     *
     * @param int $order_id Order ID
     * @param string $type Order type ('woo' or 'edd')
     * @return bool True if order is auto-renewal, false otherwise
     */
    private function isRenewalOrder($order, $type) {
        if ($type === 'woo') {
            if (!$order) {
                return false;
            }

            // Check using WooCommerce Subscriptions function
            if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order, 'renewal')) {
                return true;
            }

            // Check created_via field
            $created_via = method_exists($order, 'get_created_via') ? $order->get_created_via() : '';
            if ($created_via === 'subscription_renewal' || $created_via === 'subscription') {
                return true;
            }

            return false;
        } elseif ($type === 'edd') {

            if (!$order) {
                return false;
            }

            // If order has parent - it's a renewal payment
            if (!empty($order->parent) && $order->parent > 0) {
                return true;
            }

            // Check order type
            if (isset($order->type) && $order->type === 'renewal') {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Get enrichment data from order meta
     *
     * @param mixed $order Order object (WC_Order or EDD_Payment)
     * @param string $type Order type ('woo' or 'edd')
     * @return array Enrichment data or empty array
     */
    private function getOrderEnrichData($order, $type) {
        $data = array();

        if ($type === 'woo' && $order) {
            if (isWooUseHPStorage()) {
                $data = $order->get_meta('pys_enrich_data', true);
            } else {
                $data = get_post_meta($order->get_id(), 'pys_enrich_data', true);
            }
        } elseif ($type === 'edd' && $order) {
            if (class_exists('EDD_Payment')) {
                $payment = new \EDD_Payment($order->id ?? $order->ID);
                if ($payment && $payment->get_meta()) {
                    $meta = $payment->get_meta();
                    if (isset($meta['pys_enrich_data'])) {
                        $data = $meta['pys_enrich_data'];
                    }
                }
            }
        }

        return is_array($data) ? $data : array();
    }

    /**
     * Check if server event should be disabled by filter
     *
     * @param SingleEvent $event The event to check
     * @return bool True if event should be blocked, false otherwise
     */
    private function isEventDisabledByFilter($event) {
        $event_name = $event->getId();
        $woo_order = isset($event->payload['woo_order']) ? $event->payload['woo_order'] : null;
        $edd_order = isset($event->payload['edd_order']) ? $event->payload['edd_order'] : null;
        $order_id = $woo_order ?? $edd_order;

        $is_disabled = apply_filters('pys_disable_server_event_filter', false, $event_name, 'ga4', $order_id);

        if ($is_disabled) {
            GA()->getLog()->debug('GA4 server event blocked by pys_disable_server_event_filter', [
                'event_name' => $event_name,
                'order_id' => $order_id
            ]);
        }

        return $is_disabled;
    }

    /*
     * If server message is blocked by gprg or it dynamic
     * we send data by ajax request from js and send the same data like browser event
     */
    public function catchAjaxEvent() {
        GA()->getLog()->debug('catchAjaxEvent send ga4 server from ajax');
        $event = sanitize_text_field($_POST['event']);
        $eventSlug = sanitize_text_field($_POST['event_slug'] ?? '');
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $ids = $_POST['ids'];
        $eventID = sanitize_text_field($_POST['eventID']);
        $wooOrder = isset($_POST['woo_order']) && is_numeric($_POST['woo_order']) ? (int) $_POST['woo_order'] : null;
        $eddOrder = isset($_POST['edd_order']) && is_numeric($_POST['edd_order']) ? (int) $_POST['edd_order'] : null;

        if ( empty( $_REQUEST['ajax_event'] ) || !wp_verify_nonce( $_REQUEST['ajax_event'], 'ajax-event-nonce' ) ) {
            wp_die();
            return;
        }

        $singleEvent = $this->dataToSingleEvent($event,$data,$eventID,$ids,$wooOrder,$eddOrder,$eventSlug);
        $this->sendEventsNow([$singleEvent]);

        wp_die();
    }
    /**
     * @param $eventName
     * @param $params
     * @param $eventID
     * @param $ids
     * @param $wooOrder
     * @param $eddOrder
     * @return SingleEvent
     */
    private function dataToSingleEvent($eventName,$params,$eventID,$ids,$wooOrder,$eddOrder,$eventSlug = "") {
        $singleEvent = new SingleEvent($eventSlug,"");

        $payload = [
            'name' => $eventName,
            'eventID'   => $eventID,
            'woo_order' => $wooOrder,
            'edd_order' => $eddOrder,
            'trackingIds'  => $ids
        ];
        $singleEvent->addParams($params);
        $singleEvent->addPayload($payload);

        return $singleEvent;
    }

    /**
     * Get current hour in the format supported by Facebook
     * @return string string
     */
    public  function getHour() {
        $array = [
            '00-01',
            '01-02',
            '02-03',
            '03-04',
            '04-05',
            '05-06',
            '06-07',
            '07-08',
            '08-09',
            '09-10',
            '10-11',
            '11-12',
            '12-13',
            '13-14',
            '14-15',
            '15-16',
            '16-17',
            '17-18',
            '18-19',
            '19-20',
            '20-21',
            '21-22',
            '22-23',
            '23-24'
        ];

        return $array[ current_time( "G" ) ];

    }
}

/**
 * @return GaMeasurementProtocolAPI
 */
function GaMeasurementProtocolAPI() {
    return GaMeasurementProtocolAPI::instance();
}

GaMeasurementProtocolAPI();