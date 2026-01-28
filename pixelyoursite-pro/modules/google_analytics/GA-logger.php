<?php

namespace PixelYourSite;
defined( 'ABSPATH' ) || exit;

class GA_logger extends PYS_Logger  {

	protected $log_path = null;

    public function init() {
		$this->isEnabled = GA()->getOption('logs_enable');
		// Protection files are created by parent PYS_Logger (same logs directory)
	}

	public static function get_log_file_name( ) {
		return 'ga_debug_' . self::get_log_suffix() . '.log';
	}

	public static function get_log_file_path( ) {
		return trailingslashit( PYS_PATH ).'logs/' . self::get_log_file_name( );
	}

	public static function get_log_file_url( ) {
		return trailingslashit( PYS_URL ) .'logs/'. static::get_log_file_name( );
	}
}