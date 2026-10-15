<?php
/** Exercise real menu icon discovery without WordPress or WooCommerce. */

define( 'ABSPATH', __DIR__ );

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ); }
function sanitize_text_field( $value ) { return strip_tags( (string) $value ); }
function esc_url_raw( $value ) { return (string) $value; }
function is_wp_error( $value ) { return false; }
function get_post_status( $id ) { return 'publish'; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_terms( $args ) { return array_map( static fn( $id ) => (object) array( 'term_id' => $id ), $GLOBALS['enabled'] ); }
function wp_get_object_terms( $ids, $taxonomy, $args ) { return $GLOBALS['assigned_menus']; }
function wp_get_nav_menu_items( $id, $args ) { return $GLOBALS['menus'][ $id ] ?? array(); }
function apply_filters( $hook, $value, ...$args ) { return $GLOBALS['declarations'][ $args[0] ] ?? $value; }
function plugins_url( $path, $file ) { return 'https://example.test/plugins/mrn-mega-menu/' . $path; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_enqueue_style( $handle, ...$args ) { $GLOBALS['styles'][] = $handle; }
function wp_enqueue_script( $handle, ...$args ) { $GLOBALS['scripts'][] = $handle; }
function mrn_shared_assets_enqueue_fontawesome( $handle, $context ) { $GLOBALS['styles'][] = $handle; }

require_once dirname( __DIR__, 2 ) . '/includes/class-plugin.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-renderer.php';

use MRN_Mega_Menu\Plugin;
use MRN_Mega_Menu\Renderer;

function fixture( array $panel = array(), array $assignments = array( 10 => 200 ) ) {
	$GLOBALS['meta'] = array( 200 => array( Plugin::META_LAYOUT => array( 'panels' => array( array_merge( array( 'menu_item_id' => 10, 'columns' => array( array( 'blocks' => array() ) ) ), $panel ) ) ) ) );
	$GLOBALS['menus'] = array( 1 => array( (object) array( 'ID' => 10 ), (object) array( 'ID' => 11 ) ) );
	$GLOBALS['assigned_menus'] = array( 1 );
	$GLOBALS['enabled'] = array( 1 );
	$GLOBALS['declarations'] = array();
	$GLOBALS['styles'] = array();
	$GLOBALS['scripts'] = array();
	( new ReflectionProperty( Renderer::class, 'assignments' ) )->setValue( null, $assignments );
}

function expect_needs( $name, $dashicons, $fontawesome ) {
	$expected = array( 'dashicons' => $dashicons, 'fontawesome' => $fontawesome );
	if ( $expected !== Renderer::get_icon_asset_needs() ) {
		throw new RuntimeException( $name . ' discovered incorrect icon requirements.' );
	}
	Renderer::enqueue_assets();
	$expected_styles = array( 'mrn-mega-menu' );
	if ( $dashicons ) { $expected_styles[] = 'dashicons'; }
	if ( $fontawesome ) { $expected_styles[] = 'mrn-mega-menu-fontawesome'; }
	if ( $GLOBALS['styles'] !== $expected_styles || array( 'mrn-mega-menu' ) !== $GLOBALS['scripts'] ) {
		throw new RuntimeException( $name . ' changed menu asset availability.' );
	}
}

fixture( array(), array() );
Renderer::enqueue_assets();
if ( $GLOBALS['styles'] || $GLOBALS['scripts'] ) { throw new RuntimeException( 'An unassigned menu loaded assets.' ); }

fixture();
expect_needs( 'Text-only native menu', false, false );

fixture( array( 'item_icon' => array( 'type' => 'media', 'value' => 'https://example.test/icon.svg' ) ) );
expect_needs( 'SVG trigger icon', false, false );

fixture( array( 'item_icon' => array( 'type' => 'dashicons', 'value' => 'dashicons-home' ) ) );
expect_needs( 'Configured trigger icon', true, false );

fixture( array( 'child_arrow_icon' => array( 'type' => 'fontawesome', 'value' => 'fa-solid fa-arrow-right' ) ) );
expect_needs( 'Inherited child arrow', false, true );

fixture();
$GLOBALS['meta'][11][ Plugin::ITEM_META_ARROW_ICON ] = array( 'type' => 'dashicons', 'value' => 'dashicons-arrow-right-alt2' );
expect_needs( 'Native descendant arrow', true, false );

fixture( array( 'columns' => array( array( 'blocks' => array( array( 'type' => 'menu', 'source' => 'selected', 'menu_id' => 2 ) ) ) ) ) );
$GLOBALS['menus'][2] = array( (object) array( 'ID' => 90 ) );
$GLOBALS['meta'][90][ Plugin::ITEM_META_ICON ] = '{"type":"fontawesome","value":"fa-solid fa-bolt"}';
expect_needs( 'Selected external menu icon', false, true );

fixture();
$GLOBALS['enabled'] = array();
$GLOBALS['meta'][10][ Plugin::ITEM_META_ICON ] = array( 'type' => 'fontawesome', 'value' => 'fa-solid fa-bolt' );
expect_needs( 'Legacy assignment menu icon', false, true );

$reusable = array( 'columns' => array( array( 'blocks' => array( array( 'type' => 'reusable', 'reusable_block_id' => 500 ) ) ) ) );
fixture( $reusable );
expect_needs( 'Unknown reusable renderer compatibility', true, true );

fixture( $reusable );
$GLOBALS['declarations'][500] = array( 'dashicons' => false, 'fontawesome' => false );
expect_needs( 'Explicit media-only reusable renderer', false, false );

fixture( $reusable );
$GLOBALS['declarations'][500] = array( 'dashicons' => false );
expect_needs( 'Incomplete declaration retains undeclared library', false, true );

fixture( $reusable );
$GLOBALS['declarations'][500] = 'invalid';
expect_needs( 'Malformed declaration compatibility', true, true );

echo "Mega Menu icon asset contract: 11 cases passed without WooCommerce.\n";
