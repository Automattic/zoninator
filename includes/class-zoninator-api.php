<?php

class Zoninator_Api {
	/**
	 * Instance
	 *
	 * @var Zoninator
	 */
	private $instance;

	/**
	 * Bootstrap
	 *
	 * @var Zoninator_REST_Bootstrap
	 */
	public $bootstrap;

	/**
	 * Zoninator_Api constructor.
	 *
	 * @param Zoninator $instance The Zoninator.
	 */
	public function __construct( $instance ) {
		$this->instance = $instance;
		add_action( 'rest_api_init', array( $this, 'rest_api' ) );
	}

	/**
	 * Rest Api.
	 */
	public function rest_api() {
		include_once ZONINATOR_PATH . '/lib/zoninator_rest/class-zoninator-rest-bootstrap.php';
		$this->bootstrap = Zoninator_REST_Bootstrap::create()->load();
		include_once __DIR__ . '/class-zoninator-api-schema-converter.php';
		include_once __DIR__ . '/class-zoninator-api-filter-search.php';
		include_once __DIR__ . '/class-zoninator-api-controller.php';
		$env = $this->bootstrap->environment();

		$env->define_model( 'Zoninator_Api_Filter_Search' );

		$env->rest_api( 'zoninator/v1' )
			->add_endpoint( new Zoninator_Api_Controller( $this->instance ) );
		$env->start();
		return $this;
	}
}
