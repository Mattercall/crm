<?php

namespace PixelYourSite;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * PixelYourSite Core class.
 */
final class PYS extends Settings implements Plugin {

    private static $_instance;

    private $form_track;

    /** @var $eventsManager EventsManager */
    private $eventsManager;

    /** @var $registeredPixels array Registered pixels */
    private $registeredPixels = array();

    /** @var $registeredPlugins array Registered plugins */
    private $registeredPlugins = array();

    private $adminPagesSlugs = array();

    private $externalId;
    /**
     * @var PYS_Logger
     */
    private $logger;

    private $containers;

    private $crawlerDetect;

	public $general_domain = '';

    private $pixels_loaded = false;

	public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function getPluginName() {
        return PYS_PLUGIN_NAME;
    }

    public function getPluginFile() {
        return PYS_PRO_PLUGIN_FILE;
    }

    public function getPluginVersion() {
        return PYS_VERSION;
    }

    public function getPluginIcon() {
        return PYS_PLUGIN_ICON;
    }

    public function __construct() {

        add_filter( 'plugin_row_meta', array( $this, 'pluginRowMeta' ), 10, 2 );
        // initialize settings
        parent::__construct( 'core' );

        add_action( 'admin_init', array( $this, 'updatePlugin' ), 0 );
        add_action( 'admin_init', 'PixelYourSite\manageAdminPermissions' );

        /**
         * Priority 9 used because on some events, like EDD's CompleteRegistration, are fired on 'init' action
         * with default (10) priority and PYS should be initialized before it.
         *
         * 3rd party extensions, like Pinterest addon, should be loaded with lower priority.
         */
        add_action( 'wp', array( $this, 'controllSessionStart'), 10);
        add_action( 'init', array( $this, 'set_pbid'), 8);
        add_action( 'init', array( $this, 'init' ), 9 );
        add_action( 'init', array( $this, 'afterInit' ), 11 );

        add_action( 'admin_menu', array( $this, 'adminMenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'adminEnqueueScripts' ) );
        add_action( 'admin_notices', 'PixelYourSite\adminRenderNotices' );
        add_action( 'admin_init', array( $this, 'adminProcessRequest' ), 11 );
        add_action('admin_init', function () {

            // Activate only on PixelYourSite pages
            if (
                isset($_GET['page']) &&
                strpos($_GET['page'], 'pixelyoursite') !== false
            ) {
                ob_start(function ($html) {

                    // Remove all integrity and crossorigin attributes
                    $html = preg_replace('/\s+integrity="[^"]*"/i', '', $html);
                    $html = preg_replace('/\s+crossorigin="[^"]*"/i', '', $html);

                    return $html;
                });
            }
        });

        add_action( 'woocommerce_init', array( $this, 'init_tracking_type_logic' ) );

        // run Events Manager
        add_action( 'template_redirect', array( $this, 'managePixels' ), 1);

        // track user registrations
        add_action( 'user_register', array( $this, 'userRegisterHandler' ) );

        // track user login event
        add_action('wp_login', [$this,'userLogin'], 10, 2);

        // "admin_permission" option custom sanitization function
        add_filter( 'pys_core_settings_sanitize_admin_permissions_field', function( $value ) {

            // "administrator" always should be allowed
            if ( ! is_array( $value ) || ! in_array( 'administrator', $value ) ) {
                $value[] = 'administrator';
            }

            manageAdminPermissions();

            return $this->sanitize_multi_select_field( $value );

        } );

        // redirect template for generate file
        add_action( 'admin_init', array($this,'generate_event_export_json') );
        add_action( 'wp_ajax_pys_import_events', array( $this, 'import_custom_events' ) );

        add_action( 'wp_ajax_pys_get_gdpr_filters_values', array( $this, 'ajaxGetGdprFiltersValues' ) );
        add_action( 'wp_ajax_nopriv_pys_get_gdpr_filters_values', array( $this, 'ajaxGetGdprFiltersValues' ) );

        add_action( 'wp_ajax_pys_get_pbid', array( $this, 'get_pbid_ajax' ) );
        add_action( 'wp_ajax_nopriv_pys_get_pbid', array( $this, 'get_pbid_ajax' ) );

        add_action('wp_ajax_get_variation_info', array( $this, 'get_variation_info'));
        add_action('wp_ajax_nopriv_get_variation_info', array( $this, 'get_variation_info'));


        /*
         * Restore settings after COG plugin
         * */
        add_action( 'deactivate_pixel-cost-of-goods/pixel-cost-of-goods.php',array($this,"restoreSettingsAfterCog"));

        /**
         * For Woo
         */
        add_action("woocommerce_checkout_order_processed",array($this,'woo_checkout_process'),10,3);
        // Classic hook for checkout page
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'saveExternalIDInOrder' ), 10, 1 );
        // Hook for Store API (passes WC_Order object instead of order_id)
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'saveExternalIDInOrder' ), 10, 1 );

        /**
         * For EDD
         */
        // Hook for EDD payment insertion to save external_id
        add_action( 'edd_insert_payment', array( $this, 'saveExternalIDInEddOrder' ), 10, 2 );

        /**
         * Send Server Events
         */
        if(!$this->isCachePreload()){
	        add_action('woocommerce_order_status_completed', array($this, 'woo_completed_purchase'),30);
	        add_action('woocommerce_order_status_refunded', array($this, 'woo_refund_order'),30);

	        add_action( 'edd_recurring_record_payment', array( $this, 'edd_recurring_payment' ),10,4 );
	        add_action( 'edd_complete_purchase', array( $this, 'edd_completed_purchase' ),10,1 );
	        add_action( 'edd_refund_order', array( $this, 'edd_refund_order' ), 10, 3 );
        }

        // Defer cron event scheduling until full initialization
        add_action( 'init', function() {
            if( ! wp_next_scheduled( 'license_check_event' ) ) {
                wp_schedule_event( time(), 'daily', 'license_check_event');
            }
        }, 20);
        add_action('license_check_event', array( $this, 'cronCheckLicense'));
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this,'add_list_name_to_order_items'), 10, 4 );

        $this->logger = new PYS_Logger();
        $this->crawlerDetect = new CrawlerDetect();
        $this->containers = new gtmContainers();
        $this->general_domain = $this->get_wp_cookie_domain();
    }

    public function init() {
        $this->logger->init();

        if (current_user_can( 'manage_pys' ) ) {

            $loggers = [
                'general' => [$this->logger, 'downloadLogFile'],
                'meta' => [Facebook()->getLog(), 'downloadLogFile'],
                'ga' => [GA()->getLog(), 'downloadLogFile'],
                'tiktok' => [Tiktok()->getLog(), 'downloadLogFile'],
            ];


            $clearLoggers = [
                'clear_general_logs' => [$this->logger, 'remove'],
                'clear_meta_logs' => [Facebook()->getLog(), 'remove'],
                'clear_ga_logs' => [GA()->getLog(), 'remove'],
                'clear_tiktok_logs' => [Tiktok()->getLog(), 'remove']
            ];

            if (isPinterestActive() && class_exists('PixelYourSite\Pinterest')){
                $loggers['pinterest'] = [Pinterest()->getLog(), 'downloadLogFile'];
                $clearLoggers['clear_pinterest_logs'] = [Pinterest()->getLog(), 'remove'];
            }

            if (isset($_GET['download_logs']) && array_key_exists($_GET['download_logs'], $loggers)) {
                if (!isset($_GET['_wpnonce_download_logs']) || !wp_verify_nonce($_GET['_wpnonce_download_logs'], 'download_logs_nonce')) {
                    wp_die(__('Invalid nonce', 'pixelyoursite'));
                }
                $logger = $loggers[$_GET['download_logs']];
                if (is_callable($logger)) {
                    call_user_func($logger);
                } elseif (is_callable([$logger[0], $logger[1]])) {
                    call_user_func([$logger[0], $logger[1]]);
                }
            }

            foreach ($clearLoggers as $key => $logger) {
                if (isset($_GET[$key]) && (is_callable($logger) || (is_callable([$logger[0], $logger[1]]) && method_exists($logger[0], $logger[1])))) {
                   if (!isset($_GET['_wpnonce_clear_logs']) || !wp_verify_nonce($_GET['_wpnonce_clear_logs'], 'clear_logs_nonce')) {
                        wp_die(__('Invalid nonce', 'pixelyoursite'));
                    }
                    if (is_callable($logger)) {
                        call_user_func($logger);
                    } else {
                        call_user_func([$logger[0], $logger[1]]);
                    }
                    $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    wp_redirect(remove_query_arg($key, $actual_link));
                    exit;
                }
            }

            if (isset($_GET['download_container'])) {
                if (!isset($_GET['_wpnonce_template_logs']) || !wp_verify_nonce($_GET['_wpnonce_template_logs'], 'download_template_nonce')) {
                    wp_die(__('Invalid nonce', 'pixelyoursite'));
                }
                $this->containers->downloadContainerFile($_GET['download_container']);
            }
        }

        $db = new \PixelYourSite\DataBaseManager();
        $db->create_table();

        register_post_type( 'pys_event', array(
            'public' => false,
            'supports' => array( 'title' )
        ) );

        // initialize options
        $this->locateOptions(
            PYS_PATH . '/includes/options_fields.json',
            PYS_PATH . '/includes/options_defaults.json'
        );

        // register pixels and plugins (add-ons)
        do_action( 'pys_register_pixels', $this );
        do_action( 'pys_register_plugins', $this );

        // load dummy Pinterest plugin for admin UI
        if ( ! array_key_exists( 'pinterest', $this->registeredPlugins ) ) {
            /** @noinspection PhpIncludeInspection */
            require_once PYS_PATH . '/modules/pinterest/pinterest.php';
        }

        // load dummy Bing plugin for admin UI
        if ( ! array_key_exists( 'bing', $this->registeredPlugins ) ) {
            /** @noinspection PhpIncludeInspection */
            require_once PYS_PATH . '/modules/bing/bing.php';
        }

	    // load dummy Reddit plugin for admin UI
	    if ( ! array_key_exists( 'reddit', $this->registeredPlugins ) ) {
		    /** @noinspection PhpIncludeInspection */
		    require_once PYS_PATH . '/modules/reddit/reddit.php';
	    }

        // maybe disable Facebook for WooCommerce pixel output
        if ( isWooCommerceActive()
            && array_key_exists( 'facebook', $this->registeredPixels ) && Facebook()->configured() ) {
            add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', '__return_false' );
        }



        if(Facebook()->getOption('test_api_event_code_expiration_at'))
        {
            foreach (Facebook()->getOption('test_api_event_code_expiration_at') as $key => $test_code_expiration_at)
            {
                if(time() >= $test_code_expiration_at)
                {
                    Facebook()->updateOptions(array("test_api_event_code" => array()));
                    Facebook()->updateOptions(array("test_api_event_code_expiration_at" => array()));
                }
            }
        }

        if(Tiktok()->getOption('test_api_event_code_expiration_at'))
        {
            foreach (Tiktok()->getOption('test_api_event_code_expiration_at') as $test_code_expiration_at)
            {
                if(time() >= $test_code_expiration_at)
                {
                    Tiktok()->updateOptions(array("test_api_event_code" => array()));
                    Tiktok()->updateOptions(array("test_api_event_code_expiration_at" => array()));
                }
            }
        }

        if(isWPMLActive()){
            foreach ($this->getRegisteredPixels() as $pixel) {
                if($pixel->issetOption('woo_wpml_language') &&  empty($pixel->getOption('woo_wpml_language'))){
                    if (function_exists('wpml_get_default_language')) {
                        $default_language = wpml_get_default_language();
                    } else {
                        $default_language = apply_filters('wpml_default_language', NULL);
                    }
                    $pixel->updateOptions(array('woo_wpml_language' => $default_language));
                }
            }
        }

        $eventsFormFactory = apply_filters("pys_form_event_factory",[]);
        if(!$eventsFormFactory)
        {
            // Check current option value before updating to avoid unnecessary database updates
            $current_value = $this->getOption('enable_success_send_form');
            if($current_value !== false)
            {
                $options = array(
                    'enable_success_send_form'     => false
                );
                PYS()->updateOptions($options);
            }
        }

        if (isRealCookieBannerPluginActivated()) {

            add_action('RCB/Templates/TechnicalHandlingIntegration', function ( $integration ) {

                $this->handle_rcb_integration($integration, Facebook()->configured(), 'facebook-pixel', PYS_PRO_PLUGIN_FILE);
                $this->handle_rcb_integration($integration, GA()->configured(), 'google-analytics-analytics-4', PYS_PRO_PLUGIN_FILE);
                $this->handle_rcb_integration($integration, Ads()->configured(), 'google-ads-conversion-tracking', PYS_PRO_PLUGIN_FILE);
                $this->handle_rcb_integration($integration, Tiktok()->configured(), 'tik-tok-pixel', PYS_PRO_PLUGIN_FILE);
                if(isPinterestActive()){
                    $this->handle_rcb_integration($integration, Pinterest()->configured(), 'pinterest-tag', PYS_PINTEREST_PLUGIN_FILE);
                }
                if(isBingActive()){
                    $this->handle_rcb_integration($integration, Bing()->configured(), 'bing-ads', PYS_BING_PLUGIN_FILE);
                }



            });
        }
        EnrichOrder()->init();
        AjaxHookEventManager()->addHooks();
        new FormSubmissionHandler();
    }
    private function handle_rcb_integration( $integration, $is_active, $type, $plugin_dir) {

        if (
            $is_active
            && $integration->integrate($plugin_dir, $type)
        ) {
            $integration->setCodeOptIn('');
            $integration->setCodeOptOut('');
        }
    }
	function controllSessionStart(){
		if(PYS()->getOption('session_disable')) return;

        if (!is_admin() && PHP_SAPI !== 'cli' && session_status() != PHP_SESSION_DISABLED) {
            if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
                if (!session_start()) return;
            }

            if (session_status() !== PHP_SESSION_ACTIVE) return;

            if (empty($_SESSION['TrafficSource'])) {
                $_SESSION['TrafficSource'] = getTrafficSource(PYS()->getOption('visit_data_model') === "last_visit");
            }
            if (empty($_SESSION['LandingPage'])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $currentUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST)) . ($_SERVER['REQUEST_URI'] ?? '');
                $landing = explode('?', $currentUrl)[0];
                $_SESSION['LandingPage'] = $landing;
            }
            if (empty($_SESSION['TrafficUtms'])) {
                $_SESSION['TrafficUtms'] = getUtms(false,PYS()->getOption('visit_data_model') === "last_visit");
            }
            if (empty($_SESSION['TrafficUtmsId'])) {
                $_SESSION['TrafficUtmsId'] = getUtmsId(false, PYS()->getOption('visit_data_model') === "last_visit");
            }
        }
    }

	function get_pbid(){
		return $this->externalId;
	}
    function set_pbid() {
        $pbidCookieName = 'pbid';
        $isTrackExternalId = EventsManager::isTrackExternalId();
        $user = wp_get_current_user();

        if ($user && $isTrackExternalId) {
            $userExternalId = $user->get('external_id');
            if (!empty($userExternalId)) {
                $this->externalId = $userExternalId;
                return;
            }
        }

        if ($isTrackExternalId) {

            if (!empty($_COOKIE[$pbidCookieName])) {
                $this->externalId = sanitize_text_field($_COOKIE[$pbidCookieName]);
            } elseif ( PYS()->getOption('external_id_use_transient') && get_transient('externalId-' . PYS()->get_user_ip())) {
                $this->externalId = get_transient('externalId-' . PYS()->get_user_ip());
            }
            else {
                $uniqueId = bin2hex(random_bytes(16));
                $encryptedUniqueId = hash('sha256', $uniqueId);
                $this->externalId = $encryptedUniqueId;
                if(PYS()->getOption('external_id_use_transient')){
                    set_transient('externalId-' . PYS()->get_user_ip(), $this->externalId, 60 * 10);
                }
            }
        }
    }

    public function get_pbid_ajax(){
        if(defined('DOING_AJAX') && DOING_AJAX){
            $isTrackExternalId = EventsManager::isTrackExternalId();
	        if ($isTrackExternalId && !empty($this->externalId)) {
                $transient = get_transient('externalId-'.PYS()->get_user_ip());
                wp_send_json_success( array('pbid'=> $this->externalId, 'transient' => !empty($transient) ? $transient : false ));
            }
        }
    }
    public function add_list_name_to_order_items( $item, $cart_item_key, $values, $order ) {

        if ( array_key_exists('item_list_name', $values )) {
            $item_list_name = $values['item_list_name'];
            $item->update_meta_data('item_list_name', $item_list_name );
        }
        if ( array_key_exists('item_list_id', $values )) {
            $item_list_id = $values['item_list_id'];
            $item->update_meta_data('item_list_id', $item_list_id );
        }
    }

    public function cronCheckLicense(){
        checkLicense();
    }
    /**
     * Extend options after post types are registered
     */
    public function afterInit() {

        // add available public custom post types to settings
        foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $post_type ) {

            // skip product post type when WC is active
            if ( isWooCommerceActive() && $post_type->name == 'product' ) {
                continue;
            }

            // skip download post type when EDD is active
            if ( isEddActive() && $post_type->name == 'download' ) {
                continue;
            }

        }

    }

    public function import_custom_events() {
        if ( ! $this->adminSecurityCheck()
            || !wp_verify_nonce($_REQUEST['_wpnonce'],"import_events_file_nonce")
        ) {
            return;
        }
        if(isset($_FILES["import_events_file"])) {
            if($_FILES["import_events_file"]['size'] == 0) {
                wp_send_json_error("File is empty ");
                return;
            }
            if( $_FILES["import_events_file"]['type'] != "application/json") {
                wp_send_json_error("File has wrong format ".$_FILES["import_events_file"]['type']);
                return;
            }
            $content = file_get_contents($_FILES["import_events_file"]['tmp_name']);
            $data = json_decode($content,true);

            if(!isset($data['events'])) {
                wp_send_json_error("Events not found");
                return;
            }

            // replace new site url
            $oldSiteUrl = str_replace("/","\/",$data["site_url"]);
            $siteUrl = str_replace("/","\/",site_url());
            $content = str_replace($oldSiteUrl,$siteUrl,$content);

            $data = json_decode(  $content,true);

            // create custom events
            foreach ($data['events'] as $event) {
                CustomEventFactory::import($event);
            }
            wp_send_json_success("OK");
        } else {
            wp_send_json_error("File not found");
        }
    }

    public function generate_event_export_json() {
        if($this->adminSecurityCheck()
            && isset($_GET['tab']) && $_GET['tab'] == "events"
            && isset($_GET['action']) && $_GET['action'] == 'export'
            && wp_verify_nonce($_REQUEST['_wpnonce'],"export_events_file_nonce")
        ) {
            include "views/html-main-events-export.php";
            die();
        }
    }

    /**
     * @param Pixel|Settings $pixel
     */
    public function registerPixel( &$pixel ) {
        if(!is_admin() && !PYS()->getOption('enable_all_tracking_ids') && $pixel->getSlug() != 'gtm'){
            return;
        }
	    switch ($pixel->getSlug()) {
		    case 'pinterest':
			    if(!isPinterestVersionIncompatible()){
				    $this->registeredPixels[ $pixel->getSlug() ] = $pixel;
			    }
                else{
                    $minVersion = PYS_PINTEREST_MIN_VERSION;
	                add_action( 'wp_head', function() use ($minVersion) {
		                echo "<script type='application/javascript' id='pys-pinterest-version-incompatible'>console.warn('You are using incompatible version of PixelYourSite Pinterest Add-On. PixelYourSite PRO requires at least PixelYourSite Pinterest Add-On $minVersion. Please, update to latest version.');</script>\r\n";
	                } );
                }
			    break;
		    case 'bing' :
			    if(!isBingVersionIncompatible()){
				    $this->registeredPixels[ $pixel->getSlug() ] = $pixel;
			    }
			    else{
				    $minVersion = PYS_BING_MIN_VERSION;
				    add_action( 'wp_head', function() use ($minVersion) {
					    echo "<script type='application/javascript' id='pys-bing-version-incompatible'>console.warn('You are using incompatible version of PixelYourSite Bing Add-On. PixelYourSite PRO requires at least PixelYourSite Bing Add-On $minVersion. Please, update to latest version.');</script>\r\n";
				    } );
			    }
			    break;
		    case 'superpack' :
			    if(!isSuperPackVersionIncompatible()){
				    $this->registeredPixels[ $pixel->getSlug() ] = $pixel;
			    }
			    else{
				    $minVersion = PYS_SUPER_PACK_MIN_VERSION;
				    add_action( 'wp_head', function() use ($minVersion) {
					    echo "<script type='application/javascript' id='pys-super-pack-version-incompatible'>console.warn('You are using incompatible version of PixelYourSite Super Pack Add-On. PixelYourSite PRO requires at least PixelYourSite Super Pack Add-On $minVersion. Please, update to latest version.');</script>\r\n";
				    } );
			    }
			    break;
		    default :
			    $this->registeredPixels[ $pixel->getSlug() ] = $pixel;
			    break;
	    }
    }

    /**
     * Return array of registered pixels
     *
     * @return Pixel[]
     */
    public function getRegisteredPixels() {
        return $this->registeredPixels;
    }

    /**
     * @param Pixel|Settings $plugin
     */
    public function registerPlugin( &$plugin ) {
        $this->registeredPlugins[ $plugin->getSlug() ] = $plugin;
    }

    /**
     * Return array of registered plugins
     *
     * @return array
     */
    public function getRegisteredPlugins() {
        return $this->registeredPlugins;
    }

    /**
     * Get admin pages slugs
     *
     * @return array
     */
    public function getAdminPagesSlugs() {
        return $this->adminPagesSlugs;
    }

    /**
     * Add admin page slug
     * 
     * Usage:
     * PYS()->addAdminPageSlug('my-custom-page');
     *
     * @param string $slug
     */
    public function addAdminPageSlug( $slug ) {
        if ( ! in_array( $slug, $this->adminPagesSlugs ) ) {
            $this->adminPagesSlugs[] = $slug;
        }
    }

    /**
     * Remove admin page slug
     * 
     * Usage:
     * PYS()->removeAdminPageSlug('my-custom-page');
     *
     * @param string $slug
     */
    public function removeAdminPageSlug( $slug ) {
        $key = array_search( $slug, $this->adminPagesSlugs );
        if ( $key !== false ) {
            unset( $this->adminPagesSlugs[ $key ] );
            $this->adminPagesSlugs = array_values( $this->adminPagesSlugs );
        }
    }

    /**
     * Set admin pages slugs
     * 
     * Usage:
     * PYS()->setAdminPagesSlugs(['page1', 'page2', 'page3']);
     *
     * @param array $slugs
     */
    public function setAdminPagesSlugs( $slugs ) {
        $this->adminPagesSlugs = $slugs;
    }

    /**
     * Front-end entry point
     */
    public function managePixels() {

        if ( $this->pixels_loaded ) {
            return;
        }
        $this->pixels_loaded = true;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // disable Events Manager on Customizer and preview mode
        if (is_admin() || is_customize_preview() || is_preview()) {
            return;
        }

        // Note: We intentionally DON'T disable Events Manager for bots
        // Reasons:
        // 1. Bots don't execute JavaScript, so no actual tracking happens for them
        // 2. If we disable Events Manager for bots, cached pages won't have tracking scripts
        // 3. Real users would then get cached pages WITHOUT tracking (critical issue!)
        // 4. It's better to include tracking scripts in HTML (even for bots) to ensure
        //    cached pages always have tracking for real users
        // 5. The minimal overhead of including scripts in HTML for bots is acceptable
        //    compared to the risk of breaking tracking for real users


        // disable Events Manager on Elementor editor
        if (did_action('elementor/preview/init')
            || did_action('elementor/editor/init')
            || (isset( $_GET['action'] ) && $_GET['action'] == 'piotnetforms') // skip preview for piotnet forms plugin
        ) {
            return;
        }

        // Disable Events Manager on Divi Builder
        if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
            return;
        }

        if(PYS()->getOption( 'block_ip_enabled') && in_array($this->get_user_ip(), PYS()->getOption('blocked_ips')))
        {
            return;
        }

        $theme = wp_get_theme(); // gets the current theme
        if ( ('Bricks' == $theme->name || 'Bricks' == $theme->parent_theme) && isset($_GET['bricks']) && $_GET['bricks']=='run') {
            return;
        }

        // output debug info
        if(!PYS()->getOption( 'hide_version_plugin_in_console'))
        {
            add_action( 'wp_head', function() {
                echo "<script type='application/javascript' id='pys-version-script'>console.log('PixelYourSite PRO version " . PYS_VERSION . "');</script>\r\n";
            }, 1 );
        }

        if ( isDisabledForCurrentRole() ) {
            return;
        }

        foreach ( PYS()->getRegisteredPixels() as $pixel ) {
            /** @var Pixel|Settings $pixel */
            if ( $pixel->configured() ) {
                $pixel->checkHidePixel();
            }
        }
        // setup events
        $this->eventsManager = new EventsManager();

//form_track = array('formType' => 'gravity', 'formId' => $form['id'])
        $user_id = get_current_user_id();
        $key = 'form_track_' . ( $user_id ? $user_id : md5( PYS()->get_user_ip() ) );
        if( get_transient( $key ) )
        {
            $this->eventsManager->trackFormAfterReload(get_transient( $key ));
	        delete_transient(  $key );
        }

        if ( ! Facebook()->configured() && ! GA()->configured() && ! Ads()->configured()
            && ! Pinterest()->configured() && ! Bing()->configured() && ! Tiktok()->configured() ) {
            if(!PYS()->getOption( 'hide_version_plugin_in_console')) {
                add_action('wp_head', function () {
                    echo "<script type='application/javascript' id='pys-config-warning-script'>console.warn('PixelYourSite PRO: no pixel configured.');</script>\r\n";
                });
            }
            return;

        }



    }

    function get_user_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwarded_ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    function is_user_agent_bot(){
        if (!PYS()->getOption('block_robot_enabled')) {
            return false;
        }
	    if (!empty($_SERVER['HTTP_USER_AGENT'])) {

            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            $excludedRobots = PYS()->getOption('exclude_blocked_robots');
            $excludedRobots = array_filter($excludedRobots, function($robot) {
                return trim($robot) !== '';
            });
            if (!empty($excludedRobots)) {
                foreach ($excludedRobots as $robot) {
                    if (stripos($userAgent, strtolower($robot)) !== false) {
                        return false;
                    }
                }
            }

            $options = array(
	            'YandexBot', 'YandexAccessibilityBot', 'YandexMobileBot', 'YandexDirectDyn', 'YandexScreenshotBot',
	            'YandexImages', 'YandexVideo', 'YandexVideoParser', 'YandexMedia', 'YandexBlogs',
	            'YandexFavicons', 'YandexWebmaster', 'YandexPagechecker', 'YandexImageResizer', 'YandexAdNet',
	            'YandexDirect', 'YaDirectFetcher', 'YandexCalendar', 'YandexSitelinks', 'YandexMetrika',
	            'YandexNews', 'YandexNewslinks', 'YandexCatalog', 'YandexAntivirus', 'YandexMarket',
	            'YandexVertis', 'YandexForDomain', 'YandexSpravBot', 'YandexSearchShop', 'YandexMedianaBot',
	            'YandexOntoDB', 'YandexOntoDBAPI', 'Googlebot', 'Googlebot-Image', 'Googlebot-News',
	            'Googlebot-Video', 'Mediapartners-Google', 'AdsBot-Google', 'Chrome-Lighthouse', 'Lighthouse',
	            'Mail.RU_Bot', 'bingbot', 'Accoona', 'ia_archiver', 'Ask Jeeves', 'OmniExplorer_Bot',
	            'W3C_Validator', 'WebAlta', 'YahooFeedSeeker', 'Yahoo!', 'Ezooms', 'Tourlentabot', 'MJ12bot',
	            'AhrefsBot', 'SearchBot', 'SiteStatus', 'Nigma.ru', 'Baiduspider', 'Statsbot', 'SISTRIX',
	            'AcoonBot', 'findlinks', 'proximic', 'OpenindexSpider', 'statdom.ru', 'Exabot', 'Spider',
	            'SeznamBot', 'oBot', 'C-T bot', 'Updownerbot', 'Snoopy', 'heritrix', 'Yeti', 'DomainVader',
	            'DCPbot', 'PaperLiBot', 'APIs-Google', 'AdsBot-Google-Mobile', 'AdsBot-Google-Mobile-Apps',
	            'FeedFetcher-Google', 'Google-Read-Aloud', 'DuplexWeb-Google', 'Storebot-Google',
	            'ClaudeBot', 'SeekportBot', 'GPTBot', 'Applebot',
	            'DuckDuckBot', 'Sogou', 'facebookexternalhit', 'Swiftbot', 'Slurp', 'CCBot', 'Go-http-client',
	            'Sogou Spider', 'Facebot', 'Alexa Crawler', 'Cốc Cốc Bot', 'Majestic-12', 'SemrushBot',
	            'DotBot', 'Qwantify', 'Pinterest', 'GrapeshotCrawler', 'archive.org_bot', 'LinkpadBot',
	            'uptimebot', 'Wget', 'curl', 'Google-Structured-Data-Testing-Tool', 'Google-PageSpeed Insights',
	            'Google-Site-Verification', 'BingPreview', 'Slackbot', 'TelegramBot', 'DiscordBot', 'WhatsApp',
	            'RedditBot', 'Yahoo! Slurp', 'Tumblr', 'PetalBot', 'FlipboardProxy', 'LinkedInBot',
	            'SkypeUriPreview', 'Google-Firebase-Storage', 'BitlyBot', 'FeedlyBot', 'AppleNewsBot',
	            'ZyBorg', 'ia_archiver-web.archive.org', 'Mediatoolkitbot', 'DeuSu', 'SMTBot', 'MegaIndex.ru',
	            'Seomoz', 'BLEXBot', 'YisouSpider', '360Spider', 'AddThis', 'TweetmemeBot', 'ContextAd Bot',
	            'Screaming Frog SEO Spider', 'Nutch', 'Baiduspider-image', 'Panscient.com', 'Twitterbot',
	            'YoudaoBot', 'OpenSiteExplorer', 'Linkfluence', 'YaK', 'ContentKing', 'Spinn3r', 'PhantomJS',
	            'HeadlessChrome', 'Snapchat', 'Pingdom', 'Googlebot-Mobile', 'Barkrowler'
            );

            foreach($options as $row) {
                if (stripos($userAgent, strtolower($row)) !== false) {
                    return true;
                }
            }
        }

        return $this->crawlerDetect->isCrawler();
    }

    public function ajaxGetGdprFiltersValues() {

        wp_send_json_success( array(
            'all_disabled_by_api'       => apply_filters( 'pys_disable_by_gdpr', false ),
            'facebook_disabled_by_api'  => apply_filters( 'pys_disable_facebook_by_gdpr', false ),
            'tiktok_disabled_by_api'  => apply_filters( 'pys_disable_tiktok_by_gdpr', false ),
            'analytics_disabled_by_api' => apply_filters( 'pys_disable_analytics_by_gdpr', false ),
            'google_ads_disabled_by_api' => apply_filters( 'pys_disable_google_ads_by_gdpr', false ),
            'pinterest_disabled_by_api' => apply_filters( 'pys_disable_pinterest_by_gdpr', false ),
            'bing_disabled_by_api' => apply_filters( 'pys_disable_bing_by_gdpr', false ),
            'reddit_disabled_by_api' => apply_filters( 'pys_disable_reddit_by_gdpr', false ),
            'externalID_disabled_by_api' => apply_filters( 'pys_disable_externalID_by_gdpr', false ),
            'disabled_all_cookie'       => apply_filters( 'pys_disable_all_cookie', false ),
            'disabled_start_session_cookie' => apply_filters( 'pys_disabled_start_session_cookie', false ),
            'disabled_advanced_form_data_cookie' => apply_filters( 'pys_disable_advanced_form_data_cookie', false ) || apply_filters( 'pys_disable_advance_data_cookie', false ),
            'disabled_landing_page_cookie'  => apply_filters( 'pys_disable_landing_page_cookie', false ),
            'disabled_first_visit_cookie'  => apply_filters( 'pys_disable_first_visit_cookie', false ),
            'disabled_trafficsource_cookie' => apply_filters( 'pys_disable_trafficsource_cookie', false ),
            'disabled_utmTerms_cookie' => apply_filters( 'pys_disable_utmTerms_cookie', false ),
            'disabled_utmId_cookie' => apply_filters( 'pys_disable_utmId_cookie', false ),
        ) );

    }

    public function userRegisterHandler( $user_id ) {

        if ( PYS()->getOption( 'woo_complete_registration_enabled' )
            || PYS()->getOption( 'automatic_event_signup_enabled' )
        ) {
            update_user_meta( $user_id, 'pys_complete_registration', true );
        }

    }

    /**
     * Hook
     * @param String $user_login
     * @param \WP_User $user
     */
	function userLogin( $user_login, $user ) {
		delete_transient( 'externalId-' . PYS()->get_user_ip() );
		setcookie( "pbid", "", time() - 3600, '/', $this->general_domain);
		update_user_meta( $user->ID, 'pys_just_login', true );
		if ( !$user->get( 'external_id' ) ) {
			update_user_meta( $user->ID, 'external_id', $this->externalId );
		}
		// update advance matching user data
		update_advance_matching_user_data( $user->first_name, $user->last_name, $user->user_email, '' );
	}

    /**
     * @param $order_id
     * @param $posted_data
     * @param \WC_Order $order
     */
	public function woo_checkout_process( $order_id, $posted_data, $order ) {

		// update advance matching user data
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();
		$phone = preg_replace( '/[^0-9.]+/', '', $phone );

		update_advance_matching_user_data( $first_name, $last_name, $email, $phone );
	}

    public function getEventsManager() {
        return $this->eventsManager;
    }

    public function adminMenu() {
        global $submenu;

        add_menu_page( 'PixelYourSite', 'PixelYourSite', 'manage_pys', 'pixelyoursite',
            array( $this, 'adminPageMain' ), PYS_URL . '/dist/images/pys-logo.png' );
        add_submenu_page( 'pixelyoursite', 'Global Settings', 'Global Settings',
            'manage_pys', 'pixelyoursite_settings', array( $this, 'adminSinglePage' ), 2 );
        add_submenu_page('pixelyoursite',__('Queue Settings', 'pixelyoursite'),__('Queue Settings', 'pixelyoursite'),
            'manage_options','pixelyoursite_queue_settings',array($this, 'adminSinglePage'),3);
        PysStatistic()->adminMenu();

        add_submenu_page( 'pixelyoursite', 'UTM Builder', 'UTM Builder',
            'manage_pys', 'pixelyoursite_utm', array( $this, 'adminSinglePage' ), 6 );

        add_submenu_page( 'pixelyoursite', 'Licenses', 'Licenses',
            'manage_pys', 'pixelyoursite_licenses', array( $this, 'adminSinglePage' ), 7 );

        add_submenu_page( 'pixelyoursite', 'System Report', 'System Report',
            'manage_pys', 'pixelyoursite_report', array( $this, 'adminSinglePage' ), 8 );


        // core admin pages
        $this->setAdminPagesSlugs(array(
            'pixelyoursite',
            'pixelyoursite_settings',
            'pixelyoursite_queue_settings',
            'pixelyoursite_licenses',
            'pixelyoursite_report',
            'pixelyoursite_woo_reports',
            'pixelyoursite_edd_reports',
            'pixelyoursite_utm',
        ));

        // rename first submenu item
        if ( isset( $submenu['pixelyoursite'] ) ) {
            $submenu['pixelyoursite'][0][0] = 'Dashboard';
        }

        $this->adminSaveSettings();

    }

    public function adminEnqueueScripts() {

        wp_enqueue_style( 'pys_notice', PYS_URL . '/dist/styles/notice.min.css', array(), PYS_VERSION );
        if ( in_array( getCurrentAdminPage(), $this->adminPagesSlugs ) ) {


            wp_register_style( 'select2_css', PYS_URL . '/dist/styles/select2.min.css', array(), PYS_VERSION);
            wp_register_script( 'select2_js', PYS_URL . '/dist/scripts/select2.min.js',array( 'jquery' ), PYS_VERSION );

            wp_deregister_script( 'jquery' );
            wp_deregister_script( 'jquery-core' ); // prevent duplicate

            wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', array(), '4.7.0' );

            wp_enqueue_script( 'jquery', '//cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js' );

            wp_enqueue_script( 'popper', PYS_URL . '/dist/scripts/popper.min.js', 'jquery' );
            wp_enqueue_script( 'tippy', PYS_URL . '/dist/scripts/tippy.min.js', 'jquery' );
            wp_enqueue_script( 'bootstrap', PYS_URL . '/dist/scripts/bootstrap.min.js', 'jquery','popper' );

            wp_enqueue_style( 'pys_css', PYS_URL . '/dist/styles/admin.min.css', array( 'select2_css' ), PYS_VERSION );
            
            // Enqueue queue-settings styles for queue settings page
            if ( getCurrentAdminPage() === 'pixelyoursite_queue_settings' ) {
                wp_enqueue_style( 'pys_queue_settings_css', PYS_URL . '/dist/styles/queue-settings.min.css', array( 'pys_css' ), PYS_VERSION );
            }

            wp_enqueue_script( 'pys_js', PYS_URL . '/dist/scripts/admin.js', array( 'jquery', 'select2_js', 'popper',
                'bootstrap' ), PYS_VERSION );

            if( isset($_GET['page'])
                && $_GET['page'] == "pixelyoursite"
                && isset($_GET['tab'])
                && ($_GET['tab'] == 'woo' || $_GET['tab'] == 'edd')
            ) {
                wp_enqueue_script( 'jquery-ui-datepicker' );
                wp_enqueue_style( 'pys_calendar', PYS_URL . '/dist/styles/calendar.css', array(  ), PYS_VERSION );
                wp_enqueue_script( 'pys_js_offline_export', PYS_URL . '/dist/scripts/admin_offline_events.js', array( 'pys_js' ), PYS_VERSION );
                wp_enqueue_script( 'pys_js_customer_export', PYS_URL . '/dist/scripts/admin_export_customer.js', array( 'pys_js' ), PYS_VERSION );
            }
            if( (isset($_GET['page']) && $_GET['page'] == "pixelyoursite" && isset($_GET['tab']) && ($_GET['tab'] == 'events')) ||
                ( isset($_GET['page']) && (( $_GET['page'] == "pixelyoursite_woo_reports" ) || $_GET['page'] == "pixelyoursite_edd_reports" )) ||
                ( isset($_GET['page']) && $_GET['page'] == "pixelyoursite_settings")
            ){
                wp_enqueue_style( 'pys_confirm_style', PYS_URL . '/dist/scripts/confirm/jquery-confirm.min.css', array(  ), PYS_VERSION );
                wp_enqueue_style( 'pys_confirm_style_theme', PYS_URL . '/dist/scripts/confirm/bs3.css', array(  ), PYS_VERSION );
                wp_enqueue_script( 'pys_confirm_script', PYS_URL . '/dist/scripts/confirm/jquery-confirm.min.js', array( 'pys_js' ), PYS_VERSION );
                wp_enqueue_script( 'pys_custom_confirm_script', PYS_URL . '/dist/scripts/confirm/custom-confirm.js', array( 'pys_js' ), PYS_VERSION );
            }



        }



    }

    public function adminPageMain() {
        $this->adminExportCustomAudiences();

        include 'views/html-wrapper-main.php';

    }

    public function adminSinglePage()
    {
        $this->adminResetSettings();
        include 'views/html-wrapper-single-page.php';
    }

// Add a new column to the list of orders
    function add_tracking_type_column($columns) {
        $columns['tracking_type'] = 'Tracking type';
        return $columns;
    }


// Fill the new column with data from the order metafield
    function fill_tracking_type($column) {

        if ('tracking_type' === $column) {
            $tracking_type = 'Not tracked';
            if(get_post_meta( get_the_ID(), '_pys_advance_purchase_event_fired', true)){
                $tracking_type = 'Advanced Purchase Tracking (APT)';
            }elseif(get_post_meta( get_the_ID(), '_pys_purchase_event_fired', true)){
                $tracking_type = 'Tag or API';
            }
            echo $tracking_type;
        }


    }

	function fill_tracking_type_hpos($column, $order) {

		if ('tracking_type' === $column) {
			$tracking_type = 'Not tracked';
			if($order->get_meta('_pys_advance_purchase_event_fired', true)){
				$tracking_type = 'Advanced Purchase Tracking (APT)';
			}elseif($order->get_meta('_pys_purchase_event_fired', true)){
				$tracking_type = 'Tag or API';
			}
			echo $tracking_type;
		}


	}

    public function adminProcessRequest() {
        $this->adminCheckLicense();
        $this->adminUpdateCustomEvents();
        $this->adminEnableGdprAjax();
        OfflineEvents();


        if(isset($_GET['page']) && ($_GET['page'] == 'pixelyoursite_woo_reports' || $_GET['page'] == 'pixelyoursite_edd_reports')) {
            // Security check: verify user has permission to export reports
            if ( ! current_user_can( 'manage_pys' ) ) {
                return;
            }

            if ( isset($_REQUEST['export_csw']) && $_REQUEST['export_csw'] == 'woo_report' ) {
                PysStatistic()->globalWooReport();
                exit;
            }

            if ( isset($_REQUEST['export_csw']) && $_REQUEST['export_csw'] == 'woo_single_report' ) {
                PysStatistic()->singleWooReport();
                exit;
            }
            if ( isset($_REQUEST['export_csw']) && $_REQUEST['export_csw'] == 'edd_report' ) {
                PysStatistic()->globalEddReport();
                exit;
            }
            if ( isset($_REQUEST['export_csw']) && $_REQUEST['export_csw'] == 'edd_single_report' ) {
                PysStatistic()->singleEddReport();
                exit;
            }
        }
    }
    public function init_tracking_type_logic() {
        if ( ! is_admin() || ! function_exists( 'wc_get_container' ) ) {
            return false;
        }
        if(PYS()->getOption('woo_enabled_show_tracking_type')){
            if(isWooUseHPStorage()){
                add_filter('woocommerce_shop_order_list_table_columns', array($this,'add_tracking_type_column'));
                add_action('woocommerce_shop_order_list_table_custom_column', array($this,'fill_tracking_type_hpos'), 20, 2 );
            }
            else{
                add_filter('manage_edit-shop_order_columns', array($this,'add_tracking_type_column'));
                add_action('manage_shop_order_posts_custom_column', array($this,'fill_tracking_type'));
            }

        }
    }
    private function adminCheckLicense() {

        $is_dashboard = isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pixelyoursite';
        $license_status = $this->getOption( 'license_status' );

        // redirect to license page in case if license was never activated
        if ( $is_dashboard && empty( $license_status ) ) {
            wp_safe_redirect( buildAdminUrl( 'pixelyoursite_licenses' ) );
            exit;
        }

    }

    public function adminUpdateLicense() {

        if ( ! $this->adminSecurityCheck() ) {
            return;
        }

        updateLicense( $this );

    }

    public function updatePlugin() {

        foreach ( $this->registeredPlugins as $slug => $plugin ) {

            if ( $slug == 'head_footer' ) {
                continue;
            }

            updatePlugin( $plugin );

        }

        updatePlugin( $this );

    }

    public function adminSecurityCheck() {

        // verify user access
        if ( ! current_user_can( 'manage_pys' ) ) {
            return false;
        }

        // nonce filed are required request
        if ( ! isset( $_REQUEST['_wpnonce'] ) || !$_REQUEST['_wpnonce'] ) {
            return false;
        }

        return true;

    }

    private function adminEnableGdprAjax() {

        if ( ! $this->adminSecurityCheck() ) {
            return;
        }
	// Verify nonce specifically for GDPR AJAX action
	    if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_enable_gdpr_ajax' ) ) {
		    return;
	    }
        if ( isset( $_REQUEST['pys']['enable_gdpr_ajax'] ) ) {
            $this->updateOptions( array(
                'gdpr_ajax_enabled' => true,
                'gdpr_cookie_law_info_integration_enabled' => true,
                'consent_magic_integration_enabled' => true,
            ) );

            add_action( 'admin_notices', 'PixelYourSite\adminGdprAjaxEnabledNotice' );
            purgeCache();
        }

    }

    private function adminUpdateCustomEvents() {

        if ( ! $this->adminSecurityCheck() ) {
            return;
        }

        /**
         * Single Custom Event Actions
         */
        if ( isset( $_REQUEST['pys']['event'] ) && isset( $_REQUEST['action']) && is_array($_REQUEST['pys']['event'])  ) {

            $nonce   = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : null;
            $action  = $_REQUEST['action'];
            if(isset( $_REQUEST['pys']['event']['post_id'] )) {
                $post_id = sanitize_key( $_REQUEST['pys']['event']['post_id']) ;
            } else {
                $post_id =  false;
            }


            if ( $action == 'update' && wp_verify_nonce( $nonce, 'pys_update_event' ) ) {

                $pys_event = $_REQUEST['pys']['event'];

                if ( $post_id ) {
                    $event = CustomEventFactory::getById( $post_id );
                    $event->update( $pys_event );
                } else {
                    $event = CustomEventFactory::create( $pys_event );
                }

                purgeCache();

                // redirect to events tab
                wp_safe_redirect( buildAdminUrl( 'pixelyoursite', 'events', 'edit', array(
                    'id' => $event->getPostId()
                ) ));
                exit;

            } elseif ( $action == 'enable' && $post_id && wp_verify_nonce( $nonce, 'pys_enable_event' ) ) {

                $event = CustomEventFactory::getById( $post_id );
                $event->enable();

            } elseif ( $action == 'disable' && $post_id && wp_verify_nonce( $nonce, 'pys_disable_event' ) ) {

                $event = CustomEventFactory::getById( $post_id );
                $event->disable();

            } elseif ( $action == 'remove' && $post_id && wp_verify_nonce( $nonce, 'pys_remove_event' ) ) {

                CustomEventFactory::remove( $post_id );

            }

            purgeCache();

            // redirect to events tab
            wp_safe_redirect( buildAdminUrl( 'pixelyoursite', 'events' ) );
            exit;

        }

        /**
         * Bulk Custom Events Actions
         */
        if ( isset( $_REQUEST['pys']['bulk_event_action'], $_REQUEST['pys']['selected_events'] )
            && isset( $_REQUEST['pys']['bulk_event_action_nonce'] )
            && wp_verify_nonce( $_REQUEST['pys']['bulk_event_action_nonce'], 'bulk_event_action' )
            && is_array( $_REQUEST['pys']['selected_events'] ) ) {

            foreach ( $_REQUEST['pys']['selected_events'] as $event_id ) {

                $event_id = (int) $event_id;

                switch ( $_REQUEST['pys']['bulk_event_action'] ) {
                    case 'enable':
                        $event = CustomEventFactory::getById( $event_id );
                        $event->enable();
                        break;

                    case 'disable':
                        $event = CustomEventFactory::getById( $event_id );
                        $event->disable();
                        break;

                    case 'clone':
                        CustomEventFactory::makeClone( $event_id );
                        break;

                    case 'delete':
                        CustomEventFactory::remove( $event_id );
                        break;
                }

            }

            purgeCache();

            // redirect to events tab
            wp_safe_redirect( buildAdminUrl( 'pixelyoursite', 'events' ) );
            exit;

        }

    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param mixed $links Plugin Row Meta.
     * @param mixed $file  Plugin Base file.
     *
     * @return array
     */
    public function pluginRowMeta( $links, $file ) {

        if ( PYS_PRO_PLUGIN_BASENAME === $file ) {
            $links[] = '<a href="https://www.pixelyoursite.com/documentation">Help</a>';
        }

        return (array) $links;

    }

    private function adminSaveSettings() {

        if ( ! $this->adminSecurityCheck() ) {
            return;
        }

        if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) ) {

            if(isset( $_POST['pys']['core'] ) && is_array($_POST['pys']['core'])) {
                $core_options =  $_POST['pys']['core'];
            } else {
                $core_options =  array();
            }


            $gdpr_ajax_enabled = isset( $core_options['gdpr_ajax_enabled'] )
                ? $core_options['gdpr_ajax_enabled']        // value from form data
                : $this->getOption('gdpr_ajax_enabled');    // previous value

            // allow 3rd party plugins to by-pass option value
            $core_options['gdpr_ajax_enabled'] = apply_filters( 'pys_gdpr_ajax_enabled', $gdpr_ajax_enabled );

            if (isPixelCogActive() ) {
                if (isset($core_options['woo_purchase_value_option'])) {
                    $core_options = $this->updateDefaultNoCogOption($core_options,'woo_purchase_value_option','woo_purchase_value_cog');
                }
                if (isset($core_options['woo_view_content_value_option'])) {
                    $core_options = $this->updateDefaultNoCogOption($core_options,'woo_view_content_value_option','woo_content_value_cog');
                }
                if (isset($core_options['woo_add_to_cart_value_option'])) {
                    $core_options = $this->updateDefaultNoCogOption($core_options,'woo_add_to_cart_value_option','woo_add_to_cart_value_cog');
                }
                if (isset($core_options['woo_initiate_checkout_value_option'])) {
                    $core_options = $this->updateDefaultNoCogOption($core_options,'woo_initiate_checkout_value_option','woo_initiate_checkout_value_cog');
                }
            }

            // update core options
            $this->updateOptions( $core_options );


            $objects = array_merge( $this->registeredPixels, $this->registeredPlugins );

            // update plugins and pixels options
            foreach ( $objects as $obj ) {
                /** @var Plugin|Pixel|Settings $obj */
                $obj->updateOptions();
            }
            GATags()->updateOptions();
            purgeCache();

        }

    }

    private function updateDefaultNoCogOption($core_options,$optionName,$defaultOptionName) {
        $val = $core_options[$optionName];
        $currentVal = $this->getOption($optionName);
        if($val != 'cog') {
            $core_options[$defaultOptionName] = $val;
        } elseif ( $currentVal != 'cog' ) {
            $core_options[$defaultOptionName] = $currentVal;
        }
        return $core_options;
    }

    private function adminResetSettings() {
        if ( ! $this->adminSecurityCheck() ) {
            return;
        }
        if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) && isset( $_REQUEST['pys']['reset_settings'] ) ) {

            if ( isSuperPackActive() ) {

                $old_options = array(
                    'license_key'     => SuperPack()->getOption( 'license_key' ),
                    'license_status'  => SuperPack()->getOption( 'license_status' ),
                    'license_expires' => SuperPack()->getOption( 'license_expires' ),
                );

                SuperPack()->resetToDefaults();
                SuperPack()->updateOptions( $old_options );

            }

            if ( isPinterestActive() ) {

                $old_options = array(
                    'license_key'     => Pinterest()->getOption( 'license_key' ),
                    'license_status'  => Pinterest()->getOption( 'license_status' ),
                    'license_expires' => Pinterest()->getOption( 'license_expires' ),
                    'pixel_id'        => Pinterest()->getPixelIDs(),
                );

                Pinterest()->resetToDefaults();
                Pinterest()->updateOptions( $old_options );

            }

	        if ( isRedditActive() ) {
		        $old_options = array(
			        'license_key'     => Reddit()->getOption( 'license_key' ),
			        'license_status'  => Reddit()->getOption( 'license_status' ),
			        'license_expires' => Reddit()->getOption( 'license_expires' ),
			        'pixel_id'        => Reddit()->getPixelIDs(),
		        );

		        Reddit()->resetToDefaults();
		        Reddit()->updateOptions( $old_options );
	        }

            // Core
            $old_options = array(
                'license_key'     => $this->getOption( 'license_key' ),
                'license_status'  => $this->getOption( 'license_status' ),
                'license_expires' => $this->getOption( 'license_expires' ),
            );

            PYS()->resetToDefaults();
            PYS()->updateOptions( $old_options );

            // Facebook
            $old_options = array(
                'pixel_id' => Facebook()->getPixelIDs(),
            );

            Facebook()->resetToDefaults();
            Facebook()->updateOptions( $old_options );

            // Google Analytics
            $old_options = array(
                'tracking_id' => GA()->getPixelIDs(),
            );

            GA()->resetToDefaults();
            GA()->updateOptions( $old_options );

            // Google Analytics
            $old_options = array(
                'ads_ids' => Ads()->getPixelIDs(),
                'woo_purchase_conversion_labels' => Ads()->getOption( 'woo_purchase_conversion_labels' ),
                'woo_initiate_checkout_conversion_labels' => Ads()->getOption( 'woo_initiate_checkout_conversion_labels' ),
                'woo_add_to_cart_conversion_labels' => Ads()->getOption( 'woo_add_to_cart_conversion_labels' ),
                'woo_view_content_conversion_labels' => Ads()->getOption( 'woo_view_content_conversion_labels' ),
                'woo_view_category_conversion_labels' => Ads()->getOption( 'woo_view_category_conversion_labels' ),
                'edd_purchase_conversion_labels' => Ads()->getOption( 'edd_purchase_conversion_labels' ),
                'edd_initiate_checkout_conversion_labels' => Ads()->getOption( 'edd_initiate_checkout_conversion_labels' ),
                'edd_add_to_cart_conversion_labels' => Ads()->getOption( 'edd_add_to_cart_conversion_labels' ),
                'edd_view_content_conversion_labels' => Ads()->getOption( 'edd_view_content_conversion_labels' ),
                'edd_view_category_conversion_labels' => Ads()->getOption( 'edd_view_category_conversion_labels' ),
            );
            Ads()->resetToDefaults();
            Ads()->updateOptions( $old_options );

            //HeadFooter()->resetToDefaults();

            // do redirect
            wp_safe_redirect( buildAdminUrl( 'pixelyoursite_settings' ) );
            exit;

        }

    }

    private function adminExportCustomAudiences() {

        if ( ! $this->adminSecurityCheck() ) {
            return;
        }

        if ( isset( $_REQUEST['pys']['export_custom_audiences'] )
            && wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) ) {

            if ( $_REQUEST['pys']['export_custom_audiences'] == 'woo' && isWooCommerceActive() ) {
                wooExportCustomAudiences();
            } elseif ( $_REQUEST['pys']['export_custom_audiences'] == 'edd' ) {
                eddExportCustomAudiences();
            }

        }

    }

    public function restoreSettingsAfterCog() {
        $old = Facebook()->getOption("woo_complete_registration_custom_value_old");
        if(!empty($old) ) {
            Facebook()->updateOptions(array(
                "woo_complete_registration_custom_value" => $old,
                'woo_complete_registration_custom_value_old' => ""));
        }
        $params = array();
        $oldPurchase = $this->getOption("woo_purchase_value_cog");
        $oldContent = $this->getOption("woo_content_value_cog");
        $oldAddCart = $this->getOption("woo_add_to_cart_value_cog");
        $oldInitCheckout = $this->getOption("woo_initiate_checkout_value_cog");

        if($this->getOption('woo_purchase_value_option') == 'cog') {
            if(!empty($oldPurchase)) $params['woo_purchase_value_option'] = $oldPurchase;
            else $params['woo_purchase_value_option'] = "price";
        }
        if($this->getOption('woo_view_content_value_option') == 'cog') {
            if(!empty($oldContent)) $params['woo_view_content_value_option'] = $oldContent;
            else $params['woo_view_content_value_option'] = "price";
        }
        if($this->getOption('woo_add_to_cart_value_option') == 'cog') {
            if(!empty($oldAddCart)) $params['woo_add_to_cart_value_option'] = $oldAddCart;
            else $params['woo_add_to_cart_value_option'] = "price";
        }
        if($this->getOption('woo_initiate_checkout_value_option') == 'cog') {
            if(!empty($oldInitCheckout)) $params['woo_initiate_checkout_value_option'] = $oldInitCheckout;
            else $params['woo_initiate_checkout_value_option'] = "price";
        }

        $params['woo_purchase_value_cog'] = '';
        $params['woo_content_value_cog'] = '';
        $params['woo_add_to_cart_value_cog'] = '';
        $params['woo_initiate_checkout_value_cog'] = '';

        $this->updateOptions($params);
    }

    public function getLog() {
        return $this->logger;
    }

    function edd_completed_purchase( $payment_id) {


        if(!PYS()->getOption("edd_advance_purchase_fb_enabled")
            && !PYS()->getOption("edd_advance_purchase_ga_enabled")
			&& ( !Tiktok()->enabled() || ( Tiktok()->enabled() && !Tiktok()->getOption( 'edd_advance_purchase_enabled' ) ) )
			&& ( !Pinterest()->enabled() || ( Pinterest()->enabled() && !Pinterest()->getOption( 'edd_advance_purchase_enabled' ) ) )
        ) {
            return;
        }
        $userId = edd_get_payment_user_id($payment_id);
        $user = get_user_by('id', $userId);
        if(isDisabledForUserRole($user)
            || !PYS()->getOption("edd_enabled_purchase_recurring")
            || !PYS()->getOption( 'edd_purchase_enabled' )
        ) {
            return;
        }

        add_filter("pys_edd_checkout_order_id",function () use ($payment_id) {return $payment_id;});
        $event = EventsEdd()->getEvent('edd_purchase', true);
        if ( $event == null ) {
            return;
        }
        $event->addPayload(['eventID'=>EventIdGenerator::guidv4()]);

        $event->addParams(array('advanced_purchase_tracking' => true));

        if(PYS()->getOption("edd_advance_purchase_ga_enabled") ) { // send GA
            $gaEvents = GA()->generateEvents($event);
            if(!empty($gaEvents)){
                GaMeasurementProtocolAPI()->sendEventsNow($gaEvents);
            }
        }

        if(PYS()->getOption( 'edd_purchase_on_transaction' ) && edd_get_payment_meta( $payment_id, '_pys_purchase_event_fired', true )){
            return;
        }

        if ( function_exists('wp_doing_ajax') && wp_doing_ajax() ) {

            // Stripe Elements creates/completes an order through these actions
            $ajax_actions = [
                'edds_create_and_complete_order',
                'edds_payment_elements_process_payment',
            ];

            if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], $ajax_actions, true)
            ) {
                return;
            }

            $gateway = edd_get_payment_gateway( $payment_id );
            if(isset($gateway) && $gateway === 'stripe'){
                return;
            }
        }

        if(PYS()->getOption("edd_advance_purchase_fb_enabled") ) {//send fb server events
            $fbEvents = Facebook()->generateEvents($event);
            FacebookServer()->sendEventsNow($fbEvents);
        }
		if( Tiktok()->enabled()
            && Tiktok()->isServerApiEnabled()
            && Tiktok()->getOption( "edd_advance_purchase_enabled" )
        ) {// send Tiktok Completed Payment event
			Tiktok()->getLog()->debug( 'Send Completed Payment Tiktok' );
			$tiktokEvents = Tiktok()->generateEvents( $event );
			TikTokServer()->sendEventsNow( $tiktokEvents );
		}
		if( Pinterest()->enabled()
            && method_exists( Pinterest(), 'isServerApiEnabled' )
            && Pinterest()->isServerApiEnabled()
            && Pinterest()->getOption( "edd_advance_purchase_enabled" )
        ) {// send Pinterest Checkout
			Pinterest()->getLog()->debug( 'Send Checkout Pinterest' );
			$pinterestEvents = Pinterest()->generateEvents( $event );
			PinterestServer()->sendEventsNow( $pinterestEvents );
		}
    }
    function edd_refund_order($payment_id, $refund_id, $all_refunded) {
        $log = $this->logger;
        if(!PYS()->getOption("edd_track_refunds_GA") && !edd_get_payment_meta( $payment_id, '_pys_purchase_event_fired', true )) {
            return;
        }
        if(!$payment_id) {
            return;
        }
        $userId = edd_get_payment_user_id($payment_id);
        $user = get_user_by('id', $userId);
        if(isDisabledForUserRole($user)) {
            return;
        }


	    edd_update_payment_meta( $payment_id, '_pys_purchase_event_fired', false );
        add_filter("pys_edd_checkout_order_id",function () use ($payment_id) {return $payment_id;});
        $event = EventsEdd()->getEvent('edd_refund');

        if ( $event == null ) {
            return;
        }
        $event->addPayload(['eventID'=>EventIdGenerator::guidv4()]);

        if(PYS()->getOption("edd_track_refunds_GA") ) {// send GA

            $gaEvents = GA()->generateEvents($event);
            GaMeasurementProtocolAPI()->sendEventsNow($gaEvents);
            $log->debug("Send completed refund GA");
        }
    }
    function edd_recurring_payment( $payment_id, $parent_payment_id, $amount, $transaction_id) {

		EnrichOrder()->edd_save_subscription_meta( $payment_id );

        if(!PYS()->getOption("edd_advance_purchase_fb_enabled")
            && !PYS()->getOption("edd_advance_purchase_ga_enabled")
            && ( !Tiktok()->enabled() || ( Tiktok()->enabled() && !Tiktok()->getOption( 'edd_advance_purchase_enabled' ) ) )
            && ( !Pinterest()->enabled() || ( Pinterest()->enabled() && !Pinterest()->getOption( 'edd_advance_purchase_enabled' ) ) )
        ) {
            return;
        }

        $userId = edd_get_payment_user_id($payment_id);
        $user = get_user_by('id', $userId);
        if(isDisabledForUserRole($user)
            || !PYS()->getOption("edd_enabled_purchase_recurring")
            || !PYS()->getOption( 'edd_purchase_enabled' )
        ) {
            return;
        }
        PYS()->getLog()->debug("Purchase recurring Edd", $payment_id);
        add_filter("pys_edd_checkout_order_id",function () use ($payment_id) {return $payment_id;});

        $event = EventsEdd()->getEvent('edd_purchase', true);
        if ( $event == null ) {
            return;
        }
        $event->addPayload(['eventID'=>EventIdGenerator::guidv4()]);

        if(PYS()->getOption("edd_advance_purchase_ga_enabled") ) { // send GA
            $gaEvents = GA()->generateEvents($event);
            if(!empty($gaEvents)){
                GaMeasurementProtocolAPI()->sendEventsNow($gaEvents);
            }
        }

        if(PYS()->getOption( 'edd_purchase_on_transaction' ) && edd_get_payment_meta( $payment_id, '_pys_purchase_event_fired', true )){
            return;
        }
        if(PYS()->getOption("edd_advance_purchase_fb_enabled") ) {//send fb server events
            $fbEvents = Facebook()->generateEvents($event);
            FacebookServer()->sendEventsNow($fbEvents);
        }
        if( Tiktok()->enabled()
            && Tiktok()->isServerApiEnabled()
            && Tiktok()->getOption( "edd_advance_purchase_enabled" )
        ) {// send Tiktok Renewal Payment event
            Tiktok()->getLog()->debug( 'Send Renewal Payment Tiktok' );
            $tiktokEvents = Tiktok()->generateEvents( $event );
            TikTokServer()->sendEventsNow( $tiktokEvents );
        }
        if( Pinterest()->enabled()
            && method_exists( Pinterest(), 'isServerApiEnabled' )
            && Pinterest()->isServerApiEnabled()
            && Pinterest()->getOption( "edd_advance_purchase_enabled" )
        ) {// send Pinterest Checkout
            Pinterest()->getLog()->debug( 'Send Renewal Payment Pinterest' );
            $pinterestEvents = Pinterest()->generateEvents( $event );
            PinterestServer()->sendEventsNow( $pinterestEvents );
        }
    }
    /**
     * Tracks a completed purchase
     *
     * @param int $order_id the order ID
     */
    function woo_completed_purchase($order_id) {
        $log = $this->logger;

        if(!PYS()->getOption("woo_advance_purchase_fb_enabled")
            && !PYS()->getOption("woo_advance_purchase_ga_enabled")
            && ( !Tiktok()->enabled() || ( Tiktok()->enabled() && !Tiktok()->getOption( 'woo_advance_purchase_enabled' ) ) )
			&& ( !Pinterest()->enabled() || ( Pinterest()->enabled() && !Pinterest()->getOption( 'woo_advance_purchase_enabled' ) ) )
        ) {
            return false;
        }

        $order = wc_get_order($order_id);



        if(!$order
            || !PYS()->getOption( 'woo_purchase_enabled' )
            || isDisabledForUserRole($order->get_user()) ) {
            return false;
        }

        add_filter("pys_woo_checkout_order_id",function () use ($order_id) {return $order_id;});
        $event = EventsWoo()->getEvent('woo_purchase', true);

        if ( $event == null ) {
            return false;
        }
        $event->addPayload(['eventID'=>EventIdGenerator::guidv4()]);

        $event->addParams(array('advanced_purchase_tracking' => true));
        if(PYS()->getOption("woo_advance_purchase_ga_enabled") ) {// send GA
            GA()->getLog()->debug("Send completed purchase GA");
            $gaEvents = GA()->generateEvents($event);
            if(!empty($gaEvents)){
                GaMeasurementProtocolAPI()->sendEventsNow($gaEvents);
            }
        }

        if(PYS()->getOption( 'woo_purchase_on_transaction' ) && $order->get_meta( '_pys_purchase_event_fired', true )) {
            return false;
        }

        // Protection #1: Block ALL AJAX requests (frontend checkout)
        // Note: wp_doing_ajax() only returns true for admin-ajax.php requests
        // Payment gateway webhooks use wc-api parameter and do NOT trigger wp_doing_ajax()
        if ( (function_exists('wp_doing_ajax') && wp_doing_ajax()) || isset($_REQUEST['wc-ajax']) ) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'unknown';
            $log->debug("Blocked woo_completed_purchase: AJAX request detected - action: {$action}");
            return;
        }

        // Protection #2: Block AngellEYE PayPal for WooCommerce API requests
        // AngellEYE uses wc-api parameter with "angelleye" in the value (case-insensitive)
        if ( isset($_REQUEST['wc-api']) && stripos($_REQUEST['wc-api'], 'angelleye') !== false ) {
            $wc_api = sanitize_text_field($_REQUEST['wc-api']);
            $log->debug("Blocked woo_completed_purchase: AngellEYE PayPal API request detected - wc-api: {$wc_api}");
            return;
        }

        if(PYS()->getOption("woo_advance_purchase_fb_enabled") ) {//send fb server events
            Facebook()->getLog()->debug("Send  FB");
            $fbEvents = Facebook()->generateEvents($event);
            FacebookServer()->sendEventsNow($fbEvents);
        }

		if( Tiktok()->enabled()
            && Tiktok()->isServerApiEnabled()
            && Tiktok()->getOption( "woo_advance_purchase_enabled" )
        ) {// send Tiktok Completed Payment event
			Tiktok()->getLog()->debug( 'Send Completed Payment Tiktok' );
			$tiktokEvents = Tiktok()->generateEvents( $event );
			TikTokServer()->sendEventsNow( $tiktokEvents );
		}
		if( Pinterest()->enabled()
            && method_exists( Pinterest(), 'isServerApiEnabled' )
            && Pinterest()->isServerApiEnabled()
            && Pinterest()->getOption( "woo_advance_purchase_enabled" )
        ) {// send Pinterest Checkout
			Pinterest()->getLog()->debug( 'Send Checkout Pinterest' );
			$pinterestEvents = Pinterest()->generateEvents( $event );
			PinterestServer()->sendEventsNow( $pinterestEvents );
		}
        if ( isWooCommerceVersionGte('3.0.0') ) {
            // WooCommerce >= 3.0
            if($order) {
                $order->update_meta_data( '_pys_advance_purchase_event_fired', true );
                if(!$order->get_meta( 'traking_type', true ))
                {
                    $order->update_meta_data( 'traking_type', 'Advanced Purchase Tracking (APT)' );
                }
                $order->save();
            }

        } else {
            // WooCommerce < 3.0
            update_post_meta( $order_id, '_pys_advance_purchase_event_fired', true );
            if (!get_post_meta($order_id, "traking_type", true)) {
                update_post_meta( $order_id, 'traking_type', 'Advanced Purchase Tracking (APT)' );
            }
        }

    }
    /**
     * Tracks a refund purchase
     *
     * @param int $order_id the order ID
     */
    function woo_refund_order($order_id) {
        $log = $this->logger;
        $log->debug("Send woo_refund_order");
        if(!PYS()->getOption("woo_track_refunds_GA")) {
            return;
        }
        $order = wc_get_order($order_id);
        if(!$order) {
            return;
        }
        if(isDisabledForUserRole($order->get_user())) {
            return;
        }



        add_filter("pys_woo_checkout_order_id",function () use ($order_id) {return $order_id;});
        $event = EventsWoo()->getEvent('woo_refund');
        if ( $event == null ) {
            return;
        }
        if(PYS()->getOption("woo_track_refunds_GA") ) {// send GA

            $gaEvents = GA()->generateEvents($event);
            GaMeasurementProtocolAPI()->sendEventsNow($gaEvents);
            $log->debug("Send completed refund GA");
        }
        if ( isWooCommerceVersionGte('3.0.0') ) {
            // WooCommerce >= 3.0
            if($order) {
                $order->update_meta_data( '_pys_advance_purchase_event_fired', true );
                $order->save();
            }

        } else {
            // WooCommerce < 3.0
            update_post_meta( $order_id, '_pys_advance_purchase_event_fired', true );
        }
    }
    function woo_is_order_received_page() {
        if(is_order_received_page()) return true;
        global $post;
        $ids = PYS()->getOption("woo_checkout_page_ids");
        if(!empty($ids)) {
            if($post && in_array($post->ID,$ids)) {
                return true;
            }
        }
        if (did_action( 'elementor/loaded' )) {
            if ($post) {
                $elementor_page_id = get_option('elementor_woocommerce_purchase_summary_page_id');
                if ($elementor_page_id == $post->ID) return true;
            }
        }

        if(is_wc_endpoint_url( 'order-received')){
            return true;
        }

        return false;
    }

    public function isCachePreload() {
        if(!empty($_SERVER['HTTP_USER_AGENT'])){
	        $options = array('WP Rocket/Preload', 'WP-Rocket-Preload', 'WP Rocket/Homepage_Preload', 'lscache_runner', 'LiteSpeed Cache', 'LiteSpeed-Cache');
	        foreach($options as $row) {
		        if (stripos(strtolower($_SERVER['HTTP_USER_AGENT']), strtolower($row)) !== false) {
			        return true;
		        }
	        }
        }
        return false;
    }
	public function getCurrentUrl() {
		$protocol = ( ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$domainName = $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST);
		$requestUri = $_SERVER['REQUEST_URI'];
		return $protocol . $domainName . $requestUri;
	}

	public function get_wp_cookie_domain() {
		// Getting the site URL
		$site_url = get_site_url();

		// Parse domain from URL
		$host = parse_url($site_url, PHP_URL_HOST);

		// Remove the subdomain, if there is one, leaving the main domain
		$parts = explode('.', $host);
		if (count($parts) > 2) {
			$domain = '.' . $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
		} else {
			$domain = '.' . $host;
		}
		return $domain;
	}

    public function get_variation_info() {
        if (!empty($_POST['attributes']) && !empty($_POST['product_id'])) {
            $product_id = absint($_POST['product_id']);
            $attributes = $_POST['attributes'];

            $product = wc_get_product($product_id);

            if (!$product || !$product->is_type('variable')) {
                wp_send_json_error('Invalid product or not a variable product');
            }

            $variation_id = $this->find_matching_variation($product, $attributes);

            if (!$variation_id) {
                wp_send_json_error('No matching variation found');
            }

            // Get the variation object
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                wp_send_json_error('Variation not found');
            }

            $standardParams = getStandardParams();
            $this->eventsManager = new EventsManager();
            global $product;
            $product = wc_get_product($variation_id);
            // Set the global $post variable
            global $post;
            $post = get_post($variation_id);

            // Form data for pixels
            $events = [];
            $eventId = EventIdGenerator::guidv4();

            foreach ($this->getRegisteredPixels() as $pixel) {
                $tmp_pixel = $pixel;
                if (in_array($pixel->getSlug(), ['ga', 'google_ads'])) {
                    $tmp_pixel = GATags();
                }
                if (!$tmp_pixel->getOption('woo_variable_data_select_product') || $tmp_pixel->getOption('woo_variable_as_simple')) {
                    continue;
                }

                $view_content_event = new SingleEvent('woo_view_content', EventTypes::$TRIGGER, EventsWoo()->getSlug());
                $view_content_event->args = [
                    "id" => $variation_id,
                    "quantity" => 1,
                    "attributes" => $attributes // Pass variation attributes
                ];

                $pixel_view_content_event = $pixel->generateEvents($view_content_event);
                if (!empty($pixel_view_content_event)) {
                    $event = $pixel_view_content_event[0];
                    $event->addPayload(['eventID' => $eventId]);
	                $slug = $pixel->getSlug();
	                if( $slug !== "tiktok" && $slug !== "reddit" ) {
                        $event->addParams($standardParams);
                    }

                    $eventData = EventsManager::filterEventParams($event->getData(), "woo", [
                        'event_id' => $event->getId(),
                        'pixel' => $pixel->getSlug(),
                        'product_id' => $variation_id
                    ]);

                    $events[$pixel->getSlug()]['woo_view_content'] = $eventData;
                }
            }

            wp_send_json_success($events);
        } else {
            wp_send_json_error('Missing attributes or product ID');
        }
    }

    /**
     * Function to find variation ID by attributes
     */
    private function find_matching_variation($product, $attributes) {
        $available_variations = $product->get_available_variations();

        foreach ($available_variations as $variation) {
            $variation_id = $variation['variation_id'];
            $variation_attributes = $variation['attributes'];
            $match = true;
            foreach ($variation_attributes as $attribute_key => $attribute_value) {
                // If the product has an attribute, but it is empty (any)
                $match_any_value = empty($attribute_value);
                // If the variation has an attribute defined and its value does not match the passed ones, then it does not fit
                if (!$match_any_value && (!isset($attributes[$attribute_key]) || $attributes[$attribute_key] !== $attribute_value)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $variation_id;
            }
        }

        return false; // If nothing is found
    }

    public function saveExternalIDInOrder($order_param) {
        // Determine whether the WC_Order object or order ID is passed
        if ( $order_param instanceof WC_Order ) {
            // If the order object is transferred
            $order = $order_param;
        } else {
            // If order_id is passed
            $order = wc_get_order( $order_param );
        }
        if (!$order && empty($order_param)) return;

        $external_id = PYS()->get_pbid();

        if (isWooCommerceVersionGte('3.0.0') && !empty($order)) {
            // WooCommerce >= 3.0
            $order->update_meta_data("external_id", $external_id);
            $order->save();
        } elseif ( ! empty( $order_param ) ) {
            // WooCommerce < 3.0
            update_post_meta($order_param, 'external_id', $external_id);
        }

    }

    public function saveExternalIDInEddOrder($payment_id, $payment_data) {
        if ( empty( $payment_id ) ) {
            return;
        }

        $external_id = PYS()->get_pbid();

        if ( ! empty( $external_id ) ) {
            edd_update_payment_meta( $payment_id, 'external_id', $external_id );
        }
    }

}

/**
 * @return PYS
 */
function PYS() {
    return PYS::instance();
}