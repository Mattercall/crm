<?php
/**
 * Abstract Async Task Class
 *
 * Base class for all async task implementations (Facebook, TikTok, Pinterest)
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract class for async task handling
 */
abstract class AbstractAsyncTask extends \WP_Async_Task {

    /**
     * Get the server instance for this platform
     *
     * @return mixed Server instance (FacebookServer, TikTokServer, etc.)
     */
    abstract protected function getServerInstance();

    /**
     * Get allowed classes for unserialization
     *
     * @return array Array of allowed class names
     */
    protected function getAllowedClasses() {
        return [ 'stdClass' ];
    }

    /**
     * Prepare data for async processing
     *
     * @param array $data Data to prepare
     * @return array
     */
    protected function prepare_data( $data ) {
        try {
            if ( ! empty( $data ) ) {
                if ( empty( $this->_body_data ) ) {
                    return [ 'data' => base64_encode( serialize( $data ) ) ];
                } else {
                    $oldData = unserialize(
                        base64_decode( $this->_body_data['data'] ),
                        [ 'allowed_classes' => $this->getAllowedClasses() ]
                    );

                    // Check if $oldData[0] and $data[0] are arrays before merging
                    if ( is_array( $oldData[0] ) && is_array( $data[0] ) ) {
                        $data = [ 'data' => base64_encode( serialize( array_merge( $oldData[0], $data[0] ) ) ) ];
                    } else {
                        $data = [ 'data' => base64_encode( serialize( $data ) ) ];
                    }
                    return $data;
                }
            }
        } catch ( \Exception $ex ) {
            error_log( $ex );
        }

        return [];
    }

    /**
     * Run the async action
     */
    protected function run_action() {
        try {
            $data = unserialize(
                base64_decode( $_POST['data'] ),
                [ 'allowed_classes' => $this->getAllowedClasses() ]
            );
            $events = is_array( $data[0] ) ? $data[0] : $data;

            if ( empty( $events ) ) {
                return;
            }

            $server = $this->getServerInstance();
            foreach ( $events as $event ) {
                $server->sendEvent( $event['pixelIds'], $event['event'] );
            }
        } catch ( \Exception $ex ) {
            error_log( $ex );
        }
    }
}

