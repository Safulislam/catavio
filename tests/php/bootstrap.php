<?php
/**
 * Unit test bootstrap.
 *
 * These tests deliberately run without WordPress or WooCommerce loaded. The
 * check rules operate on a ProductSnapshot, a plain data object, so the whole
 * rule set can be exercised in milliseconds and without a database. The small
 * number of WordPress functions the rules touch are stubbed below.
 *
 * WooCommerce integration is verified separately, against a real catalog.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

// Every plugin file guards on this, so it must exist before autoloading.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

/**
 * Stubbed meta store, keyed by "object_id:meta_key".
 *
 * @var array<string, mixed>
 */
$GLOBALS['catavio_test_meta'] = array();

/**
 * Stubbed option store.
 *
 * @var array<string, mixed>
 */
$GLOBALS['catavio_test_options'] = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub: returns the string unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Escaping translation stub.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );

		return $text;
	}
}

// Callbacks registered by a test, keyed by hook name, and every action fired.
$GLOBALS['catavio_test_hooks']   = array();
$GLOBALS['catavio_test_actions'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Hook registration stub. Priority is ignored; callbacks run in the order
	 * they were added.
	 *
	 * @param string   $tag           Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Ignored.
	 * @param int      $accepted_args Number of arguments passed to the callback.
	 * @return true
	 */
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $priority );

		$GLOBALS['catavio_test_hooks'][ $tag ][] = array( $callback, (int) $accepted_args );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Action registration stub.
	 *
	 * @param string   $tag           Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Ignored.
	 * @param int      $accepted_args Number of arguments passed to the callback.
	 * @return true
	 */
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $tag, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter stub: runs callbacks a test registered, otherwise returns the
	 * value unchanged.
	 *
	 * @param string $tag     Filter name.
	 * @param mixed  $value   Value to filter.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	function apply_filters( $tag, $value, ...$args ) {
		if ( empty( $GLOBALS['catavio_test_hooks'][ $tag ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['catavio_test_hooks'][ $tag ] as $hook ) {
			$value = call_user_func_array( $hook[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $hook[1] ) ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Action stub: records the call, then runs callbacks a test registered.
	 *
	 * @param string $tag     Action name.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( $tag, ...$args ) {
		$GLOBALS['catavio_test_actions'][] = array(
			'tag'  => $tag,
			'args' => $args,
		);

		if ( empty( $GLOBALS['catavio_test_hooks'][ $tag ] ) ) {
			return;
		}

		foreach ( $GLOBALS['catavio_test_hooks'][ $tag ] as $hook ) {
			call_user_func_array( $hook[0], array_slice( $args, 0, $hook[1] ) );
		}
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Tag stripping stub, matching WordPress behaviour closely enough.
	 *
	 * @param string $text          Text to strip.
	 * @param bool   $remove_breaks Whether to collapse whitespace.
	 * @return string
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = (string) $text;
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( (string) $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', (string) $text );
		}

		return trim( (string) $text );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Meta stub backed by the $catavio_test_meta global.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @param bool   $single    Whether to return a single value.
	 * @return mixed
	 */
	function get_post_meta( $object_id, $key = '', $single = false ) {
		$index = $object_id . ':' . $key;

		if ( ! isset( $GLOBALS['catavio_test_meta'][ $index ] ) ) {
			return $single ? '' : array();
		}

		return $GLOBALS['catavio_test_meta'][ $index ];
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option stub backed by the $catavio_test_options global.
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Value returned when the option is unset.
	 * @return mixed
	 */
	function get_option( $option, $default_value = false ) {
		if ( ! isset( $GLOBALS['catavio_test_options'][ $option ] ) ) {
			return $default_value;
		}

		return $GLOBALS['catavio_test_options'][ $option ];
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Option write stub.
	 *
	 * @param string $option   Option name.
	 * @param mixed  $value    Value to store.
	 * @param bool   $autoload Ignored.
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		unset( $autoload );

		$GLOBALS['catavio_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Option delete stub.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		unset( $GLOBALS['catavio_test_options'][ $option ] );

		return true;
	}
}

if ( ! defined( 'CATAVIO_VERSION' ) ) {
	define( 'CATAVIO_VERSION', '1.0.0' );
	define( 'CATAVIO_MIN_PHP', '7.4' );
	define( 'CATAVIO_MIN_WP', '6.4' );
	define( 'CATAVIO_MIN_WC', '8.0' );
	define( 'CATAVIO_PLUGIN_BASENAME', 'catavio/catavio.php' );
}

/**
 * Stubbed role capability map: role name => array of capabilities.
 *
 * @var array<string, string[]>
 */
$GLOBALS['catavio_test_roles'] = array();

/**
 * Capabilities the stubbed current user holds.
 *
 * @var string[]
 */
$GLOBALS['catavio_test_user_caps'] = array();

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Site info stub.
	 *
	 * @param string $show What to return.
	 * @return string
	 */
	function get_bloginfo( $show = '' ) {
		if ( 'version' === $show ) {
			return isset( $GLOBALS['catavio_test_wp_version'] ) ? $GLOBALS['catavio_test_wp_version'] : '6.4';
		}

		return '';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability stub for the current user.
	 *
	 * @param string $capability Capability to test.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return in_array( $capability, $GLOBALS['catavio_test_user_caps'], true );
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	/**
	 * Roles stub exposing role_objects, as WP_Roles does.
	 *
	 * @return object
	 */
	function wp_roles() {
		$objects = array();

		foreach ( $GLOBALS['catavio_test_roles'] as $name => $caps ) {
			$objects[ $name ] = new \Catavio\Tests\Unit\FakeRole( $caps );
		}

		return (object) array( 'role_objects' => $objects );
	}
}

/**
 * Records every meta write, so tests can assert that unchanged data is not
 * rewritten.
 *
 * @var array<int, array{op: string, id: int, key: string}>
 */
$GLOBALS['catavio_test_meta_writes'] = array();

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Post meta write stub.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @param mixed  $value     Value.
	 * @return bool
	 */
	function update_post_meta( $object_id, $key, $value ) {
		$GLOBALS['catavio_test_meta_writes'][] = array(
			'op'  => 'update',
			'id'  => (int) $object_id,
			'key' => (string) $key,
		);

		$GLOBALS['catavio_test_meta'][ $object_id . ':' . $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_post_meta' ) ) {
	/**
	 * Post meta append stub.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @param mixed  $value     Value.
	 * @param bool   $unique    Ignored.
	 * @return bool
	 */
	function add_post_meta( $object_id, $key, $value, $unique = false ) {
		unset( $unique );

		$GLOBALS['catavio_test_meta_writes'][] = array(
			'op'  => 'add',
			'id'  => (int) $object_id,
			'key' => (string) $key,
		);

		$index = $object_id . ':' . $key;

		if ( ! isset( $GLOBALS['catavio_test_meta'][ $index ] ) || ! is_array( $GLOBALS['catavio_test_meta'][ $index ] ) ) {
			$GLOBALS['catavio_test_meta'][ $index ] = array();
		}

		$GLOBALS['catavio_test_meta'][ $index ][] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Post meta delete stub.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @return bool
	 */
	function delete_post_meta( $object_id, $key ) {
		$GLOBALS['catavio_test_meta_writes'][] = array(
			'op'  => 'delete',
			'id'  => (int) $object_id,
			'key' => (string) $key,
		);

		unset( $GLOBALS['catavio_test_meta'][ $object_id . ':' . $key ] );

		return true;
	}
}

if ( ! function_exists( 'register_post_meta' ) ) {
	/**
	 * Meta registration stub.
	 *
	 * @param string $object_type Object type.
	 * @param string $key         Meta key.
	 * @param array  $args        Registration arguments.
	 * @return bool
	 */
	function register_post_meta( $object_type, $key, $args = array() ) {
		$GLOBALS['catavio_test_registered_meta'][ $key ] = $args;

		return true;
	}
}

/**
 * Records every menu registration, so a test can assert what was registered.
 *
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['catavio_test_menu_pages'] = array();

/**
 * Records every submenu registration.
 *
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['catavio_test_submenu_pages'] = array();

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * Top level menu registration stub.
	 *
	 * @param string $page_title Page title.
	 * @param string $menu_title Menu title.
	 * @param string $capability Required capability.
	 * @param string $menu_slug  Menu slug.
	 * @param mixed  $callback   Render callback.
	 * @param string $icon       Menu icon.
	 * @param int    $position   Menu position.
	 * @return string
	 */
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = null, $icon = '', $position = null ) {
		$GLOBALS['catavio_test_menu_pages'][] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
			'icon'       => $icon,
			'position'   => $position,
		);

		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Submenu registration stub.
	 *
	 * @param string $parent_slug Parent slug.
	 * @param string $page_title  Page title.
	 * @param string $menu_title  Menu title.
	 * @param string $capability  Required capability.
	 * @param string $menu_slug   Menu slug.
	 * @param mixed  $callback    Render callback.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = null ) {
		$GLOBALS['catavio_test_submenu_pages'][] = array(
			'parent_slug' => $parent_slug,
			'menu_slug'   => $menu_slug,
			'menu_title'  => $menu_title,
			'callback'    => $callback,
		);

		return $parent_slug . '_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Admin URL stub.
	 *
	 * @param string $path Path relative to wp-admin.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Query argument stub, covering both signatures WordPress accepts.
	 *
	 * @param string|array $key   Argument name, or a map of arguments.
	 * @param mixed        $value Argument value, or the URL when $key is a map.
	 * @param string       $url   URL, when $key is an argument name.
	 * @return string
	 */
	function add_query_arg( $key, $value = null, $url = null ) {
		if ( is_array( $key ) ) {
			$params = $key;
			$target = (string) $value;
		} else {
			$params = array( $key => $value );
			$target = (string) $url;
		}

		$parts = explode( '?', $target, 2 );
		$query = array();

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		return $parts[0] . '?' . http_build_query( array_merge( $query, $params ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Key sanitisation stub.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Slash removal stub.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

$GLOBALS['catavio_test_taxonomies'] = array();
$GLOBALS['catavio_test_terms']      = array();

if ( ! function_exists( 'taxonomy_exists' ) ) {
	/**
	 * Taxonomy stub: exists when a test lists it.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, $GLOBALS['catavio_test_taxonomies'], true );
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	/**
	 * Term stub: returns the terms a test assigned, or false.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return object[]|false
	 */
	function get_the_terms( $post_id, $taxonomy ) {
		return isset( $GLOBALS['catavio_test_terms'][ $post_id ][ $taxonomy ] ) ? $GLOBALS['catavio_test_terms'][ $post_id ][ $taxonomy ] : false;
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	/**
	 * Pluck stub.
	 *
	 * @param array<int, object|array> $items Items.
	 * @param string                   $field Field name.
	 * @return array<int, mixed>
	 */
	function wp_list_pluck( $items, $field ) {
		$values = array();

		foreach ( $items as $item ) {
			$values[] = is_object( $item ) ? $item->$field : $item[ $field ];
		}

		return $values;
	}
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
