<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Facebook REST API handler
 *
 * Extends AbstractRestAPI to handle Facebook server events via REST API
 */
class Facebook_REST_API extends AbstractRestAPI {

    /**
     * Get the REST API namespace
     *
     * @return string
     */
    protected function getRestNamespace() {
        return 'pys-facebook/v1';
    }

    /**
     * Get the server instance for Facebook
     *
     * @return FacebookServer
     */
    protected function getServerInstance() {
        return FacebookServer();
    }

    /**
     * Get the pixel instance for Facebook
     *
     * @return Facebook
     */
    protected function getPixelInstance() {
        return Facebook();
    }

    /**
     * Check if Facebook server API is enabled
     *
     * @return bool
     */
    protected function isServerApiEnabled() {
        return Facebook()->isServerApiEnabled();
    }

    /**
     * Get the localized script variable name
     *
     * @return string
     */
    protected function getScriptVariableName() {
        return 'pysFacebookRest';
    }
}

/**
 * Accessor function for Facebook REST API
 *
 * @return Facebook_REST_API
 */
function Facebook_REST_API() {
    static $instance = null;
    if ( $instance === null ) {
        $instance = new Facebook_REST_API();
    }
    return $instance;
}
