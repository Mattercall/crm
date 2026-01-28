<?php
namespace PixelYourSite;

defined('ABSPATH') or die('Direct access not allowed');

/**
 * Facebook Async Task Handler
 *
 * Extends AbstractAsyncTask to handle Facebook server events asynchronously
 */
class FacebookAsyncTask extends AbstractAsyncTask {

    /**
     * Action name for this async task
     *
     * @var string
     */
    protected $action = 'pys_send_server_event';

    /**
     * Get the server instance for Facebook
     *
     * @return FacebookServer
     */
    protected function getServerInstance() {
        return FacebookServer();
    }

    /**
     * Get allowed classes for unserialization
     * Facebook uses specific SDK classes that need to be allowed
     *
     * @return array Array of allowed class names
     */
    protected function getAllowedClasses() {
        return [
            'stdClass',
            'PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Event',
            'PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\UserData',
            'PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\CustomData',
            'PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Content'
        ];
    }
}