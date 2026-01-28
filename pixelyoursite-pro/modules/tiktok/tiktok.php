<?php

namespace PixelYourSite;

use PixelYourSite;
use PixelYourSite\TikTok\Helpers;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
require_once PYS_PATH . '/modules/tiktok/function_helpers.php';
require_once PYS_PATH . '/modules/tiktok/tiktok-logger.php';

class Tiktok extends Settings implements Pixel {
	private static $_instance;
	private        $configured;
	private        $logger;


	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}

	public function __construct() {
		parent::__construct( 'tiktok' );
		$this->locateOptions( PYS_PATH . '/modules/tiktok/options_fields.json', PYS_PATH . '/modules/tiktok/options_defaults.json' );
		add_action( 'pys_register_pixels', function ( $core ) {
			/** @var PYS $core */
			$core->registerPixel( $this );
		} );

		$this->logger = new TikTok_logger();
		add_action( 'init', array(
			$this,
			'init'
		), 9 );
	}

	public function enabled() {
		if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) ) {
			return true;
		} else {
			return $this->getOption( 'main_pixel_enabled' );
		}
	}

	public function init() {
		$this->logger->init();
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

		if( isSuperPackActive()
			&& SuperPack()->getOption( 'enabled' )
			&& SuperPack()->getOption( 'additional_ids_enabled' ) )
		{
			if ( !$this->getOption( 'main_pixel_enabled' ) ) {
				return apply_filters( "pys_tiktok_ids", [] );
			}
		}
		$pixels = (array) $this->getOption( 'pixel_id' );

		if ( count( $pixels ) == 0 || empty( $pixels[ 0 ] ) ) {
			return apply_filters( "pys_tiktok_ids", [] );
		} else {
			$id = array_shift( $pixels );
			return apply_filters( "pys_tiktok_ids", array( $id ) ); // return first id only
		}
	}

	public function getAllPixels( $checkLang = true ) {
		return $this->getPixelIDs();
	}

	/**
	 * @param SingleEvent $event
	 * @return array
	 */
	public function getAllPixelsForEvent( $event ) {

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
		} elseif ( $this->getOption( 'main_pixel_enabled' ) ) {
			$pixels = array_merge( $pixels, $main_pixel );
		}

		return $pixels;
	}

	/**
	 * @return array
	 */
	public function getPixelOptions() {
		$options = [
			'pixelIds'         => $this->getAllPixels(),
			'serverApiEnabled' => $this->isServerApiEnabled(),
		];

		if ( $this->getOption( 'advanced_matching_enabled' ) ) {
			$options[ 'advanced_matching' ] = $this->getAdvancedMatchingParams();
		}
		if ( isSuperPackActive( '3.3.1' ) && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'enable_hide_this_tag_by_tags' ) ) {
			$options[ 'hide_pixels' ] = $this->getHideInfoPixels();
		}
		return $options;
	}

	public function updateOptions( $values = null ) {

		if ( isset( $_POST[ 'pys' ][ $this->getSlug() ][ 'test_api_event_code' ] ) ) {
			$api_event_code_expiration_at = array();
			foreach ( $_POST[ 'pys' ][ $this->getSlug() ][ 'test_api_event_code' ] as $key => $test_api ) {
				if ( !empty( $test_api ) && empty( $this->getOption( 'test_api_event_code_expiration_at' )[ $key ] ) ) {
					$api_event_code_expiration_at[] = time() + $this->convertTimeToSeconds();
				} elseif ( !empty( $this->getOption( 'test_api_event_code_expiration_at' )[ $key ] ) ) {
					$api_event_code_expiration_at[] = $this->getOption( 'test_api_event_code_expiration_at' )[ $key ];
				}

			}
			$_POST[ 'pys' ][ $this->getSlug() ][ 'test_api_event_code_expiration_at' ] = $api_event_code_expiration_at;
		}

		parent::updateOptions( $values );
	}

	/**
	 * Create pixel event and fill it
	 * @param SingleEvent $event
	 * @return array
	 */
	public function generateEvents( $event ) {
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


        $pixelIds = $this->getAllPixelsForEvent( $event );

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
        $listOfWooEventWithProducts = ['woo_purchase','woo_initiate_checkout','woo_paypal','woo_add_to_cart_on_checkout_page','woo_add_to_cart_on_cart_page'];
        $isWooEventWithProducts = in_array($event->getId(),$listOfWooEventWithProducts);
        $isEddEventWithProducts = in_array($event->getId(),$listOfEddEventWithProducts);

        if(($isWooEventWithProducts || $isEddEventWithProducts) && isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' ) )
        {
            $pixelEvent = clone $event;

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
                        $products = $event->args['products'];
                    } else {
                        if($isWooEventWithProducts) {
                            $products = EventsWoo()->filterEventProductsBy($event,$filter,$_pixel);
                            $pixelEvent->args['products'] = $products;
                        }
                        if($isEddEventWithProducts) {
                            $products = EventsEdd()->filterEventProductsBy($event,$filter,$_pixel);
                            $pixelEvent->args['products'] = $products;
                        }
                    }

                    if ( !empty($products) && $this->addParamsToEvent( $pixelEvent ) ) {
                        $pixelEvent->addPayload( [ 'pixelIds' => [$_pixel->pixel] ] );
                        $pixelEvents[] = $pixelEvent;

                        if ($event->getId() == "woo_purchase") { // dublicate event
                            $pixelEvent = clone $event;
                            $pixelEvent->setId("woo_complete_payment");
                            if ($this->addParamsToEvent($pixelEvent)) {
                                $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                                $pixelEvents[] = $pixelEvent;
                            }
                        }
                        if ($event->getId() == "edd_purchase") { // dublicate event
                            $pixelEvent = clone $event;
                            $pixelEvent->setId("edd_complete_payment");
                            if ($this->addParamsToEvent($pixelEvent)) {
                                $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                                $pixelEvents[] = $pixelEvent;
                            }
                        }
                    }
                } else {
                    if ( $this->addParamsToEvent( $pixelEvent ) ) {
                        $pixelEvent->addPayload( [ 'pixelIds' => [$_pixel->pixel] ] );
                        $pixelEvents[] = $pixelEvent;

                        if ($event->getId() == "woo_purchase") { // dublicate event
                            $pixelEvent = clone $event;
                            $pixelEvent->setId("woo_complete_payment");
                            if ($this->addParamsToEvent($pixelEvent)) {
                                $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                                $pixelEvents[] = $pixelEvent;
                            }
                        }
                        if ($event->getId() == "edd_purchase") { // dublicate event
                            $pixelEvent = clone $event;
                            $pixelEvent->setId("edd_complete_payment");
                            if ($this->addParamsToEvent($pixelEvent)) {
                                $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                                $pixelEvents[] = $pixelEvent;
                            }
                        }
                    }
                }
            }
        }
        else
        {
            if(count($pixelIds) > 0) {
                $pixelEvent = clone $event;
                if ($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                    $pixelEvents[] = $pixelEvent;
                }
                if ($event->getId() == "woo_purchase") { // dublicate event
                    $pixelEvent = clone $event;
                    $pixelEvent->setId("woo_complete_payment");
                    if ($this->addParamsToEvent($pixelEvent)) {
                        $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                        $pixelEvents[] = $pixelEvent;
                    }
                }
                if ($event->getId() == "edd_purchase") { // dublicate event
                    $pixelEvent = clone $event;
                    $pixelEvent->setId("edd_complete_payment");
                    if ($this->addParamsToEvent($pixelEvent)) {
                        $pixelEvent->addPayload(['pixelIds' => $pixelIds]);
                        $pixelEvents[] = $pixelEvent;
                    }
                }
            }
        }
		return $pixelEvents;
	}

	public function outputNoScriptEvents() {

	}

	/**
	 * @param SingleEvent $event
	 * @return false
	 */
	private function addParamsToEvent( &$event ) {
		$isActive = false;

		switch ( $event->getId() ) {

			//Automatic events
			case 'automatic_event_form' :

				$event->addPayload( [ "name" => "SubmitForm" ] );
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case 'automatic_event_signup' :
				$event->addPayload( [ "name" => "SignUp" ] );
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case 'automatic_event_download' :
				$event->addPayload( [ "name" => "Download" ] );
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case 'automatic_event_search' :
				$event->addPayload( [ "name" => "Search" ] );
				if ( !empty( $_GET[ 's' ] ) ) {
					$event->addParams( [ "query" => $_GET[ 's' ] ] );
				}

				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case "automatic_event_outbound_link":
			case "automatic_event_internal_link":
				$isActive = $this->add_click_button_params( $event );
				break;

			case 'automatic_event_login' :
				$event->addPayload( [ "name" => "Login" ] );
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case 'automatic_event_scroll' :
			case 'automatic_event_tel_link' :
			case 'automatic_event_email_link' :
			case 'automatic_event_comment' :
			case 'automatic_event_adsense' :
			case 'automatic_event_time_on_page' :
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				break;

			case 'automatic_event_video' :
				$isActive = $this->getOption( $event->getId() . '_enabled' );
				if ( $isActive ) {
					$event->addPayload( array( 'automatic_event_video_trigger' => $this->getOption( "automatic_event_video_trigger" ) ) );
				}
				break;

			//Woo
			case 'woo_add_to_cart_on_button_click':
				$isActive = $this->add_woo_add_to_cart_params( $event );
				break;

			case 'woo_view_content':
				$isActive = $this->add_woo_view_content_params( $event );
				break;

			case 'woo_initiate_checkout':
				$isActive = $this->add_woo_initiate_checkout_params( $event );
				break;

			case 'woo_purchase':
				$isActive = $this->add_woo_purchase_params( $event );
				break;

			case 'woo_complete_payment':
				$isActive = $this->add_woo_compete_payment_params( $event );
				break;

			case 'woo_frequent_shopper':
			case 'woo_vip_client':
			case 'woo_big_whale':
			case 'woo_FirstTimeBuyer':
			case 'woo_ReturningCustomer':
				$isActive = $this->getWooAdvancedMarketingEventParams( $event );
				break;

			case 'edd_view_content':
				$isActive = $this->add_edd_view_content_params( $event );
				break;

			case 'edd_add_to_cart_on_checkout_page':
				$isActive = $this->add_edd_add_to_cart_on_check_params( $event );
				break;

			case 'edd_initiate_checkout':
				$isActive = $this->add_edd_init_checkout_params( $event );
				break;

			case 'edd_purchase':
				$isActive = $this->add_edd_purchase_params( $event );
				break;

			case 'edd_complete_payment':
				$isActive = $this->add_edd_complete_payment_params( $event );
				break;

			case 'edd_add_to_cart_on_button_click':
				$isActive = $this->add_edd_add_to_cart_params( $event );
				break;

			case 'edd_frequent_shopper':
			case 'edd_vip_client':
			case 'edd_big_whale':
				$isActive = $this->setEddCartEventParams( $event );
				break;

			case 'custom_event':
				$isActive = $this->add_custom_event_params( $event );
				break;

			case 'wcf_add_to_cart_on_bump_click':
			case 'wcf_add_to_cart_on_next_step_click':
				$isActive = $this->add_wcf_add_to_cart_params( $event );
				break;

			case 'wcf_view_content':
				$isActive = $this->addwcf_view_content_params( $event );
				break;
		}

		if ( $isActive ) {
			if ( $this->isServerApiEnabled() ) {
				$event->payload[ 'event_id' ] = PixelYourSite\pys_generate_token();
			}
		}

		return $isActive;
	}

	public function getEventData( $eventType, $args = null ) {

		return false;
	}

	private function get_edd_add_to_cart_on_button_click_params( $download_id ) {
		global $post;

		// maybe extract download price id
		if ( strpos( $download_id, '_' ) !== false ) {
			list( $download_id, $price_index ) = explode( '_', $download_id );
		} else {
			$price_index = null;
		}

		$params = array(
			'content_type' => 'product',
            'content_name' => get_the_title( $download_id ),
            'currency'   => edd_get_currency(),
		);

		// content_name, category_name
		$content_category = implode( ', ', getObjectTerms( 'download_category', $download_id ) );
        $total = getEddDownloadPriceToDisplay( $download_id, $price_index );
		$contents = array(
			'content_name'     => get_the_title( $download_id ),
			'content_category' => $content_category,
			'content_id'       => (string) $download_id,
			'quantity'         => 1,
			'content_type'     => 'product',
            'price'            => $total,
		);

		// currency, value
		if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

            $value_option = PYS()->getOption('edd_add_to_cart_value_option');
            $percents_value = PYS()->getOption('edd_add_to_cart_value_percent', 100);
            $global_value = PYS()->getOption('edd_add_to_cart_value_global', 0);
            $params['value'] = getEddEventValue( $value_option, $total, $global_value, $percents_value );
		}

		// contents
		$params[ 'contents' ][] = $contents;

		return $params;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	function add_page_view_params( &$event ) {
		global $post;

		$cpt = get_post_type();
		$params = array(
			'content_name' => $post->post_title,
			'content_id'   => $post->ID,
		);

		if ( isWooCommerceActive() && $cpt == 'product' ) {
			$params[ 'content_category' ] = implode( ', ', getObjectTerms( 'product_cat', $post->ID ) );
		} elseif ( isEddActive() && $cpt == 'download' ) {
			$params[ 'content_category' ] = implode( ', ', getObjectTerms( 'download_category', $post->ID ) );
		} elseif ( $post instanceof \WP_Post ) {
			$catIds = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
			$params[ 'content_category' ] = implode( ", ", $catIds );
		}

		$data = array(
			'name' => 'ViewContent',
		);
		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 * content_type, quantity, description, content_id, currency, value
	 */
	private function add_woo_add_to_cart_params( &$event ) {

		if ( !$this->getOption( 'woo_add_to_cart_enabled' ) ) {
			return false;
		}

		if ( isset( $event->args[ 'productId' ] ) ) {
			$quantity = $event->args[ 'quantity' ];
			$product = wc_get_product( $event->args[ 'productId' ] );

			if ( !$product ) {
				return false;
			}

			$product_id = Helpers\getTikTokWooVariableToSimpleProductId( $product );

			$params = PixelYourSite\TikTok\Helpers\getWooSingleAddToCartParams( $product_id, $quantity );
			$event->addParams( $params );
			$content_id = Helpers\getTikTokWooProductContentId( $product_id );
			$params = [
				'content_category' => implode( ', ', getObjectTerms( 'product_cat', $product_id ) ),
				'quantity'         => $quantity,
				'currency'         => get_woocommerce_currency(),
				'content_name'     => $product->get_name(),
				'content_id'       => $content_id,
				'content_type'     => 'product'
			];

			$customProductPrice = getWfcProductSalePrice( $product, $event->args );
			$isGrouped = $product->get_type() == "grouped";
			if ( $isGrouped ) {
				$product_ids = $product->get_children();
			} else {
				$product_ids[] = $product_id;
			}
			$price = 0;
			foreach ( $product_ids as $child_id ) {
				$childProduct = wc_get_product( $child_id );
				if ( !$childProduct || ($childProduct->get_type() == "variable" && $isGrouped) ) {
					continue;
				}
				$price += getWooProductPriceToDisplay( $child_id, $quantity, $customProductPrice );
			}

            $value_enabled_option = 'woo_add_to_cart_value_enabled';
            $value_option_option  = 'woo_add_to_cart_value_option';
            $value_global_option  = 'woo_add_to_cart_value_global';
            $value_percent_option = 'woo_add_to_cart_value_percent';

            // currency, value
            if ( PYS()->getOption( $value_enabled_option ) ) {

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

			$event->addParams( $params );
		}

		$data = [
			'name' => 'AddToCart'
		];

		$event->addPayload( $data );
		return true;
	}


	/**
	 * @param SingleEvent $event
	 * @return bool
	 * content_type, quantity, description, content_id, currency, value
	 */
	private function add_woo_view_content_params( &$event ) {
		if ( !$this->getOption( 'woo_view_content_enabled' ) ) {
			return false;
		}
		$product = wc_get_product( $event->args[ 'id' ] );
        if ( !$product ) return false;
		$quantity = $event->args[ 'quantity' ];
		$customProductPrice = getWfcProductSalePrice( $product, $event->args );

		$params = $this->getWooViewContentParams($product, $quantity);

        $data = [
            'name' => 'ViewContent'
        ];

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

    private function getWooViewContentParams( $product, $quantity = 1 ) {
        if ( $this->getOption( 'woo_variable_data_select_product' ) && !$this->getOption( 'woo_variable_as_simple' ) ) {
            $product_id = getVariableIdByAttributes( $product );
        } else {
            $product_id = Helpers\getTikTokWooVariableToSimpleProductId( $product );
        }
        $content_id = Helpers\getTikTokWooProductContentId( $product_id ?? $product->get_id() );
        $params = [
            'quantity'         => $quantity,
            'currency'         => get_woocommerce_currency(),
            'content_name'     => $product->get_name(),
            'content_category' => implode( ', ', getObjectTerms( 'product_cat', $product_id ?? $product->get_id() ) ),
            'content_id'       => $content_id,
        ];
        if ( wooProductIsType( $product, 'variable' ) && !$this->getOption( 'woo_variable_as_simple' ) ) {
            $params[ 'content_type' ] = 'product_group';
        } else {
            $params[ 'content_type' ] = 'product';
        }


        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_id ?? $product->get_id(),
                'qty' => $quantity
            ];

            $params[ 'value' ] = getWooProductValue($valueArgs);
        }

        return $params;
    }

	/**
	 * @param SingleEvent $event
	 * @return bool
	 * content_type, quantity, description, content_id, currency, value
	 */
	function addwcf_view_content_params( &$event ) {
		if ( !$this->getOption( 'woo_view_content_enabled' ) || empty( $event->args[ 'products' ] ) ) {
			return false;
		}
		$contents = [];
		$total = 0;

		foreach ( $event->args[ 'products' ] as $product_data ) {

			$product = wc_get_product( $product_data[ 'id' ] );
            if(!$product) continue;
			if ( $this->getOption( 'woo_variable_data_select_product' ) && !$this->getOption( 'woo_variable_as_simple' ) ) {
				$product_id = getVariableIdByAttributes( $product );
			} else {
				$product_id = Helpers\getTikTokWooVariableToSimpleProductId( $product );
			}
			$content_id = Helpers\getTikTokWooProductContentId( $product_id ?? $product->get_id() );

			$contents[] = [
				'price'            => $product_data[ 'price' ],
				'content_name'     => $product_data[ 'name' ],
				'content_category' => implode( ', ', array_column( $product_data[ 'categories' ], "name" ) ),
				'content_id'       => $content_id,
				'quantity'         => $product_data[ 'quantity' ],
				'content_type'     => 'product',
			];
			$total += $product_data[ 'price' ] * $product_data[ 'quantity' ];
		}
		$params = [
			'content_type' => 'product',
			'currency'     => get_woocommerce_currency(),
			'contents'     => $contents,
		];

		$data = [
			'name' => 'ViewContent'
		];

		if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
			$params[ 'value' ] = $total;
		}

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_woo_initiate_checkout_params( &$event ) {

		if ( !$this->getOption( 'woo_initiate_checkout_enabled' ) ) {
			return false;
		}

		$params = $this->getWooEventViewCartParams($event);

		$data = [
			'name' => 'InitiateCheckout'
		];

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

    private function getWooEventViewCartParams($event)
    {
        $contents = [];
        foreach ( $event->args[ 'products' ] as $product ) {
            $product_id = Helpers\getTikTokWooCartProductId( $product );
            $content_id = Helpers\getTikTokWooProductContentId( $product_id );

            $contents[] = array(
                'price'            => $product[ 'price' ],
                'content_name'     => $product[ 'name' ],
                'content_category' => implode( ', ', array_column( $product[ 'categories' ], "name" ) ),
                'content_id'       => $content_id,
                'quantity'         => $product[ 'quantity' ],
                'content_type'     => 'product',
            );
        }

        if ( empty( $contents ) ) {
            return false;
        }

        $total = getWooEventCartSubtotal( $event );
        $params = array(
            'content_type' => 'product',
            'contents'     => $contents,
            'currency'     => get_woocommerce_currency(),
        );

        $value_enabled_option = 'woo_initiate_checkout_value_enabled';
        // currency, value
        if ( PYS()->getOption( $value_enabled_option ) ) {

            $value_option_option  = 'woo_initiate_checkout_value_option';
            $value_global_option  = 'woo_initiate_checkout_value_global';
            $value_percent_option = 'woo_initiate_checkout_value_percent';

            $value_option   = PYS()->getOption( $value_option_option );
            $global_value   = PYS()->getOption( $value_global_option, 0 );
            $percents_value = PYS()->getOption( $value_percent_option, 100 );

            $params['value']    = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$event->args);

        }

        return $params;
    }

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_woo_compete_payment_params( &$event ) {

		if ( !$this->getOption( 'woo_compete_payment_enabled' ) ) {
			return false;
		}

		$contents = [];

        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );

		foreach ( $event->args[ 'products' ] as $product_data ) {
			$product_id = Helpers\getTikTokWooProductDataId( $product_data );
			$content_id = Helpers\getTikTokWooProductContentId( $product_id );

			$contents[] = array(
				'price'            => getWooProductPrice($product_id),
				'content_name'     => $product_data[ 'name' ],
				'content_category' => implode( ', ', array_column( $product_data[ 'categories' ], "name" ) ),
				'content_id'       => $content_id,
				'quantity'         => $product_data[ 'quantity' ],
				'content_type'     => 'product',
			);
		}
        $total = getWooEventOrderTotal($event);
        $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$event->args);
		$params = array(
			'content_type' => 'product',
			'contents'     => $contents,
			'currency'     => get_woocommerce_currency(),
			'value'        => $value,
		);

		$data = [
			'name'  => 'CompletePayment',
			'delay' => 0.2,
		];

		$event->addParams( $params );
		$event->addPayload( $data );

		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_woo_purchase_params( &$event ) {
		if ( !$this->getOption( 'woo_purchase_enabled' ) ) {
			return false;
		}

        $params = $this->getWooPurchaseParams($event);

		$data = [
			'name' => 'PlaceAnOrder'
		];

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

    private function getWooPurchaseParams( $event ) {
        $contents = [];

        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );

        foreach ( $event->args[ 'products' ] as $product_data ) {
            $product_id = Helpers\getTikTokWooProductDataId( $product_data );
            $content_id = Helpers\getTikTokWooProductContentId( $product_id );

            $contents[] = array(
                'price'            => getWooProductPrice($product_id),
                'content_name'     => $product_data[ 'name' ],
                'content_category' => implode( ', ', array_column( $product_data[ 'categories' ], "name" ) ),
                'content_id'       => $content_id,
                'quantity'         => $product_data[ 'quantity' ],
                'content_type'     => 'product',
            );
        }
        $total = getWooEventOrderTotal($event);
        $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total,$event->args);
        return array(
            'content_type' => 'product',
            'contents'     => $contents,
            'currency'     => get_woocommerce_currency(),
            'value'        => $value,
        );
    }

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_add_to_cart_on_check_params( &$event ) {
		if ( !$this->getOption( 'edd_add_to_cart_enabled' ) ) return false;

		$data = [
			'name' => 'AddToCart'
		];
		$params = $this->getEddProductParams( $event );
		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_view_content_params( &$event ) {
		if ( !$this->getOption( 'edd_view_content_enabled' ) ) return false;

		$data = [
			'name' => 'ViewContent'
		];
		$params = $this->getEddProductParams( $event );
		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_init_checkout_params( &$event ) {
		if ( !$this->getOption( 'edd_initiate_checkout_enabled' ) ) return false;

		$params = $this->getEddProductParams( $event );

		$data = [
			'name' => 'InitiateCheckout'
		];

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_purchase_params( &$event ) {
		if ( !$this->getOption( 'edd_purchase_enabled' ) ) return false;
		$data = [
			'name' => 'PlaceAnOrder'
		];
		$params = $this->getEddProductParams( $event );

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_complete_payment_params( &$event ) {
		if ( !$this->getOption( 'edd_complete_payment_enabled' ) ) return false;
		$data = [
			'name' => 'CompletePayment'
		];
		$params = $this->getEddProductParams( $event );

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_edd_add_to_cart_params( &$event ) {
		if ( !$this->getOption( 'edd_add_to_cart_enabled' ) ) return false;
		$params = [];
		if ( $event->args != null ) {
			$params = $this->get_edd_add_to_cart_on_button_click_params( $event->args );
		}
		$data = [
			'name' => 'AddToCart'
		];
		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}


	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_custom_event_params( &$event ) {
        global $post;

		/**
		 * @var CustomEvent $customEvent
		 */
		$customEvent = $event->args;
		if ( !$customEvent->isTikTokEnabled() ) return false;

		$params = [];

		if ( $customEvent->tiktok_params_enabled ) {
			$params = $customEvent->tiktok_params;


			$customParams = $customEvent->tiktok_custom_params;
			foreach ( $customParams as $custom_param ) {
				$params[ $custom_param[ 'name' ] ] = $custom_param[ 'value' ];
			}
			// SuperPack Dynamic Params feature
			$params = apply_filters( 'pys_superpack_dynamic_params', $params, 'tiktok' );
		}

        $trigger_types = array();
        foreach ($customEvent->getTriggers() as $trigger) {
            $trigger_types[] = $trigger->getTriggerType();
        }
        if ( in_array( 'purchase', $trigger_types ) && ($trigger->getTrackValueAndCurrency()) ) {
            $order = EventsWoo()->getOrder();
            if ($order) {
                $added_params = array();
                if($trigger->getTrackValueAndCurrency()) {
                    $added_params['value'] = $order->get_total();
                    $added_params['currency'] = $order->get_currency();
                }
                $params = array_merge($params, $added_params);
            }
        }

        if ( in_array( 'add_to_cart', $trigger_types ) && $trigger->getTrackValueAndCurrency() && $event->getPayloadValue('productId')) {
            $product_id = $event->getPayloadValue('productId');
            $quantity = $event->getPayloadValue('quantity') ?? 1;

            $product = wc_get_product($product_id);
            if( !$product ) {
                return false; // only simple products
            }
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
        if(isWooCommerceActive()) {
            if ($customEvent->tiktok_track_single_woo_data && is_product() && ($post->post_type === 'product' || $event->getPayloadValue('productId'))) {
                $product_id = $event->getPayloadValue('productId') ?? $post->ID;
                $quantity = $event->getPayloadValue('quantity') ?? 1;

                $product = wc_get_product($product_id);

                if (!$product) {
                    return false; // only simple products
                }

                $eventData = $this->getWooViewContentParams($product, $quantity);

                $params = array_merge($params, $eventData);

            } elseif ($customEvent->tiktok_track_cart_woo_data) {
                $order = EventsWoo()->getOrder();
                if ($order) {
                    $purchaseEvent = EventsWoo()->getEvent('woo_purchase');
                    if ($purchaseEvent) {
                        $params = array_merge($params, $this->getWooPurchaseParams($purchaseEvent));
                    }
                } else {
                    $viewContentEvent = EventsWoo()->getEvent('woo_initiate_checkout');
                    if ($viewContentEvent) {
                        $params = array_merge($params, $this->getWooEventViewCartParams($viewContentEvent));
                    }
                }
            }
        }

		$data = [
			'name'  => $customEvent->getTikTokEventType(),
			'delay' => $customEvent->getDelay(),
		];
		$event->addPayload( $data );
		$event->addParams( $params );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return bool
	 */
	private function add_wcf_add_to_cart_params( &$event ) {
		if ( !$this->getOption( 'woo_add_to_cart_enabled' ) || empty( $event->args[ 'products' ] ) ) {
			return false; // return if args is empty
		}
		$contents = [];
		$total = 0;
		foreach ( $event->args[ 'products' ] as $product_data ) {

			$product = wc_get_product( $product_data[ 'id' ] );
            if(!$product) continue;
			$product_id = Helpers\getTikTokWooVariableToSimpleProductId( $product );
			$content_id = Helpers\getTikTokWooProductContentId( $product_id );

			$contents[] = array(
				'price'            => $product_data[ 'price' ],
				'content_name'     => $product_data[ 'name' ],
				'content_category' => implode( ', ', array_column( $product_data[ 'categories' ], "name" ) ),
				'content_id'       => $content_id,
				'quantity'         => $product_data[ 'quantity' ],
				'content_type'     => 'product',
			);

			$total += $product_data[ 'price' ] * $product_data[ 'quantity' ];
		}
		$params = [
			'content_type' => 'product',
			'currency'     => get_woocommerce_currency(),
			'contents'     => $contents,
		];


        $value_enabled_option = 'woo_add_to_cart_value_enabled';
        // currency, value
        if ( PYS()->getOption( $value_enabled_option ) ) {

            $value_option_option  = 'woo_add_to_cart_value_option';
            $value_global_option  = 'woo_add_to_cart_value_global';
            $value_percent_option = 'woo_add_to_cart_value_percent';

            $value_option   = PYS()->getOption( $value_option_option );
            $global_value   = PYS()->getOption( $value_global_option, 0 );
            $percents_value = PYS()->getOption( $value_percent_option, 100 );

            $params['value']    = getWooEventValueProducts($value_option, $global_value, $percents_value, $total, $event->args);

        }
		$data = [
			'name' => 'AddToCart'
		];

		$event->addParams( $params );
		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @return array
	 */
	private function getEddProductParams( $event ) {

        switch ($event->getId()) {
            case 'edd_add_to_cart_on_checkout_page':
                {
                    if (!$this->getOption('edd_add_to_cart_enabled')) return false;
                    $value_enabled = PYS()->getOption('edd_add_to_cart_value_enabled');
                    $value_option = PYS()->getOption('edd_add_to_cart_value_option');
                    $percents_value = PYS()->getOption('edd_add_to_cart_value_percent', 100);
                    $global_value = PYS()->getOption('edd_add_to_cart_value_global', 0);
                }
                break;
            case 'edd_initiate_checkout':
                {
                    $value_enabled = PYS()->getOption('edd_initiate_checkout_value_enabled');
                    $value_option = PYS()->getOption('edd_initiate_checkout_value_option');
                    $percents_value = PYS()->getOption('edd_initiate_checkout_value_percent', 100);
                    $global_value = PYS()->getOption('edd_initiate_checkout_value_global', 0);
                }
                break;
            case 'edd_view_content' :
                {
                    $value_enabled = PYS()->getOption('edd_view_content_value_enabled');
                    $value_option = PYS()->getOption('edd_view_content_value_option');
                    $percents_value = PYS()->getOption('edd_view_content_value_percent', 100);
                    $global_value = PYS()->getOption('edd_view_content_value_global', 0);
                }
                break;
            case 'edd_purchase' :
            case 'edd_complete_payment':
                {
                    $value_enabled = PYS()->getOption('edd_purchase_value_enabled', true);
                    $value_option = PYS()->getOption('edd_purchase_value_option');
                    $percents_value = PYS()->getOption('edd_purchase_value_percent', 100);
                    $global_value = PYS()->getOption('edd_purchase_value_global', 0);
                }
                break;
        }

		$total = 0;
		$total_as_is = 0;
		$isPurchase = $event->getId() == 'edd_purchase' || $event->getId() == 'edd_complete_payment';

		foreach ( $event->args[ 'products' ] as $product ) {
			$download_id = (int) $product[ 'product_id' ];
			$edd_download_price = getEddDownloadPrice( $download_id, $product[ 'price_index' ] );

			$contents[] = array(
				'price'            => $edd_download_price,
				'content_name'     => $product[ 'name' ],
				'content_category' => implode( ', ', array_column( $product[ 'categories' ], 'name' ) ),
				'content_id'       => $download_id,
				'quantity'         => $product[ 'quantity' ],
				'content_type'     => 'product',
			);

			if ( $isPurchase ) {
				if ( PYS()->getOption( 'edd_tax_option' ) == 'included' ) {
					$total += $product[ 'subtotal' ] + $product[ 'tax' ] - $product[ 'discount' ];
				} else {
					$total += $product[ 'subtotal' ] - $product[ 'discount' ];
				}
				$total_as_is += $product[ 'price' ];

			} else {

				$total += $edd_download_price * $product[ 'quantity' ];
				if ( isset( $product[ 'cart_item_key' ] ) ) {
					$total_as_is += edd_get_cart_item_final_price( $product[ 'cart_item_key' ] );
				} else {
					$total_as_is += floatval( edd_get_download_final_price( $download_id, [] ) );
				}
			}
		}

		//add fee
		$fee = $event->args[ 'fee' ] ?? 0;
		$feeTax = $event->args[ 'fee_tax' ] ?? 0;

		if ( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
			if ( PYS()->getOption( 'edd_tax_option' ) == 'included' ) {
				$total += $fee + $feeTax;
			} else {
				$total += $fee;
			}
		} else {
			if ( edd_prices_include_tax() ) {
				$total_as_is += $fee + $feeTax;
			} else {
				$total_as_is += $fee;
			}
		}

		$params = [
			'content_type' => 'product',
		];
		if(!empty($contents)){
			$params['contents'] = $contents;
		}
		if ( $value_enabled ) {
			if ( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
                $amount = $total;
			} else {
                $amount = $total_as_is;
			}
            $params['currency'] = edd_get_currency();
            $params['value']    = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
		}

		return $params;
	}

	function getAdvancedMatchingParams() {

		$params = array();
		$user_email = $user_phone = '';
		$user = wp_get_current_user();

		if ( $user && $user->ID ) {
			// get user regular data
			$user_email = $user->get( 'user_email' );
			$user_phone = $user->get( 'billing_phone' );
		}

		if ( isEddActive() ) {
			$payment_key = getEddPaymentKey();
			$order_id = (int) edd_get_purchase_id_by_key( $payment_key );
			if ( $order_id ) {
				$userEdd = edd_get_payment_meta_user_info( $order_id );
				if ( !empty( $userEdd[ 'email' ] ) ) {
					$user_email = $userEdd[ 'email' ];
				}
			}
		}

		if ( isWooCommerceActive() ) {
			$orderId = wooGetOrderIdFromRequest();
			if ( $orderId > 0 ) {
				$order = wc_get_order( $orderId );
				if ( $order ) {
					$user_email = $order->get_billing_email();
					$user_phone = $order->get_billing_phone();
				}
			}
		}

		$user_persistence_data = get_persistence_user_data( $user_email, '', '', $user_phone );
		if ( !empty( $user_persistence_data[ 'em' ] ) ) $params[ 'sha256_email' ] = hash( 'sha256', mb_strtolower( $user_persistence_data[ 'em' ] ) );
		if ( !empty( $user_persistence_data[ 'tel' ] ) ) $params[ 'sha256_phone_number' ] = hash( 'sha256', preg_replace( '/[^0-9]/', '', $user_persistence_data[ 'tel' ] ) );


		if ( EventsManager::isTrackExternalId() ) {
			if ( PYS()->get_pbid() ) {
				$params[ 'external_id' ] = PYS()->get_pbid();
			}
		}

		return apply_filters( "pys_tt_advanced_matching", $params );
	}

	private function add_click_button_params( &$event ) {

		$isActive = $this->getOption( $event->getId() . '_enabled' );
		$params = array();
		$event->addParams( $params );
		$data = array(
			'name' => 'ClickButton'
		);

		$event->addPayload( $data );

		return $isActive;
	}

	private function getWooAdvancedMarketingEventParams( $event ) {

		if ( !$this->getOption( $event->getId() . '_enabled' ) ) {
			return false;
		}

		$data = array();

		switch ( $event->getId() ) {
			case 'woo_frequent_shopper':
				$data[ 'name' ] = 'FrequentShopper';
				break;
			case 'woo_vip_client':
				$data[ 'name' ]  = 'VipClient';
				break;
			case 'woo_FirstTimeBuyer':
				$data[ 'name' ]  = 'FirstTimeBuyer';
				break;
			case 'woo_ReturningCustomer':
				$data[ 'name' ]  = 'ReturningCustomer';
				break;
			case 'woo_big_whale':
				$data[ 'name' ]  = 'BigWhale';
				break;
			default:
				return false;
		}

		$event->addPayload( $data );
		return true;
	}

	/**
	 * @param SingleEvent $event
	 * @param array $args
	 * @return boolean
	 */
	private function setEddCartEventParams( $event ) {

		if ( !$this->getOption( $event->getId() . '_enabled' ) ) {
			return false;
		}

		$data = array();

		switch ( $event->getId() ) {
			case 'edd_frequent_shopper':
				$data[ 'name' ] = 'FrequentShopper';
				break;
			case 'edd_vip_client':
				$data[ 'name' ] = 'VipClient';
				break;
			case 'edd_big_whale':
				$data[ 'name' ] = 'BigWhale';
				break;
		}

		$event->addPayload( $data );
		return true;
	}

	private function addDataToEvent( $eventData, &$event ) {
		$params = $eventData[ "data" ];
		unset( $eventData[ "data" ] );
		$event->addParams( $params );
		$event->addPayload( $eventData );
	}

	/**
	 * @return bool
	 */
	public function isServerApiEnabled() {
		return $this->getOption( "use_server_api" );
	}

	public function getApiTokens() {

		$tokens = array();
		$pixel_ids = (array) $this->getOption( 'pixel_id' );
		if ( count( $pixel_ids ) > 0 ) {
			$tokens[ $pixel_ids[ 0 ] ] = (array) $this->getOption( 'server_access_api_token' );
		}

		return $tokens;
	}

	public function getApiTestCode() {

		$testCode = array();
		$pixelids = (array) $this->getOption( 'pixel_id' );
		if ( count( $pixelids ) > 0 ) {
			$serverTestCode = (array) $this->getOption( 'test_api_event_code' );
			$testCode[ $pixelids[ 0 ] ] = reset( $serverTestCode );
		}

		return $testCode;
	}

	public function getLog() {
		return $this->logger;
	}
}


/**
 * @return Tiktok
 */
function Tiktok() {
	return Tiktok::instance();
}

Tiktok();