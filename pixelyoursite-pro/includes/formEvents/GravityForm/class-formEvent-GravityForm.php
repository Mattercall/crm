<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FormEventGravityForm extends Settings implements FormEventsFactory {
    private static $_instance;
    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {
        parent::__construct( 'Gravity' );

        $this->locateOptions(
            PYS_PATH . '/includes/formEvents/options_fields.json',
            PYS_PATH . '/includes/formEvents/options_defaults.json'
        );

        if($this->isActivePlugin()){
            add_filter("pys_form_event_factory",[$this,"register"]);
        }
    }

    function register($list) {
        $list[] = $this;
        return $list;
    }

    public function getSlug() {
        return "gravity";
    }
    public function getName() {
        return "Gravity";
    }

    function isEnabled()
    {
        return $this->getOption( 'enabled' );
    }
    function isActivePlugin()
    {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return is_plugin_active( 'gravityforms/gravityforms.php' );
    }
    function getForms(){
        global $wpdb, $table_prefix;
        $forms = array();
        if (class_exists('GFFormsModel')) {
            // Получение списка всех форм Gravity Forms
            $forms = \GFFormsModel::get_forms(null, 'title');

            if (!empty($forms)) {
                $forms = wp_list_pluck($forms, 'title', 'id');
            }

        }
        return $forms;
    }
    function getOptions() {
        return array(
            "name" => $this->getName(),
            "enabled" => $this->getOption( "enabled"),
            "form_ID_event" => $this->getOption( "form_ID_event")
        );
    }
    function getDefaultMatchingInput()
    {
        return array(
            "first_name" => array(),
            "last_name" => array()
        );
    }
}
/**
 * @return FormEventGravityForm
 */
function FormEventGravityForm() {
    return FormEventGravityForm::instance();
}

FormEventGravityForm();