<?php

namespace PixelYourSite\Events;

use PixelYourSite;
use PixelYourSite\CustomEvent;
use function PixelYourSite\Ads;
use function PixelYourSite\GA;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderHiddenInput( &$event, $key ) {

	$attr_name = "pys[event][$key]";
	$attr_value = $event->$key;

	?>

	<input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>"
	       value="<?php echo esc_attr( $attr_value ); ?>">

	<?php

}

/**
 * @param CustomEvent $event
 * @param string $key
 * @param string $placeholder
 */
function renderTextInput( &$event, $key, $placeholder = '' ) {

	$attr_name = "pys[event][$key]";
	$attr_id = 'pys_event_' . $key;
	$attr_value = $event->$key;

	?>

    <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           placeholder="<?php echo esc_attr( $placeholder ); ?>"
           class="input-standard">
	<?php
}

/**
 * @param CustomEvent $event
 * @param string $key
 * @param string $placeholder
 */
function renderNumberInput( &$event, $key, $placeholder = null, $default = null ) {

	$attr_name = "pys[event][$key]";
	$attr_id = 'pys_event_' . $key;
	$attr_value = $event->$key;

	?>

    <div class="input-number-wrapper">
        <button class="decrease"><i class="icon-minus"></i></button>
        <input type="number" name="<?php echo esc_attr( $attr_name ); ?>"
               id="<?php echo esc_attr( $attr_id ); ?>"
               value="<?php echo !empty( $attr_value ) ? esc_attr($attr_value) : esc_attr($default) ; ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               min="0"
        >
        <button class="increase"><i class="icon-plus"></i></button>
    </div>

	<?php
}

/**
 * @param $trigger
 * @param string $key
 * @param null $placeholder
 * @param null $default
 */
function renderTriggerNumberInput( $trigger, $key, $placeholder = null, $default = null ) {

	$i = $trigger->getTriggerIndex();
	$attr_name = "pys[event][triggers][$i][$key]";
	$attr_id = 'pys_event_' . $i . '_' . $key;
	$attr_value = $trigger->getParam( $key );

	?>

    <div class="input-number-wrapper">
        <button class="decrease"><i class="icon-minus"></i></button>
        <input type="number" name="<?php echo esc_attr( $attr_name ); ?>"
               id="<?php echo esc_attr( $attr_id ); ?>"
               value="<?php echo !empty( $attr_value ) ? esc_attr( $attr_value ) : esc_attr( $default ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               min="0">
        <button class="increase"><i class="icon-plus"></i></button>
    </div>

	<?php
}

/**
 * @param $trigger
 * @param $label
 * @param $key
 * @param $value
 * @param null $placeholder
 * @param null $default
 * @return void
 */
function renderTriggerNumberInputPercent( $trigger, $label, $key, $value, $placeholder = null, $default = null ) {

	$i = $trigger->getTriggerIndex();
	$attr_name = "pys[event][triggers][$i][$label][$key][value]";
	$attr_id = 'pys_event_' . $i . '_' . $key;

	?>

    <div class="input-number-wrapper input-number-wrapper-percent">
        <button class="decrease"><i class="icon-minus"></i></button>
        <input type="number" name="<?php echo esc_attr( $attr_name ); ?>"
               id="<?php echo esc_attr( $attr_id ); ?>"
               value="<?php echo (int) !empty( $value ) ? esc_attr( $value ) : esc_attr( $default ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               min="0"
               max="100"
               step="1"
        >
        <button class="increase"><i class="icon-plus"></i></button>
    </div>

	<?php
}

/**
 * @param CustomEvent $event
 * @param string $key
 */
function renderSwitcherInput( &$event, $key ) {

	$disabled = false;

	$attr_name = "pys[event][$key]";
	$attr_id = 'pys_event_' . $key;
	$attr_value = $event->$key;

	$classes = array( 'secondary-switch' );

	if ( $disabled ) {
		$attr_value = false;
		$classes[] = 'disabled';
	}

	$classes = implode( ' ', $classes );

	?>

    <div class="<?php echo esc_attr( $classes ); ?>">

		<?php if ( !$disabled ) : ?>
            <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
		<?php endif; ?>

        <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>"
               value="1" <?php checked( $attr_value, true ); ?> <?php disabled( $disabled, true ); ?>
               id="<?php echo esc_attr( $attr_id ); ?>" class="custom-switch-input">
        <label class="custom-switch-btn" for="<?php echo esc_attr( $attr_id ); ?>"></label>
    </div>

	<?php
}

/**
 * Output radio input
 *
 * @param      $key
 * @param      $value
 * @param      $label
 * @param bool $disabled
 */
function render_radio_input( &$event, $key, $value, $label, $disabled = false, $with_pro_badge = false ) {
    $id = $key . "_" . rand( 1, 1000000 );
    $attr_name = "pys[event][$key]";
    $attr_value = $event->$key;

    ?>
    <div class="radio-standard">
        <input type="radio"
               name="<?php echo esc_attr( $attr_name ); ?>"
                <?php disabled( $disabled, true ); ?>
               class="custom-control-input"
               id="<?php echo esc_attr( $id ); ?>"
                <?php checked( $attr_value, $value ); ?>
               value="<?php echo esc_attr( $value ); ?>">
        <label class="standard-control radio-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
            <span class="standard-control-indicator"></span>
            <span class="standard-control-description"><?php echo wp_kses_post( $label ); ?></span>
            <?php if ( $with_pro_badge ) {
                renderCogBadge();
            } ?>
        </label>
    </div>
    <?php

}

/**
 * Output checkbox input
 *
 * @param $event
 * @param      $key
 * @param      $label
 * @param bool $disabled
 */
function render_checkbox_input( &$event, $key, $label, $disabled = false ) {

    $attr_name  = "pys[event][$key]";
    $attr_value = $event->$key;

    $classes = array( 'custom-control', 'custom-checkbox' );

    if ( $disabled ) {
        $attr_value = false;
        $classes[] = 'disabled';
    }

    $classes = implode( ' ', $classes );

    ?>

    <label class="<?php echo esc_attr( $classes ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
        <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>" value="1"
               class="custom-control-input" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value,
            true ); ?> >
        <span class="custom-control-indicator"></span>
        <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
    </label>

    <?php

}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderSwitcherTriggerFormInput( $trigger, $plugin) {

    $i = $trigger->getTriggerIndex();
    $disabled = false;
    $key = $plugin->getSlug();
    $disabled_form_action = $trigger->getParam( 'disabled_form_action' );
    $attr_name = "pys[event][triggers][$i][".$key."][disabled_form_action]";
    $attr_id = 'pys_event_' . $i . '_' . $key . '_disabled_form_action';

    $classes = array( 'secondary-switch' );

    if ( $disabled ) {
        $classes[] = 'disabled';
    }

    $classes = implode( ' ', $classes );
    ?>

    <div class="<?php echo esc_attr( $classes ); ?>">

        <?php if ( ! $disabled ) : ?>
            <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
        <?php endif; ?>

        <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>" value="1" <?php checked( $disabled_form_action,
            true ); ?> <?php disabled( $disabled, true ); ?>
               id="<?php echo esc_attr( $attr_id ); ?>" class="custom-switch-input">
        <label class="custom-switch-btn" for="<?php echo esc_attr( $attr_id ); ?>"></label>
    </div>

    <?php

}

function renderSwitcherTriggerInput( $trigger, $key, $disabled = false, $classes = array() ) {

	$i = $trigger->getTriggerIndex();
	$value = $trigger->getParam( $key );
	$attr_name = "pys[event][triggers][$i][$key]";
	$attr_id = 'pys_event_' . $i . '_' . $key;

	$classes[] = 'secondary-switch';

	if ( $disabled ) {
		$classes[] = 'disabled';
	}

	$classes = implode( ' ', $classes );
	?>

    <div class="<?php echo esc_attr( $classes ); ?>">

		<?php if ( ! $disabled ) : ?>
            <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
		<?php endif; ?>

        <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>" value="1" <?php checked( $value,
			true ); ?> <?php disabled( $disabled, true ); ?>
               id="<?php echo esc_attr( $attr_id ); ?>" class="custom-switch-input">
        <label class="custom-switch-btn" for="<?php echo esc_attr( $attr_id ); ?>"></label>
    </div>

	<?php

}

/**
 * @param CustomEvent $event
 * @param string      $key
 * @param array       $options
 */
function renderSelectInput( &$event, $key, $options, $full_width = false ,$classes = '') {

	if ( $key == 'currency' ) {
		
		$attr_name  = "pys[event][facebook_params][$key]";
		$attr_id    = 'pys_event_facebook_params_' . $key;
		$attr_value = $event->getFacebookParam( $key );
        
	} else {

		$attr_name  = "pys[event][$key]";
		$attr_id    = 'pys_event_' . $key;
		$attr_value = $event->$key;
    }

	$attr_width = $full_width ? 'width: 100%;' : '';

	?>
    <div class="select-standard-wrap">
        <select class="select-standard <?=$classes?>" id="<?php echo esc_attr( $attr_id ); ?>"
                name="<?php echo esc_attr( $attr_name ); ?>" autocomplete="off" style="<?php echo esc_attr( $attr_width ); ?>">
            <?php foreach ( $options as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key,
                    esc_attr( $attr_value ) ); ?> <?php disabled( $option_key,
                    'disabled' ); ?>><?php echo esc_attr( $option_value ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

	<?php
}

/**
 * @param $trigger
 * @param string $key
 * @param array $options
 * @param bool $full_width
 * @param string $classes
 * @param string $select_type
 */
function renderTriggerSelectInput( $trigger, $key, $options, $full_width = false, $classes = '', $select_type = 'standard' ) {
	$i = $trigger->getTriggerIndex();
	$attr_name = "pys[event][triggers][$i][$key]";
	$attr_id = 'pys_event_' . $i . '_' . $key;
	$attr_value = $trigger->getParam( $key );

	$attr_width = $full_width ? 'width: 100%;' : '';

	?>
    <div class="select-<?php echo esc_attr( $select_type ); ?>-wrap">
        <select class="select-<?php echo esc_attr( $select_type ); ?> <?= $classes ?>"
                id="<?php echo esc_attr( $attr_id ); ?>"
                name="<?php echo esc_attr( $attr_name ); ?>" autocomplete="off"
                style="<?php echo esc_attr( $attr_width ); ?>">
			<?php foreach ( $options as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $attr_value ) ); ?> <?php disabled( $option_key, 'disabled' ); ?>><?php echo esc_attr( $option_value ); ?></option>
			<?php endforeach; ?>
        </select>
    </div>

	<?php
}

/**
 * @param CustomEvent $event
 * @param string $key
 * @param $groups
 * @param bool $full_width
 * @param string $classes
 */
function renderGroupSelectInput( &$event, $key, $groups, $full_width = false, $classes = '' ) {

	$attr_name = "pys[event][$key]";
	$attr_id = 'pys_event_' . $key;
	$attr_value = $event->$key;
    $group_key = $key . '_group';

	$attr_width = $full_width ? 'width: 100%;' : '';

	?>
    <input type="hidden" name="pys[event][<?php echo esc_attr( $group_key ); ?>]"
           value="<?php echo esc_attr( $event->$group_key ?? '' ); ?>" id="<?php echo esc_attr( $group_key ); ?>">

    <div class="select-standard-wrap">
        <select class="select-standard <?= $classes ?>" id="<?php echo esc_attr( $attr_id ); ?>"
                name="<?php echo esc_attr( $attr_name ); ?>" autocomplete="off"
                style="<?php echo esc_attr( $attr_width ); ?>">

			<?php foreach ( $groups as $group => $options ) : ?>
                <optgroup label="<?= $group ?>">
					<?php foreach ( $options as $option_key => $option_value ) :
						$selected_group = $event->$group_key ?? $group;
						?>
                        <option group="<?= $group ?>"
                                value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $selected_group == $group && $option_key == $attr_value ); ?> <?php disabled( $option_key, 'disabled' ); ?>><?php echo esc_attr( $option_key ); ?></option>
					<?php endforeach; ?>
                </optgroup>
			<?php endforeach; ?>
        </select>
    </div>

	<?php

    }

function render_multi_select_trigger_form_input( $trigger, $plugin, $disabled = false, $placeholder = "", $pysselect2 = true, $classes = '' ) {
	$key = $plugin->getSlug();

	if ( $key === 'elementor_form' ) {
		$data = $trigger->getElementorFormData();
		$values = !empty( $data ) ? array_merge( ...array_map( function ( $item ) {
			return array( $item[ 'id' ] => $item[ 'title' ] );
		}, $data ) ) : array();
	} else {
		$values = $plugin->getForms();
	}

	$forms = $trigger->getForms();

	$i = $trigger->getTriggerIndex();
	$attr_name = "pys[event][triggers][$i][" . $key . "][forms][]";
	$attr_id = 'pys_event_' . $i . '_' . $key;

	?>

    <select class="trigger_form_select <?php echo esc_attr( $classes ); ?> <?php echo $pysselect2 ? "pys-pysselect2" : "" ?>"
            data-placeholder="<?= $placeholder ?>"
            name="<?php echo esc_attr( $attr_name ); ?>"
            id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
            multiple>
		<?php foreach ( $values as $option_key => $option_value ) : ?>
            <option value="<?php echo esc_attr( $option_key ); ?>"
				<?php selected( in_array( $option_key, $forms ) ); ?>
				<?php disabled( $option_key, 'disabled' ); ?>
            >
				<?php echo esc_attr( $option_value ) . ' - ID ' . esc_attr( $option_key ); ?>
            </option>
		<?php endforeach; ?>

    </select>

	<?php
}

function render_select_trigger_any_form_input( $trigger, $disabled = false, $placeholder = "", $pysselect2 = true, $classes = '' ) {
    $key = 'form_field';

    $i = $trigger->getTriggerIndex();
    $attr_name = "pys[event][triggers][$i][" . $key . "][form]";
    $attr_id = 'pys_event_' . $i . '_' . $key.'_form';

    $values = $trigger->getAllFormsAnyForms();
    $form = $trigger->getAnyForm();
    ?>

    <select class="trigger_form_select <?php echo esc_attr( $classes ); ?> <?php echo $pysselect2 ? "pys-pysselect2" : "" ?>"
            data-placeholder="<?= $placeholder ?>"
            name="<?php echo esc_attr( $attr_name ); ?>"
            id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;">

            <?php foreach ( $values as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_value['selector'] ); ?>" data-selector="<?php echo esc_attr( $option_value['selector'] ); ?>"
                    <?php selected($option_value['selector'], $form); ?>
                >
                    <?php echo esc_attr( $option_value['title'] ); ?>
                </option>
            <?php endforeach; ?>
    </select>

    <?php
}

function render_select_trigger_field_input($trigger, $disabled = false, $placeholder = "", $pysselect2 = true, $classes = '')
{
    $key = 'form_field';

    $i = $trigger->getTriggerIndex();
    $attr_name = "pys[event][triggers][$i][" . $key . "][field]";
    $attr_id = 'pys_event_' . $i . '_' . $key. '_field';

    $values = $trigger->getAllFieldsAnyForms();
    $field = $trigger->getAnyFormField();
    ?>

    <select class="trigger_field_select <?php echo esc_attr( $classes ); ?> <?php echo $pysselect2 ? "pys-pysselect2" : "" ?>"
            data-placeholder="<?= $placeholder ?>"
            name="<?php echo esc_attr( $attr_name ); ?>"
            id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;">
        <?php foreach ( $values as $option_key => $option_value ) : ?>
            <option value="<?php echo esc_attr( $option_value['selector'] ); ?>"
                    data-selector="<?php echo esc_attr( $option_value['selector'] ); ?>"
                    data-type="<?php echo esc_attr( $option_value['type'] ); ?>"
                    data-id="<?php echo esc_attr( $option_value['id'] ); ?>"
                <?php selected($option_value['selector'], $field); ?>
            >
                <?php echo 'Name: '.esc_attr( $option_value['name'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php
}

function render_multi_select_input( &$event, $key, $options, $disabled = false, $placeholder = "" ) {

	$attr_name = "pys[event][" . $key . "][]";
	$attr_id = 'pys_' . $key . '_event';
	$attr_value = (array) $event->$key;
	?>

    <div class="select-standard-wrap">
        <select class="pys-pysselect2 select-standard"
                data-placeholder="<?= $placeholder ?>"
                name="<?php echo esc_attr( $attr_name ); ?>"
                id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>
			<?php foreach ( $options as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>"
					<?php selected( in_array( $option_key, $attr_value ) ); ?>
					<?php disabled( $option_key, 'disabled' ); ?>
                >
					<?php echo esc_attr( $option_value ); ?>
                </option>
			<?php endforeach; ?>

        </select>
    </div>

	<?php
}

function render_multi_select_trigger_input( $trigger, $key, $options, $selected, $disabled = false, $placeholder = "", $classes = '', $pysselect2 = true ) {


	$i = $trigger->getTriggerIndex();
	$attr_name = "pys[event][triggers][$i][" . $key . "][]";
	$attr_id = 'pys_event_' . $i . '_' . $key;

	if ( !empty( $classes ) ) {
		$classes = ' ' . $classes;
	}
    $classes .= $pysselect2 ? ' pys-pysselect2' : '';
	?>

    <select class="<?php echo esc_attr( $classes ); ?>"
            data-placeholder="<?= $placeholder ?>"
            name="<?php echo esc_attr( $attr_name ); ?>"
            id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
            multiple>
		<?php foreach ( $options as $option_key => $option_value ) : ?>
            <option value="<?php echo esc_attr( $option_key ); ?>"
				<?php selected( in_array( $option_key, $selected ) ); ?>
				<?php disabled( $option_key, 'disabled' ); ?>
            >
				<?php echo esc_attr( $option_value ); ?>
            </option>
		<?php endforeach; ?>
    </select>

	<?php
}

function render_merged_multi_select_input(&$event, $key, $options, $disabled = false ,$placeholder = "") {

    $attr_name = "pys[event][".$key."][]";
    $attr_id = 'pys_' . $key . '_event';
    if($event->google_ads_enabled && $event->google_ads_conversion_id){
        $attr_value = array_merge($event->ga_pixel_id,$event->google_ads_conversion_id);
    }
    else{
        $attr_value = $event->ga_pixel_id;
    }
    ?>

    <select class="pys-pysselect2"
            data-placeholder="<?=$placeholder?>"
            name="<?php echo esc_attr( $attr_name ); ?>"
            id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
            multiple>
        <?php foreach ( $options as $option_key => $option_value ) : ?>
            <option value="<?php echo esc_attr( $option_key ); ?>"
                <?php selected(  in_array($option_key, $attr_value)  ); ?>
                <?php disabled( $option_key, 'disabled' ); ?>
            >
                <?php echo esc_attr( $option_value ); ?>
            </option>
        <?php endforeach; ?>

    </select>

    <?php
}

function renderTriggerConditionalNumberPage( $trigger, $key ) {

	$options = array(
		'equal'           => '=',
		'equal_or_larger' => '>=',
		'equal_or_less'   => '<=',
		'larger'          => '>',
		'less'            => '<',
	);

	renderTriggerSelectInput( $trigger, $key, $options, false, '', 'short' );
}

/**
 * @param CustomEvent $event
 * @param string $key
 */
function renderTriggerTypeInput( $trigger, $key ) {

	$options = array(
		'page_visit'        => 'Page visit',
        'home_page'         => 'Home page',
        'add_to_cart'       =>  'WooCommerce add to cart',
        'purchase'          => 'WooCommerce purchase',
		'number_page_visit' => 'Number of Page Visits',
		'url_click'         => 'Click on HTML link',
		'css_click'         => 'Click on CSS selector',
		'css_mouseover'     => 'Mouse over CSS selector',
		'scroll_pos'        => 'Page Scroll',
		'post_type'         => 'Post type',
		'video_view'        => 'Embedded Video View',
		'email_link'        => 'Email Link',
        'form_field'        => 'Filling out a form field',
		//Default event fires
	);

	$eventsFormFactory = apply_filters( "pys_form_event_factory", [] );
	foreach ( $eventsFormFactory as $activeFormPlugin ) :
		$options[ $activeFormPlugin->getSlug() ] = $activeFormPlugin->getName();
	endforeach;

	asort( $options );

	renderTriggerSelectInput( $trigger, $key, $options, false, 'pys_event_trigger_type' );
}

function renderPostTypeSelect( $trigger, $key ) {
	$types = get_post_types( null, "objects" );

	$options = array();
	foreach ( $types as $type ) {
		$options[ $type->name ] = $type->label . ' (' . $type->name . ')';
	}

	renderTriggerSelectInput( $trigger, $key, $options );
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderCurrencyParamInput( &$event, $key ) {

	//@since: 7.0.7
    $currencies = apply_filters( 'pys_currencies_list', CustomEvent::$currencies );
	
	$options['']         = 'Please, select...';
	$options             = array_merge( $options, $currencies );
	$options['custom']   = 'Custom currency';

	renderSelectInput( $event, $key, $options, true );
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderFacebookEventTypeInput( &$event, $key ) {

	$options = array(
		'AddPaymentInfo'       => 'AddPaymentInfo',
		'AddToCart'            => 'AddToCart',
		'AddToWishlist'        => 'AddToWishlist',
		'CompleteRegistration' => 'CompleteRegistration',
		'Contact'              => 'Contact',
		'CustomizeProduct'     => 'CustomizeProduct',
		'CustomEvent'          => 'CustomEvent',
		'Donate'               => 'Donate',
		'FindLocation'         => 'FindLocation',
		'InitiateCheckout'     => 'InitiateCheckout',
		'Lead'                 => 'Lead',
		'Purchase'             => 'Purchase',
		'Schedule'             => 'Schedule',
		'StartTrial'           => 'StartTrial',
		'SubmitApplication'    => 'SubmitApplication',
		'Subscribe'            => 'Subscribe',
		'ViewContent'          => 'ViewContent',
	);

	renderSelectInput( $event, $key, $options );
}

/**
 * @param CustomEvent $event
 * @param string $key
 */
function renderTikTokEventTypeInput( &$event, $key ) {

	$attr_name = "pys[event][$key]";
	$attr_id = 'pys_event_' . $key;
	$attr_value = esc_attr( $event->$key );

	?>
    <div class="select-standard-wrap">
        <select id="<?php echo esc_attr( $attr_id ); ?>" name="<?php echo esc_attr( $attr_name ); ?>" autocomplete="off"
                class="select-standard">
			<?php foreach ( CustomEvent::$tikTokEvents as $option_key => $option_value ) :
				$value = esc_attr( $option_key ); ?>

                <option data-fields='<?= json_encode( $option_value ) ?>'
                        value="<?= $value ?>" <?php selected( $value, $attr_value ); ?> >
					<?= $value ?>
                </option>

			<?php endforeach; ?>
        </select>
    </div>
	<?php
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderTikTokEventId( &$event, $key ) {
    $options = array(
        'all'          => 'All pixels',
    );
    $mainPixels = PixelYourSite\Tiktok()->getPixelIDs();

    foreach ($mainPixels as $mainPixel) {
        $options[$mainPixel] = $mainPixel.'(global)';
    }

    renderSelectInput( $event, $key, $options );
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderFacebookEventId( &$event, $key ) {
    $options = array(
        'all'          => 'All pixels',
    );
    $mainPixels = PixelYourSite\Facebook()->getPixelIDs();
    foreach ($mainPixels as $mainPixel) {
        $options[$mainPixel] = $mainPixel.'(global)';
    }
    if(PixelYourSite\isSuperPackActive('3.0.0')){
        $additionalPixels = PixelYourSite\SuperPack()->getFbAdditionalPixel();
        foreach ($additionalPixels as $aPixel) {
            $options[$aPixel->pixel] = $aPixel->pixel.'(conditional)';
        }
    }
    render_multi_select_input( $event, $key, $options );
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderMergedGaEventId( &$event, $key) {
    $options = array(
        'all'          => 'All pixels',
    );
    $mainPixels = GA()->getPixelIDs();
    $mainPixelsGAds = Ads()->getPixelIDs();
    $mainPixels = array_merge($mainPixels, $mainPixelsGAds);

    foreach ($mainPixels as $mainPixel) {
        if(strpos($mainPixel, 'UA-') === false){
            $options[$mainPixel] = $mainPixel.' (global)';
        }
        else{
            $options[$mainPixel] = $mainPixel.' (not supported)';
        }
    }
    if(PixelYourSite\isSuperPackActive('3.0.0')){
        $additionalPixels = PixelYourSite\SuperPack()->getGaAdditionalPixel();
        $additionalPixelsGAds = PixelYourSite\SuperPack()->getAdsAdditionalPixel();
        $additionalPixels = array_merge($additionalPixels,$additionalPixelsGAds);
        foreach ($additionalPixels as $aPixel) {
            if(strpos($aPixel->pixel, 'UA-') === false){
                $options[$aPixel->pixel] = $aPixel->pixel.' (conditional)';
            }
            else{
                $options[$aPixel->pixel] = $aPixel->pixel.' (not supported)';
            }
        }
    }
    render_multi_select_input( $event, $key, $options );

}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGaEventId( &$event, $key) {
    $options = array(
        'all'          => 'All pixels',
    );
    $mainPixels = PixelYourSite\GA()->getPixelIDs();
    foreach ($mainPixels as $mainPixel) {
        if(strpos($mainPixel, 'UA-') === false){
            $options[$mainPixel] = $mainPixel.' (global)';
        }
        else{
            $options[$mainPixel] = $mainPixel.' (not supported)';
        }

    }
    if(PixelYourSite\isSuperPackActive('3.0.0')){
        $additionalPixels = PixelYourSite\SuperPack()->getGaAdditionalPixel();

        foreach ($additionalPixels as $aPixel) {
            if(strpos($aPixel->pixel, 'UA-') === false){
                $options[$aPixel->pixel] = $aPixel->pixel.' (conditional)';
            }
            else{
                $options[$aPixel->pixel] = $aPixel->pixel.' (not supported)';
            }
        }
    }
    render_multi_select_input( $event, $key, $options );

}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGTMEventId( &$event, $key) {
    $options = array();
    $mainPixels = PixelYourSite\GTM()->getPixelIDs();
    foreach ($mainPixels as $mainPixel) {
	    if (strpos($mainPixel, 'GTM-') === 0 && strpos($mainPixel, 'GTM-') !== false) {
            $options[$mainPixel] = $mainPixel.' (global)';
        }
        else{
            $options[$mainPixel] = $mainPixel.' (not supported)';
        }

    }

    render_multi_select_input( $event, $key, $options );

}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderBingEventId( &$event, $key ) {
    $options = array(
        'all'          => 'All pixels',
    );
    $mainPixels = PixelYourSite\Bing()->getPixelIDs();
    foreach ($mainPixels as $mainPixel) {
        $options[$mainPixel] = $mainPixel.'(global)';
    }

    renderSelectInput( $event, $key, $options );
}

/**
 * @param CustomEvent $event
 * @param string $key
 */
function renderFacebookParamInput( &$event, $key ) {

	$attr_name = "pys[event][facebook_params][$key]";
	$attr_id = 'pys_event_facebook_' . $key;
	$attr_value = $event->getFacebookParam( $key );

	?>

    <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           placeholder="Enter value"
           class="input-standard">
	<?php
}

/**
 * @param string $key
 * @param $val
 */
function renderMergedGAParamInput( $key, $val ) {

	$attr_name = "pys[event][ga_ads_params][$key]";
	$attr_id = 'pys_event_ga_ads_' . $key;
	$attr_value = $val;

	?>

    <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           class="input-standard">
	<?php

}

/**
 * @param CustomEvent $event
 * @param string      $key
 * @param string      $placeholder
 */
function renderGAParamInput( $key, $val ) {

    $attr_name = "pys[event][ga_params][$key]";
    $attr_id = 'pys_event_ga_' . $key;
    $attr_value = $val;

    ?>

    <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           >
    <?php

}

/**
 * @param CustomEvent $event
 * @param string $key
 * @param string $placeholder
 */
function renderGTMParamInput( $key, $val ) {

	$attr_name = "pys[event][gtm_params][$key]";
	$attr_id = 'pys_event_gtm_' . $key;
	$attr_value = $val;

	?>
    <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           class="input-standard"
    >
	<?php
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGoogleAnalyticsMergedActionInput( &$event, $key ) {
    renderGroupSelectInput( $event, $key, $event->GAEvents, false,'action_merged_g4' );
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGoogleAnalyticsV4ActionInput( &$event, $key ) {
    renderGroupSelectInput( $event, $key, $event->GAEvents, false,'action_g4' );
}
/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGTMActionInput( &$event, $key ) {
    renderGroupSelectInput( $event, $key, $event->GAEvents, false,'action_gtm' );
}
/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGoogleAdsActionInput( &$event, $key ) {
	
	$options = array(
		'_custom'             => 'Custom Action',
		'disabled'            => '',
		'add_payment_info'    => 'add_payment_info',
		'add_to_cart'         => 'add_to_cart',
		'add_to_wishlist'     => 'add_to_wishlist',
		'begin_checkout'      => 'begin_checkout',
		'checkout_progress'   => 'checkout_progress',
		'conversion'          => 'conversion',
		'generate_lead'       => 'generate_lead',
		'login'               => 'login',
		'purchase'            => 'purchase',
		'refund'              => 'refund',
		'remove_from_cart'    => 'remove_from_cart',
		'search'              => 'search',
		'select_content'      => 'select_content',
		'set_checkout_option' => 'set_checkout_option',
		'share'               => 'share',
		'sign_up'             => 'sign_up',
		'view_item'           => 'view_item',
		'view_item_list'      => 'view_item_list',
		'view_promotion'      => 'view_promotion',
		'view_search_results' => 'view_search_results',
	);
	
	renderSelectInput( $event, $key, $options, true );
	
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderGoogleAdsConversionID( &$event, $key ) {
	
	$options = array(
        'all'          => 'All pixels',
	);

    foreach (PixelYourSite\Ads()->getPixelIDs() as $mainPixel) {
        $options[$mainPixel] = $mainPixel.'(global)';
    }
    if(PixelYourSite\isSuperPackActive('3.0.0')){
        $additionalPixels = PixelYourSite\SuperPack()->getAdsAdditionalPixel();
        foreach ($additionalPixels as $aPixel) {
            $options[$aPixel->pixel] = $aPixel->pixel.'(conditional)';
        }
    }

    render_multi_select_input( $event, $key, $options );
	
}

/**
 * @param CustomEvent $event
 * @param string      $key
 */
function renderPinterestEventTypeInput( &$event, $key ) {

	$options = array(
		'addtocart'    => 'AddToCart',
		'checkout'     => 'Checkout',
		'custom'       => 'Custom',
		'partner_defined'  => 'Partner Defined',
		'lead'         => 'Lead',
		'pagevisit'    => 'PageVisit',
		'search'       => 'Search',
		'signup'       => 'Signup',
		'viewcategory' => 'ViewCategory',
		'watchvideo'   => 'WatchVideo',
	);
	
	renderSelectInput( $event, $key, $options );
	
}

/**
 * Output checkbox input
 *
 * @param $event
 * @param      $key
 * @param      $label
 * @param bool $disabled
 */
function renderTriggerCheckboxInput( $trigger, $key, $label, $disabled = false ) {

    $i = $trigger->getTriggerIndex();
	$attr_name  = "pys[event][triggers][$i][$key]";
	$attr_value = $trigger->getParam( $key );

	?>

    <label class="custom-control custom-checkbox">
        <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
        <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>" value="1"
               class="custom-control-input" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value,
			true ); ?>>
        <span class="custom-control-indicator"></span>
        <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
    </label>

	<?php
}

/**
 * Output radio input for trigger
 *
 * @param $trigger
 * @param $key
 * @param $value
 * @param $label
 * @param bool $disabled
 */
function renderTriggerRadioInput( $trigger, $key, $value, $label, $disabled = false ) {
    $i = $trigger->getTriggerIndex();
    $id = $key . "_" . $value . "_" . $i;
    $attr_name = "pys[event][triggers][$i][$key]";
    $attr_value = $trigger->getParam( $key );

    ?>
    <div class="radio-standard">
        <input type="radio"
               name="<?php echo esc_attr( $attr_name ); ?>"
                <?php disabled( $disabled, true ); ?>
               class="custom-control-input"
               id="<?php echo esc_attr( $id ); ?>"
                <?php checked( $attr_value, $value ); ?>
               value="<?php echo esc_attr( $value ); ?>">
        <label class="standard-control radio-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
            <span class="standard-control-indicator"></span>
            <span class="standard-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>
    </div>
    <?php
}

/**
 * Output radio input for trigger form
 *
 * @param $trigger
 * @param $plugin
 * @param $value
 * @param $label
 * @param bool $disabled
 */
function renderTriggerRadioFormInput( $trigger, $plugin, $value, $label, $disabled = false ) {
    $i = $trigger->getTriggerIndex();
    $key = $plugin->getSlug();
    $form_submit_mode = $trigger->getParam( 'form_submit_mode' );
    $id = 'form_submit_mode_' . $value . "_" . $i . "_" . $key;
    $attr_name = "pys[event][triggers][$i][" . $key . "][form_submit_mode]";

    ?>
    <div class="radio-standard">
        <input type="radio"
               name="<?php echo esc_attr( $attr_name ); ?>"
                <?php disabled( $disabled, true ); ?>
               class="custom-control-input"
               id="<?php echo esc_attr( $id ); ?>"
                <?php checked( $form_submit_mode, $value ); ?>
               value="<?php echo esc_attr( $value ); ?>">
        <label class="standard-control radio-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
            <span class="standard-control-indicator"></span>
            <span class="standard-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>
    </div>
    <?php
}