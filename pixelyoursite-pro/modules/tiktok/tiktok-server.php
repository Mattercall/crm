<?php

namespace PixelYourSite;

require_once PYS_PATH . '/modules/tiktok/tiktok-server-async-task.php';

use DateTimeInterface;
use PixelYourSite;
use PYS_PRO_GLOBAL\GuzzleHttp\Client;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TikTok Server Events Handler
 *
 * Extends AbstractServerEvents to handle TikTok Conversions API
 */
class TikTokServer extends AbstractServerEvents {

	private static $_instance;
	private        $isEnabled;
	private        $testCode;

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
        $this->isEnabled = Tiktok()->isServerApiEnabled();

        if ( $this->isEnabled ) {
            // AJAX handlers for fallback (from parent class)
            $this->registerAjaxHandlers();

            // Initialize tiktok event async task (only if queue is disabled)
            if (!PYS()->getOption('queue_enabled', true)) {
                new TikTokAsyncTask();
            }
        }
    }

	/**
	 * Get the pixel instance
	 *
	 * @return Tiktok
	 */
	protected function getPixelInstance() {
		return Tiktok();
	}

	/**
	 * Get the platform name for queue system
	 *
	 * @return string
	 */
	protected function getQueuePlatformName() {
		return 'tiktok';
	}

	/**
	 * Get the async task action name
	 *
	 * @return string
	 */
	protected function getServerEventAction() {
		return 'pys_send_tiktok_server_event';
	}

	/**
	 * Get the AJAX action name for catching events
	 *
	 * @return string
	 */
	protected function getAjaxActionName() {
		return 'pys_tiktok_api_event';
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
	function trackAddToCartEvent( $cart_item_key, $product_id, $quantity, $variation_id ) {
		if ( EventsWoo()->isReadyForFire( "woo_add_to_cart_on_button_click" ) && PYS()->getOption( 'woo_add_to_cart_catch_method' ) == "add_cart_js" ) {
			// it ok. We send server method after js, and take event id from cookies
			Tiktok()->getLog()->debug( ' trackAddToCartEvent send TikTok server without browser event' );

			if ( !empty( $variation_id ) && $variation_id > 0 && ( !Tiktok()->getOption( 'woo_variable_as_simple' ) ) ) {
				$_product_id = $variation_id;
			} else {
				$_product_id = $product_id;
			}

			$event = new SingleEvent( "woo_add_to_cart_on_button_click", EventTypes::$DYNAMIC, 'woo' );
			$event->args = [
				'productId' => $_product_id,
				'quantity'  => $quantity
			];
			$event->params[ 'uri' ] = self::getRequestUri( PYS()->getOption( 'enable_remove_source_url_params' ) );

			add_filter( 'pys_conditional_post_id', function ( $id ) use ( $product_id ) {
				return $product_id;
			} );
			$events = Tiktok()->generateEvents( $event );
			remove_all_filters( 'pys_conditional_post_id' );

			do_action( 'pys_send_tiktok_server_event', $events );
		}
	}

	/**
	 * Send event for each pixel id
	 * @param array $pixel_Ids //array of TikTok pixel ids
	 * @param $event //One TikTok event object
	 */
	function sendEvent( $pixel_Ids, $event ) {

		if ( !$event ) {
			return;
		}

		if ( !$this->access_token ) {
			$this->access_token = Tiktok()->getApiTokens();
			$this->testCode = Tiktok()->getApiTestCode();
		}

		foreach ( $pixel_Ids as $pixel_Id ) {

			if ( !Tiktok()->enabled() || empty( $this->access_token[ $pixel_Id ] ) ) continue;

			$event->pixel_code = $pixel_Id;

			if ( $this->testCode[ $pixel_Id ] ) {
				$event->test_event_code = $this->testCode[ $pixel_Id ];
			}

			$url = "https://business-api.tiktok.com/open_api/v1.3/pixel/track/";
			$headers = array(
				'Content-Type' => 'application/json',
				'Access-Token' => $this->access_token[ $pixel_Id ]
			);

			Tiktok()->getLog()->debug( ' Send TikTok server event', $event );
			try {
				$client = new Client();
				$response = $client->request( 'POST', $url, [
					'headers' => $headers,
					'body'    => json_encode( $event )
				] );
				Tiktok()->getLog()->debug( ' Response from Tiktok server', $response );

			} catch ( \Exception $e ) {
				Tiktok()->getLog()->error( 'Error send TikTok server event ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Map SingleEvent to TikTok server event format
	 *
	 * @param SingleEvent $event The event to map
	 * @return \stdClass TikTok event object
	 */
	protected function mapEventToServerEvent( $event ) {

		$eventData = $event->getData();

		$eventData = EventsManager::filterEventParams( $eventData, $event->getCategory(), [
			'event_id' => $event->getId(),
			'pixel'    => Tiktok()->getSlug()
		] );

		$eventName = $eventData[ 'name' ];
		$wooOrder = isset( $event->payload[ 'woo_order' ] ) ? $event->payload[ 'woo_order' ] : null;
		$eddOrder = isset( $event->payload[ 'edd_order' ] ) ? $event->payload[ 'edd_order' ] : null;

		$user_data = $this->getUserData( $wooOrder, $eddOrder );
		$custom_data = $this->paramsToCustomData( $eventData[ 'params' ] );

		$uri = $this->getEventUri( $event );

		$user_data->page = new \stdClass;
		$user_data->page->url = $uri;
		$user_data->page->referrer = get_home_url();

		$serverEvent = new \stdClass;
		$serverEvent->event = $eventName;

		$datetime = new \DateTime( 'now' );
		$serverEvent->timestamp = $datetime->format( DateTimeInterface::ATOM );
		$serverEvent->context = $user_data;
		if ( count( get_object_vars( $custom_data ) ) > 0 ) {
			$serverEvent->properties = $custom_data;
		}


		if ( is_array( $event->params ) ) {
			foreach ( $event->params as $key => $param ) {
				$serverEvent->$key = $param;
			}
		}
        $serverEvent->event_id = $event->payload[ 'eventID' ] ?? $event->payload[ 'event_id' ] ?? '';

        return $serverEvent;
    }

	/**
	 * Get user data for TikTok event
	 *
	 * @param int|null $wooOrder WooCommerce order ID
	 * @param int|null $eddOrder EDD order ID
	 * @return \stdClass User data object
	 */
    protected function getUserData( $wooOrder = null, $eddOrder = null ) {

        $userData = new \stdClass;
        $userData->user = new \stdClass;
        $user = wp_get_current_user();
        /**
         * Add purchase WooCommerce Advanced Matching params
         */
        if ( PixelYourSite\isWooCommerceActive() && isEventEnabled( 'woo_purchase_enabled' ) && ( $wooOrder || ( PYS()->woo_is_order_received_page() && wooIsRequestContainOrderId() ) ) ) {
            if ( wooIsRequestContainOrderId() ) {
                $order_id = wooGetOrderIdFromRequest();
            } else {
                $order_id = $wooOrder;
            }

            $order = wc_get_order( $order_id );

            if ( $order ) {
                $this->woo_order = $order_id;

                if ( PixelYourSite\isWooCommerceVersionGte( '3.0.0' ) ) {
                    $user_email = $order->get_billing_email();
                    $user_phone = $order->get_billing_phone();

                } else {
                    $user_email = $order->billing_email;
                    $user_phone = $order->billing_phone;
                }

                $user_persistence_data = get_persistence_user_data( $user_email, '', '', $user_phone );
                if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->user->email = hash( 'sha256', mb_strtolower( $user_persistence_data[ 'em' ] ), false );
                if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->user->phone_number = hash( 'sha256', preg_replace( '/[^0-9]/', '', $user_persistence_data[ 'tel' ] ), false );

                // Get external_id from WooCommerce order meta
                if ( PixelYourSite\EventsManager::isTrackExternalId() ) {
                    $external_id = null;
                    if ( PixelYourSite\isWooCommerceVersionGte( '3.0.0' ) ) {
                        $external_id = $order->get_meta( 'external_id' );
                    } else {
                        $external_id = get_post_meta( $order_id, 'external_id', true );
                    }
                    if ( !empty( $external_id ) ) {
                        $userData->user->external_id = $external_id;
                    }
                }

                if ( isset( $_COOKIE[ '_ttp' ] ) && !empty( $_COOKIE[ '_ttp' ] ) ) {
                    $userData->user->ttp = sanitize_text_field($_COOKIE[ '_ttp' ]);
                }

            } else {
                $userData = $this->getRegularUserData();
            }

        } else {

            if ( PixelYourSite\isEddActive() && isEventEnabled( 'edd_purchase_enabled' ) && ( $eddOrder || edd_is_success_page() ) ) {

                $this->edd_order = $eddOrder;

                if ( $eddOrder ) $payment_id = $eddOrder; else {
                    $payment_key = getEddPaymentKey();
                    $payment_id = (int) edd_get_purchase_id_by_key( $payment_key );
                }

                $user_persistence_data = get_persistence_user_data( edd_get_payment_user_email( $payment_id ), '', '', '' );
                if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->user->email = hash( 'sha256', mb_strtolower( $user_persistence_data[ 'em' ] ), false );
                if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->user->phone_number = hash( 'sha256', preg_replace( '/[^0-9]/', '', $user_persistence_data[ 'tel' ] ), false );

                // Get external_id from EDD payment meta
                if ( PixelYourSite\EventsManager::isTrackExternalId() && $payment_id > 0 ) {
                    $external_id = edd_get_payment_meta( $payment_id, 'external_id', true );
                    if ( !empty( $external_id ) ) {
                        $userData->user->external_id = $external_id;
                    }
                }

                if ( isset( $_COOKIE[ '_ttp' ] ) && !empty( $_COOKIE[ '_ttp' ] ) ) {
                    $userData->user->ttp = sanitize_text_field($_COOKIE[ '_ttp' ]);
                }

            } else {
                $userData = $this->getRegularUserData();
            }
        }

        // Set external_id from user meta or pbid if not already set from order
        if(PixelYourSite\EventsManager::isTrackExternalId() && !isset($userData->user->external_id)){
            if($user && $user->get( 'external_id' )){
                $userData->user->external_id = $user->get( 'external_id' );
            } elseif (PixelYourSite\PYS()->get_pbid()) {
                $userData->user->external_id = PixelYourSite\PYS()->get_pbid();
            }
        }

        $userData->ip = self::getIpAddress();
        $userData->user_agent = self::getHttpUserAgent();

        return apply_filters( "pys_tiktok_server_user_data", $userData );
    }

	private function getRegularUserData() {
		$user = wp_get_current_user();
		$userData = new \stdClass;
		$userData->user = new \stdClass;
		$user_email = $user_phone = '';

		if ( $user->ID ) {
			// get user regular data
			$user_email = $user->get( 'user_email' );

			/**
			 * Add common WooCommerce Advanced Matching params
			 */
			if ( PixelYourSite\isWooCommerceActive() ) {
				$user_phone = $user->get( 'billing_phone' );
			}
		}

		$user_persistence_data = get_persistence_user_data( $user_email, '', '', $user_phone );
		if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->user->email = hash( 'sha256', mb_strtolower( $user_persistence_data[ 'em' ] ), false );
		if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->user->phone_number = hash( 'sha256', preg_replace( '/[^0-9]/', '', $user_persistence_data[ 'tel' ] ), false );

		if ( !empty( $_COOKIE[ '_ttp' ] ) ) {
			$userData->user->ttp = sanitize_text_field($_COOKIE[ '_ttp' ]);
		}

		return $userData;
	}

	private function paramsToCustomData( $data ) {

		$custom_data = new \stdClass;
		if ( isset( $data[ 'quantity' ] ) ) {
			$data_line_items = array();
			$data_line_items[ 'quantity' ] = $data[ 'quantity' ];
			if ( isset( $data[ 'value' ] ) ) {
				$data_line_items[ 'price' ] = $data[ 'value' ];
			}
			if ( isset( $data[ 'content_id' ] ) ) {
				$data_line_items[ 'content_id' ] = $data[ 'content_id' ];
			}

			if ( isset( $data[ 'content_category' ] ) ) {
				$data_line_items[ 'content_category' ] = $data[ 'content_category' ];
			}

			if ( isset( $data[ 'content_name' ] ) ) {
				$data_line_items[ 'content_name' ] = $data[ 'content_name' ];
			}

			if ( isset( $data[ 'content_type' ] ) ) {
				$data_line_items[ 'content_type' ] = $data[ 'content_type' ];
			} else {
				$data_line_items[ 'content_type' ] = 'product';
			}

			$data[ 'contents' ][] = $data_line_items;
			if ( isset( $data[ 'currency' ] ) ) {
				$custom_data->currency = $data[ 'currency' ];
			}
		}

		if ( isset( $data[ 'contents' ] ) && is_array( $data[ 'contents' ] ) ) {
			$contents = array();
			$cost = 0;
			$custom_data->content_type = $data[ 'content_type' ];

			foreach ( $data[ 'contents' ] as $c ) {
				if ( isset( $c[ 'quantity' ] ) ) {
					$contents[] = array(
						'quantity'         => (int) $c[ 'quantity' ],
						'price'            => isset( $c[ 'price' ] ) ? strval( $c[ 'price' ] ) : '',
						'content_id'       => $c[ 'content_id' ],
						'content_category' => $c[ 'content_category' ] ?? '',
						'content_name'     => $c[ 'content_name' ] ?? '',
					);

					$cost += $c[ 'quantity' ] * ( $c[ 'price' ] ?? 0 );
				}
			}

			$custom_data->contents = $contents;
			$custom_data->value = $cost;
			if ( isset( $data[ 'currency' ] ) ) {
				$custom_data->currency = $data[ 'currency' ];
			}
		}

		if ( !empty( $_GET[ 's' ] ) ) {
			$custom_data->query = $_GET[ 's' ];
		}

		return apply_filters( "pys_tiktok_server_custom_data", $custom_data );
	}
}

/**
 * @return TikTokServer
 */
function TikTokServer() {
	return TikTokServer::instance();
}

TikTokServer();