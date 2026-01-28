<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_analytics/function-helpers.php';
/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_ads/function-helpers.php';

use PixelYourSite\Ads\Helpers;

class GoogleAds extends Settings implements Pixel {

	private static $_instance;

	private $configured;

	/** @var array $wooOrderParams Cached WooCommerce Purchase and AM events params */
	private $wooOrderParams = array();

	private $googleBusinessVertical;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	public function __construct() {

		parent::__construct( 'google_ads' );

		$this->locateOptions(
			PYS_PATH . '/modules/google_ads/options_fields.json',
			PYS_PATH . '/modules/google_ads/options_defaults.json'
		);

		add_action( 'pys_register_pixels', function( $core ) {
			/** @var PYS $core */
			$core->registerPixel( $this );
		} );

        add_action('init', array($this, 'init'));

        add_filter('pys_google_ads_settings_sanitize_ads_ids_field', 'PixelYourSite\Ads\Helpers\sanitizeTagIDs');
		add_filter('pys_google_ads_settings_sanitize_verify_meta_tag_field', array($this, 'sanitize_verify_meta_tag_field'));
        add_action( 'add_meta_boxes', array( $this, 'registerProductMetaBox' ) );
        add_action( 'save_post_product', array( $this, 'saveProductMetaBox' ), 10, 3 );
        add_action( 'wp_head', array( $this, 'output_meta_tag' ) );
	}

    public function init()
    {
        $this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) === 'ecomm' ? 'retail' : 'custom';
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

        if (EventsWcf()->isEnabled() && isWcfStep()) {
            $ids = $this->getOption('wcf_pixel_id');
            if (!empty($ids))
                return [$ids];
        }

		if( isSuperPackActive()
			&& SuperPack()->getOption( 'enabled' )
			&& SuperPack()->getOption( 'additional_ids_enabled' ) )
		{
			if ( !$this->getOption( 'main_pixel_enabled' ) ) {
				return apply_filters( "pys_google_ads_ids", [] );
			}
		}

        $ids = (array)$this->getOption('ads_ids');

        if(count($ids) == 0 || empty($ids[0])) {
            return apply_filters("pys_google_ads_ids",[]);
        } else {
			$id = array_shift($ids);
			return apply_filters("pys_google_ads_ids", array($id)); // return first id only
        }
	}

    public function getAllPixels($checkLang = true) {
        $pixels = $this->getPixelIDs();

        if( isSuperPackActive()
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' )
        ) {
            $additionalPixels = SuperPack()->getAdsAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isEnable
                    && (!$checkLang || $_pixel->isValidForCurrentLang())
                ) {

                        $pixels[]=$_pixel->pixel;
                }

            }
        }

        return $pixels;
    }


    /**
     * @param SuperPack\SPPixelId $pixelId
     * @return bool
     */
    private function isValidForCurrentLang($pixelId) {
        if(isWPMLActive()) {
            $current_lang_code = apply_filters( 'wpml_current_language', NULL );
            if(is_array($pixelId->wpmlActiveLang) && !in_array($current_lang_code,$pixelId->wpmlActiveLang)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param PYSEvent $event
     * @return array|mixed|void
     */
    public function getAllPixelsForEvent($event) {

		$pixels = array();
		$main_pixel = $this->getPixelIDs();



		if(isSuperPackActive('3.0.0')
			&& SuperPack()->getOption( 'enabled' )
			&& SuperPack()->getOption( 'additional_ids_enabled' )
		) {
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

            $additionalPixels = SuperPack()->getAdsAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isValidForEvent($event) && $_pixel->isConditionalValidForEvent($event)) {
                    $pixels[]=$_pixel->pixel;
                }
            }
		} elseif ( $this->getOption( 'main_pixel_enabled' ) ) {
			$pixels = array_merge( $pixels, $main_pixel );
		}
        return $pixels;
    }

	public function getPixelOptions() {

        $enhanced_conversion = $this->getOption('enhanced_conversions_manual_enabled');
		$data = array(
			'conversion_ids'      => $this->getAllPixels(),
            'enhanced_conversion' => $enhanced_conversion,
            'woo_purchase_conversion_track' => $this->getOption( 'woo_purchase_conversion_track' ),
			'woo_initiate_checkout_conversion_track' => $this->getOption( 'woo_initiate_checkout_conversion_track' ),
			'woo_add_to_cart_conversion_track' => $this->getOption( 'woo_add_to_cart_conversion_track' ),
			'woo_view_content_conversion_track' => $this->getOption( 'woo_view_content_conversion_track' ),
			'woo_view_category_conversion_track' => $this->getOption( 'woo_view_category_conversion_track' ),
			'edd_purchase_conversion_track' => $this->getOption( 'edd_purchase_conversion_track' ),
			'edd_initiate_checkout_conversion_track' => $this->getOption( 'edd_initiate_checkout_conversion_track' ),
			'edd_add_to_cart_conversion_track' => $this->getOption( 'edd_add_to_cart_conversion_track' ),
			'edd_view_content_conversion_track' => $this->getOption( 'edd_view_content_conversion_track' ),
			'edd_view_category_conversion_track' => $this->getOption( 'edd_view_category_conversion_track' ),
            'wooVariableAsSimple' => GATags()->getOption( 'woo_variable_as_simple' ),
			'crossDomainEnabled'            => GA()->getOption( 'cross_domain_enabled' ),
			'crossDomainAcceptIncoming'     => GA()->getOption( 'cross_domain_accept_incoming' ),
			'crossDomainDomains'            => GA()->getOption( 'cross_domain_domains' ),
		);

        if(isSuperPackActive('3.3.1') && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'enable_hide_this_tag_by_tags' )){
            $data['hide_pixels'] = $this->getHideInfoPixels();
        }
        return $data;
	}

    /**
     * Create pixel event and fill it
     * @param SingleEvent $event
     * @return SingleEvent[]
     */
    public function generateEvents($event) {
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

        $pixelEvents = [];
        $conversionLabel = []; // only for custom (send only conversion without main pixel)
        $pixelIds = [];

        if($event->getId() == 'custom_event' && !GA()->configured()) {
                // ids
                $allIds = $this->getAllPixelsForEvent($event);
                $customEvent = $event->args;
                $conversion_label = $customEvent->ga_ads_conversion_label;
                $conversion_id = $customEvent->ga_ads_pixel_id;

                if(is_array($conversion_id)){
	                $preselectedPixel = array_filter((array)$event->args->ga_ads_pixel_id, static function ($element) use ($allIds) {
		                return in_array( $element, $allIds, true ) || $element == 'all';
	                });

	                if(in_array('all', $preselectedPixel)){
		                $pixelIds = $allIds;
	                }
	                else{
		                $pixelIds = array_filter($preselectedPixel, static function ($element) use ($disabledPixel) {
			                return !in_array($element, $disabledPixel);
		                });
		                $pixelIds = array_values($pixelIds);
	                }
	                if($conversion_label != NULL){
		                $pixelIds = array_map(function($pixelId) use ($conversion_label) {
			                if (strpos($pixelId, "AW") === 0) {
				                return $pixelId . '/' . $conversion_label;
			                }
			                return $pixelId;
		                }, $pixelIds);
	                }

                }else{
                    if ( $conversion_id == 'all' ) {
                        if(count($allIds) > 0) {
                            $conversion_id = $allIds[0];
                        }
                    }

                    if(!in_array($conversion_id,$allIds) || $conversion_id == $disabledPixel) {
                        return []; // not fire event if pixel id was disabled or deleted
                    }
                    // AW-12345678 => AW-12345678/da324asDvas
                    if ( ! empty( $conversion_label ) ) {
                        $conversionLabel = [$conversion_id. '/' . $conversion_label];
                    } else {
                        if($conversion_id) {
                            $pixelIds = [$conversion_id];
                        }
                    }
                }


        } else {

            // filter disabled pixels
            $pixelIds = $this->getAllPixelsForEvent($event);

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
        }


        $listOfEddEventWithProducts = ['edd_add_to_cart_on_checkout_page','edd_initiate_checkout','edd_purchase',];
        $listOfWooEventWithProducts = ['woo_purchase', 'woo_initiate_checkout','woo_add_to_cart_on_checkout_page','woo_add_to_cart_on_cart_page'];
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
                $pixels_for_filter = array_merge($pixels_for_filter, SuperPack()->getAdsAdditionalPixel());
            }
            $array_products = array();
            foreach ($pixels_for_filter as $_pixel) {
                $filter = null;

                if(!$_pixel->isValidForEvent($event)|| $_pixel->pixel == $disabledPixel) continue;

                if($isWooEventWithProducts) {
                    $filter = $_pixel->getWooFilter();
                }
                if($isEddEventWithProducts) {
                    $filter = $_pixel->getEddFilter();
                }
                if($filter != null) {
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
                $group = [$pixel];
                foreach ($array_products as $other_pixel => $other_products) {
                    if ($pixel !== $other_pixel && !in_array($other_pixel, $processed)) {
                        $other_ids = array_column($other_products, 'product_id');
                        if ($ids === $other_ids) {
                            $group[] = $other_pixel;
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
                $pixelEvent->addPayload([ 'conversion_ids' => $group ]);
                if($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvents[] = $pixelEvent;
                }
            }
        } elseif ( count( $pixelIds ) > 0 || count( $conversionLabel ) > 0 ) {
            $pixelEvent = clone $event;
            if ( count( $pixelIds ) > 0 ) {
                $pixelEvent->addPayload( [ 'conversion_ids' => $pixelIds ] );
            }

            $labels = array();
            $conversionLabel = array_merge( $conversionLabel, $labels );
            if ( count( $conversionLabel ) > 0 ) {
                $pixelEvent->addPayload( [ 'conversion_labels' => $conversionLabel ] );
            }

            if ( $this->addParamsToEvent( $pixelEvent ) ) {
                $pixelEvents[] = $pixelEvent;
            }
        }
        return $pixelEvents;
    }
    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function addParamsToEvent(&$event) {
        if ( ! $this->configured() ) {
            return false;
        }
        $isActive = false;
        switch ($event->getId()) {
            case 'init_event':{
                $eventData = $this->getPageViewEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            //Automatic events
            case 'automatic_event_signup' : {
                $event->addPayload(["name" => "sign_up"]);
				$isActive = $this->addEventLabels($event);
            } break;
            case 'automatic_event_login' :{
                $event->addPayload(["name" => "login"]);
				$isActive = $this->addEventLabels($event);
            } break;
            case 'automatic_event_search' :{
                $event->addPayload(["name" => "search"]);
                if(!empty( $_GET['s'] )) {
                    $event->addParams(["search_term" => $_GET['s']]);
                }
				$isActive = $this->addEventLabels($event);
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
            case "automatic_event_internal_link":{
				$isActive = $this->addEventLabels($event);
            }break;

            case 'woo_view_content':{
                $eventData = $this->getWooViewContentEventParams($event->args);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            } break;
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page': {
                $isActive = $this->getWooAddToCartOnCartEventParams($event);
            }break;

            case 'woo_view_item_list':{
                $eventData = $this->getWooViewCategoryEventParams($event);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
	        case 'woo_initiate_checkout':{
		        $isActive =  $this->setWooInitiateCheckoutEventParams($event);

	        }break;
            case 'woo_purchase':{
                $isActive = $this->getWooPurchaseEventParams($event);

            }break;

			case 'woo_frequent_shopper':
			case 'woo_vip_client':
			case 'woo_big_whale':
			case 'woo_FirstTimeBuyer':
			case 'woo_ReturningCustomer':
			case 'edd_frequent_shopper':
			case 'edd_vip_client':
			case 'edd_big_whale': {
					$eventData = $this->getWooAdvancedMarketingEventParams( $event );
					if ( $eventData ) {
						$isActive = true;
						$this->addDataToEvent( $eventData, $event );
					}
				}
				break;

            case 'edd_view_content':{
                $eventData = $this->getEddViewContentEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'edd_purchase':
            case 'edd_add_to_cart_on_checkout_page':{
                $isActive = $this->setEddCartEventParams( $event );
            }break;
	        case 'edd_initiate_checkout': {
		        $isActive = $this->setEddCartEventParams($event);

	        }break;
            case 'edd_view_category':{
                $eventData = $this->getEddViewCategoryEventParams($event);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'custom_event':{
                $eventData =  $this->getCustomEventData( $event );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_add_to_cart_on_button_click': {
                if (  $this->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $eventData =  $this->getWooAddToCartOnButtonClickEventParams( $event->args );
                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }


                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

            case 'woo_affiliate': {
                if (  $this->getOption( 'woo_affiliate_enabled' ) ) {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData = $this->getWooAffiliateEventParams( $productId,$quantity );
                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }
                }
            }break;

            case 'edd_add_to_cart_on_button_click': {
                if (  $this->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    if($event->args != null) {
                        $eventData =  $this->getEddAddToCartOnButtonClickEventParams( $event->args );
                        $event->addParams($eventData['params']);
                        $event->addPayload(['ids'=>$eventData["ids"]]);
                    }
                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

            case 'wcf_view_content': {
                $isActive =  $this->getWcfViewContentEventParams($event);
            }break;
            case 'wcf_add_to_cart_on_bump_click':
            case 'wcf_add_to_cart_on_next_step_click': {
                $isActive = $this->prepare_wcf_add_to_cart($event);
            }break;

            case 'wcf_remove_from_cart_on_bump_click': {
                    $isActive = $this->prepare_wcf_remove_from_cart($event);
                } break;

            case 'wcf_bump': {
                    $isActive = $this->getOption('wcf_bump_event_enabled');
                }break;

            case 'wcf_page': {
                    $isActive = $this->getOption('wcf_cart_flows_event_enabled');
                }break;

            case 'wcf_step_page': {
                    $isActive = $this->getOption('wcf_step_event_enabled');
                }break;

            case 'wcf_lead': {
                $isActive = PYS()->getOption('wcf_lead_enabled');
            }break;
        }

        return $isActive;
    }

    private function addDataToEvent($eventData,&$event) {
        $params = $eventData["data"];
        unset($eventData["data"]);

        $event->addParams($params);
        $event->addPayload($eventData);
    }

	public function getEventData( $eventType, $args = null ) {

        return false;

    }

    public function outputNoScriptEvents() {

	    /* dont send google ads no script events to google analytics */

    }

    private function getPageViewEventParams() {
        global $post;
        $cpt = get_post_type();
        $params = array();
        $items = array();

        if((!isWooCommerceActive() || ($cpt != "product" && !is_checkout() && !is_cart() && !PYS()->woo_is_order_received_page() && !is_tax('product_cat'))) &&
            (!isEddActive() || ($cpt != "download" && !edd_is_checkout() && !edd_is_success_page() && !is_tax('download_category')))
            ) {

            if (!$this->getOption("page_view_post_enabled") && $cpt == "post") return false;
            if (!$this->getOption("page_view_page_enabled") && $cpt == "page") return false;

            if ($cpt != "post" && $cpt != "page") {
                $enabledCustom = (array)$this->getOption("page_view_custom_post_enabled");
                if (!in_array("index_" . $cpt, $enabledCustom)) return false;
            }

            if(is_category() ) {
                global $posts;
                if($posts) {
                    foreach ($posts as $p) {
                        $items[] = array(
                            "id"=> $p->ID,
                            "google_business_vertical" => $this->getOption("page_view_business_vertical")
                        );
                    }
                }
            } else {
                if($post) {
                    $items[] = array(
                        "id"=> $post->ID,
                        "google_business_vertical" => $this->getOption("page_view_business_vertical")
                    );
                }

            }
        }

        $params['items'] = $items;

	    return array(
		    'name' => 'page_view',
		    'data' => $params,
	    );

    }

    /**
     * @param PYSEvent $event
     *
     * @return array|bool
     */
    private function getCustomEventData( $event ) {
        global $post;
        /**
         * @var CustomEvent $customEvent
         */
        $customEvent = $event->args;

	    $ga_action = $customEvent->getMergedAction();


	    if ( ! $customEvent->isUnifyAnalyticsEnabled() || empty( $ga_action ) ) {
		    return false;
	    }


	    $params = $customEvent->getMergedGaParams();

	    $customParams = $customEvent->getGAMergedCustomParams();
	    foreach ($customParams as $item) {
            $params[$item['name']]=$item['value'];
        }
        if(isWooCommerceActive()) {
            if ($customEvent->ga_ads_track_single_woo_data && is_product() && ($post->post_type === 'product' || $event->getPayloadValue('productId'))) {
                $product_id = $event->getPayloadValue('productId') ?? $post->ID;
                $quantity = $event->getPayloadValue('quantity') ?? 1;
                $eventData = $this->getWooViewContentEventParams(array('id' => $product_id, 'quantity' => $quantity), $event);
                $params = isset($eventData['data']) ? array_merge($params, $eventData['data']) : $params;

            } elseif ($customEvent->ga_ads_track_cart_woo_data) {
                $order = EventsWoo()->getOrder();
                if ($order) {
                    $purchaseEvent = EventsWoo()->getEvent('woo_purchase');
                    if ($purchaseEvent) {
                        $params = array_merge($params, $this->getWooPurchaseParams($purchaseEvent, $order));
                    }
                } else {
                    $params = array_merge($params, $this->getWooCartParams('cart', $event));
                }
            }
        }

	    // SuperPack Dynamic Params feature
	    $params = apply_filters( 'pys_superpack_dynamic_params', $params, 'ga' );

	    return array(
		    'name'  => $customEvent->getMergedAction(),
		    'data'  => $params,
		    'delay' => $customEvent->getDelay(),

	    );
    }

    private function getWooViewCategoryEventParams($event) {
        global $posts;

        if ( ! $this->getOption( 'woo_view_category_enabled' ) ) {
            return false;
        }

        $term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );
        if(!is_a($term,"WP_Term") || !$term)
            return false;
        $parent_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

        $product_categories = array();
        $product_categories[] = $term->name;

        foreach ( $parent_ids as $term_id ) {
            $parent_term = get_term_by( 'id', $term_id, 'product_cat' );
            $product_categories[] = $parent_term->name;
        }

        $list_name = implode( '/', array_reverse( $product_categories ) );

        $items = array();
        $total_value = 0;

        for ( $i = 0; $i < count( $posts ); $i ++ ) {

            if ( $posts[ $i ]->post_type !== 'product' ) {
                continue;
            }

            $item = array(
                'id'            => Helpers\getWooFullItemId( $posts[ $i ]->ID ),
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value += getWooProductPriceToDisplay( $posts[ $i ]->ID );

        }

        $params = array(
            'event_category' => 'ecommerce',
            'event_label'    => $list_name,
            'value'          => $total_value,
            'items'          => $items,
            'currency'        => get_woocommerce_currency(),
        );

        return array(
            'name'  => 'view_item_list',
            'ids' => Helpers\getConversionIDs( 'woo_view_category', $event->getPayloadValue('conversion_ids') ),
            'data'  => $params,
        );

    }

    /**
     * @param SingleEvent $event
     * @return bool
     */
    function prepare_wcf_remove_from_cart(&$event) {
        if( ! $this->getOption( 'woo_remove_from_cart_enabled' )
            || empty($event->args['products'])
        ) {
            return false; // return if args is empty
        }
        $product_data = $event->args['products'][0];
        $product_id = $product_data['id'];
        $content_id = Helpers\getWooFullItemId( $product_id );
        $value = getWooProductPriceToDisplay( $product_id, $product_data['quantity'],$product_data['price'] );

        $event->addParams(array(
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'value'           => $value,
            'items'           => array(
                array(
                    'id'       => $content_id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
                )
        );

        $event->addPayload([
            'name'=>"remove_from_cart",
        ]);
        return true;
    }

    /**
     * @param SingleEvent $event
     * @return bool
     */
    private function prepare_wcf_add_to_cart(&$event) {
        if(  !$this->getOption( 'woo_add_to_cart_enabled' )
            || empty($event->args['products']) ) {
            return false; // return if args is empty
        }

        if(is_home() || is_front_page()) {
            $ecomm_pagetype = "home";
        }elseif(is_shop()) {
            $ecomm_pagetype = "shop";
        }elseif(is_cart()) {
            $ecomm_pagetype = "cart";
        }elseif(is_single()) {
            $ecomm_pagetype = "product";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }
        $value          = 0;
        $content_ids    = array();
        $content_names  = array();
        $items = array();

        foreach ($event->args['products'] as $product_data) {
            $content_id = Helpers\getWooFullItemId( $product_data['id'] );
            $content_ids[] = $content_id;
            $content_names[] = $product_data['name'];
            $value += getWooProductPriceToDisplay( $product_data['id'], $product_data['quantity'] ,$product_data['price']);
            $items[] = array(
                'id'       => $content_id,
                'google_business_vertical' => $this->googleBusinessVertical,
            );

        }

        $params = array(
            'ecomm_prodid' => $content_ids,
            'ecomm_pagetype'=> $ecomm_pagetype,
            'event_category'  => 'ecommerce',
            'value' => $value,
            'items' => $items
        );

        $event->addParams($params);
        $event->addPayload(array(
            'name'=>"add_to_cart",
            'ids' => Helpers\getConversionIDs( 'woo_add_to_cart', $event->getPayloadValue('conversion_ids') ),
        ));
        return true;
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

        $product_data = $event->args['products'][0];

        $product_id = $product_data['id'];
        $id = Helpers\getWooFullItemId( $product_id );
        $params = array(
            'ecomm_prodid'=> $id,
            'ecomm_pagetype'=> 'product',
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'items'           => array(
                array(
                    'id'       => $id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );

        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_id,
                'qty' => $product_data['quantity']
            ];

            $params[ 'value' ] = getWooProductValue($valueArgs);
        }

        $event->addParams($params);
        $event->addPayload([
            'name'  => 'view_item',
            'ids'   => Helpers\getConversionIDs( 'woo_view_content', $event->getPayloadValue('conversion_ids') ),
            'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
        ]);
        return true;
    }

    private function getWooViewContentEventParams($eventArgs = null) {


        if ( ! $this->getOption( 'woo_view_content_enabled' ) ) {
            return false;
        }

        $quantity = 1;
        $variable_id = null;
        if($eventArgs && isset($eventArgs['id'])) {
            $productId = $eventArgs['id'];
            $product = wc_get_product($eventArgs['id']);
            $quantity = $eventArgs['quantity'];
        } else {
            global $post;
            $productId = $post->ID ;
            $product = wc_get_product($post->ID);
        }
        if ($product && GATags()->getOption('woo_variable_data_select_product') && !GATags()->getOption('woo_variable_as_simple')) {
            $variable_id = getVariableIdByAttributes($product);
        }
        $id = Helpers\getWooFullItemId( $variable_id ?? $productId );


        $params = array(
            'ecomm_prodid'=> $id,
            'ecomm_pagetype'=> 'product',
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'items'           => array(
                array(
                    'id'       => $id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );

        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $variable_id ?? $productId,
                'qty' => $quantity
            ];

            $params[ 'value' ] = getWooProductValue($valueArgs);
        }


        return array(
            'name'  => 'view_item',
            'data'  => $params,
            'ids'   => Helpers\getConversionIDs( 'woo_view_content' ),
            'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
        );

    }

    private function getWooAddToCartOnButtonClickEventParams( $args ) {
        $product_id = $args['productId'];
        $quantity = $args['quantity'];

        $product = wc_get_product( $product_id );
        if(!$product) return false;

        $customProductPrice = getWfcProductSalePrice($product,$args);

        $price = getWooProductPriceToDisplay( $product_id, $quantity ,$customProductPrice);
        $contentId = Helpers\getWooFullItemId( $product_id );


        if(is_home() || is_front_page()) {
            $ecomm_pagetype = "home";
        }elseif(is_shop()) {
            $ecomm_pagetype = "shop";
        }elseif(is_cart()) {
            $ecomm_pagetype = "cart";
        }elseif(is_single()) {
            $ecomm_pagetype = "product";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }

        $params = array(
            'ecomm_prodid' => $contentId,
            'ecomm_pagetype'=> $ecomm_pagetype,
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
        );

        $value_enabled_option = 'woo_add_to_cart_value_enabled';

        // currency, value
        if ( PYS()->getOption( $value_enabled_option ) ) {

            $value_option_option  = 'woo_add_to_cart_value_option';
            $value_global_option  = 'woo_add_to_cart_value_global';
            $value_percent_option = 'woo_add_to_cart_value_percent';
            $value_option   = PYS()->getOption( $value_option_option );
            $global_value   = PYS()->getOption( $value_global_option, 0 );
            $percents_value = PYS()->getOption( $value_percent_option, 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_id,
                'qty' => $quantity
            ];

            $params['value']    = getWooProductValue($valueArgs);

        }

        $product_ids = array();
        $items = array();

        $isGrouped = $product->get_type() == "grouped";
        if($isGrouped) {
            $product_ids = $product->get_children();
        } else {
            $product_ids[] = $product_id;
        }

        foreach ($product_ids as $child_id) {
            $childProduct = wc_get_product($child_id);
            if(!$childProduct || ($childProduct->get_type() == "variable" && $isGrouped)) {
                continue;
            }
            $childContentId = Helpers\getWooFullItemId( $child_id );
            $items[] = array(
                'id'       => $childContentId,
                'google_business_vertical' => $this->googleBusinessVertical,
            );
        }
        $params['items'] = $items;

        $data = array(
            'ids' => Helpers\getConversionIDs( 'woo_add_to_cart' ),
            'params'  => $params,
        );


        if($product->get_type() == 'grouped') {
            $grouped = array();
            foreach ($product->get_children() as $childId) {
                $grouped[$childId] = array(
                    'content_id' => Helpers\getWooFullItemId( $childId ),
                    'price' => getWooProductPriceToDisplay( $childId )
                );
            }
            $data['grouped'] = $grouped;
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
        $data = [
            'name' => 'add_to_cart',
        ];
        $params = $this->getWooEventCartParams($event);

        if(is_home() || is_front_page()) {
            $ecomm_pagetype = "home";
        }elseif(is_shop()) {
            $ecomm_pagetype = "shop";
        }elseif(is_cart()) {
            $ecomm_pagetype = "cart";
        }elseif(is_single()) {
            $ecomm_pagetype = "product";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }

        $params['ecomm_prodid'] = array_column($params['items'],'id');
        $params['ecomm_pagetype'] = $ecomm_pagetype;
        $params['event_category']  = 'ecommerce';
	    $params['currency'] = get_woocommerce_currency();

        $data['ids'] = Helpers\getConversionIDs( 'woo_add_to_cart' );
        $event->addPayload($data);
        $event->addParams($params);


        return  true;
    }



    private function getWooAffiliateEventParams( $product_id,$quantity ) {

        if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
            return false;
        }

        $product = get_post( $product_id );

        $params = array(
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'items'           => array(
                array(
                    'id'       => Helpers\getWooFullItemId( $product_id ),
                    'name'     => $product->post_title,
                    'category' => implode( '/', getObjectTerms( 'product_cat', $product_id ) ),
                    'quantity' => $quantity,
                    'price'    => getWooProductPriceToDisplay( $product_id, $quantity ),
                ),
            ),
        );

        return array(
            'params'  => $params,
        );

    }

	/**
	 * @param SingleEvent $event
	 * @return boolean
	 */
	private function setWooInitiateCheckoutEventParams(&$event) {

		if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
			return false;
		}
		$params = $this->getWooEventCartParams( $event );
		$event->addParams($params);
		$event->addPayload([
			'name' => 'begin_checkout',
			'ids' => Helpers\getConversionIDs( 'woo_initiate_checkout', $event->getPayloadValue('conversion_ids') ),
		]);
		return true;

	}

    /**
     * @param SingleEvent $event
     * @return array|false
     */
    private function getWooPurchaseEventParams(&$event)
    {

        if (!$this->getOption('woo_purchase_enabled') || empty($event->args['order_id'])) {
            return false;
        }

        if(isset($event->args['order_id'])){
            $order = wc_get_order($event->args['order_id']);

        } else { return false; }

        $params = $this->getWooPurchaseParams($event, $order);

        if($params === false) {
            return false;
        }

        if($this->getOption('woo_purchase_new_customer') && ($order->get_customer_id() || PYS()->getOption('woo_purchase_new_customer_guest') === 'yes')){
            $params['new_customer'] = $event->args['new_customer'] ?? 0;
        }

        $event->addParams($params);
        $event->addPayload([
            'name' => 'purchase',
            'ids' => Helpers\getConversionIDs( 'woo_purchase', $event->getPayloadValue('conversion_ids') ),
        ]);

        return true;
    }

    private function getWooPurchaseParams($event, $order, &$product_ids = [])
    {
        $tax = 0;
        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $withTax = 'incl' === get_option('woocommerce_tax_display_cart');

        // Handle AWCDP (Deposits & Partial Payments) plugin integration
        $awcdp_order = null;
        if (isset($event->args['awcdp_order']) && $event->args['awcdp_order'] instanceof \AWCDP_Order) {
            $awcdp_order = $event->args['awcdp_order'];
        } elseif ($order instanceof \AWCDP_Order) {
            $awcdp_order = $order;
        }

        // If we have an AWCDP order, get parent order for products
        if ($awcdp_order) {
            $parent_order_id = $awcdp_order->get_parent_id();
            if ($parent_order_id) {
                $parent_order = wc_get_order($parent_order_id);
                if ($parent_order) {
                    $order = $parent_order;
                }
            }
        }

        $order_Items = $order->get_items();

        foreach ( $order_Items as $order_Item ) {

            $product = $order_Item->get_product();

            if ($product && GATags()->getOption('woo_variable_as_simple') && $product->is_type('variation')) {
                $product = wc_get_product($product->get_parent_id());
            }

            if(!$product) continue;

            $event_has_product = true;
            if (isset($event->args['products']) && is_array($event->args['products'])) {
                $event_has_product = false;
                // Defining the filtering condition
                $filtered_products = array_filter($event->args['products'], function ($filter_product) use ($product) {
                    return $filter_product['product_id'] == $product->get_id() || (isset($filter_product['parent_id']) && $filter_product['parent_id'] == $product->get_id());
                });
                $event_has_product = !empty($filtered_products);
            }
            if (!$event_has_product) {
                continue;
            }

            $product_data = $product->get_data();
            $product_array = (array) $product_data;
            $product_array['type'] = $product->get_type();

            $product_id = Helpers\getWooProductDataId($product_array);
            $content_id = Helpers\getWooFullItemId($product_id);
            $price = getWooProductPrice($product_id);

            $quantity = $order_Item->get_quantity();
            $tax += $order_Item->get_total_tax();
            $item = array(
                'id' => $content_id,
                'quantity' => $quantity,
                'price'    => $price,
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
        }

        if (empty($items)) return false; // order is empty

        $total = getWooEventOrderTotal($event);
        $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$event->args);
        $tax += (float)$event->args['shipping_tax'];
        $shipping_cost = $event->args['shipping_cost'];
        if($withTax) {
            $shipping_cost += $event->args['shipping_tax'];
        }
        if(PYS()->getOption('enable_CwCD')) {
            if(!empty(PYS()->getOption('aw_merchant_id'))){
                $event->addParams(array('aw_merchant_id'=> PYS()->getOption('aw_merchant_id') ));
            }
            if(!empty(PYS()->getOption('aw_feed_country'))){
                $event->addParams(array('aw_feed_country'=> PYS()->getOption('aw_feed_country') ));
            }
            if(!empty(PYS()->getOption('aw_feed_language'))){
                $event->addParams(array('aw_feed_language'=> PYS()->getOption('aw_feed_language') ));
            }
        }

        $params = array(
            'ecomm_prodid' => array_column($items, 'id'),
            'ecomm_pagetype' => "purchase confirmation",
            'ecomm_totalvalue' => $total,
            'event_category' => 'ecommerce',
            'transaction_id' => wooMapOrderId($event->args['order_id']),
            'value' => $value,
            'currency' => $event->args['currency'],
            'items' => $items,
            'tax' => pys_round($tax),
            'shipping' => pys_round( $shipping_cost, 2 ),
            'payment_method' => $event->args['payment_method'],
            'coupon' => $event->args['coupon_name'],
        );
        if(isset($event->args['fees'])){
            $params['fees'] = (float) $event->args['fees'];
        }

        return $params;
    }

    /**
     * @param SingleEvent $event
     * @return array
     */
    private function getWooEventCartParams( $event ) {
        $items = [];


        foreach ($event->args['products'] as $product) {
            $product_id = Helpers\getWooEventCartItemId( $product );

            if(!$product_id) continue;

            $content_id = Helpers\getWooFullItemId( $product_id );
            $item = array(
                'id'       => $content_id,
                'google_business_vertical' => $this->googleBusinessVertical,
            );
            $items[] = $item;

        }

        $total_value =  getWooEventCartTotal($event);

        $params = array(
            'event_category' => 'ecommerce',
            'items' => $items,
            'coupon' => $event->args['coupon'],
            'currency'        => get_woocommerce_currency(),
        );

        if($event->getId() == 'woo_add_to_cart_on_cart_page'
            || $event->getId() == 'woo_add_to_cart_on_checkout_page'
            || $event->getId() == 'woo_initiate_checkout'
        ) {
            if ($event->getId() == 'woo_initiate_checkout') {
                $context = 'woo_initiate_checkout';
            } else {
                $context = 'woo_add_to_cart';
            }
            $value_enabled_option = $context.'_value_enabled';
            // currency, value
            if ( PYS()->getOption( $value_enabled_option ) ) {

                $value_option_option  = $context.'_value_option';
                $value_global_option  = $context.'_value_global';
                $value_percent_option = $context.'_value_percent';

                $value_option   = PYS()->getOption( $value_option_option );
                $global_value   = PYS()->getOption( $value_global_option, 0 );
                $percents_value = PYS()->getOption( $value_percent_option, 100 );

                $params['value']    = getWooEventValueProducts($value_option,$global_value,$percents_value,$total_value,$event->args);

            }
        }
        else{
            $params['value'] = $total_value;
        }

        return $params;
    }
    /**
     * @param string $context
     * @return array
     *@deprecated
     */
    private function getWooCartParams( $context = 'cart', $event = null ) {

        $items = array();
        $total_value = 0;

        foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {

            $product_id = Helpers\getWooCartItemId( $cart_item );
            if(!$product_id) continue;
            $content_id = Helpers\getWooFullItemId( $product_id );
            $price = $cart_item['line_subtotal'];
            $withTax = 'incl' === get_option('woocommerce_tax_display_cart');

            if ($withTax) {
                $price += $cart_item['line_subtotal_tax'];
            }
            $item = array(
                'id'       => $content_id,
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;

        }
        $coupons =  WC()->cart->get_applied_coupons();
        if ( !empty($coupons) ) {
            $coupon = $coupons[0];
        } else {
            $coupon = null;
        }

        if(empty($items)) {
            return []; // cart is empty
        }

        $total_value = getWooEventCartTotal($event);

        $params = array(
            'event_category' => 'ecommerce',
            'value' => $total_value,
            'items' => $items,
            'currency' => get_woocommerce_currency(),
            'coupon' => $coupon
        );

        return $params;

    }


    private function getEddViewContentEventParams() {
        global $post;
        $download_id = $post->ID;
        if ( ! $this->getOption( 'edd_view_content_enabled' ) || empty($download_id)) {
            return false;
        }
        if ( strpos( $download_id, '_') !== false ) {
            list( $download_id, $price_index ) = explode( '_', $download_id );
        } else {
            $price_index = null;
        }
        $total_value = getEddDownloadPriceToDisplay( $download_id, $price_index );
        $id = Helpers\getEddDownloadContentId($download_id);
        $params = array(
            'ecomm_prodid'=> $id,
            'ecomm_pagetype'=> 'product',
            'ecomm_totalvalue' => $total_value,
            'event_category'  => 'ecommerce',
            'currency' => edd_get_currency(),
            'items'           => array(
                array(
                    'id'       => $id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );
        if(PYS()->getOption( 'edd_view_content_value_enabled' )){
            $value_option = PYS()->getOption('edd_view_content_value_option');
            $percents_value = PYS()->getOption('edd_view_content_value_percent', 100);
            $global_value = PYS()->getOption('edd_view_content_value_global', 0);

            $params['value'] = getEddEventValue( $value_option, $total_value, $global_value, $percents_value );
        }
        return array(
            'name'  => 'view_item',
            'ids' => Helpers\getConversionIDs( 'edd_view_content' ),
            'data'  => $params,
        );

    }

    private function getEddAddToCartOnButtonClickEventParams( $download_id ) {


        // maybe extract download price id
        if ( strpos( $download_id, '_') !== false ) {
            list( $download_id, $price_index ) = explode( '_', $download_id );
        } else {
            $price_index = null;
        }

        $total = getEddDownloadPriceToDisplay( $download_id, $price_index );

        if(is_home()) {
            $ecomm_pagetype = "home";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }
        $contentId = Helpers\getEddDownloadContentId($download_id);
        $params = array(
            'ecomm_prodid' => $contentId,
            'ecomm_pagetype'=> $ecomm_pagetype,
            'event_category'  => 'ecommerce',
	        'currency' => edd_get_currency(),
            'items'           => array(
                array(
                    'id'       => $contentId,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );

        if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

            $value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
            $percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
            $global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );

            $params['value'] = getEddEventValue( $value_option, $total, $global_value, $percents_value );
        }

        return array(
            'ids' => Helpers\getConversionIDs( 'edd_add_to_cart' ),
            'params' => $params,
        );

    }

    /**
     * @param SingleEvent $event
     * @return bool
     */
    private function setEddCartEventParams(&$event) {
        $params = [
            'ecomm_pagetype'=> "purchase confirmation",
            'event_category' => 'ecommerce',
        ];
        $data = [];
        $value_enabled = false;
        switch ($event->getId()) {
            case 'edd_add_to_cart_on_checkout_page' : {
                if(! $this->getOption( 'edd_add_to_cart_enabled' )) return false;
                $data['name'] = 'add_to_cart';
                $data['ids'] = Helpers\getConversionIDs( 'edd_add_to_cart' );

                $value_enabled  = PYS()->getOption( 'edd_add_to_cart_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
                $percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
            }break;
            case 'edd_purchase' : {
                if(! $this->getOption( 'edd_purchase_enabled' )) return false;
                $data['name'] = 'purchase';
                $params['coupon'] = $event->args['coupon'];
                $params['transaction_id'] = eddMapOrderId($event->args['order_id']);

                if($this->getOption('edd_purchase_new_customer') && (is_user_logged_in() || PYS()->getOption('edd_purchase_new_customer_guest') === 'yes')){
                    $params['new_customer'] = $event->args['new_customer'];
                }

                $data['ids'] = Helpers\getConversionIDs( 'edd_purchase', $event->getPayloadValue('conversion_ids') );

                $value_enabled  = PYS()->getOption( 'edd_purchase_value_enabled', true );
                $value_option   = PYS()->getOption( 'edd_purchase_value_option' );
                $percents_value = PYS()->getOption( 'edd_purchase_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_purchase_value_global', 0 );
            }break;
	        case 'edd_initiate_checkout': {
		        if( !$this->getOption( 'edd_initiate_checkout_enabled' ) ) return false;
		        $data['name'] = 'begin_checkout';
		        $data['ids'] = Helpers\getConversionIDs( 'edd_initiate_checkout', $event->getPayloadValue('conversion_ids') );

                $value_enabled  = PYS()->getOption( 'edd_initiate_checkout_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_initiate_checkout_value_option' );
                $percents_value = PYS()->getOption( 'edd_initiate_checkout_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_initiate_checkout_value_global', 0 );
	        }break;
        }

        $items = array();
        $total = 0;
        $total_as_is = 0;
        $tax = 0;
        $include_tax = PYS()->getOption( 'edd_tax_option' ) == 'included';
	    $params['currency'] = edd_get_currency();
        foreach ( $event->args['products'] as  $product ) {
            $download_id   = (int) $product['product_id'];

            if ( $event->getId() == 'edd_purchase' ) {

                if ( $include_tax ) {
                    $total += $product['subtotal'] + $product['tax'] - $product['discount'];
                } else {
                    $total += $product['subtotal'] - $product['discount'];
                }
                $tax += $product['tax'];
                $total_as_is += $product['price'];
            } else {
                $total += getEddDownloadPriceToDisplay( $download_id,$product['price_index'] );
                $total_as_is += edd_get_cart_item_final_price( $product['cart_item_key']  );
            }

            $items[] = [
                    'id'       => Helpers\getEddDownloadContentId($download_id),
                    'google_business_vertical' => $this->googleBusinessVertical,
//				'variant'  => $variation_name,
            ];
        }
        $params['items'] =  $items;

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

        if ( $event->getId() == 'edd_purchase' ) {

            if ( $value_enabled ) {

                if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
                    $amount = $total;
                } else {
                    $amount = $total_as_is;
                }
                $params['value']    = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
            }

            $params['tax'] = $tax;
            $params['ecomm_prodid'] = array_column($items,'id');
            $params['ecomm_totalvalue'] = $total;
        }
        else{
            if ( $value_enabled ) {
                $params['value']    = getEddEventValue( $value_option, $total, $global_value, $percents_value );
            }
        }

        $event->addParams($params);
        $event->addPayload($data);

        return true;
    }


    private function getEddViewCategoryEventParams($event) {
        global $posts;

        if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
            return false;
        }

        $term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
        if(!$term) return false;
        $parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );

        $download_categories = array();
        $download_categories[] = $term->name;

        foreach ( $parent_ids as $term_id ) {
            $parent_term = get_term_by( 'id', $term_id, 'download_category' );
            $download_categories[] = $parent_term->name;
        }

        $list_name = implode( '/', array_reverse( $download_categories ) );

        $items = array();
        $total_value = 0;

        for ( $i = 0; $i < count( $posts ); $i ++ ) {

            $item = array(
                'id'            => Helpers\getEddDownloadContentId($posts[ $i ]->ID),
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value += getEddDownloadPriceToDisplay( $posts[ $i ]->ID );

        }

        $params = array(
            'event_category' => 'ecommerce',
            'event_label'    => $list_name,
            'value'          => $total_value,
            'currency' => edd_get_currency(),
            'items'          => $items,
        );

        return array(
            'name'  => 'view_item_list',
            'ids' => Helpers\getConversionIDs( 'edd_view_category', $event->getPayloadValue('conversion_ids') ),
            'data'  => $params,
        );

    }

    function registerProductMetaBox () {

        if ( current_user_can( 'manage_pys' ) ) {
            add_meta_box( 'pys-gads-box', 'PYS Google Ads',
                array( $this, 'render_meta_box' ),
                "product","side" );

        }
    }

    function render_meta_box () {
        wp_nonce_field( 'pys_save_meta_box', '_pys_nonce' );
        include 'views/html-meta-box.php';
    }


    /**
     * @param $post_id
     * @param \WP_Post $post
     * @param $update
     */
    function saveProductMetaBox($post_id, $post, $update) {
        if ( ! isset( $_POST['_pys_nonce'] ) || ! wp_verify_nonce( $_POST['_pys_nonce'], 'pys_save_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $data = $_POST['pys_ads_conversion_label'];

        $meta = array(
            'enable' => isset( $data['enable'] ),
            'label'  => isset( $data['label'] ) ? trim( sanitize_text_field($data['label']) ) : '',
            'id'     => isset( $data['id'] ) ? trim( sanitize_text_field($data['id']) ) : '',
        );

        update_post_meta($post_id,"_pys_conversion_label_settings",$meta);
    }
    function output_meta_tag() {
        if(EventsWcf()->isEnabled() && isWcfStep()) {
            $tag = $this->getOption( 'wcf_verify_meta_tag' );
            if(!empty($tag)) {
                echo $tag;
                return;
            }
        }
        $metaTags = (array) $this->getOption( 'verify_meta_tag' );
        foreach ($metaTags as $tag) {
            echo $tag;
        }
    }


    /*function has_bought( $value = 0 ) {
        if ( ! is_user_logged_in() && $value === 0 ) {
            return false;
        }

        $start_date = strtotime('540 days ago');
        $end_date = strtotime('today');

        // Based on user ID (registered users)
        if ( is_numeric( $value) ) {
            $meta_key   = '_customer_user';
            $meta_value = $value == 0 ? (int) get_current_user_id() : (int) $value;
        }
        // Based on billing email (Guest users)
        else {
            $meta_key   = '_billing_email';
            $meta_value = sanitize_email( $value );
        }


        $args = array(
            'post_type'      => 'shop_order',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'   => $meta_key,
                    'value' => $meta_value,
                    'compare' => '=',
                    'type' => 'numeric',
                ),
                array(
                    'key' => '_completed_date',
                    'value' => array(date('Y-m-d H:i:s', $start_date), date('Y-m-d H:i:s', $end_date)),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ),
            ),
            'fields' => 'ids',
        );

        $orders = get_posts($args);
        var_dump($orders);
        $count = count($orders);

        // Return a boolean value based on orders count
        return $count > 0;
    }*/

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

	/**
	 * Add conversion labels to events
	 * @param $event
	 * @return bool
	 */
	private function addEventLabels( &$event ) {
		$event_id = $event->getId();
		if ( $this->getOption( $event_id . '_enabled' ) ) {
			if ( $this->issetOption( $event_id ) . '_conversion_labels' ) {
				$data = array();
				$data[ 'ids' ] = Helpers\getConversionIDs( $event_id, $event->getPayloadValue('conversion_ids') );
				$event->addPayload( $data );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get advanced marketing event params
	 * @param $eventType
	 * @return array|false
	 */
	private function getWooAdvancedMarketingEventParams( $event ) {

		if ( !$this->getOption( $event->getId() . '_enabled' ) ) {
			return false;
		}

		$params = array();

		switch ( $event->getId() ) {
			case 'woo_frequent_shopper':
			case 'edd_frequent_shopper':
				$eventName = 'FrequentShopper';
				break;

			case 'woo_vip_client':
			case 'edd_vip_client':
				$eventName = 'VipClient';
				break;
			case 'woo_FirstTimeBuyer':
				$eventName = 'FirstTimeBuyer';
				break;
			case 'woo_ReturningCustomer':
				$eventName = 'ReturningCustomer';
				break;
			default:
				$eventName = 'BigWhale';
		}
		return array(
			'name' => $eventName,
			'data' => $params,
			'ids'  => Helpers\getConversionIDs( $event->getId(), $event->getPayloadValue('conversion_ids') ),
		);
	}

}

/**
 * @return GoogleAds
 */
function Ads() {
	return GoogleAds::instance();
}

Ads();
