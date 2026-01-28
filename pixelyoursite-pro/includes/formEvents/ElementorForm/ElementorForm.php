<?php

namespace PixelYourSite;
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ElementorForm extends Settings implements FormEventsFactory {

	private static $_instance;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		parent::__construct( 'ElementorForm' );

		$this->locateOptions( PYS_PATH . '/includes/formEvents/options_fields.json', PYS_PATH . '/includes/formEvents/options_defaults.json' );

		if ( $this->isActivePlugin() ) {
			add_filter( "pys_form_event_factory", [
				$this,
				"register"
			] );
		}

		add_action( 'wp_ajax_pys_scan_elementor_form', array(
			$this,
			'scan_handler'
		) );
	}

	function register( $list ) {
		$list[] = $this;
		return $list;
	}

	public function getSlug() {
		return "elementor_form";
	}

	public function getName() {
		return "Elementor Form";
	}

	function isEnabled() {
		return $this->getOption( 'enabled' );
	}

	function isActivePlugin() {
		if ( !function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active( 'elementor/elementor.php' ) || is_plugin_active( 'elementor-pro/elementor-pro.php' );
	}

	function getForms() {
		return array();
	}

	function getOptions() {
		return array(
			"name"          => $this->getName(),
			"enabled"       => $this->getOption( "enabled" ),
			"form_ID_event" => $this->getOption( "form_ID_event" )
		);
	}

	function getDefaultMatchingInput() {
		return array(
			"first_name" => array(),
			"last_name"  => array(),
			"tel"        => array()
		);
	}

	/**
	 * @hook wp_ajax_pys_scan_elementor_form
	 * @return void
	 */
	public function scan_handler() {
		$nonce = $_REQUEST[ '_wpnonce' ] ?? null;
		wp_verify_nonce( $nonce, 'pys_update_event' );
		if ( !$this->isActivePlugin() ) {
			wp_send_json_error( 'Elementor is not active', 400 );
		} elseif ( !empty( $_POST[ 'urls' ] ) ) {
			$videos = $this->scan( $_POST[ 'urls' ] );
			wp_send_json_success( $videos, 200 );
		} else {
			wp_send_json_error( 'No URLs provided', 400 );
		}
	}

	/**
	 * Scan Elementor forms on pages
	 * @param $urls
	 * @return array
	 */
	public function scan( $urls ) {

		$form_identifiers = array();
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

					$dom = new \DOMDocument();
					libxml_use_internal_errors( true );
					$dom->loadHTML( $page_html );
					libxml_clear_errors();

					$xpath = new \DOMXPath( $dom );
					$forms = $xpath->query( "//form[contains(@class, 'elementor-form')]" );

					foreach ( $forms as $form ) {

						$form_id = $form->getAttribute( 'id' );
						$form_title = $form->getAttribute( 'name' );

						if ( empty( $form_id ) ) {
							$input = $xpath->query( ".//input[@type='hidden' and contains(@name, 'form_id')]", $form );
							if ( $input->length > 0 ) {
								$form_id = $input->item( 0 )->getAttribute( 'value' );
							}
						}

						if ( !empty( $form_id ) ) {
							$form_identifiers[] = array(
								'title' => $form_title,
								'id'    => $form_id,
							);
						}
					}
				}
			}
		}

		return $form_identifiers;
	}
}

/**
 * @return ElementorForm
 */
function ElementorForm() {
	return ElementorForm::instance();
}

ElementorForm();
