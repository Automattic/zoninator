<?php
/**
 * Environment
 *
 * Contains variables, rest api, type and model definitions
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Environment
 *
 * Our global Environment
 *
 * @package Mixtape
 */
class Zoninator_REST_Environment {
	public const REGISTRABLE = 'IRegistrable';
	public const BUNDLES     = 'Bundles';
	public const MODELS      = 'Models';

	/**
	 * This environment's registered REST bundles
	 *
	 * (used for versioned APIs, or logical grouping of related api parts)
	 *
	 * @var array
	 */
	protected $rest_apis;

	/**
	 * This environment's model definitions
	 *
	 * @var array
	 */
	protected $model_definitions;

	/**
	 * The Environment Variables
	 *
	 * A key-value array of things.
	 *
	 * @var array
	 */
	protected $variables;

	/**
	 * Did this Environment start?
	 *
	 * @var bool
	 */
	private $has_started;

	/**
	 * Our Bootstrap
	 *
	 * @var Zoninator_REST_Bootstrap
	 */
	private $bootstrap;

	/**
	 * Our Type Registry
	 *
	 * @var Zoninator_REST_Type_Registry
	 */
	private $type_registry;

	/**
	 * Mixtape_Environment constructor.
	 *
	 * @param Zoninator_REST_Bootstrap $bootstrap The bootstrap.
	 */
	public function __construct( $bootstrap ) {
		$this->bootstrap         = $bootstrap;
		$this->has_started       = false;
		$this->rest_apis         = array();
		$this->variables         = array();
		$this->model_definitions = array();
		$this->type_registry     = new Zoninator_REST_Type_Registry();
		$this->type_registry->initialize( $this );
		// initialize our array vars.
		$this->array_var( self::MODELS )
			->array_var( self::REGISTRABLE )
			->array_var( self::BUNDLES );
	}

	/**
	 * Push a Builder to the Environment.
	 *
	 * All builders are evaluated lazily when needed
	 *
	 * @param string                            $where The queue to push the builder to.
	 * @param Zoninator_REST_Interfaces_Builder $builder The builder to push.
	 *
	 * @return Zoninator_REST_Environment $this
	 * @throws Zoninator_REST_Exception In case the $builder is not a Mixtape_Interfaces_Builder.
	 */
	public function push_builder( $where, $builder ) {
		Zoninator_REST_Expect::that( is_string( $where ), '$where should be a string' );
		Zoninator_REST_Expect::is_a( $builder, 'Zoninator_REST_Interfaces_Builder' );
		return $this->array_var( $where, $builder );
	}

	/**
	 * Retrieve a previously defined Zoninator_REST_Model
	 *
	 * @param string $class the class name.
	 * @return Zoninator_REST_Model the definition.
	 * @throws Zoninator_REST_Exception Throws in case the model is not registered.
	 */
	public function model( $class ) {
		if ( ! class_exists( $class ) ) {
			throw new Zoninator_REST_Exception( $class . ' does not exist' );
		}
		Zoninator_REST_Expect::that( isset( $this->model_definitions[ $class ] ), $class . ' definition does not exist' );
		return $this->model_definitions[ $class ];
	}

	/**
	 * Time to build pending models and bundles
	 *
	 * @param string $type One of (models, bundles).
	 * @return Zoninator_REST_Environment
	 */
	private function load_pending_builders( $type ) {
		$things = $this->get( $type );
		if ( ! empty( $things ) && is_array( $things ) ) {
			foreach ( $things as $pending ) {
				/**
				 * Our pending builder.
				 *
				 * @var Zoninator_REST_Interfaces_Builder $pending Our builder.
				 */
				if ( self::BUNDLES === $type ) {
					$this->add_rest_bundle( $pending->build() );
				}
			}
		}

		return $this;
	}

	/**
	 * Start things up
	 *
	 * This should be called once our Environment is set up to our liking.
	 * Evaluates all Builders, creating missing REST Api and Model Definitions.
	 *
	 *
	 * It is recommended we hook this into 'rest_api_init'.
	 *
	 * @return Zoninator_REST_Environment $this
	 */
	public function start() {
		if ( ! $this->bootstrap->is_compatible() ) {
			// Do not even start on an incompatible system.
			return $this;
		}

		if ( false === $this->has_started ) {
			do_action( 'mt_environment_before_start', $this, get_class( $this ) );
			$this->load_pending_builders( self::MODELS );
			$this->load_pending_builders( self::BUNDLES );
			$registrables = $this->get( self::REGISTRABLE ) ?: array();
			foreach ( $registrables as $registrable ) {
				/**
				 * A Registrable
				 *
				 * @var Zoninator_REST_Interfaces_Registrable $registrable
				 */
				$registrable->register( $this );
			}

			/**
			 * Use this hook to add/remove rest api bundles
			 *
			 * @param array          $rest_apis The existing rest apis.
			 * @param Zoninator_REST_Environment $this The Environment.
			 */
			$rest_apis = (array) apply_filters( 'mt_environment_get_rest_apis', $this->rest_apis, $this );

			foreach ( $rest_apis as $bundle ) {
				/**
				 * Register this bundle
				 *
				 * @var Zoninator_REST_Interfaces_Controller_Bundle
				 */
				$bundle->register( $this );
			}
			$this->has_started = true;
			do_action( 'mt_environment_after_start', $this );
		}

		return $this;
	}

	/**
	 * Add Registrable
	 *
	 * @param Zoninator_REST_Interfaces_Registrable $registrable_thing Registrable.
	 * @return Zoninator_REST_Environment
	 * @throws Zoninator_REST_Exception When not a Zoninator_REST_Interfaces_Registrable.
	 */
	public function add_registrable( $registrable_thing ) {
		Zoninator_REST_Expect::is_a( $registrable_thing, 'Zoninator_REST_Interfaces_Registrable' );
		$this->array_var( self::REGISTRABLE, $registrable_thing );
		return $this->define_var( get_class( $registrable_thing ), $registrable_thing );
	}

	/**
	 * Has Variable
	 *
	 * @param string $name Is this variable Set.
	 * @return bool
	 */
	public function has_variable( $name ) {
		return isset( $this->variables[ $name ] );
	}

	/**
	 * Append to an array
	 *
	 * @param string $name  The VarArray Name.
	 * @param mixed  $thing The thing.
	 * @return Zoninator_REST_Environment
	 */
	public function array_var( $name, $thing = null ) {
		return $this->define_var( $name, $thing, true );
	}

	/**
	 * Get A Variable
	 *
	 * @param string $name The Variable Name.
	 * @return mixed|null The variable or null
	 *
	 * @throws Zoninator_REST_Exception Name should be a string.
	 */
	public function get( $name ) {
		Zoninator_REST_Expect::that( is_string( $name ), '$name should be a string' );
		$value = $this->has_variable( $name ) ? $this->variables[ $name ] : null;
		/**
		 * Filter the variable value
		 *
		 * @param mixed          $value The value.
		 * @param Zoninator_REST_Environment $this The Environment.
		 * @param string         $name The var name.
		 *
		 * @return mixed
		 */
		return apply_filters( 'mt_variable_get', $value, $this, $name );
	}

	/**
	 * Def.
	 *
	 * @param string $name The Variable To Add.
	 * @param mixed  $thing The thing that is associated with the var.
	 * @param bool   $append If true, this var is a list.
	 *
	 * @return $this
	 *
	 * @throws Zoninator_REST_Exception When name is not a string.
	 */
	public function define_var( $name, $thing = null, $append = false ) {
		Zoninator_REST_Expect::that( is_string( $name ), '$name should be a string' );
		if ( $append && ! $this->has_variable( $name ) ) {
			$this->variables[ $name ] = array();
		}
		if ( null !== $thing ) {
			if ( $append ) {
				$this->variables[ $name ][] = $thing;
			} else {
				$this->variables[ $name ] = $thing;
			}
		}
		return $this;
	}

	/**
	 * Auto start on rest_api_init. For more control, use ::start();
	 */
	public function auto_start() {
		add_action( 'rest_api_init', array( $this, 'start' ) );
	}

	/**
	 * Get this Environment's bootstrap instance
	 *
	 * @return Zoninator_REST_Bootstrap our bootstrap.
	 */
	public function get_bootstrap() {
		return $this->bootstrap;
	}

	/**
	 * Create a new Field Declaration Builder
	 *
	 * @param null|string $name Optional, the field name.
	 * @param null|string $description Optional, the description.
	 * @param null|string $field_kind The field kind (default 'field').
	 *
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function field( $name = null, $description = null, $field_kind = null ) {
		$builder = new Zoninator_REST_Field_Declaration_Builder();

		if ( ! empty( $name ) ) {
			$builder->with_name( $name );
		}

		if ( ! empty( $description ) ) {
			$builder->with_description( $description );
		}

		if ( empty( $field_kind ) ) {
			$field_kind = Zoninator_REST_Field_Declaration::FIELD;
		}

		$builder->with_kind( $field_kind );

		return $builder;
	}

	/**
	 * Get our registered types
	 *
	 * @return Zoninator_REST_Type_Registry
	 */
	public function get_type_registry() {
		return $this->type_registry;
	}

	/**
	 * Get a known type definition
	 *
	 * @param string $type_name The type name.
	 * @return Zoninator_REST_Interfaces_Type
	 *
	 * @throws Zoninator_REST_Exception When provided with an unknown/invalid type.
	 */
	public function type( $type_name ) {
		return $this->get_type_registry()->definition( $type_name );
	}

	/**
	 * Define a new REST API Bundle.
	 *
	 * @param null|string|Zoninator_REST_Interfaces_Controller_Bundle $maybe_bundle_or_prefix The bundle name.
	 * @return Zoninator_REST_Controller_Bundle_Builder
	 */
	public function rest_api( $maybe_bundle_or_prefix = null ) {
		if ( is_a( $maybe_bundle_or_prefix, 'Zoninator_REST_Interfaces_Controller_Bundle' ) ) {
			$builder = new Zoninator_REST_Controller_Bundle_Builder( $maybe_bundle_or_prefix );
		} else {
			$builder = new Zoninator_REST_Controller_Bundle_Builder();
			if ( is_string( $maybe_bundle_or_prefix ) ) {
				$builder->with_prefix( $maybe_bundle_or_prefix );
			}
			$builder->with_environment( $this );
		}

		$this->push_builder( self::BUNDLES, $builder );
		return $builder;
	}

	/**
	 * Define a new Model
	 *
	 * @param string $declaration A Model class string.
	 *
	 * @return Zoninator_REST_Model
	 */
	function define_model( $declaration ) {
		Zoninator_REST_Expect::that( class_exists( $declaration ), '$declaration string should be an existing class' );
		Zoninator_REST_Expect::that( in_array( 'Zoninator_REST_Interfaces_Model', class_implements( $declaration ), true ), '$declaration does not implement Zoninator_REST_Interfaces_Model' );

		/**
		 * Create an empty Model to act as our factory (I know this is weird, see php5.2)
		 *
		 * @var Zoninator_REST_Model $factory
		 */
		$factory = new $declaration();
		$factory->with_environment( $this );
		$factory->with_data_store( new Zoninator_REST_Data_Store_Nil() );
		$factory->with_permissions_provider( new Zoninator_REST_Permissions_Any() );
		$this->model_definitions[ $declaration ] = $factory;
		return $factory;
	}

	/**
	 * Add a Bundle to our bundles (muse be Mixtape_Interfaces_Rest_Api_Controller_Bundle)
	 *
	 * @param Zoninator_REST_Interfaces_Controller_Bundle $bundle the bundle.
	 *
	 * @return Zoninator_REST_Environment $this
	 * @throws Zoninator_REST_Exception In case it's not a Zoninator_REST_Interfaces_Controller_Bundle.
	 */
	private function add_rest_bundle( $bundle ) {
		Zoninator_REST_Expect::is_a( $bundle, 'Zoninator_REST_Interfaces_Controller_Bundle' );
		$key                     = $bundle->get_prefix();
		$this->rest_apis[ $key ] = $bundle;
		return $this;
	}
}
