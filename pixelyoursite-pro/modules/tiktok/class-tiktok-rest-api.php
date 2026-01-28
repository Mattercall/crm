<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * TikTok REST API handler
 *
 * Extends AbstractRestAPI to handle TikTok server events via REST API
 */
class TikTok_REST_API extends AbstractRestAPI {

    /**
     * Get the REST API namespace
     *
     * @return string
     */
    protected function getRestNamespace() {
        return 'pys-tiktok/v1';
    }

    /**
     * Get the server instance for TikTok
     *
     * @return TikTokServer
     */
    protected function getServerInstance() {
        return TikTokServer();
    }

    /**
     * Get the pixel instance for TikTok
     *
     * @return Tiktok
     */
    protected function getPixelInstance() {
        return Tiktok();
    }

    /**
     * Check if TikTok server API is enabled
     *
     * @return bool
     */
    protected function isServerApiEnabled() {
        return Tiktok()->isServerApiEnabled();
    }

    /**
     * Get the localized script variable name
     *
     * @return string
     */
    protected function getScriptVariableName() {
        return 'pysTikTokRest';
    }

    /**
     * Convert data to SingleEvent object
     * TikTok uses 'event_id' instead of 'eventID' in payload
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

        // Make sure params and ids are arrays
        if ( ! is_array( $params ) ) {
            $params = [];
        }

        if ( ! is_array( $ids ) ) {
            $ids = [];
        }

        // Process order IDs
        $woo_order_id = is_numeric( $woo_order ) ? (int) $woo_order : 0;
        $edd_order_id = is_numeric( $edd_order ) ? (int) $edd_order : 0;

        // TikTok uses 'event_id' instead of 'eventID'
        $payload = [
            'name'      => $event_name,
            'event_id'  => $event_id,
            'woo_order' => $woo_order_id,
            'edd_order' => $edd_order_id,
            'pixelIds'  => $ids,
        ];

        $single_event->addParams( $params );
        $single_event->addPayload( $payload );

        return $single_event;
    }
}

/**
 * Accessor function for TikTok REST API
 *
 * @return TikTok_REST_API
 */
function TikTok_REST_API() {
    static $instance = null;
    if ( $instance === null ) {
        $instance = new TikTok_REST_API();
    }
    return $instance;
}
