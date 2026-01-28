<?php
namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
/*
 * @see https://github.com/facebook/facebook-php-business-sdk
 * This class use for sending facebook server events
 */
require_once PYS_PATH . '/modules/facebook/facebook-server-async-task.php';
use PYS_PRO_GLOBAL\FacebookAds\Api;
use PYS_PRO_GLOBAL\FacebookAds\Http\Exception\RequestException;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\EventRequest;

/**
 * Facebook Server Events Handler
 *
 * Extends AbstractServerEvents to handle Facebook Conversions API
 */
class FacebookServer extends AbstractServerEvents {

    private static $_instance;
    private $isEnabled;
    private $testCode;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }


    public function __construct() {
        parent::__construct();
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        $this->isEnabled = Facebook()->isServerApiEnabled();

        if($this->isEnabled) {
            // Classic hook for checkout page
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'saveFbTagsInOrder' ), 10, 1 );
            // Hook for Store API (passes WC_Order object instead of order_id)
            add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'saveFbTagsInOrder' ), 10, 1 );

            // AJAX handlers for fallback (from parent class)
            $this->registerAjaxHandlers();
            add_action( 'woocommerce_remove_cart_item', array($this, 'trackRemoveFromCartEvent'), 10, 2);

            // Initialize the s2s event async task (only if queue is disabled)
            if (!PYS()->getOption('queue_enabled', true)) {
                new FacebookAsyncTask();
            }
        }
    }

    /**
     * Get the pixel instance
     *
     * @return Facebook
     */
    protected function getPixelInstance() {
        return Facebook();
    }

    /**
     * Get the platform name for queue system
     *
     * @return string
     */
    protected function getQueuePlatformName() {
        return 'facebook';
    }

    /**
     * Get the async task action name
     *
     * @return string
     */
    protected function getServerEventAction() {
        return 'pys_send_server_event';
    }

    /**
     * Get the AJAX action name for catching events
     *
     * @return string
     */
    protected function getAjaxActionName() {
        return 'pys_api_event';
    }

    /**
     * Map SingleEvent to Facebook server event format
     *
     * @param SingleEvent $event The event to map
     * @return mixed Facebook event object
     */
    protected function mapEventToServerEvent( $event ) {
        return ServerEventHelper::mapEventToServerEvent($event);
    }

    /**
     * Get user data for Facebook event
     * Facebook uses ServerEventHelper for user data, so this returns null
     *
     * @param int|null $wooOrder WooCommerce order ID
     * @param int|null $eddOrder EDD order ID
     * @return null
     */
    protected function getUserData( $wooOrder = null, $eddOrder = null ) {
        // Facebook uses ServerEventHelper for user data
        return null;
    }

    public function saveFbTagsInOrder($order_param) {
        $pysData = [];
        $pysData['fbc'] = ServerEventHelper::getFbc();
        $pysData['fbp'] = ServerEventHelper::getFbp();

        // Determine whether the WC_Order object or order ID is passed
        if ( $order_param instanceof WC_Order ) {
            // If the order object is transferred
            $order = $order_param;
        } else {
            // If order_id is passed
            $order = wc_get_order( $order_param );
        }

        if (isWooCommerceVersionGte('3.0.0') && !empty($order)) {
            // WooCommerce >= 3.0
            $order->update_meta_data("pys_fb_cookie", $pysData);
            $order->save();
        } elseif ( ! empty( $order_param ) ) {
            // WooCommerce < 3.0
            update_post_meta($order_param, 'pys_fb_cookie', $pysData);
        }

    }

    /**
     * Track Woo Add to cart events
     *
     * @param $cart_item_key
     * @param $product_id
     * @param $quantity
     * @param $variation_id
     * @return void
     */
    function trackAddToCartEvent($cart_item_key, $product_id, $quantity, $variation_id) {
        if(EventsWoo()->isReadyForFire("woo_add_to_cart_on_button_click")
            && PYS()->getOption('woo_add_to_cart_catch_method') == "add_cart_js") // it ok. We send server method after js, and take event id from cookies
        {
            Facebook()->getLog()->debug('trackAddToCartEvent send fb server without browser event');
            if( !empty($variation_id)
                && $variation_id > 0
                && ( !Facebook()->getOption( 'woo_variable_as_simple' )
                    || ( Facebook()->getSlug() == "facebook"
                        && !Facebook\Helpers\isDefaultWooContentIdLogic()
                    )
                )
            ) {
                $_product_id = $variation_id;
            } else {
                $_product_id = $product_id;
            }

            $event =  new SingleEvent("woo_add_to_cart_on_button_click",EventTypes::$DYNAMIC,'woo');
            $event->args = ['productId' => $_product_id,'quantity' => $quantity];
            add_filter('pys_conditional_post_id', function($id) use ($product_id) { return $product_id; });
            $events = Facebook()->generateEvents($event);
            remove_all_filters('pys_conditional_post_id');

            foreach ($events as $singleEvent) {

                if(PYS()->getOption('visit_data_model') === "first_visit" && isset($_COOKIE['pys_landing_page']))
                    $singleEvent->addParams(['landing_page'=> sanitize_url($_COOKIE['pys_landing_page'])]);
                if(PYS()->getOption( 'visit_data_model') === "last_visit" && isset($_COOKIE['last_pys_landing_page']))
                    $singleEvent->addParams(['landing_page'=> sanitize_url($_COOKIE['last_pys_landing_page'])]);

                if(isset($_COOKIE["pys_fb_event_id"])) {
                    $singleEvent->payload['eventID'] = json_decode(stripslashes(sanitize_text_field($_COOKIE["pys_fb_event_id"])))->AddToCart;
                }
            }

            $this->sendEventsAsync($events);
        }

    }

    /**
     * @param String $cart_item_key
     * @param \WC_Cart $cart
     */

    function trackRemoveFromCartEvent ($cart_item_key,$cart) {
        $eventId = 'woo_remove_from_cart';

        $url = $_SERVER['HTTP_HOST'].strtok($_SERVER["REQUEST_URI"], '?');
        $postId = url_to_postid($url);
        $cart_id = wc_get_page_id( 'cart' );
        $item = $cart->get_cart_item($cart_item_key);

        if(isset($item['variation_id'])) {
            $product_id = $item['variation_id'];
        } else {
            $product_id = $item['product_id'];
        }


        if(PYS()->getOption( 'woo_remove_from_cart_enabled') && $cart_id==$postId) {
            Facebook()->getLog()->debug('trackRemoveFromCartEvent send fb server with out browser event');
            $event = new SingleEvent("woo_remove_from_cart",EventTypes::$STATIC,'woo');
            $event->args=['item'=>$item];

            $events = Facebook()->generateEvents($event);

            foreach ($events as $singleEvent) {
                $singleEvent->addParams(getStandardParams());
                if(isset($_COOKIE['pys_landing_page'])){
                    $singleEvent->addParams(['landing_page'=> sanitize_url($_COOKIE['pys_landing_page'])]);
                }
                if(isset($_COOKIE["pys_fb_event_id"])) {
                    $singleEvent->payload['eventID'] = json_decode(stripslashes(sanitize_text_field($_COOKIE["pys_fb_event_id"])))->RemoveFromCart;
                }

            }

            $this->sendEventsAsync($events);
        }
    }

    /**
     * Send event for each pixel id
     * @param array $pixel_Ids //array of facebook ids
     * @param $event //One Facebook event object
     */
    function sendEvent($pixel_Ids, $event) {

        if (!$event) {
            return;
        }

        if(!$this->access_token) {
            $this->access_token = Facebook()->getApiTokens();
            $this->testCode = Facebook()->getApiTestCode();
        }

	    $event_id = $event->getEventId();
	    foreach($pixel_Ids  as $pixel_Id) {

		    if(empty($this->access_token[$pixel_Id])) continue;

		    if(!empty($event->getEventId())) {
			    $event->setEventId($event_id);
		    }


            /**
             * filter pys_before_send_fb_server_event
             * Help add custom options or get data from event before send
             * FacebookAds\Object\ServerSide\Event $event
             * String $pixel_Id
             * String EventId
             */

            try{

                $api = Api::init(null, null, $this->access_token[$pixel_Id],false);
                $opts = $api->getHttpClient()->getAdapter()->getOpts();
                if ($opts instanceof \ArrayObject && $opts->offsetExists(CURLOPT_CONNECTTIMEOUT)) {
                    $opts->offsetSet(CURLOPT_CONNECTTIMEOUT, 30);
                    $api->getHttpClient()->getAdapter()->setOpts($opts);
                }

                $event = apply_filters("pys_before_send_fb_server_event",$event,$pixel_Id,$event->getEventId());

                $request = (new EventRequest($pixel_Id))->setEvents([$event]);
                $request->setPartnerAgent("dvpixelyoursite");
                if(!empty($this->testCode[$pixel_Id])) {
                    $request->setTestEventCode($this->testCode[$pixel_Id]);
                }
                Facebook()->getLog()->debug('Send FB server event',$request);

                $response = $request->execute();
                Facebook()->getLog()->debug('Response from FB server',$response);
            } catch (\Exception   $e) {
                if($e instanceof RequestException) {
                    Facebook()->getLog()->error('Error send FB server event '.$e->getErrorUserMessage(),$e->getResponse());
                } else {
                    Facebook()->getLog()->error('Error send FB server event',$e);
                }
            }
        }
    }

}

/**
 * @return FacebookServer
 */
function FacebookServer() {
    return FacebookServer::instance();
}

FacebookServer();





