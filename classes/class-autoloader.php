<?php
/**
 * Class autoloader.
 *
 * @package TimeMachine
 */

namespace TechWebUX\TimeMachine;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 *
 * Maps fully qualified class names in the TechWebUX\TimeMachine namespace
 * to files inside the classes directory, following the WordPress file
 * naming convention (class-name-here.php).
 */
class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 *
	 * @var string
	 */
	const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Register the autoloader with the SPL autoload stack.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class_name Fully qualified class name.
	 *
	 * @return void
	 */
	public static function autoload( $class_name ) {

		if ( 0 !== strpos( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );
		$relative_class = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );
		$file_name      = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';
		$file_path      = TIME_MACHINE_DIR . 'classes' . DIRECTORY_SEPARATOR . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}
