<?php
/**
 * Bootstrap
 *
 * Loads classes and creates an Environment subclass instance from
 * the specified lib location, with the specified prefix
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Bootstrap
 *
 * This is the entry point for.
 */
class Zoninator_REST_Bootstrap {
	public const MINIMUM_PHP_VERSION = '5.2.0';

	/**
	 * The Environment we will use
	 *
	 * @var null|object the Environment implementation.
	 */
	private $environment;

	/**
	 * The class loader we will use
	 *
	 * @var null|Zoninator_REST_Classloader
	 */
	private $class_loader;

	/**
	 * Construct a new Bootstrap
	 *
	 * @param null|Zoninator_REST_Interfaces_Classloader $class_loader The class loader to use.
	 */
	private function __construct( $class_loader = null ) {
		$this->class_loader = $class_loader;
	}

	/**
	 * Check compatibility of PHP Version.
	 *
	 * @return bool
	 */
	public static function is_compatible() {
		return version_compare( phpversion(), self::MINIMUM_PHP_VERSION, '>=' );
	}

	/**
	 * Get Base Dir
	 *
	 * @return string
	 */
	public static function get_base_dir() {
		return untrailingslashit( __DIR__ );
	}

	/**
	 * Create a Bootstrap, unless we are using a really early php version (< 5.3.0)
	 *
	 * @param Zoninator_REST_Interfaces_Classloader|null $class_loader The class loader to use.
	 * @return Zoninator_REST_Bootstrap|null
	 */
	public static function create( $class_loader = null ) {
		if ( empty( $class_loader ) ) {
			include_once __DIR__ . '/interfaces/class-zoninator-rest-interfaces-classloader.php';
			include_once __DIR__ . '/class-zoninator-rest-classloader.php';
			$prefix       = str_replace( '_Bootstrap', '', self::class );
			$base_dir     = self::get_base_dir();
			$class_loader = new Zoninator_REST_Classloader( $prefix, $base_dir );
		}
		return new self( $class_loader );
	}

	/**
	 * Run the app
	 *
	 * @return bool
	 */
	public function run() {
		if ( ! self::is_compatible() ) {
			return false;
		}
		$this->load()
			->environment()->start();
		return true;
	}

	/**
	 * Optional: Instead of calling load() you can
	 * register as an auto-loader
	 *
	 * @return Zoninator_REST_Bootstrap $this
	 */
	public function register_autoload() {
		if ( function_exists( 'spl_autoload_register' ) ) {
			spl_autoload_register( array( $this->class_loader(), 'load_class' ), true );
		}
		return $this;
	}

	/**
	 * Loads all classes
	 *
	 * @return Zoninator_REST_Bootstrap $this
	 * @throws Exception In case a class/file is not found.
	 */
	public function load() {
		$this->class_loader()
			->load_class( 'Interfaces_Data_Store' )
			->load_class( 'Interfaces_Registrable' )
			->load_class( 'Interfaces_Type' )
			->load_class( 'Interfaces_Model' )
			->load_class( 'Interfaces_Builder' )
			->load_class( 'Interfaces_Model_Collection' )
			->load_class( 'Interfaces_Controller' )
			->load_class( 'Interfaces_Controller_Bundle' )
			->load_class( 'Interfaces_Permissions_Provider' )
			->load_class( 'Exception' )
			->load_class( 'Expect' )
			->load_class( 'Environment' )
			->load_class( 'Type' )
			->load_class( 'Type_String' )
			->load_class( 'Type_Integer' )
			->load_class( 'Type_Number' )
			->load_class( 'Type_Boolean' )
			->load_class( 'Type_Array' )
			->load_class( 'Type_TypedArray' )
			->load_class( 'Type_Nullable' )
			->load_class( 'Type_Registry' )
			->load_class( 'Data_Store_Nil' )
			->load_class( 'Data_Store_Abstract' )
			->load_class( 'Data_Store_CustomPostType' )
			->load_class( 'Data_Store_Option' )
			->load_class( 'Permissions_Any' )
			->load_class( 'Field_Declaration' )
			->load_class( 'Field_Declaration_Builder' )
			->load_class( 'Model' )
			->load_class( 'Model_Settings' )
			->load_class( 'Model_Collection' )
			->load_class( 'Controller' )
			->load_class( 'Controller_Action' )
			->load_class( 'Controller_Model' )
			->load_class( 'Controller_Settings' )
			->load_class( 'Controller_Route' )
			->load_class( 'Controller_CRUD' )
			->load_class( 'Controller_Bundle' )
			->load_class( 'Controller_Extension' )
			->load_class( 'Controller_Bundle_Builder' );

		return $this;
	}

	/**
	 * Load Unit Testing Base Classes
	 *
	 * @return Zoninator_REST_Bootstrap $this
	 */
	public function load_testing_classes() {
		$this->class_loader()
			->load_class( 'Testing_TestCase' )
			->load_class( 'Testing_Model_TestCase' )
			->load_class( 'Testing_Controller_TestCase' );
		return $this;
	}

	/**
	 * Get the class loader
	 *
	 * @return Zoninator_REST_Classloader
	 */
	public function class_loader() {
		return $this->class_loader;
	}

	/**
	 * Lazy-load the environment
	 *
	 * @return Zoninator_REST_Environment
	 */
	public function environment() {
		if ( null === $this->environment ) {
			$this->environment = new Zoninator_REST_Environment( $this );
		}
		return $this->environment;
	}
}
