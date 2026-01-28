<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Bing extends Settings implements Pixel {

	private static $_instance = null;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	public function __construct() {
        add_action( 'pys_admin_pixel_ids', array( $this, 'renderPixelIdField' ), 15 );
	}
	
	public function enabled() {
		return false;
	}
	
	public function configured() {
		return false;
	}
	
	public function getPixelIDs() {
		return array();
	}
	
	public function getPixelOptions() {
	    return array();
    }
    
    public function getEventData( $eventType, $args = null ) {
	    return false;
    }
    public function addParamsToEvent(&$event) {
	    return false;
    }
	
	public function outputNoScriptEvents() {}

	public function render_switcher_input( $key, $collapse = false, $disabled = false,  $type = 'secondary' ) {

		$attr_id = 'pys_bing_' . $key;

		?>

		<div class="custom-switch disabled">
			<input type="checkbox" value="1" disabled="disabled"
			       id="<?php echo esc_attr( $attr_id ); ?>" class="custom-switch-input">
			<label class="custom-switch-btn" for="<?php echo esc_attr( $attr_id ); ?>"></label>
		</div>

		<?php
	}

	public function renderCustomEventOptions( $event ) {}

	public function renderAddonNotice() {
	    echo '&nbsp;<a href="https://www.pixelyoursite.com/bing-tag" target="_blank" class="badge badge-pill badge-secondary link">The paid add-on is required</a>';
    }

	public function renderPixelIdField() {
		?>

        <div class="line"></div>

        <div class="d-flex pixel-wrap align-items-center justify-content-between">
            <div class="pixel-heading d-flex justify-content-start align-items-center">
                <img class="tag-logo" src="<?php echo PYS_URL; ?>/dist/images/bing-logo.svg" alt="bing-logo">
                <h4 class="secondary_heading">Add the Bing tag with our <br><a
                            href="https://www.pixelyoursite.com/bing-tag?utm_source=pixelyoursite-pro-plugin&utm_medium=plugin&utm_campaign=pro-plugin-bing"
                            target="_blank" class="link link-underline">Paid add-on.</a></h4>
            </div>
        </div>

		<?php
	}
}

/**
 * @return Bing
 */
function Bing() {
	return Bing::instance();
}

Bing();