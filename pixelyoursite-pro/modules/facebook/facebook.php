<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/facebook/function-helpers.php';
require_once PYS_PATH . '/modules/facebook/FDPEvent.php';
require_once PYS_PATH . '/modules/facebook/server_event_helper.php';
require_once PYS_PATH . '/modules/facebook/facebook-logger.php';
use PixelYourSite\Facebook\Helpers;
use function PixelYourSite\Facebook\Helpers\getFacebookWooProductContentId;


class Facebook extends Settings implements Pixel {

	private static $_instance;

	private $configured;
    private        $logger;
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

    public function __construct() {

        parent::__construct( 'facebook' );

        $this->locateOptions(
            PYS_PATH . '/modules/facebook/options_fields.json',
            PYS_PATH . '/modules/facebook/options_defaults.json'
        );

	    add_action( 'pys_register_pixels', function( $core ) {
	    	/** @var PYS $core */
	    	$core->registerPixel( $this );
	    } );
		add_filter('pys_facebook_settings_sanitize_verify_meta_tag_field', array($this, 'sanitize_verify_meta_tag_field'));
        add_action( 'wp_head', array( $this, 'output_meta_tag' ) );

        $this->logger = new Facebook_logger();

        add_action( 'init', array(
            $this,
            'init'
        ), 9 );

    }
    public function init() {
        $this->logger->init();
    }

	public function enabled() {
		if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
			return true;
		} else {
			return $this->getOption( 'main_pixel_enabled' );
		}
	}

	public function configured() {

		$license_status = PYS()->getOption( 'license_status' );
		$pixel_id = $this->getAllPixels();
		$disabled = false;
		$main_pixel_enabled = $this->getOption( 'main_pixel_enabled' );

		if ( isSuperPackActive() && version_compare( SuperPack()->getPluginVersion(), '3.1.1.1', '>=' ) ) {
			$disabledPixel = apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
			$disabled = in_array( '1', $disabledPixel ) && in_array( 'all', $disabledPixel );
			$main_pixel_enabled = true;
		}

		$this->configured = !empty( $license_status ) // license was activated before
			&& count( $pixel_id ) > 0
			&& $main_pixel_enabled
			&& !$disabled;

		return $this->configured;
	}


	public function getPixelIDs() {
        if(EventsWcf()->isEnabled() && isWcfStep()) {
            $ids = $this->getOption( 'wcf_pixel_id' );
            if(!empty($ids))
                return [$ids];
        }

		if( isSuperPackActive()
			&& SuperPack()->getOption( 'enabled' )
			&& SuperPack()->getOption( 'additional_ids_enabled' ) )
		{
			if ( !$this->getOption( 'main_pixel_enabled' ) ) {
				return apply_filters( "pys_facebook_ids", [] );
			}
		}

		$ids = (array) $this->getOption( 'pixel_id' );
        if(count($ids) == 0|| empty($ids[0])) {
            return apply_filters("pys_facebook_ids",[]);
        } else {
			$id = array_shift($ids);
            return apply_filters("pys_facebook_ids", array($id)); // return first id only
        }

	}
    public function getAllPixels($checkLang = true) {

        $pixels = $this->getPixelIDs();

        if(isSuperPackActive()
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' ))
        {
            $additionalPixels = SuperPack()->getFbAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isEnable
                    && (!$checkLang || $_pixel->isValidForCurrentLang())
                ) {
                    $pixels[]=$_pixel->pixel;
                }
            }
        }
        $hide_pixels = apply_filters('hide_pixels', array());
        $pixels = array_filter($pixels, static function ($element) use ($hide_pixels) {
            return !in_array($element, $hide_pixels);
        });
        $pixels = array_values($pixels);
        return $pixels;
    }

    /**
     * @param SingleEvent $event
     * @return array|mixed|void
     */
	public function getAllPixelsForEvent($event) {
        $pixels = array();
        $main_pixel = $this->getPixelIDs();

        if(isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' ))
        {
			if ( !empty( $main_pixel ) ) {
				$main_pixel_options = $this->getOption( 'main_pixel' );
				if ( !empty( $main_pixel_options ) && isset( $main_pixel_options[ 0 ] ) ) {
					$main_pixel_options = $this->normalizeSPOptions( $main_pixel[ 0 ], $main_pixel_options[ 0 ] );
				} else {
					$main_pixel_options = $this->normalizeSPOptions( $main_pixel[ 0 ], '' );
				}
				$pixel_options = SuperPack\SPPixelId::fromArray( $main_pixel_options );
				if ( $pixel_options->isValidForEvent( $event ) && $pixel_options->isConditionalValidForEvent( $event ) ) {
					$pixels = array_merge( $pixels, $main_pixel );
				}
			}

            $additionalPixels = SuperPack()->getFbAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isValidForEvent($event) && $_pixel->isConditionalValidForEvent($event)) {
                    $pixels[] = $_pixel->pixel;
                }

            }
		} elseif ( $this->getOption( 'main_pixel_enabled' ) ) {
			$pixels = array_merge( $pixels, $main_pixel );
		}

        return $pixels;
    }

    public function getDPAPixelID() {
	    if($this->getOption( 'fdp_use_own_pixel_id' )) {
	        return $this->getOption( 'fdp_pixel_id' );
        } else {
	        return "";
        }
    }


	public function getPixelOptions() {
		if ( $this->getOption( 'advanced_matching_enabled' ) ) {
			$advancedMatching = Helpers\getAdvancedMatchingParams();
		} else {
			$advancedMatching = array();
		}

		$options = array(
			'pixelIds'                   => $this->getAllPixels(),
			'advancedMatchingEnabled'    => $this->getOption( 'advanced_matching_enabled' ),
			'advancedMatching'           => $advancedMatching,
			'removeMetadata'             => $this->getOption( 'remove_metadata' ),
			'wooVariableAsSimple'        => $this->getOption( 'woo_variable_as_simple' ),
			'serverApiEnabled'           => $this->isServerApiEnabled() && count( $this->getApiTokens() ) > 0,
			'wooCRSendFromServer'        => $this->getOption( "woo_complete_registration_send_from_server" ) && $this->getOption( "woo_complete_registration_fire_every_time" ),
			'send_external_id'           => $this->getOption( 'send_external_id' ),
			'enabled_medical'            => $this->getOption( 'enabled_medical' ),
			'do_not_track_medical_param' => $this->getOption( 'do_not_track_medical_param' ),
			'meta_ldu'                   => $this->getLDUMode(),
		);

		if ( isSuperPackActive( '3.3.1' ) && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'enable_hide_this_tag_by_tags' ) ) {
			$options[ 'hide_pixels' ] = $this->getHideInfoPixels();
		}
		return $options;

	}

    public function updateOptions( $values = null ) {
	    if(isPixelCogActive() &&
            isset($_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value'])
        ) {
	        $val = $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value'];
	        $currentVal = $this->getOption('woo_complete_registration_custom_value');
	        if($val != 'cog') {
                $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value_old'] = $val;
            } elseif ( $currentVal != 'cog' ) {
                $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value_old'] = $currentVal;
            }
        }
        if(isset($_POST['pys'][$this->getSlug()]['test_api_event_code']))
        {


            $api_event_code_expiration_at = array();
            foreach ($_POST['pys'][$this->getSlug()]['test_api_event_code'] as $key => $test_api)
            {
                if(!empty($test_api) && empty($this->getOption('test_api_event_code_expiration_at')[$key]))
                {
                    $api_event_code_expiration_at[] = time() + $this->convertTimeToSeconds();
                }
                elseif (!empty($this->getOption('test_api_event_code_expiration_at')[$key]))
                {
                    $api_event_code_expiration_at[] = $this->getOption('test_api_event_code_expiration_at')[$key];
                }
            }

            $_POST['pys'][$this->getSlug()]['test_api_event_code_expiration_at'] = $api_event_code_expiration_at;
        }
        parent::updateOptions($values);
    }

    /**
     * Create pixel event and fill it
     * @param SingleEvent $event
     * @return SingleEvent[]
     */
    public function generateEvents($event) {
        $pixelEvents = [];

		if ( !$this->configured() ) {
			return [];
		}
        if(isSuperPackActive() && version_compare( SuperPack()->getPluginVersion(), '3.1.1.1', '>=' ))
        {
            $disabledPixel =  apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
            if(is_array($disabledPixel) && (in_array('1', $disabledPixel) || in_array('all', $disabledPixel))) return [];
            $hide_pixels = apply_filters('hide_pixels', array());
            $disabledPixel = array_merge($disabledPixel, $hide_pixels);
        }
        else{
            $disabledPixel =  apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
            if(in_array('1', $disabledPixel) || in_array('all', $disabledPixel)) return [];
        }

        if($event->getId() == 'woo_remove_from_cart') {
            $product_id = $event->args['item']['product_id'];
            add_filter('pys_conditional_post_id', function($id) use ($product_id) { return $product_id; });
        }

        $pixelIds = $this->getAllPixelsForEvent($event);

        if($event->getId() == 'woo_remove_from_cart') {
            remove_all_filters('pys_conditional_post_id');
        }

        if($event->getId() == 'custom_event'){
            $preselectedPixel = $event->args->facebook_pixel_id;
            if(is_array($preselectedPixel)){
                if(in_array('all', $preselectedPixel)){
                        $pixelIds = $pixelIds;
                }
                else{
                    $pixelIds = array_filter($preselectedPixel, static function ($element) use ($pixelIds) {
                        return in_array($element, $pixelIds);
                    });
                }
            }else{
                if($preselectedPixel != 'all') {
                    if(in_array($preselectedPixel,$pixelIds)) {
                        $pixelIds = [$preselectedPixel];
                    } else {
                        return []; // not send event if pixel was disabled
                    }

                }
            }
        }

        if($event->getCategory() == EventsFdp::getSlug()) {
            $dpaPixel = $this->getDPAPixelID();
            if($dpaPixel) { $pixelIds=[$dpaPixel]; }
        }


        // filter disabled pixels
        if(!empty($disabledPixel)) {
            if(is_array($disabledPixel))
            {
                $pixelIds = array_filter($pixelIds, static function ($element) use ($disabledPixel) {
                    return !in_array($element, $disabledPixel);
                });
                $pixelIds = array_values($pixelIds);
            }
            else
            {
                foreach ($pixelIds as $key => $value) {
                    if($value == $disabledPixel) {
                        array_splice($pixelIds,$key,1);
                    }
                }
            }
        }


        $listOfEddEventWithProducts = ['edd_add_to_cart_on_checkout_page','edd_initiate_checkout','edd_purchase','edd_frequent_shopper','edd_vip_client','edd_big_whale'];
        $listOfWooEventWithProducts = ['woo_view_category','woo_purchase','woo_initiate_checkout','woo_paypal','woo_add_to_cart_on_checkout_page','woo_add_to_cart_on_cart_page'];
        $isWooEventWithProducts = in_array($event->getId(),$listOfWooEventWithProducts);
        $isEddEventWithProducts = in_array($event->getId(),$listOfEddEventWithProducts);
        if(($isWooEventWithProducts || $isEddEventWithProducts) && isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' ) )
        {
            $main_pixel = $this->getPixelIDs();
            $pixels_for_filter = array();
            if ( !empty( $main_pixel ) ) {
                $main_pixel_options = $this->getOption('main_pixel');
                if (!empty($main_pixel_options) && isset($main_pixel_options[0])) {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0], $main_pixel_options[0]);
                } else {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0], '');
                }
                $pixels_for_filter[] = SuperPack\SPPixelId::fromArray($main_pixel_options);
            }
            if(SuperPack()->getOption( 'additional_ids_enabled' )){
                $pixels_for_filter = array_merge($pixels_for_filter, SuperPack()->getFbAdditionalPixel());
            }

            $array_products = array();
            foreach ($pixels_for_filter as $_pixel) {
                $filter = null;
                if(!$_pixel->isValidForEvent($event) || in_array($_pixel->pixel, $disabledPixel)) continue;

                if($isWooEventWithProducts) {
                    $filter = $_pixel->getWooFilter();
                }
                if($isEddEventWithProducts) {
                    $filter = $_pixel->getEddFilter();
                }
                if($filter != null) {
                    $products = [];


                    $containsAllFilter = array_filter($filter, function($item) {
                        return $item['filter'] == "all";
                    });
                    if($containsAllFilter) {
                        $array_products[$_pixel->pixel] = $event->args['products'];
                    } else {
                        if($isWooEventWithProducts) {
                            $products = EventsWoo()->filterEventProductsBy($event,$filter,$_pixel);
                            $array_products[$_pixel->pixel] = $products;
                        }
                        if($isEddEventWithProducts) {
                            $products = EventsEdd()->filterEventProductsBy($event,$filter,$_pixel);
                            $array_products[$_pixel->pixel] = $products;
                        }
                    }

                } else {
                    $array_products[$_pixel->pixel] = $event->args['products'];
                }
            }
            $grouped_pixels = [];
            $processed = [];

            foreach ($array_products as $pixel => $products) {
                if (in_array($pixel, $processed)) {
                    continue;
                }
                $ids = array_column($products, 'product_id');
                $group = [(string) $pixel];
                foreach ($array_products as $other_pixel => $other_products) {
                    if ($pixel !== $other_pixel && !in_array($other_pixel, $processed)) {
                        $other_ids = array_column($other_products, 'product_id');
                        if ($ids === $other_ids) {
                            $group[] = (string) $other_pixel;
                            $processed[] = $other_pixel;
                        }
                    }
                }
                $grouped_pixels[] = $group;
                $processed[] = $pixel;
            }

            foreach ($grouped_pixels as $group) {
                $pixelEvent = clone $event;
                if(isset($array_products[$group[0]])) {
                    $pixelEvent->args['products'] = $array_products[$group[0]];
                }
                $pixelEvent->addPayload([ 'pixelIds' => $group ]);
                if($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvents[] = $pixelEvent;
                }
            }
        } else {
            // if list of pixels are empty return empty array
            if(count($pixelIds) > 0) {
                $pixelEvent = clone $event;

                if($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvent->addPayload([ 'pixelIds' => $pixelIds ]);
                    $pixelEvents[] = $pixelEvent;
                }
            }
        }

        return $pixelEvents;
    }
    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function addParamsToEvent(&$event) {

        $isActive = false;

        switch ($event->getId()) {
            case 'init_event':{
                    $eventData = $this->getPageViewEventParams();
                    if($eventData) {
                        $isActive = true;
						$event->addPayload( [ "ajaxFire" => !Consent()->checkConsent( 'facebook' ) ] );
                        $this->addDataToEvent($eventData,$event);
                    }
            } break;
            //Automatic events
            case 'automatic_event_signup' : {
                if(isWooCommerceActive() &&  Facebook()->getOption("woo_complete_registration_fire_every_time")) {
                    $isActive = false;
                } else {
                    $event->addPayload(["name" => "CompleteRegistration"]);
                    $isActive = $this->getOption($event->getId().'_enabled');
                }
            } break;
            case 'automatic_event_login' :{
                $event->addPayload(["name" => "Login"]);
                $isActive = $this->getOption($event->getId().'_enabled');
            } break;
            case 'automatic_event_search' :{
                $event->addPayload(["name" => "Search"]);
                if(!empty( $_GET['s'] )) {
                    $event->addParams(["search_string" => $_GET['s']]);
                }
                $isActive = $this->getOption($event->getId().'_enabled');
            } break;
            case 'automatic_event_tel_link' :
            case 'automatic_event_email_link':
            case 'automatic_event_form' :

            case 'automatic_event_download' :
            case 'automatic_event_comment' :
            case 'automatic_event_adsense' :
            case 'automatic_event_scroll' :
            case 'automatic_event_time_on_page' :

            case "automatic_event_video":
            case "automatic_event_outbound_link":
            case "automatic_event_internal_link": {
                $isActive = $this->getOption($event->getId().'_enabled');
            }break;

            case 'wcf_add_to_cart_on_bump_click':
            case 'wcf_add_to_cart_on_next_step_click': {
                $isActive = $this->prepare_wcf_add_to_cart($event);
            }break;

            case 'wcf_remove_from_cart_on_bump_click': {
                $isActive = $this->prepare_wcf_remove_from_cart($event);
            }break;
            case 'wcf_lead': {
                $isActive = PYS()->getOption('wcf_lead_enabled');
            }break;
            case 'wcf_step_page': {
                $isActive = $this->getOption('wcf_step_event_enabled');
            }break;
            case 'wcf_bump': {
                $isActive = $this->getOption('wcf_bump_event_enabled');
            }break;
            case 'wcf_page': {
                $isActive = $this->getOption('wcf_cart_flows_event_enabled');
            }break;

            case 'woo_complete_registration': {
                if( $this->getOption("woo_complete_registration_fire_every_time") ||
                    get_user_meta( get_current_user_id(), 'pys_complete_registration', true )
                ) {
                    $isActive = $this->getWooCompleteRegistrationEventParams($event);
                }
            }break;
            case 'woo_frequent_shopper':
            case 'woo_vip_client':
            case 'woo_big_whale':
            case 'woo_FirstTimeBuyer':
            case 'woo_ReturningCustomer':{

                $eventData =  $this->getWooAdvancedMarketingEventParams( $event->getId() );
                if($eventData) {
                    $isActive = true;
                    $event->addParams($eventData["data"]);
                    $event->addPayload($eventData["payload"]);
                }
            }break;
            case 'wcf_view_content': {
                $isActive =  $this->getWcfViewContentEventParams($event);
            }break;
            case 'woo_view_content': {
                $eventData =  $this->getWooViewContentEventParams($event->args);
                if($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData,$event);
                }
            }break;

            case 'woo_view_category':{
                $eventData =  $this->getWooViewCategoryEventParams($event);
                if($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData,$event);
                }
            }break;
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page': {
                $isActive =  $this->getWooAddToCartOnCartEventParams($event);
            }break;


            case 'woo_initiate_checkout': {
                $isActive =  $this->getWooInitiateCheckoutEventParams($event);

            }break;


            case 'woo_purchase':{
                $isActive =  $this->getWooPurchaseEventParams($event);
            }break;


            case 'woo_remove_from_cart':{
                $eventData =  $this->getWooRemoveFromCartParams( $event->args['item'] );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }

            }break;

            case 'woo_paypal': {
                $isActive =  $this->getWooPayPalEventParams($event);

            }break;

            case 'edd_view_content':{
                $eventData = $this->getEddViewContentEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            } break;

            case 'edd_remove_from_cart': {
                $eventData =  $this->getEddRemoveFromCartParams( $event->args['item'] );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_view_category': {
                    $eventData = $this->getEddViewCategoryEventParams();
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;

            case 'edd_add_to_cart_on_checkout_page':
            case 'edd_initiate_checkout':
            case 'edd_purchase':
            case 'edd_frequent_shopper':
            case 'edd_vip_client':
            case 'edd_big_whale': {
                $isActive = $this->setEddCartEventParams($event);
            }break;

            case 'fdp_view_content':{
                if($this->getOption("fdp_view_content_enabled")){
                    $params = Helpers\getFDPViewContentEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $payload = array(
                        'name' => "ViewContent",
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;
            case 'fdp_view_category':{
                if($this->getOption("fdp_view_category_enabled")){
                    $params = Helpers\getFDPViewCategoryEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $payload = array(
                        'name' => "ViewCategory",
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'fdp_add_to_cart':{
                if($this->getOption("fdp_add_to_cart_enabled")){
                    $params = Helpers\getFDPAddToCartEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $params["value"] = $this->getOption("fdp_add_to_cart_value");
                    $params["currency"] = $this->getOption("fdp_currency");
                    $trigger_type = $this->getOption("fdp_add_to_cart_event_fire");
                    $trigger_value = $trigger_type == "scroll_pos" ?
                        $this->getOption("fdp_add_to_cart_event_fire_scroll") :
                        $this->getOption("fdp_add_to_cart_event_fire_css") ;
                    $payload = array(
                        'name' => "AddToCart",
                        'trigger_type' => $trigger_type,
                        'trigger_value' => [$trigger_value]
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'fdp_purchase':{
                if($this->getOption("fdp_purchase_enabled")){
                    $params = Helpers\getFDPPurchaseEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $params["value"] = $this->getOption("fdp_purchase_value");
                    $params["currency"] = $this->getOption("fdp_currency");
                    $trigger_type = $this->getOption("fdp_purchase_event_fire");
                    $trigger_value = $trigger_type == "scroll_pos" ?
                        $this->getOption("fdp_purchase_event_fire_scroll") :
                        $this->getOption("fdp_purchase_event_fire_css");
                    $payload = array(
                        'name' => "Purchase",
                        'trigger_type' => $trigger_type,
                        'trigger_value' => [$trigger_value]
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'custom_event':{
                $eventData =  $this->getCustomEventParams( $event);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_add_to_cart_on_button_click':{
                if (  $this->getOption( 'woo_add_to_cart_enabled' )
                    && PYS()->getOption( 'woo_add_to_cart_on_button_click' ) )
                {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $eventData =  $this->getWooAddToCartOnButtonClickEventParams( $event->args );
                        $event->addParams($eventData["params"]);
	                    if($eventData) {
		                    $event->addParams($eventData["params"]);
		                    unset($eventData["params"]);
		                    $event->addPayload($eventData);
	                    }
                    }
	                $event->addPayload(array(
		                'name'=>"AddToCart",
	                ));

                }
            }break;

            case 'woo_affiliate':{
                if($this->getOption( 'woo_affiliate_enabled' )){
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData =  $this->getWooAffiliateEventParams( $productId,$quantity );
                        $event->addParams($eventData["params"]);
                    }
                }
            }break;

            case 'edd_add_to_cart_on_button_click':{
                if (  $this->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    if($event->args != null) {
                        $eventData =  $this->getEddAddToCartOnButtonClickEventParams( $event->args );
                        $event->addParams($eventData);
                    }
                    $event->addPayload(array(
                        'name'=>"AddToCart"
                    ));
                }
            }break;
        }

        if($isActive && !isset($event->payload['eventID']) && $this->isServerApiEnabled()) {
            $event->payload['eventID'] = EventIdGenerator::guidv4();
        }

        return $isActive;
    }

    private function addDataToEvent($eventData,&$event) {
        $params = $eventData["data"];
        unset($eventData["data"]);
        //unset($eventData["name"]);
        $event->addParams($params);
        $event->addPayload($eventData);
    }

	public function getEventData( $eventType, $args = null ) {

        return false;

	}

	public function outputNoScriptEvents() {

		if ( ! $this->configured() || $this->getOption('disable_noscript') ) {
			return;
		}

		$ldu = $this->getLDUMode();
		$eventsManager = PYS()->getEventsManager();
		foreach ( $eventsManager->getStaticEvents( 'facebook' ) as $eventId => $events ) {

			foreach ( $events as $event ) {
                if($event['name']== "hCR") continue;
				foreach ( $event['pixelIds'] as $pixelID ) {
					$args = array(
						'id'       => $pixelID,
						'ev'       => urlencode( $event['name'] ),
						'noscript' => 1,
					);
					if(isset($event['eventID'])) {
                        $args['eid'] = $pixelID.$event['eventID'];
                    }

					$params = $event[ 'params' ];
					if ( $ldu ) {
						$params = array_merge( $params, array(
							'vdpo'  => 'LDU',
							'dpoco' => 0,
							'dpost' => 0
						) );
					}

					foreach ( $params as $param => $value ) {
					    if(is_array($value))
                            $value = json_encode($value);
						@$args[ 'cd[' . $param . ']' ] = urlencode( $value );
					}
                    $src = add_query_arg( $args, 'https://www.facebook.com/tr' );
                    $src = str_replace("[","%5B",$src);
                    $src = str_replace("]","%5D",$src);
					// ALT tag used to pass ADA compliance
					printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt=""></noscript>',
                        $src);

					echo "\r\n";

				}
			}
		}

	}

	private function getPageViewEventParams() {
        global $post;

        $cpt = get_post_type();
        $params = array();

		if(!$cpt) return false;

        if(isWooCommerceActive() && $cpt == 'product' && !is_shop()) {
            $params['categories'] = implode( ', ', getObjectTerms( 'product_cat', $post->ID ) );
            $params['tags']       = implode( ', ', getObjectTerms( 'product_tag', $post->ID ) );
        } elseif (isEddActive() && $cpt == 'download') {
            $params['categories'] = implode( ', ', getObjectTerms( 'download_category', $post->ID ) );
            $params['tags']       = implode( ', ', getObjectTerms( 'download_tag', $post->ID ) );
        } elseif ($post instanceof \WP_Post) {
            $params['tags'] = implode( ', ', getObjectTerms( 'post_tag', $post->ID ) );
            if ( ! empty( $taxonomies ) && $terms = getObjectTerms( $taxonomies[0], $post->ID ) ) {
                $params['categories'] = implode( ', ', $terms );
            }
        }

        $data = array(
            'name'  => 'PageView',
            'data'  => $params
        );

        return $data;
    }

    /**
     * @param SingleEvent $event
     * @return false
     */
    private function getWcfViewContentEventParams(&$event) {
        if ( ! $this->getOption( 'woo_view_content_enabled' )
            || empty($event->args['products'])
        ) {
            return false;
        }
        $params = array();
        $product_data = $event->args['products'][0];

        $content_id = Helpers\getFacebookWooProductContentId( $product_data['id'] );
        $params['content_ids'] = $content_id;

        if ( $product_data['type'] ==  'variable'
            && ( !$this->getOption( 'woo_variable_as_simple' )
                || !Helpers\isDefaultWooContentIdLogic()
            )
        ) {
            $params['content_type'] = 'product_group';
        } else {
            $params['content_type'] = 'product';
        }
        if(count($product_data['tags']))
            $params['tags'] = implode( ', ', $product_data['tags'] );

        $params['content_name'] = $product_data['name'];
        $params['category_name'] = implode( ', ', array_column($product_data['categories'],"name") );

        // currency, value
        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );
            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_data['id'],
                'qty' => $product_data['quantity'],
                'price' => $product_data['price']
            ];
            $params['value']    = getWooProductValue($valueArgs);
            $params['currency'] = $event->args['currency'];
        }
        if ( Helpers\isDefaultWooContentIdLogic() )  {
            $params['contents'] =  array(
                array(
                    'id'         => (string) reset( $content_id ),
                    'quantity'   => $product_data['quantity'],
                )
            );
        }
        $params['product_price'] = getWooProductPriceToDisplay( $product_data['id'],
            $product_data['quantity'],
            $product_data['price']
        );

        $event->addParams($params);
        $event->addPayload([
            'name'  => 'ViewContent',
            'delay' => 0,
        ]);
        return true;
    }

	private function getWooViewContentEventParams($eventArgs = null) {
		if ( ! $this->getOption( 'woo_view_content_enabled' ) ) {
			return false;
		}
        $variable_id = null;
		$params = array();
		$quantity = 1;

		if($eventArgs && isset($eventArgs['id'])) {
            $product = wc_get_product($eventArgs['id']);
            $quantity = $eventArgs['quantity'];
        } else {
		    global $post;
            $product = wc_get_product( $post->ID );
        }

        if(!$product) return false;

        $params = $this->getWooViewContentParamsArray($product, $quantity, $eventArgs);

		return array(
			'name'  => 'ViewContent',
			'data'  => $params,
			'delay' => 0,
		);

	}

    private function getWooViewContentParamsArray($product, $quantity = 1, $eventArgs = null) {
        $params = [];
        $variable_id = null;

        // Determine variable_id if needed
        if ($this->getOption('woo_variable_data_select_product') && !$this->getOption('woo_variable_as_simple')) {
            $variable_id = getVariableIdByAttributes($product);
        }

        $product_id = $variable_id ?? $product->get_id();
        $content_id = Helpers\getFacebookWooProductContentId($product_id);
        $params['content_ids'] = $content_id;

        // Set content_type
        if (!Helpers\isDefaultWooContentIdLogic() && wooProductIsType($product, 'variable')) {
            $params['content_type'] = 'product_group';
        } elseif (wooProductIsType($product, 'variable') && !$this->getOption('woo_variable_as_simple')) {
            $params['content_type'] = 'product_group';
        } else {
            $params['content_type'] = 'product';
        }

        // Tags
        $tagsList = getObjectTerms('product_tag', $product->get_id());
        if (!empty($tagsList)) {
            $params['tags'] = implode(', ', $tagsList);
        }

        // Value and currency
        if (PYS()->getOption('woo_view_content_value_enabled')) {
            $valueArgs = [
                'valueOption' => PYS()->getOption('woo_view_content_value_option'),
                'global'      => PYS()->getOption('woo_view_content_value_global', 0),
                'percent'     => PYS()->getOption('woo_view_content_value_percent', 100),
                'product_id'  => $product_id,
                'qty'         => $quantity
            ];
            if ($eventArgs && !empty($eventArgs['discount_value']) && !empty($eventArgs['discount_type'])) {
                $valueArgs['discount_value'] = $eventArgs['discount_value'];
                $valueArgs['discount_type'] = $eventArgs['discount_type'];
            }
            $params['value'] = getWooProductValue($valueArgs);
            $params['currency'] = get_woocommerce_currency();
        }

        // Contents
        if (Helpers\isDefaultWooContentIdLogic()) {
            $params['contents'] = [
                [
                    'id' => (string)reset($content_id),
                    'quantity' => $quantity,
                ]
            ];
        }

        $params['product_price'] = getWooProductPriceToDisplay($product_id);

        // Merge custom audience params last
        $params = array_merge($params, Helpers\getWooCustomAudiencesOptimizationParams($product_id));

        return $params;
    }

	private function getWooAddToCartOnButtonClickEventParams( $args ) {

        $product_id = $args['productId'];
        $quantity = $args['quantity'];

		$params = Helpers\getWooSingleAddToCartParams( $product_id, $quantity, false,$args );
		$data = array(
            'params' => $params,
        );

        $product = wc_get_product($product_id);
        if($product && $product->get_type() == 'grouped') {
            $grouped = array();
            foreach ($product->get_children() as $childId) {
                $conId = getFacebookWooProductContentId( $childId );
                $grouped[$childId] = array(
                    'content_id' => (string) reset($conId),
                    'price' => getWooProductPriceToDisplay( $childId )
                );
            }
            $data['grouped'] = $grouped; // used for add to cart
        }



		return $data;

	}

    /**
     * @param SingleEvent $event
     * @return boolean
     */
	private function getWooAddToCartOnCartEventParams(&$event) {

		if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
			return false;
		}
        if ( !isset( $event->args['products'] ) || empty($event->args['products'])) {
            return false;
        }
        $params = Helpers\getWooCartEventParams($event);

        $event->addParams($params);
        $event->addPayload(['name' => 'AddToCart',]);
        return true;
	}

	private function getWooRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'woo_remove_from_cart_enabled' ) ) {
			return false;
		}

		$product_id = Helpers\getFacebookWooCartItemId( $cart_item );
        $product = wc_get_product($product_id);
        if(!$product) return false;
		$content_id = Helpers\getFacebookWooProductContentId( $product_id );

		$params['content_type'] = 'product';
		$params['content_ids']  =  $content_id ;

		// content_name, category_name, tags
        $tagsList = getObjectTerms( 'product_tag', $product_id );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }
		$params = array_merge( $params, Helpers\getWooCustomAudiencesOptimizationParams( $product_id ) );

		$params['num_items'] = $cart_item['quantity'];
		$params['product_price'] = getWooProductPriceToDisplay( $product_id );



		$params['contents'] =  array(
			array(
				'id'         => (string) reset( $content_id ),
				'quantity'   => $cart_item['quantity'],
				//'item_price' => getWooProductPriceToDisplay( $product_id ),
			)
		) ;

		return array( 'name' => "RemoveFromCart",
            'data' => $params );

	}

	private function getWooViewCategoryEventParams($event) {
		global $posts;

		if ( ! $this->getOption( 'woo_view_category_enabled' ) ) {
			return false;
		}

		if ( Helpers\isDefaultWooContentIdLogic() ) {
			$params['content_type'] = 'product';
		} else {
			$params['content_type'] = 'product_group';
		}

        $params['content_category'] = array();
		$term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );

		if ( $term ) {

            $params['content_name'] = $term->name;

            $parent_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

            foreach ( $parent_ids as $term_id ) {
                $term = get_term_by( 'id', $term_id, 'product_cat' );
                if($term) {
                    $params['content_category'][] = $term->name;
                }

            }

        }

		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
        if(isset($event->args['products'])) {
            foreach ($event->args['products'] as $product_data) {
                $content_ids = array_merge( Helpers\getFacebookWooProductContentId( $product_data['product_id'] ), $content_ids );
            }
        }

		$params['content_ids']  =  $content_ids ;

		return array(
			'name' => 'ViewCategory',
			'data' => $params,
		);

	}

    /**
     * @param SingleEvent $event
     * @return boolean
     */
	private function getWooInitiateCheckoutEventParams(&$event) {

		if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
			return false;
		}
        if ( !isset( $event->args['products'] ) || empty($event->args['products'])) {
            return false;
        }
        $params = Helpers\getWooCartEventParams($event);

        $event->addParams($params);
        $event->addPayload(['name' => 'InitiateCheckout']);
        return true;
	}

    /**
     * @param SingleEvent $event
     * @return array|false
     */
	private function getWooPurchaseEventParams(&$event) {

		if ( ! $this->getOption( 'woo_purchase_enabled' ) && !empty($event->args['order_id'])) {
			return false;
		}

        $params = $this->getWooPurchaseParams($event);

        $event->addParams($params);
        $event->addPayload([
            'name' => 'Purchase',
        ]);

		return true;

	}

    private function getWooPurchaseParams($event)
    {
        $contents = [];
        $content_ids = [];
        $tags = [];
        $categories = [];
        $content_names = [];
        $num_items = 0;
        $tax = 0;

        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $withTax = 'incl' === get_option( 'woocommerce_tax_display_cart' );

        foreach ($event->args['products'] as $product_data) {
            $product_id = Helpers\getFacebookWooProductDataId($product_data);
            $content_id  = Helpers\getFacebookWooProductContentId( $product_id );

            $content_ids = array_merge( $content_ids, $content_id );
            $num_items += $product_data['quantity'];
            $content_names[] = $product_data['name'];
            $tags = array_merge( $tags, $product_data['tags'] );
            $categories = array_merge( $categories, array_column($product_data['categories'],"name") );

            $price = getWooProductPrice($product_id);

            $contents[] = [
                'id'         => (string) reset( $content_id ),
                'quantity'   => $product_data['quantity'],
                'item_price' => $price,
            ];
            $tax += (float) $product_data['total_tax'];
        }

        $tags = array_unique( $tags );
        $categories = array_unique( $categories );

        $tax += (float) $event->args['shipping_tax'];
        $total = getWooEventOrderTotal($event);
        $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$event->args);

        $shipping_cost = $event->args['shipping_cost'];
        if($withTax) {
            $shipping_cost += $event->args['shipping_tax'];
        }
        $params = [
            'content_type'  => 'product',
            'content_ids'   => $content_ids,
            'content_name'  => implode( ', ', $content_names ),
            'category_name' => implode( ', ', $categories ),
            'tags'          => implode( ', ', $tags ),
            'num_items'     => $num_items,
            'value'         => pys_round($value,2),
            'currency'      => $event->args['currency'],
            'order_id'      => wooMapOrderId($event->args['order_id']),
            'shipping'      => $event->args['shipping'],
            'coupon_used'   => $event->args['coupon_used'],
            'coupon_name'   => $event->args['coupon_name'],
            'total'         => pys_round($total,2),
            'tax'           => pys_round($tax,2),
            'shipping_cost' => pys_round($shipping_cost,2),
            'predicted_ltv' => isset($event->args['predicted_ltv']) ?$event->args['predicted_ltv']: "",
            'average_order' => isset($event->args['average_order']) ?$event->args['average_order']: "",
            'transactions_count' => isset($event->args['transactions_count']) ? $event->args['transactions_count']: "",
        ];
        if(isset($event->args['fees'])){
            $params['fees'] = (float) $event->args['fees'];
        }
        // contents
        if ( Helpers\isDefaultWooContentIdLogic() ) {
            // Facebook for WooCommerce plugin does not support new Dynamic Ads parameters
            $params['contents'] = $contents;
        }

        return $params;
    }


	private function getWooAffiliateEventParams( $product_id,$quantity ) {

		if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooSingleAddToCartParams( $product_id, $quantity, true );

		return array(
			'params' => $params,
		);

	}

    /**
     * @param SingleEvent $event
     * @return boolean
     */
	private function getWooPayPalEventParams(&$event) {

		if ( ! $this->getOption( 'woo_paypal_enabled' ) ) {
			return false;
		}
        if ( !isset( $event->args['products'] ) || empty($event->args['products'])) {
            return false;
        }
		// we're using Cart date as of Order not exists yet
		$params = Helpers\getWooCartEventParams( $event );
        $event->addParams($params);
        $event->addPayload(['name' => getWooPayPalEventName(),]);
        return true;


	}

	private function getWooAdvancedMarketingEventParams( $eventType ) {

		if ( ! $this->getOption( $eventType . '_enabled' ) ) {
			return false;
		}

        $customer_params = EventsWoo()->getCustomerTotals();
        $params = array(
            "plugin" => "PixelYourSite"
        );


		switch ( $eventType ) {
			case 'woo_frequent_shopper':
				$eventName = 'FrequentShopper';
                $params['transactions_count'] = $customer_params['orders_count'];
				break;

			case 'woo_vip_client':
				$eventName = 'VipClient';
                $params['average_order'] = $customer_params['avg_order_value'];
                $params['transactions_count'] = $customer_params['orders_count'];
				break;

            case 'woo_FirstTimeBuyer':

                $eventName = 'FirstTimeBuyer';
                break;
            case 'woo_ReturningCustomer':
                $eventName = 'ReturningCustomer';
                break;
			default:
                $params['predicted_ltv'] = $customer_params['ltv'];
				$eventName = 'BigWhale';
		}

		return array(
			'payload' => array('name' => $eventName),
			'data' => $params,
		);

	}

	/**
	 * @param CustomEvent $customEvent
	 *
	 * @return array|bool
	 */
	private function getCustomEventParams( $event ) {
        global $post;
        $customEvent = $event->args;
		$event_type = $customEvent->getFacebookEventType();

		if ( ! $customEvent->isFacebookEnabled() || empty( $event_type ) ) {
			return false;
		}

		$params = array();

		// add pixel params
		if ( $customEvent->isFacebookParamsEnabled() ) {


            $params = $customEvent->getFacebookParams();


			// use custom currency if any
			if ( ! empty( $params['custom_currency'] ) ) {
				$params['currency'] = $params['custom_currency'];
				unset( $params['custom_currency'] );
			}

			// add custom params
            $customParams = $customEvent->getFacebookCustomParams();
			foreach ( $customParams as $custom_param ) {
				$params[ $custom_param['name'] ] = $custom_param['value'];
			}

		}

        $trigger_types = array();
        foreach ($customEvent->getTriggers() as $trigger) {
            $trigger_types[] = $trigger->getTriggerType();
        }
        if ( in_array( 'purchase', $trigger_types ) && ($trigger->getTrackValueAndCurrency() || $trigger->getTrackTransactionID()) ) {
            $order = EventsWoo()->getOrder();
            if ($order) {
                $added_params = array();
                if($trigger->getTrackValueAndCurrency()) {
                    $added_params['value'] = $order->get_total();
                    $added_params['currency'] = $order->get_currency();
                }
                if($trigger->getTrackTransactionID()) {
                    $added_params['transaction_id'] = $order->get_id();
                }
                $params = array_merge($params, $added_params);
            }
        }

        if ( in_array( 'add_to_cart', $trigger_types )  && $trigger->getTrackValueAndCurrency() && $event->getPayloadValue('productId')) {
            $product_id = $event->getPayloadValue('productId');
            $quantity = $event->getPayloadValue('quantity') ?? 1;

            $product = wc_get_product($product_id);
            if(!$product) return false;
            $value = $product->get_price();
            if ( $product->is_taxable()) {
                $value = wc_get_price_including_tax( $product, array(
                    'price' => $value,
                    'qty' => $quantity
                ) );
            } else {
                $value = wc_get_price_excluding_tax( $product, array(
                    'price' => $value,
                    'qty' => $quantity
                ) );
            }
            $params = array_merge($params, [
                'currency' => get_woocommerce_currency(),
                'value' => $value,
                'quantity' => $quantity,
            ]);
        }
        // SuperPack Dynamic Params feature
        $params = apply_filters( 'pys_superpack_dynamic_params', $params, 'facebook' );
        if(isWooCommerceActive()){
            if($customEvent->facebook_track_single_woo_data && is_product() && ($post->post_type == 'product' || $event->getPayloadValue('productId'))){
                $product_id = $event->getPayloadValue('productId') ?? $post->ID;
                $quantity = $event->getPayloadValue('quantity') ?? 1;
                $product = wc_get_product( $product_id );
                if($product){
                    $viewEvent = EventsWoo()->getEvent('woo_view_content');
                    $params = array_merge($params, $this->getWooViewContentParamsArray($product, $quantity, $viewEvent));
                }
            } elseif($customEvent->facebook_track_cart_woo_data) {
                $order = EventsWoo()->getOrder();
                if($order){
                    $purchaseEvent = EventsWoo()->getEvent('woo_purchase');
                    if($purchaseEvent) {
                        $params = array_merge($params, $this->getWooPurchaseParams($purchaseEvent));
                    }
                } else {
                    $cart_params = Helpers\getWooCartParams('InitiateCheckout');
                    if(count($cart_params['content_ids']) > 0) {
                        $params = array_merge($params, $cart_params);
                    }
                }
            }
        }


        $data = array(
            'name'  => $customEvent->getFacebookEventType(),
            'data'  => $params,
            'delay' => $customEvent->getDelay(),
        );

		return $data;

	}

    /**
     * @param SingleEvent $event
     * @return array|false
     */
	private function getWooCompleteRegistrationEventParams(&$event) {

        $eventName =  'CompleteRegistration';
        if($this->getOption('woo_complete_registration_value_enabled')) {
            $params = Helpers\getCompleteRegistrationOrderParams($event);
        } else {
            $params = array();
        }
        $event->addParams($params);
        $event->addPayload(['name' => $eventName]);

		return  true;

	}


	private function getEddViewContentEventParams() {
		global $post;

		if ( ! $this->getOption( 'edd_view_content_enabled' ) ) {
			return false;
		}


		$params = array(
			'content_type' => 'product',
			'content_ids'  =>  Helpers\getFacebookEddDownloadContentId( $post->ID ) ,
		);

		// content_name, category_name
        $tagsList = getObjectTerms( 'download_tag', $post->ID );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'edd_view_content_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $post->ID );
			} else {
				$amount = getEddDownloadPriceToDisplay( $post->ID );
			}

			$value_option   = PYS()->getOption( 'edd_view_content_value_option' );
			$global_value   = PYS()->getOption( 'edd_view_content_value_global', 0 );
			$percents_value = PYS()->getOption( 'edd_view_content_value_percent', 100 );

			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
            $params['currency'] = edd_get_currency();

		}

		// contents
		$params['contents'] =  array(
			array(
				'id'         => (string) $post->ID,
				'quantity'   => 1,
				//'item_price' => getEddDownloadPriceToDisplay( $post->ID ),
			)
		) ;

		return array(
			'name'      => 'ViewContent',
			'data'      => $params,
		);

	}

	private function getEddAddToCartOnButtonClickEventParams( $download_id ) {
		global $post;

		// maybe extract download price id
		if ( strpos( $download_id, '_') !== false ) {
			list( $download_id, $price_index ) = explode( '_', $download_id );
		} else {
			$price_index = null;
		}

		$params = array(
			'content_type' => 'product',
			'content_ids'  =>  Helpers\getFacebookEddDownloadContentId( $download_id ) ,
		);

		// content_name, category_name
        $tagsList = getObjectTerms( 'download_tag', $download_id );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $download_id ) );

		// currency, value
		if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $download_id, $price_index );
			} else {
				$amount = getEddDownloadPriceToDisplay( $download_id, $price_index );
			}

			$value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
			$percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
            $params['currency'] = edd_get_currency();
			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
		}


		$license = getEddDownloadLicenseData( $download_id );
		$params  = array_merge( $params, $license );

		// contents
		$params['contents'] =  array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => 1,
				//'item_price' => getEddDownloadPriceToDisplay( $download_id ),
			)
		);

		return $params;

	}

    /**
     * @param SingleEvent $event
     * @param array $args
     * @return boolean
     */
	private function setEddCartEventParams( $event,$args = [] ) {

        $data=[];
        $params = [];
        $value_enabled = false;

        switch ($event->getId()) {
            case 'edd_add_to_cart_on_checkout_page': {
                if(!$this->getOption( 'edd_add_to_cart_enabled' )) return false;
                $data['name'] = 'AddToCart';
                $params['content_type'] = 'product';
                $value_enabled  = PYS()->getOption( 'edd_add_to_cart_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
                $percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
            }break;
            case 'edd_initiate_checkout': {
                if(!$this->getOption( 'edd_initiate_checkout_enabled' )) return false;
                $data['name'] = 'InitiateCheckout';
                $params['content_type'] = 'product';
                $value_enabled  = PYS()->getOption( 'edd_initiate_checkout_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_initiate_checkout_value_option' );
                $percents_value = PYS()->getOption( 'edd_initiate_checkout_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_initiate_checkout_value_global', 0 );
            }break;
            case 'edd_purchase':{
                if(! $this->getOption( 'edd_purchase_enabled' )) return false;
                $data['name'] = 'Purchase';
                $params['content_type'] = 'product';
                $value_enabled  = PYS()->getOption( 'edd_purchase_value_enabled', true );
                $value_option   = PYS()->getOption( 'edd_purchase_value_option' );
                $percents_value = PYS()->getOption( 'edd_purchase_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_purchase_value_global', 0 );
            }break;
            case 'edd_frequent_shopper':
                {
                    if ( ! $this->getOption( $event->getId() . '_enabled' ) ) return false;
                    $data['name'] = 'FrequentShopper';
                }
                break;

            case 'edd_vip_client':
                {
                    if ( ! $this->getOption( $event->getId() . '_enabled' ) ) return false;
                    $data['name'] = 'VipClient';
                }
                break;

            case 'edd_big_whale': {
                if ( ! $this->getOption( $event->getId() . '_enabled' ) ) return false;
                $data['name'] = 'BigWhale';
            }break;

        }



		$content_ids        = array();
		$content_names      = array();
		$content_categories = array();
		$tags               = array();
		$contents           = array();

		$num_items   = 0;
		$total       = 0;
		$total_as_is = 0;
        $tax = 0;
		$licenses = array(
			'transaction_type'   => null,
			'license_site_limit' => null,
			'license_time_limit' => null,
			'license_version'    => null
		);

		foreach ( $event->args['products'] as $product ) {

			$download_id   = (int) $product['product_id'];
			$content_ids[] = Helpers\getFacebookEddDownloadContentId( $download_id );

			$content_names[]    = $product['name'];
            $content_categories = array_merge($content_categories,array_column($product['categories'],'name'));
			$tags = array_merge( $tags,$product['tags'] );

			$num_items += $product['quantity'];

			// calculate cart items total
            if ( in_array($event->getId(), array('edd_purchase','edd_frequent_shopper','edd_vip_client','edd_big_whale')) ) {

                if ( PYS()->getOption( 'edd_tax_option' ) == 'included' ) {
                    $total += $product['subtotal'] + $product['tax'] - $product['discount'];
                } else {
                    $total += $product['subtotal'] - $product['discount'];
                }
                $tax += $product['tax'];
                $total_as_is += $product['price'];

            } else {

                $total += getEddDownloadPrice( $download_id,$product['price_index']  ) * $product['quantity'];
                $total_as_is += edd_get_cart_item_final_price( $product['cart_item_key']  );

            }



			// get download license data
			array_walk( $licenses, function( &$value, $key, $license ) {

				if ( ! isset( $license[ $key ] ) ) {
					return;
				}

				if ( $value ) {
					$value = $value . ', ' . $license[ $key ];
				} else {
					$value = $license[ $key ];
				}

			}, getEddDownloadLicenseData( $download_id ) );

			// contents
			$contents[] = array(
				'id'         => (string) $download_id,
				'quantity'   => $product['quantity'],
				//'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			);

		}
        $content_categories = array_unique($content_categories);
        $tags = array_unique( $tags );
        $tags = array_slice( array_unique( $tags ), 0, 100 );



		$params['category_name'] = implode( ', ', $content_categories );
        $params['tags']          = implode( ', ', $tags );

		$params['num_items']     = $num_items;

        if($event->getId() == 'edd_frequent_shopper'
            || $event->getId() == 'edd_vip_client'
            || $event->getId() == 'edd_big_whale'
        ) {
            $params['product_names'] = implode( ', ', $content_names );
            $params['product_ids'] = $content_ids;
        } else {
            $params['content_ids']   =  $content_ids ;
            $params['content_name']  = implode( ', ', $content_names );
            $params['contents']      =  $contents ;
        }
//add fee
        $fee = isset($event->args['fee']) ? $event->args['fee'] : 0;
        $feeTax= isset($event->args['fee_tax']) ? $event->args['fee_tax'] : 0;
        if(PYS()->getOption( 'edd_event_value' ) == 'custom') {
            if(PYS()->getOption( 'edd_tax_option' ) == 'included') {
                $total += $fee + $feeTax;
            } else {
                $total += $fee;
            }
        } else {
            if(edd_prices_include_tax()) {
                $total_as_is += $fee + $feeTax;
            } else {
                $total_as_is += $fee;
            }
        }

        $tax += $feeTax;



		// currency, value
		if ( $value_enabled ) {
            $params['currency'] = edd_get_currency();
			$params['value']    = getEddEventValue( $value_option, $total, $global_value, $percents_value );
		}


		$params = array_merge( $params, $licenses );

		if ( $event->getId() == 'edd_purchase' ) {
            $payment_id = $event->args['order_id'];
            $params['coupon'] = $event->args['coupon'];

			// calculate value
            if( PYS()->getOption( 'edd_event_value' ) == 'custom' && $value_enabled) {
                $params['value']  = getEddEventValue( $value_option, $total, $global_value, $percents_value );
            } else {
                $params['value']  = getEddEventValue( $value_option, $total_as_is, $global_value, $percents_value );
            }
            $params['currency'] = edd_get_currency();
			if ( edd_use_taxes() ) {
				$params['tax'] = $tax;
			} else {
				$params['tax'] = 0;
			}
            $data['edd_order'] = $params['order_id'] = eddMapOrderId($payment_id);
		}

        $event->addParams($params);
        $event->addPayload($data);
		return true;

	}

	private function getEddRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'edd_remove_from_cart_enabled' ) ) {
			return false;
		}

		$download_id = $cart_item['id'];
		$price_index = ! empty( $cart_item['options'] ) && !empty($cart_item['options']['price_id']) ? $cart_item['options']['price_id'] : null;

		$params = array(
			'content_type' => 'product',
			'content_ids' => Helpers\getFacebookEddDownloadContentId( $download_id )
		);

		// content_name, category_name, tags
        $tagsList = getObjectTerms( 'download_tag', $download_id );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $download_id ) );

		$params['num_items'] = $cart_item['quantity'];

		$params['contents'] =  array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => $cart_item['quantity'],
				'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			)
		) ;

		return array(
		    'name' => 'RemoveFromCart',
		    'data' => $params );

	}

	private function getEddViewCategoryEventParams() {
		global $posts;

		if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
			return false;
		}



		$params['content_type'] = 'product';

		$term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
        if(!$term) return false;
		$params['content_name'] = $term->name;

		$parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );
		$params['content_category'] = array();

		foreach ( $parent_ids as $term_id ) {
			$parentTerm = get_term_by( 'id', $term_id, 'download_category' );
			$params['content_category'][] = $parentTerm->name;
		}

		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
		$limit       = min( count( $posts ), 5 );

		for ( $i = 0; $i < $limit; $i ++ ) {
			$content_ids = array_merge( array( Helpers\getFacebookEddDownloadContentId( $posts[ $i ]->ID ) ),
				$content_ids );
		}

		$params['content_ids']  =  $content_ids ;

		return array(
			'name' => 'ViewCategory',
			'data' => $params
		);

	}

    /**
     * @return []
     */
    public function getApiTokens() {

        $tokens = array();

        if(EventsWcf()->isEnabled() ) {
            $ids = $this->getOption( 'wcf_pixel_id' );
            $token = $this->getOption( 'wcf_server_access_api_token' );
            if(!empty($ids) && !empty($token)) {
                $tokens[$ids]=$token;
            }
        }


        $pixelids = (array) $this->getOption( 'pixel_id' );
        if(count($pixelids) > 0) {
            $serverids = (array) $this->getOption( 'server_access_api_token' );
            $tokens[$pixelids[0]] =  reset( $serverids );
        }


        if(isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' )) {
            $additionalPixels = SuperPack()->getFbAdditionalPixel();
            foreach ($additionalPixels as $additionalPixel) {
                if(array_key_exists('api_token',$additionalPixel->extensions)){
                    $tokens[$additionalPixel->pixel] = $additionalPixel->extensions['api_token'];
                }
            }
        }
        if($this->getOption( 'fdp_use_own_pixel_id' )) {
            $fdpPixel = $this->getOption( 'fdp_use_own_pixel_id' );
            $fdpServerApi = $this->getOption( 'fdp_pixel_server_id' );
            if($fdpServerApi)
                $tokens[$fdpPixel] = $fdpServerApi;
        }

        return $tokens;
    }

    /**
     * @return []
     */
    public function getApiTestCode() {
        $testCode = array();
        if(EventsWcf()->isEnabled() ) {
            $ids = $this->getOption( 'wcf_pixel_id' );
            $code = $this->getOption( 'wcf_test_api_event_code' );
            if(!empty($ids) && !empty($code)) {
                $testCode[$ids]=$code;
            }
        }

        $pixelids = (array) $this->getOption( 'pixel_id' );
        if(count($pixelids) > 0) {
            $serverTestCode = (array)$this->getOption('test_api_event_code');
            $testCode[$pixelids[0]] = reset($serverTestCode);
        }

        if(isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' )) {
            $additionalPixels = SuperPack()->getFbAdditionalPixel();
            foreach ($additionalPixels as $additionalPixel) {
                if(array_key_exists('api_code',$additionalPixel->extensions)){
                    $testCode[$additionalPixel->pixel] = $additionalPixel->extensions['api_code'];
                }
            }

        }

        if($this->getOption( 'fdp_use_own_pixel_id' )) {
            $fdpPixel = $this->getOption( 'fdp_use_own_pixel_id' );
            $fdpTestCode = $this->getOption( 'fdp_pixel_server_test_code' );
            if($fdpTestCode)
            $testCode[$fdpPixel] = $fdpTestCode;
        }

        return $testCode;
    }

    function output_meta_tag() {
        if(EventsWcf()->isEnabled() && isWcfStep()) {
            $tag = $this->getOption( 'wcf_verify_meta_tag' );
            if(!empty($tag)) {
                echo $tag;
                return;
            }
        }
        $metaTags = (array) Facebook()->getOption( 'verify_meta_tag' );
        foreach ($metaTags as $tag) {
            echo $tag;
        }
    }



    /**
     * @return bool
     */
    public function isServerApiEnabled() {
        return $this->getOption("use_server_api");
    }

    private function prepare_wcf_remove_from_cart(&$event) {
        if( ! $this->getOption( 'woo_remove_from_cart_enabled' )
            || empty($event->args['products'])
        ) {
            return false; // return if args is empty
        }
        $product_data = $event->args['products'][0];
        $content_id = getFacebookWooProductContentId( $product_data['id'] );
        $product_price = getWooProductPriceToDisplay($product_data['id'],$product_data['quantity'],$product_data['price']);
        $params = [
            'content_type'  => 'product',
            'content_ids'   => $content_id,
            'tags'          => implode( ', ', $product_data['tags'] ),
            'num_items'     => $product_data['quantity'],
            'content_name'  => $product_data['name'],
            'product_price' => $product_price,
            'category_name' => implode( ', ', array_column($product_data['categories'],"name") ),
            'contents'      => [
                'id'         => (string) reset( $content_id ),
                'quantity'   => $product_data['quantity'],
            ]
        ];

        $event->addParams($params);

        // add additional information for event
        $payload = [
            'name'=>"RemoveFromCart",
        ];

        $event->addPayload($payload);

        return true;
    }

    /**
     * @param SingleEvent $event
     * @return false
     */
    private function prepare_wcf_add_to_cart(&$event) {

        if(  !$this->getOption( 'woo_add_to_cart_enabled' )
            || empty($event->args['products'])
        ) {
            return false; // return if args is empty
        }
        $params = [
            'content_type'  => 'product',
        ];

        $value_enabled_option = 'woo_add_to_cart_value_enabled';
        $value_option_option  = 'woo_add_to_cart_value_option';
        $value_global_option  = 'woo_add_to_cart_value_global';
        $value_percent_option = 'woo_add_to_cart_value_percent';

        $content_ids        = array();
        $content_names      = array();
        $content_categories = array();
        $tags               = array();
        $contents           = array();

        $value = 0;

        foreach ($event->args['products'] as $product_data) {

            $content_id = getFacebookWooProductContentId( $product_data['id'] );
            $content_ids = array_merge( $content_ids, $content_id );
            $content_names[] = $product_data['name'];
            $content_categories[] = implode( ', ',array_column($product_data['categories'],"name"));
            $tags = array_merge( $tags, $product_data['tags'] );
            $contents[] = array(
                'id'         => (string) reset( $content_id ),
                'quantity'   => $product_data['quantity'],
            );

            if(PYS()->getOption( $value_enabled_option ) ) {
                $value += getWooProductValue([
                    'valueOption'   => PYS()->getOption( $value_option_option ),
                    'global'        => PYS()->getOption( $value_global_option, 0 ),
                    'percent'       => (float) PYS()->getOption( $value_percent_option, 100 ),
                    'product_id'    => $product_data['id'],
                    'qty'           => $product_data['quantity'],
                    'price'         => $product_data['price']
                ]);
            }
        }

        $params['content_ids']   = ( $content_ids );
        $params['content_name']  = implode( ', ', $content_names );
        $params['category_name'] = implode( ', ', $content_categories );

        // contents
        if ( Helpers\isDefaultWooContentIdLogic() ) {
            // Facebook for WooCommerce plugin does not support new Dynamic Ads parameters
            $params['contents'] = ( $contents );
        }

        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 100 );
        if(count($tags)) {
            $params['tags'] = implode( ', ', $tags );
        }

        if(PYS()->getOption( $value_enabled_option ) ) {
            $params['value'] = $value;
            $params['currency'] = $event->args['currency'];
        }


        $event->addParams($params);

        // add additional information for event
        $payload = [
            'name'=>"AddToCart",
        ];

        $event->addPayload($payload);
        return true;
    }
	function sanitize_verify_meta_tag_field($values) {
		$values = is_array( $values ) ? $values : array();
		$sanitized = array();
		$allowed_html = array(
			'meta' => array(
				'name' => array(),
				'content' => array(),
			),
		);
		foreach ( $values as $key => $value ) {

			$value = wp_kses($value, $allowed_html);
			$new_value = $this->sanitize_textarea_field( $value );

			if ( ! empty( $new_value ) && ! in_array( $new_value, $sanitized ) ) {
				$sanitized[ $key ] = $new_value;
			}

		}

		return $sanitized;
	}

	public function getLDUMode() {
		return apply_filters( 'pys_meta_ldu_mode', false );
	}

    public function getLog() {
        return $this->logger;
    }
}

/**
 * @return Facebook
 */
function Facebook() {
	return Facebook::instance();
}

Facebook();
