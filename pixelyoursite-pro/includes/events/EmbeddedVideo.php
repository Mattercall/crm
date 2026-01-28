<?php

namespace PixelYourSite;
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EmbeddedVideo {

	private static $_instance;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static array $youtube_patterns = array(
		'/<iframe.*?src="(.*?youtube.com.*?)".*?>.*?<\/iframe>/i',
		'/<object.*?>.*?<embed.*?src="(https?:\/\/(?:www\.)?youtube\.com\/v\/[a-zA-Z0-9_-]{11}).*?".*?<\/object>/is',
		'/<embed.*?src="(.*?youtube.com.*?)".*?>/i'
	);

	public static array $vimeo_patterns = array(
		'/<iframe.*?src="(.*?vimeo.com.*?)".*?>.*?<\/iframe>/i',
		'/<object[^>]*data="(https:\/\/player\.vimeo\.com\/video\/\d+.*?)"[^>]*>.*?<\/object>/s',
		'/<embed.*?src="(.*?vimeo.com.*?)".*?>/i'
	);

	public static array $youtube_url_patterns = array(
		'/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i',
	);

	public static array $vimeo_url_patterns = array(
		'/video\/(\d+)/',
	);

	public function __construct() {

		add_action( 'wp_ajax_pys_scan_video', array(
			$this,
			'scan_video_handler'
		) );
		add_action( 'wp_ajax_nopriv_pys_scan_video', array(
			$this,
			'scan_video_handler'
		) );

	}

	/**
	 * @hook wp_ajax_pys_scan_video
	 * @return void
	 */
	public function scan_video_handler() {
		$nonce = $_REQUEST[ '_wpnonce' ] ?? null;
		wp_verify_nonce( $nonce, 'pys_update_event' );
		if ( !empty( $_POST[ 'urls' ] ) ) {
			$videos = $this->scan_video( $_POST[ 'urls' ] );
			wp_send_json_success( $videos, 200 );
		} else {
			wp_send_json_error( 'No URLs provided', 400 );
		}
	}

	/**
	 * Scan video on pages
	 * @param $urls
	 * @return array
	 */
	public function scan_video( $urls ) {

		$videos = array();
		if ( !empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$page_html = @file_get_contents( sanitize_url( $url ) );

				if ( $page_html === false ) {
					$curl = curl_init( sanitize_url( $url ) );
					curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 2 );
					curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
					curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36' );

					$page_html = curl_exec( $curl );
				}

				if ( $page_html ) {
					foreach ( self::$youtube_patterns as $pattern ) {
						if ( preg_match_all( $pattern, $page_html, $matches, PREG_SET_ORDER ) ) {
							foreach ( $matches as $match ) {
								$youtube_url = $match[ 1 ];
								foreach ( self::$youtube_url_patterns as $url_pattern ) {

									if ( preg_match( $url_pattern, $youtube_url, $matches_url ) ) {
										$videos[] = array(
											'type'  => 'youtube',
											'url'   => $youtube_url,
											'title' => $this->get_youtube_video_title( $matches_url[ 1 ] ),
											'id'    => $matches_url[ 1 ],
										);
									}
								}
							}
						}
					}

					foreach ( self::$vimeo_patterns as $pattern ) {
						if ( preg_match_all( $pattern, $page_html, $matches, PREG_SET_ORDER ) ) {
							foreach ( $matches as $match ) {
								$vimeo_url = $match[ 1 ];
								foreach ( self::$vimeo_url_patterns as $url_pattern ) {

									if ( preg_match( $url_pattern, $vimeo_url, $matches_url ) ) {
										$videos[] = array(
											'type'  => 'vimeo',
											'url'   => $vimeo_url,
											'title' => $this->get_vimeo_video_title( $matches_url[ 1 ] ),
											'id'    => $matches_url[ 1 ],
										);
									}
								}
							}
						}
					}

					if ( isElementorActive() ) {

						$dom = new \DOMDocument;
						libxml_use_internal_errors( true );
						$dom->loadHTML( $page_html );
						libxml_clear_errors();

						$finder = new \DomXPath( $dom );
						$nodes = $finder->query( "//div[contains(@class, 'elementor-widget-video')]" );

						foreach ( $nodes as $node ) {
							$data_settings_json = $node->getAttribute( 'data-settings' );
							$data_settings = json_decode( htmlspecialchars_decode( $data_settings_json ), true );

							if ( isset( $data_settings[ 'youtube_url' ] ) ) {
								$youtube_url = $data_settings[ 'youtube_url' ];

								foreach ( self::$youtube_url_patterns as $url_pattern ) {
									if ( preg_match( $url_pattern, $youtube_url, $matches_url ) ) {
										$videos[] = array(
											'type'  => 'youtube',
											'url'   => $youtube_url,
											'title' => $this->get_youtube_video_title( $matches_url[ 1 ] ),
											'id'    => $matches_url[ 1 ],
										);
									}
								}
							}
						}
					}
				}
			}
		}

		return $videos;
	}

	/**
	 * Get youtube video title
	 * @param $video_id
	 * @return array|string|string[]|null
	 */
	private function get_youtube_video_title( $video_id ) {
		$url = "https://www.youtube.com/watch?v={$video_id}";
		$html = file_get_contents( $url );

		if ( preg_match( '/<title>(.*?)<\/title>/i', $html, $matches ) ) {
			return str_replace( ' - YouTube', '', $matches[ 1 ] );
		} else {
			return null;
		}
	}

	/**
	 * Get vimeo video title
	 * @param $video_id
	 * @return mixed|null
	 */
	private function get_vimeo_video_title( $video_id ) {
		$apiUrl = "https://vimeo.com/api/v2/video/$video_id.json";

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $apiUrl );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		$response = curl_exec( $ch );
		curl_close( $ch );

		$data = json_decode( $response, true );

		return $data[ 0 ][ 'title' ] ?? null;
	}
}

/**
 * @return EmbeddedVideo
 */
function EmbeddedVideo() {
	return EmbeddedVideo::instance();
}

EmbeddedVideo();
