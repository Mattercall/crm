<?php

namespace PixelYourSite;

use FluentCrm\App\Models\Tag;
use FluentCrm\App\Models\Subscriber;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\UserData;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class FluentCRMIntegration extends Settings {

	private static $_instance = null;

	private const META_KEY_FIRED = 'pys_fluentcrm_tag_events';

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		parent::__construct( 'fluentcrm' );

		$this->locateOptions(
			PYS_PATH . '/includes/integrations/fluentcrm/options_fields.json',
			PYS_PATH . '/includes/integrations/fluentcrm/options_defaults.json'
		);

		add_action( 'pys_register_plugins', function( $core ) {
			/** @var PYS $core */
			$core->registerPlugin( $this );
		} );

		add_action( 'init', array( $this, 'registerHooks' ), 20 );
		add_filter( 'pys_fluentcrm_settings_sanitize_rules_field', array( $this, 'sanitizeRules' ) );
	}

	public function registerHooks() {
		add_action( 'fluentcrm_contact_added_to_tags', array( $this, 'handleTagsAdded' ), 10, 2 );
		add_action( 'fluentcrm_contact_removed_from_tags', array( $this, 'handleTagsRemoved' ), 10, 2 );
	}

	public function handleTagsAdded( $tagIds, $subscriber ) {
		$this->handleTagChange( 'tag_added', $tagIds, $subscriber );
	}

	public function handleTagsRemoved( $tagIds, $subscriber ) {
		$this->handleTagChange( 'tag_removed', $tagIds, $subscriber );
	}

	private function handleTagChange( $trigger, $tagIds, $subscriber ) {
		if ( ! $this->getOption( 'enabled' ) ) {
			return;
		}

		if ( ! $subscriber instanceof Subscriber ) {
			return;
		}

		$tagIds = array_filter( array_map( 'intval', (array) $tagIds ) );
		if ( empty( $tagIds ) ) {
			return;
		}

		$rules = $this->getOption( 'rules' );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return;
		}

		PYS()->getLog()->debug( 'FluentCRM tags event received. Contact ' . $subscriber->id . ' tags: ' . implode( ',', $tagIds ) );

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			if ( empty( $rule['trigger'] ) || $rule['trigger'] !== $trigger ) {
				continue;
			}

			$ruleTagIds = isset( $rule['tag_ids'] ) ? (array) $rule['tag_ids'] : array();
			$ruleTagIds = array_filter( array_map( 'intval', $ruleTagIds ) );

			if ( empty( $ruleTagIds ) ) {
				continue;
			}

			$matchedTags = array_intersect( $tagIds, $ruleTagIds );
			if ( empty( $matchedTags ) ) {
				continue;
			}

			foreach ( $matchedTags as $tagId ) {
				$ruleId = $rule['id'] ?? '';

				if ( ! $this->shouldFireRule( $rule, $subscriber, $tagId ) ) {
					continue;
				}

				$eventResult = $this->triggerRuleEvent( $rule, $subscriber );

				if ( $eventResult['success'] ) {
					$this->markRuleFired( $rule, $subscriber, $tagId );
					PYS()->getLog()->debug(
						'FluentCRM rule fired.',
						array(
							'contact_id' => $subscriber->id,
							'rule_id' => $ruleId,
							'tag_id' => $tagId,
							'event_name' => $eventResult['event_name'],
							'destinations' => $eventResult['destinations'],
						)
					);
				} else {
					PYS()->getLog()->error(
						'FluentCRM rule failed to fire.',
						array(
							'contact_id' => $subscriber->id,
							'rule_id' => $ruleId,
							'tag_id' => $tagId,
							'error' => $eventResult['message'],
						)
					);
				}
			}
		}
	}

	private function shouldFireRule( array $rule, Subscriber $subscriber, int $tagId ): bool {
		if ( empty( $rule['fire_once'] ) ) {
			return true;
		}

		if ( empty( $rule['id'] ) ) {
			return true;
		}

		$stored = $this->getRuleTrackingData( $subscriber );
		$ruleId = $rule['id'];

		return empty( $stored[ $ruleId ][ $tagId ] );
	}

	private function markRuleFired( array $rule, Subscriber $subscriber, int $tagId ): void {
		if ( empty( $rule['fire_once'] ) ) {
			return;
		}

		if ( empty( $rule['id'] ) ) {
			return;
		}

		$stored = $this->getRuleTrackingData( $subscriber );
		$ruleId = $rule['id'];

		if ( ! isset( $stored[ $ruleId ] ) ) {
			$stored[ $ruleId ] = array();
		}

		$stored[ $ruleId ][ $tagId ] = time();
		$this->updateRuleTrackingData( $subscriber, $stored );
	}

	private function getRuleTrackingData( Subscriber $subscriber ): array {
		$value = $subscriber->getMeta( self::META_KEY_FIRED, Subscriber::class );
		if ( empty( $value ) ) {
			return array();
		}

		$data = json_decode( $value, true );
		return is_array( $data ) ? $data : array();
	}

	private function updateRuleTrackingData( Subscriber $subscriber, array $data ): void {
		$subscriber->updateMeta( self::META_KEY_FIRED, wp_json_encode( $data ), Subscriber::class );
	}

	private function triggerRuleEvent( array $rule, Subscriber $subscriber ): array {
		if ( ! function_exists( 'PixelYourSite\\PYS' ) ) {
			return array(
				'success' => false,
				'message' => 'PixelYourSite core is unavailable.',
				'event_name' => '',
				'destinations' => array(),
			);
		}

		$eventName = $rule['event_name'] ?? '';
		$destinations = isset( $rule['destinations'] ) ? (array) $rule['destinations'] : array();
		$eventConfig = new FluentCRMCustomEventConfig( $rule );
		$event = new SingleEvent( 'custom_event', EventTypes::$STATIC, 'custom' );
		$event->args = $eventConfig;

		$eventsManager = PYS()->getEventsManager();
		$sent = false;

		if ( in_array( 'facebook', $destinations, true ) && class_exists( 'PixelYourSite\\Facebook' ) && Facebook()->configured() ) {
			$pixelEvents = Facebook()->generateEvents( $event );
			if ( $eventsManager ) {
				foreach ( $pixelEvents as $pixelEvent ) {
					$eventsManager->addStaticEvent( $pixelEvent, Facebook(), 'custom' );
				}
			}

			if ( Facebook()->isServerApiEnabled() && ! empty( $pixelEvents ) ) {
				$filter = $this->buildFacebookUserDataFilter( $subscriber );
				add_filter( 'pys_fb_server_user_data', $filter );
				FacebookServer()->sendEventsAsync( $pixelEvents );
				remove_filter( 'pys_fb_server_user_data', $filter );
			}

			$sent = true;
		}

		if ( in_array( 'ga', $destinations, true ) && class_exists( 'PixelYourSite\\GA' ) && GA()->configured() ) {
			$gaEvents = GA()->generateEvents( $event );
			if ( $eventsManager ) {
				foreach ( $gaEvents as $gaEvent ) {
					$eventsManager->addStaticEvent( $gaEvent, GA(), 'custom' );
				}
			}

			if ( GA()->isServerApiEnabled() && ! empty( $gaEvents ) ) {
				GaMeasurementProtocolAPI()->sendEventsNow( $gaEvents );
			}

			$sent = true;
		}

		if ( in_array( 'gtm', $destinations, true ) && class_exists( 'PixelYourSite\\GTM' ) && GTM()->configured() ) {
			$gtmEvents = GTM()->generateEvents( $event );
			if ( $eventsManager ) {
				foreach ( $gtmEvents as $gtmEvent ) {
					$eventsManager->addStaticEvent( $gtmEvent, GTM(), 'custom' );
				}
			}
			$sent = true;
		}

		if ( $sent ) {
			return array(
				'success' => true,
				'message' => '',
				'event_name' => $eventName,
				'destinations' => $destinations,
			);
		}

		return array(
			'success' => false,
			'message' => 'No destinations were eligible or configured.',
			'event_name' => $eventName,
			'destinations' => $destinations,
		);
	}

	private function buildFacebookUserDataFilter( Subscriber $subscriber ): callable {
		$firstName = $subscriber->first_name ?? '';
		$lastName = $subscriber->last_name ?? '';
		$email = $subscriber->email ?? '';
		$phone = $subscriber->phone ?? '';

		update_advance_matching_user_data( $firstName, $lastName, $email, $phone );

		return static function( UserData $userData ) use ( $firstName, $lastName, $email, $phone ) {
			if ( ! empty( $email ) ) {
				$userData->setEmail( $email );
			}
			if ( ! empty( $phone ) ) {
				$userData->setPhone( $phone );
			}
			if ( ! empty( $firstName ) ) {
				$userData->setFirstName( $firstName );
			}
			if ( ! empty( $lastName ) ) {
				$userData->setLastName( $lastName );
			}

			return $userData;
		};
	}

	public function sanitizeRules( $rules ): array {
		$rules = is_array( $rules ) ? $rules : array();
		$sanitized = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$ruleId = ! empty( $rule['id'] ) ? sanitize_text_field( $rule['id'] ) : wp_generate_uuid4();

			$trigger = isset( $rule['trigger'] ) && in_array( $rule['trigger'], array( 'tag_added', 'tag_removed' ), true )
				? $rule['trigger']
				: 'tag_added';

			$tagIds = isset( $rule['tag_ids'] ) ? (array) $rule['tag_ids'] : array();
			$tagIds = array_filter( array_map( 'intval', $tagIds ) );

			$eventType = isset( $rule['event_type'] ) && in_array( $rule['event_type'], array( 'standard', 'custom' ), true )
				? $rule['event_type']
				: 'standard';

			$eventName = sanitize_text_field( $rule['event_name'] ?? '' );
			$standardEvents = $this->getStandardEventNames();

			if ( $eventType === 'standard' ) {
				if ( ! in_array( $eventName, $standardEvents, true ) ) {
					$eventName = 'Lead';
				}
			} else {
				$eventName = sanitizeKey( $eventName );
				if ( $eventName === '' ) {
					$eventName = 'FluentCRMTag';
				}
			}

			$destinations = isset( $rule['destinations'] ) ? (array) $rule['destinations'] : array();
			$destinations = array_values( array_intersect( $destinations, array( 'facebook', 'ga', 'gtm' ) ) );

			$params = isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array();

			$sanitized[] = array(
				'id' => $ruleId,
				'enabled' => ! empty( $rule['enabled'] ),
				'trigger' => $trigger,
				'tag_ids' => $tagIds,
				'event_type' => $eventType,
				'event_name' => $eventName,
				'destinations' => $destinations,
				'params' => array(
					'value' => sanitize_text_field( $params['value'] ?? '' ),
					'currency' => sanitize_text_field( $params['currency'] ?? '' ),
					'content_name' => sanitize_text_field( $params['content_name'] ?? '' ),
				),
				'fire_once' => ! empty( $rule['fire_once'] ),
			);
		}

		return $sanitized;
	}

	public function getStandardEventNames(): array {
		return array(
			'ViewContent',
			'AddToCart',
			'AddToWishlist',
			'InitiateCheckout',
			'AddPaymentInfo',
			'Purchase',
			'Lead',
			'CompleteRegistration',
			'Subscribe',
			'CustomizeProduct',
			'FindLocation',
			'StartTrial',
			'SubmitApplication',
			'Schedule',
			'Contact',
			'Donate',
		);
	}

	public function getAvailableTags(): array {
		if ( ! class_exists( Tag::class ) ) {
			return array();
		}

		$tags = Tag::orderBy( 'title', 'asc' )->get();
		$options = array();
		foreach ( $tags as $tag ) {
			$options[ $tag->id ] = $tag->title;
		}

		return $options;
	}
}

class FluentCRMCustomEventConfig {
	public $facebook_pixel_id = array( 'all' );
	public $facebook_event_type = 'Lead';
	public $facebook_custom_event_type = null;
	public $facebook_params_enabled = true;
	public $facebook_params = array();
	public $facebook_custom_params = array();

	public $ga_ads_enabled = false;
	public $ga_ads_pixel_id = array( 'all' );
	public $ga_ads_event_action = '_custom';
	public $ga_ads_custom_event_action = null;
	public $ga_ads_params = array();
	public $ga_ads_custom_params = array();
	public $ga_ads_conversion_label = null;

	public $gtm_enabled = false;
	public $gtm_pixel_id = array( 'all' );
	public $gtm_event_action = '_custom';
	public $gtm_custom_event_action = null;
	public $gtm_params = array();
	public $gtm_custom_params = array();
	public $gtm_automated_param = false;
	public $gtm_remove_customTrigger = false;
	public $gtm_track_single_woo_data = false;
	public $gtm_track_cart_woo_data = false;

	private $triggers = array();

	public function __construct( array $rule ) {
		$eventType = $rule['event_type'] ?? 'standard';
		$eventName = $rule['event_name'] ?? 'Lead';

		$this->facebook_event_type = $eventType === 'custom' ? 'CustomEvent' : $eventName;
		$this->facebook_custom_event_type = $eventType === 'custom' ? $eventName : null;

		$params = isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array();
		$filteredParams = array_filter(
			array(
				'value' => $params['value'] ?? null,
				'currency' => $params['currency'] ?? null,
				'content_name' => $params['content_name'] ?? null,
			),
			static function( $value ) {
				return $value !== null && $value !== '';
			}
		);

		$this->facebook_params = $filteredParams;
		$this->ga_ads_params = $filteredParams;
		$this->gtm_params = $filteredParams;

		$destinations = isset( $rule['destinations'] ) ? (array) $rule['destinations'] : array();
		$this->ga_ads_enabled = in_array( 'ga', $destinations, true );
		$this->gtm_enabled = in_array( 'gtm', $destinations, true );

		if ( $eventType === 'custom' ) {
			$this->ga_ads_custom_event_action = $eventName;
			$this->gtm_custom_event_action = $eventName;
		} else {
			$this->ga_ads_custom_event_action = $eventName;
			$this->gtm_custom_event_action = $eventName;
		}
	}

	public function isFacebookEnabled(): bool {
		return true;
	}

	public function getFacebookEventType(): string {
		return $this->facebook_event_type === 'CustomEvent' ? $this->facebook_custom_event_type : $this->facebook_event_type;
	}

	public function isFacebookParamsEnabled(): bool {
		return $this->facebook_params_enabled;
	}

	public function getFacebookParams(): array {
		return $this->facebook_params_enabled ? $this->facebook_params : array();
	}

	public function getFacebookCustomParams(): array {
		return $this->facebook_params_enabled ? $this->facebook_custom_params : array();
	}

	public function isUnifyAnalyticsEnabled(): bool {
		return $this->ga_ads_enabled;
	}

	public function getMergedAction() {
		return $this->ga_ads_event_action === '_custom' ? $this->ga_ads_custom_event_action : $this->ga_ads_event_action;
	}

	public function getMergedGaParams(): array {
		return $this->ga_ads_params;
	}

	public function getGAMergedCustomParams(): array {
		return $this->ga_ads_custom_params;
	}

	public function isGTMEnabled(): bool {
		return $this->gtm_enabled;
	}

	public function getGTMAction() {
		return $this->gtm_event_action === '_custom' ? $this->gtm_custom_event_action : $this->gtm_event_action;
	}

	public function getAllGTMParams(): array {
		$params = array();
		foreach ( $this->gtm_params as $key => $value ) {
			$params[ $this->getManualCustomObjectName() ][ $key ] = $value;
		}
		return $params;
	}

	public function getManualCustomObjectName(): string {
		return 'manual_fluentcrm';
	}

	public function hasAutomatedParam(): bool {
		return (bool) $this->gtm_automated_param;
	}

	public function removeGTMCustomTrigger(): bool {
		return (bool) $this->gtm_remove_customTrigger;
	}

	public function getDelay(): int {
		return 0;
	}

	public function getTriggers(): array {
		return $this->triggers;
	}
}

function FluentCRMIntegration() {
	return FluentCRMIntegration::instance();
}

FluentCRMIntegration();
