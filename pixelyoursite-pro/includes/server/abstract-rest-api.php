<?php
/**
 * Abstract REST API Class
 *
 * Base class for all REST API implementations (Facebook, TikTok, Pinterest)
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract class for REST API event handling
 */
abstract class AbstractRestAPI {

    /**
     * REST API namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Server instance
     *
     * @var mixed
     */
    protected $server_instance;

    /**
     * Get the REST API namespace
     *
     * @return string Namespace (e.g., 'pys-facebook/v1')
     */
    abstract protected function getRestNamespace();

    /**
     * Get the server instance for this platform
     *
     * @return mixed Server instance (FacebookServer, TikTokServer, etc.)
     */
    abstract protected function getServerInstance();

    /**
     * Get the pixel instance for this platform
     *
     * @return mixed Pixel instance (Facebook, TikTok, etc.)
     */
    abstract protected function getPixelInstance();

    /**
     * Check if server API is enabled for this platform
     *
     * @return bool
     */
    abstract protected function isServerApiEnabled();

    /**
     * Get the localized script variable name
     *
     * @return string Variable name (e.g., 'pysFacebookRest')
     */
    abstract protected function getScriptVariableName();

    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = $this->getRestNamespace();
        $this->server_instance = $this->getServerInstance();
        $this->init();
    }

    /**
     * Initialize hooks
     */
    protected function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/event',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_event' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => $this->get_event_args(),
            ]
        );
    }

    /**
     * Handle the event request
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_event( $request ) {
        $this->getPixelInstance()->getLog()->debug(
            'handleEvent send to ' . ucfirst( $this->getPlatformName() ) . ' server from REST API'  .
            ' event: ' . $request->get_param( 'event' ) .
            ' eventID: ' . $request->get_param( 'eventID' )
        );

        $event      = $request->get_param( 'event' );
        $data       = $request->get_param( 'data' ) ?: [];
        $ids        = $request->get_param( 'ids' );
        $event_id   = $request->get_param( 'eventID' );
        $woo_order  = $request->get_param( 'woo_order' );
        $edd_order  = $request->get_param( 'edd_order' );
        $event_slug = $request->get_param( 'event_slug' );

        if ( empty( $event ) || empty( $ids ) ) {
            return new \WP_Error(
                'missing_parameters',
                'Missing mandatory parameters: event and ids',
                [ 'status' => 400 ]
            );
        }

        if ( $event === 'hCR' ) {
            $event = 'CompleteRegistration';
        }

        $single_event = $this->data_to_single_event(
            $event, $data, $event_id, $ids, $woo_order, $edd_order, $event_slug
        );
        if($this->server_instance->isQueueEnabled() && PYS()->getOption("queue_use_for_ajax")) {
            $this->server_instance->sendEventsToQueue( [ $single_event ] );
        }
        else {
            $this->server_instance->sendEventsNow( [ $single_event ] );
        }

        return new \WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * Get platform name for logging
     *
     * @return string
     */
    protected function getPlatformName() {
        return str_replace( 'pys-', '', explode( '/', $this->namespace )[0] );
    }

    /**
     * Check access permissions
     *
     * @param \WP_REST_Request $request The request object
     * @return bool
     */
    public function check_permission( $request ) {
        return true;
    }

    /**
     * Get arguments for the event endpoint
     *
     * @return array
     */
    protected function get_event_args() {
        return [
            'event'      => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'data'       => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_data' ],
            ],
            'ids'        => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_ids' ],
            ],
            'eventID'    => [
                'required' => false,
                'type'     => 'string',
            ],
            'woo_order'  => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_order_id' ],
            ],
            'edd_order'  => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_order_id' ],
            ],
            'event_slug' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Sanitize event data
     *
     * @param mixed $data Data to sanitize
     * @return array
     */
    public function sanitize_data( $data ) {
        if ( is_string( $data ) ) {
            $decoded = json_decode( $data, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        if ( is_array( $data ) ) {
            return $data;
        }
        return [];
    }

    /**
     * Sanitize order ID
     *
     * @param mixed $order_id Order ID to sanitize
     * @return int
     */
    public function sanitize_order_id( $order_id ) {
        if ( is_string( $order_id ) ) {
            $order_id = trim( $order_id );
            if ( empty( $order_id ) || $order_id === 'null' || $order_id === 'undefined' ) {
                return 0;
            }
            return (int) $order_id;
        }
        if ( is_numeric( $order_id ) ) {
            return (int) $order_id;
        }
        return 0;
    }

    /**
     * Sanitize IDs
     *
     * @param mixed $ids IDs to sanitize
     * @return array
     */
    public function sanitize_ids( $ids ) {
        if ( is_string( $ids ) ) {
            $decoded = json_decode( $ids, true );
            if ( is_array( $decoded ) ) {
                return array_map( 'sanitize_text_field', $decoded );
            }
            $ids_array = explode( ',', $ids );
            return array_map( 'sanitize_text_field', $ids_array );
        }
        if ( is_array( $ids ) ) {
            return array_map( 'sanitize_text_field', $ids );
        }
        return [];
    }

    /**
     * Convert data to SingleEvent object
     *
     * @param string   $event_name Event name
     * @param array    $params     Event parameters
     * @param string   $event_id   Event ID
     * @param array    $ids        Pixel IDs
     * @param int|null $woo_order  WooCommerce order ID
     * @param int|null $edd_order  EDD order ID
     * @param string   $event_slug Event slug
     * @return SingleEvent
     */
    protected function data_to_single_event( $event_name, $params, $event_id, $ids, $woo_order, $edd_order, $event_slug = '' ) {
        $single_event = new SingleEvent( $event_slug, '' );
        if ( ! is_array( $params ) ) {
            $params = [];
        }
        if ( ! is_array( $ids ) ) {
            $ids = [];
        }
        $woo_order_id = is_numeric( $woo_order ) ? (int) $woo_order : 0;
        $edd_order_id = is_numeric( $edd_order ) ? (int) $edd_order : 0;
        $payload = [
            'name'      => $event_name,
            'eventID'   => $event_id,
            'woo_order' => $woo_order_id,
            'edd_order' => $edd_order_id,
            'pixelIds'  => $ids,
        ];
        $single_event->addParams( $params );
        $single_event->addPayload( $payload );
        return $single_event;
    }

    /**
     * Enqueue scripts for frontend
     */
    public function enqueue_scripts() {
        wp_localize_script(
            'jquery',
            $this->getScriptVariableName(),
            [
                'restApiUrl' => rest_url( $this->namespace . '/event' ),
                'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ]
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        wp_localize_script(
            'jquery',
            $this->getScriptVariableName(),
            [
                'restApiUrl' => rest_url( $this->namespace . '/event' ),
                'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ]
        );
    }
}
