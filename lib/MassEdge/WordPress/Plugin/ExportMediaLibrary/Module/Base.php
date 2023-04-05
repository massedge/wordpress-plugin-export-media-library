<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary\Module;

abstract class Base {
	protected $options;

	public function __construct( array $options = array() ) {
		$this->options = $options;
	}

	abstract function registerHooks();
}
