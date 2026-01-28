<?php

namespace PixelYourSite;
class EventsCustom extends EventsFactory {
	private static $_instance;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	private function __construct() {
		add_filter( "pys_event_factory", [
			$this,
			"register"
		] );
	}
	function register( $list ) {
		$list[] = $this;
		return $list;
	}

	static function getSlug() {
		return "custom";
	}

	function getEvents() {
		return CustomEventFactory::get( 'active' );
	}

	function getCount() {
		if ( !$this->isEnabled() ) {
			return 0;
		}
		return count( $this->getEvents() );
	}

	function isEnabled() {
		return PYS()->getOption( 'custom_events_enabled' );
	}

	function getOptions() {
		return array();
	}

	/**
	 * @param CustomEvent $event
	 * @return bool
	 */
	function isReadyForFire( $event ) {

        if(!$event->checkConditions()) return false;

		$event_triggers = $event->getTriggers();
		$isReady = array();
		$visitTracked = false;

		if ( !empty( $event_triggers ) ) {
			foreach ( $event_triggers as $event_trigger ) {
				$trigger_type = $event_trigger->getTriggerType();
				switch ( $trigger_type ) {
					case 'post_type' :
					{
						$isTriggerReady = $event_trigger->getPostTypeValue() == get_post_type();
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}
					case 'number_page_visit' :
					{
						$triggers = $event_trigger->getNumberPageVisitTriggers();
						if ( !empty( $triggers ) && compareURLs( $triggers ) ) {
							$user = get_current_user_id() && get_current_user_id() !== 0 ? get_current_user_id() : null;
							$tracker = new PageVisitTracker( $user );
							if ( !$visitTracked ) {
								$tracker->update_page_visits( $event->getPostId() );
								$visitTracked = true;
							}
							$visitCount = $tracker->get_page_visit_count( $event->getPostId() );
							if ( $this->isConditionalNumberVisit( $event_trigger->getConditionalNumberVisit(), $event_trigger->getNumberVisit(), $visitCount ) ) {
								$event_trigger->setTriggerStatus( true );
								$isReady[] = true;
							}
						}
						break;
					}
					case 'page_visit':
					{
						$triggers = $event_trigger->getPageVisitTriggers();
						$isTriggerReady = !empty( $triggers ) && compareURLs( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}
                    case 'home_page':
                    {
                        $isTriggerReady = is_front_page();
                        $event_trigger->setTriggerStatus( $isTriggerReady );
                        $isReady[] = $isTriggerReady;
                        break;
                    }
                    case 'purchase':
                    {
                        $isTriggerReady = isWooCommerceActive() && PYS()->woo_is_order_received_page() && wooIsRequestContainOrderId();
                        $event_trigger->setTriggerStatus($isTriggerReady);

                        if (!$isTriggerReady) {
                            $isReady[] = false;
                            break;
                        }

                        $order = EventsWoo()->getOrder();
                        $fire_event = true;

                        if ($order) {
                            $meta_key = '_pys_custom_purchase_event_fired_' . $event->getPostId();
                            if ($event_trigger->getOnlyTransactionsPurchase() && $order->get_meta($meta_key, true)) {
                                $fire_event = false;  // skip woo_purchase if this transaction was fired
                            } else {
                                $order->update_meta_data($meta_key, true);
                                $order->save();
                            }
                        }

                        $isReady[] = $isTriggerReady && $fire_event;
                        break;
                    }

                    case 'add_to_cart':
                    {
                        $isTriggerReady = isWooCommerceActive();
                        $event_trigger->setTriggerStatus( $isTriggerReady );
                        $isReady[] = $isTriggerReady;
                        break;
                    }

					case 'url_click':
					{
						$triggers = $event_trigger->getURLClickTriggers();
						$isTriggerReady = !empty( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}

					case 'css_click':
					{
						$triggers = $event_trigger->getCSSClickTriggers();
						$isTriggerReady = !empty( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}

					case 'css_mouseover':
					{
						$triggers = $event_trigger->getCSSMouseOverTriggers();
						$isTriggerReady = !empty( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}

					case 'scroll_pos':
					{
						$triggers = $event_trigger->getScrollPosTriggers();
						$isTriggerReady = !empty( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}

					case 'video_view':
					{
						$triggers = $event_trigger->getVideoViewTriggers();
						$isTriggerReady = !empty( $triggers );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}

                    case 'form_field':
                    {
                        $triggers = array_map(function($formValue) {
                            return ['value' => $formValue, 'rule' => 'contains'];
                        }, $event_trigger->getAnyFormUrls());
                        $isTriggerReady = !empty( $event_trigger->getAnyForm() ) && !empty( $event_trigger->getAnyFormField() ) && compareURLs( $triggers ) ;
                        $event_trigger->setTriggerStatus( $isTriggerReady );
                        $isReady[] = $isTriggerReady;
                        break;
                    }

					case 'email_link':
					{
						$isTriggerReady = !empty( $event_trigger->getEmailLinkTriggers() );
						$event_trigger->setTriggerStatus( $isTriggerReady );
						$isReady[] = $isTriggerReady;
						break;
					}
				}
				if ( $event_trigger->isFormTriggerType( $trigger_type ) ) {
					$triggers = $event_trigger->getForms();
					$event_trigger->setTriggerStatus( !empty( $triggers ) );
					$isReady[] = !empty( $triggers );
				}
			}
		}

		return in_array( true, $isReady );
	}

	/**
	 * @param CustomEvent $event
	 * @return PYSEvent
	 */
	function getEvent( $event ) {
		$event_triggers = $event->getTriggers();
		$trigger_types = array();
		$eventObject = null;
		$eventId = $event->getPostId();
		$triggerEventTypes = array();

		if ( !empty( $event_triggers ) ) {
			foreach ( $event_triggers as $event_trigger ) {
				if ( $event_trigger->getTriggerStatus() ) {
					$trigger_type = $event_trigger->getTriggerType();
					switch ( $trigger_type ) {
						case 'post_type' :
						case 'page_visit':
						case 'number_page_visit':
                        case 'home_page':
                        case 'purchase':
							$trigger_types[] = EventTypes::$STATIC;
							break;
						case 'url_click':
						case 'css_click':
						case 'css_mouseover':
						case 'scroll_pos':
						case 'video_view':
						case 'email_link':
                        case 'add_to_cart':
                        case 'form_field':
							$trigger_types[] = EventTypes::$TRIGGER;
							break;
					}

					if ( $event_trigger->isFormTriggerType( $trigger_type ) ) {
						$trigger_types[] = EventTypes::$TRIGGER;
					}

					$trigger = $event_trigger->getEventTriggers( $event_trigger );

					if ( isset( $triggerEventTypes[ $trigger[ 'trigger_type' ] ][ $eventId ] ) ) {
						$triggerEventTypes[ $trigger[ 'trigger_type' ] ][ $eventId ] = array_merge( $triggerEventTypes[ $trigger[ 'trigger_type' ] ][ $eventId ], $trigger[ 'data' ] );
					} else {
						$triggerEventTypes[ $trigger[ 'trigger_type' ] ][ $eventId ] = $trigger[ 'data' ];
					}
				}
			}
		}

		if ( in_array( EventTypes::$STATIC, $trigger_types ) ) {
			$singleEvent = new SingleEvent( 'custom_event', EventTypes::$STATIC, self::getSlug() );
			$singleEvent->args = $event;
			$eventObject = $singleEvent;
		} elseif ( in_array( EventTypes::$TRIGGER, $trigger_types ) ) {
			$singleEvent = new SingleEvent( 'custom_event', EventTypes::$TRIGGER, self::getSlug() );
			$singleEvent->args = $event;
			$singleEvent->args->__set( 'triggerEventTypes', $triggerEventTypes );
			$eventObject = $singleEvent;
		}

		if ( $eventObject ) {
			$eventObject->addPayload( [ "custom_event_post_id" => $event->__get( 'post_id' ) ] );
			if ( $event->hasTimeWindow() ) {
				$eventObject->addPayload( [ "hasTimeWindow" => $event->hasTimeWindow() ] );
				$eventObject->addPayload( [ "timeWindow" => $event->getTimeWindow() ] );
			}

			$delay = $event->getDelay();
			if ( $delay > 0 ) {
				$eventObject->addPayload( [ "delay" => $delay ] );
			}
		}


		return $eventObject;
	}

    public function hasTriggerAddToCart() {
        $flag = false;
        foreach ($this->getEvents() as $event) {
            if ($event->hasTriggerAddToCart()) {
                $flag = true;
                break;
            }
        }

        return $flag;
    }

	function isConditionalNumberVisit( $operator, $visitCount, $currentVisits ) {
		switch ( $operator ) {
			case 'equal':
				return $currentVisits == $visitCount;
			case 'equal_or_larger':
				return $currentVisits >= $visitCount;
			case 'equal_or_less':
				return $currentVisits <= $visitCount;
			case 'larger':
				return $currentVisits > $visitCount;
			case 'less':
				return $currentVisits < $visitCount;
			default:
				// Handle unexpected operator
				return false;
		}
	}

    function chechConditionals($event)
    {
        if($event->conditions_enabled) {
            //var_dump($event->getConditions());
        }
    }
}

/**
 * @return EventsCustom
 */
function EventsCustom() {
	return EventsCustom::instance();
}

EventsCustom();