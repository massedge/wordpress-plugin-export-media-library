<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary;

class Plugin {
	protected $options = array();

	/** @var Module\Base[] $modules */
	protected $modules = null;

	function __construct( $options ) {
		$this->options = $options;
	}

	function registerHooks() {
		$this->modules = array(
			new Module\AdminPageExport(),
		);

		// register module hooks
		foreach ( $this->modules as $module ) {
			$module->registerHooks();
		}
	}
}
