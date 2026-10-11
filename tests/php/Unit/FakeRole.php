<?php
/**
 * Minimal stand-in for WP_Role.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

/**
 * Answers has_cap() from a fixed capability list.
 */
class FakeRole {

	/**
	 * Capabilities this role holds.
	 *
	 * @var string[]
	 */
	private $caps;

	/**
	 * Constructor.
	 *
	 * @param string[] $caps Capabilities this role holds.
	 */
	public function __construct( array $caps ) {
		$this->caps = $caps;
	}

	/**
	 * Whether the role holds a capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function has_cap( $capability ) {
		return in_array( $capability, $this->caps, true );
	}
}
