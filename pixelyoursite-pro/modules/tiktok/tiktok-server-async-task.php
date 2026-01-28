<?php
namespace PixelYourSite;

defined('ABSPATH') or die('Direct access not allowed');

/**
 * TikTok Async Task Handler
 *
 * Extends AbstractAsyncTask to handle TikTok server events asynchronously
 */
class TikTokAsyncTask extends AbstractAsyncTask {

	/**
	 * Action name for this async task
	 *
	 * @var string
	 */
	protected $action = 'pys_send_tiktok_server_event';

	/**
	 * Get the server instance for TikTok
	 *
	 * @return TikTokServer
	 */
	protected function getServerInstance() {
		return TikTokServer();
	}
}