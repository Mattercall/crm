<?php
/**
 * Abstract Server Events Class
 *
 * Base class for all server-side event implementations (Facebook, TikTok, Pinterest, GA4)
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract class for server-side event handling
 */
abstract class AbstractServerEvents {

    /**
     * Debug mode flag
     *
     * @var bool
     */
    protected $isDebug = false;

    /**
     * Access tokens for API calls
     *
     * @var array
     */
    protected $access_token = [];

    /**
     * WooCommerce order ID
     *
     * @var int
     */
    protected $woo_order = 0;

    /**
     * EDD order ID
     *
     * @var int
     */
    protected $edd_order = 0;

    /**
     * Constructor
     */
    public function __construct() {
        $this->isDebug = PYS()->getOption( 'debug_enabled' );
    }

    /**
     * Get the pixel instance (Facebook, TikTok, Pinterest, GA)
     *
     * @return mixed
     */
    abstract protected function getPixelInstance();

    /**
     * Get the platform name for queue system
     *
     * @return string Platform name (facebook, tiktok, pinterest, ga4)
     */
    abstract protected function getQueuePlatformName();

    /**
     * Get the async task action name
     *
     * @return string Action name for async task
     */
    abstract protected function getServerEventAction();

    /**
     * Get the AJAX action name for catching events
     *
     * @return string AJAX action name (e.g., 'pys_api_event', 'pys_tiktok_api_event')
     */
    abstract protected function getAjaxActionName();

    /**
     * Map SingleEvent to platform-specific server event format
     *
     * @param SingleEvent $event The event to map
     * @return mixed Platform-specific event object
     */
    abstract protected function mapEventToServerEvent( $event );

    /**
     * Get user data for the event
     *
     * @param int|null $wooOrder WooCommerce order ID
     * @param int|null $eddOrder EDD order ID
     * @return mixed User data object
     */
    abstract protected function getUserData( $wooOrder = null, $eddOrder = null );

    /**
     * Send a single event to the platform API
     *
     * @param array $pixel_Ids Array of pixel IDs
     * @param mixed $event Platform-specific event object
     * @return void
     */
    abstract public function sendEvent( $pixel_Ids, $event );

    /**
     * Check if queue system is enabled and compatible
     *
     * @return bool
     */
    public function isQueueEnabled() {
        return PYS()->getOption( 'queue_enabled', true );
    }

    /**
     * Send events asynchronously (via queue or async task)
     *
     * @param SingleEvent[] $events Array of events to send
     * @return void
     */
    public function sendEventsAsync( $events ) {
        // Check if queue system is enabled
        if ( $this->isQueueEnabled() ) {
            $this->sendEventsToQueue( $events );
            return;
        }

        // Old method through async tasks
        $serverEvents = [];

        foreach ( $events as $event ) {
            if ( $this->isEventDisabledByFilter( $event ) ) {
                continue;
            }

            if ( ! $this->isEventSupported( $event ) ) {
                continue;
            }

            $ids = $event->payload['pixelIds'];
            $serverEvents[] = [
                'pixelIds' => $ids,
                'event'    => $this->mapEventToServerEvent( $event ),
            ];
        }

        if ( ! empty( $serverEvents ) ) {
            do_action( $this->getServerEventAction(), $serverEvents );
        }
    }

    /**
     * Send events to queue
     *
     * @param SingleEvent[] $events Array of events to send
     * @return void
     */
    public function sendEventsToQueue( $events ) {
        $serverEvents = [];

        foreach ( $events as $event ) {
            if ( $this->isEventDisabledByFilter( $event ) ) {
                continue;
            }

            if ( ! $this->isEventSupported( $event ) ) {
                continue;
            }

            $ids = $event->payload['pixelIds'];
            $serverEvents[] = [
                'pixelIds' => $ids,
                'event'    => $this->mapEventToServerEvent( $event ),
            ];
        }

        if ( ! empty( $serverEvents ) ) {
            EventsQueue()->addEvent( $this->getQueuePlatformName(), $serverEvents );
        }
    }

    /**
     * Send events immediately (synchronously)
     *
     * @param SingleEvent[] $events Array of events to send
     * @return void
     */
    public function sendEventsNow( $events ) {
        // Only send early response for our PYS AJAX requests, not for WooCommerce or other AJAX

        if ( $this->isPysAjaxRequest() && ! defined( 'REST_REQUEST' ) ) {
            $this->sendResponseAndContinue(['success' => true]);
        }
        foreach ( $events as $event ) {
            if ( $this->isEventDisabledByFilter( $event ) ) {
                continue;
            }

            if ( ! $this->isEventSupported( $event ) ) {
                continue;
            }

            $serverEvent = $this->mapEventToServerEvent( $event );
            $ids = $event->payload['pixelIds'];

            $this->sendEvent( $ids, $serverEvent );
        }
    }

    /**
     * Check if event is supported by this platform
     *
     * @param SingleEvent $event The event to check
     * @return bool True if event is supported
     */
    protected function isEventSupported( $event ) {
        return true; // Override in child classes if needed
    }

    /**
     * Check if server event should be disabled by filter
     *
     * @param SingleEvent $event The event to check
     * @return bool True if event should be blocked, false otherwise
     */
    protected function isEventDisabledByFilter( $event ) {
        $event_name = $event->getId();
        $woo_order = isset( $event->payload['woo_order'] ) ? $event->payload['woo_order'] : null;
        $edd_order = isset( $event->payload['edd_order'] ) ? $event->payload['edd_order'] : null;
        $order_id = $woo_order ?? $edd_order;

        $is_disabled = apply_filters(
            'pys_disable_server_event_filter',
            false,
            $event_name,
            $this->getQueuePlatformName(),
            $order_id
        );

        if ( $is_disabled ) {
            $this->getPixelInstance()->getLog()->debug(
                ucfirst( $this->getQueuePlatformName() ) . ' server event blocked by pys_disable_server_event_filter',
                [
                    'event_name' => $event_name,
                    'order_id'   => $order_id,
                ]
            );
        }

        return $is_disabled;
    }

    /**
     * Convert POST data to SingleEvent object
     *
     * @param string      $eventName  Event name
     * @param array       $params     Event parameters
     * @param string      $eventID    Event ID
     * @param array       $ids        Pixel IDs
     * @param int|null    $wooOrder   WooCommerce order ID
     * @param int|null    $eddOrder   EDD order ID
     * @param string      $eventSlug  Event slug
     * @return SingleEvent
     */
    protected function dataToSingleEvent( $eventName, $params, $eventID, $ids, $wooOrder, $eddOrder, $eventSlug = '' ) {
        $singleEvent = new SingleEvent( $eventSlug, '' );

        $payload = [
            'name'      => $eventName,
            'eventID'   => $eventID,
            'woo_order' => $wooOrder,
            'edd_order' => $eddOrder,
            'pixelIds'  => $ids,
        ];

        $singleEvent->addParams( $params );
        $singleEvent->addPayload( $payload );

        return $singleEvent;
    }

    /**
     * Check if current request is a PYS AJAX or REST request
     *
     * @return bool True if this is a PYS AJAX/REST request
     */
    protected function isPysAjaxRequest() {
        // Check for REST API requests from PYS
        if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_rest_request' ) && wp_is_rest_request() ) ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

            // Check using getRestNamespace() if available
            if ( method_exists( $this, 'getRestNamespace' ) ) {
                $namespace = $this->getRestNamespace();
                if ( strpos( $request_uri, '/' . $namespace ) !== false ) {
                    return true;
                }
            }

            // Fallback: check for common PYS REST prefix
            return strpos( $request_uri, '/pys-' ) !== false;
        }

        // Check if it's an AJAX request
        if ( ! wp_doing_ajax() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return false;
        }

        // Check if action matches our PYS AJAX action using getAjaxActionName()
        $request_action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

        return $request_action === $this->getAjaxActionName();
    }

    /**
     * Register AJAX handlers for catching events
     *
     * @return void
     */
    protected function registerAjaxHandlers() {
        $action = $this->getAjaxActionName();
        add_action( 'wp_ajax_' . $action, array( $this, 'catchAjaxEvent' ) );
        add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'catchAjaxEvent' ) );
    }

    /**
     * Handle AJAX event request
     * If server message is blocked by GDPR or it's dynamic,
     * we send data by AJAX request from JS and send the same data like browser event
     *
     * @return void
     */
    public function catchAjaxEvent() {
        $platformName = ucfirst( $this->getQueuePlatformName() );
        $this->getPixelInstance()->getLog()->debug( "catchAjaxEvent send {$platformName} server from ajax" );

        $event = sanitize_text_field( $_POST['event'] ?? '' );
        $eventSlug = sanitize_text_field( $_POST['event_slug'] ?? '' );
        $data = isset( $_POST['data'] ) ? $_POST['data'] : array();
        $ids = isset( $_POST['ids'] ) ? $_POST['ids'] : array();
        $eventID = sanitize_text_field( $_POST['eventID'] ?? $_POST['event_id'] ?? '' );
        $wooOrder = isset( $_POST['woo_order'] ) && is_numeric( $_POST['woo_order'] ) ? (int) $_POST['woo_order'] : null;
        $eddOrder = isset( $_POST['edd_order'] ) && is_numeric( $_POST['edd_order'] ) ? (int) $_POST['edd_order'] : null;

        if ( empty( $_REQUEST['ajax_event'] ) || ! wp_verify_nonce( $_REQUEST['ajax_event'], 'ajax-event-nonce' ) ) {
            wp_die();
            return;
        }

        // Unmask CompleteRegistration event if it was hidden
        if ( $event === 'hCR' ) {
            $event = 'CompleteRegistration';
        }

        $singleEvent = $this->dataToSingleEvent( $event, $data, $eventID, $ids, $wooOrder, $eddOrder, $eventSlug );
        if($this->isQueueEnabled() && PYS()->getOption("queue_use_for_ajax")) {
            $this->sendEventsToQueue( [ $singleEvent ] );
            wp_die();
        }
        else {
            // sendEventsNow() already sends response via sendResponseAndContinue()
            // so we use exit instead of wp_die() to avoid "headers already sent" warning
            $this->sendEventsNow( [ $singleEvent ] );
            exit;
        }
    }

    /**
     * Get the current request URI
     *
     * @param bool $removeQuery Whether to remove query string
     * @return string|null
     */
    protected static function getRequestUri( $removeQuery = false ) {
        $request_uri = null;

        if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? parse_url( get_site_url(), PHP_URL_HOST );
            $request_uri = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
        }

        if ( $removeQuery && isset( $_SERVER['QUERY_STRING'] ) ) {
            $request_uri = str_replace( '?' . $_SERVER['QUERY_STRING'], '', $request_uri );
        }

        return $request_uri;
    }

    /**
     * Get the client IP address
     *
     * @return string
     */
    protected static function getIpAddress() {
        $headersToScan = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ( $headersToScan as $header ) {
            if ( array_key_exists( $header, $_SERVER ) ) {
                $ip_list = explode( ',', $_SERVER[ $header ] );
                foreach ( $ip_list as $ip ) {
                    $trimmed_ip = trim( $ip );
                    if ( self::isValidIpAddress( $trimmed_ip ) ) {
                        return $trimmed_ip;
                    }
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Validate IP address
     *
     * @param string $ip_address IP address to validate
     * @return bool
     */
    protected static function isValidIpAddress( $ip_address ) {
        return filter_var(
            $ip_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get the HTTP User Agent
     *
     * @return string|null
     */
    protected static function getHttpUserAgent() {
        $user_agent = null;

        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        return $user_agent;
    }

    /**
     * Get event URI from event params or request
     *
     * @param SingleEvent $event The event
     * @return string
     */
    protected function getEventUri( $event ) {
        if ( isset( $event->params['uri'] ) ) {
            return $event->params['uri'];
        }

        $uri = self::getRequestUri( PYS()->getOption( 'enable_remove_source_url_params' ) );

        if ( isset( $_POST['url'] ) ) {
            $uri = $this->sanitizeUri( $_POST['url'] );
        }

        return $uri;
    }

    /**
     * Sanitize URI
     *
     * @param string $url URL to sanitize
     * @return string
     */
    protected function sanitizeUri( $url ) {
        if ( PYS()->getOption( 'enable_remove_source_url_params' ) ) {
            $list = explode( '?', esc_url_raw( $url ) );
            return is_array( $list ) && ! empty( $list ) ? $list[0] : esc_url_raw( $url );
        }
        return esc_url_raw( $url );
    }

    /**
     * Hash a value using SHA256
     *
     * @param string $value Value to hash
     * @return string
     */
    protected function hashValue( $value ) {
        return hash( 'sha256', mb_strtolower( $value ), false );
    }

    /**
     * Hash phone number (digits only)
     *
     * @param string $phone Phone number
     * @return string
     */
    protected function hashPhone( $phone ) {
        $digits = preg_replace( '/\D/', '', $phone );
        return hash( 'sha256', $digits, false );
    }

    /**
     * Send JSON response to browser and close connection, but continue PHP execution
     *
     * @param array $response The response data to send
     */
    private function sendResponseAndContinue($response) {
        if (headers_sent()) {
            return;
        }
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Prepare response
        $json = wp_json_encode($response);
        $length = strlen($json);

        // Prevent PHP from aborting when client disconnects
        ignore_user_abort(true);

        // Send headers to close connection
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . $length);
        header('Connection: close');

        // Send response body
        echo $json;

        // Flush output to browser
        if (function_exists('fastcgi_finish_request')) {
            // FastCGI: cleanly finish request and continue in background
            fastcgi_finish_request();
        } else {
            // Non-FastCGI fallback: flush buffers
            flush();
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
        }

        // Extend time limit for background processing
        if (!ini_get('safe_mode')) {
            @set_time_limit(60);
        }
    }
}

