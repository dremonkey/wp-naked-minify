<?php
/*
Plugin Name: Naked Minify
Description: Responsible for concatenating and minifying css and js files. Uses the closure compiler to do the minification and concatenation. Used to be part of naked-utils but separated it into a separate plugin. 
Author: Andre Deutmeyer
Version: 0.1
*/


/*** Constant that can be checked by themes and plugins ***/
define( "NAKED_MINIFY", 1 );


/*** Plugin Name ***/
define( 'NAKED_MINIFY_PLUGIN_NAME', 'naked-compile-static' );


/*** Plugin Version ***/
define( 'NAKED_MINIFY_PLUGIN_VERSION', '0.1' );


/*** Plugin Path and URL ***/
define( 'NAKED_MINIFY_URL', plugin_dir_url( __FILE__ ) );
define( 'NAKED_MINIFY_PATH', trailingslashit( dirname( __FILE__ ) ) );


/*** Plugin Subdirectories ***/
define( 'NAKED_MINIFY_CLASSES_DIR', NAKED_MINIFY_PATH . 'classes/' );

/*** Plugin Javascript Directory and URL ***/
define( 'NAKED_MINIFY_JS_DIR', NAKED_MINIFY_PATH . 'js/' );
define( 'NAKED_MINIFY_JS_URL', NAKED_MINIFY_URL . 'js/' );

/** 
 * 
 * files are added through the 'plugins_loaded' hook so that we
 * can ensure that naked-utils is loaded before trying to use
 * classes and function declared there. 
 */
add_action( 'plugins_loaded', 'naked_minify_init' );

// warn if naked-utils is not installed
add_action( 'admin_notices', 'naked_minify_activation_notice');

/**
 * Initializes all files
 */
function naked_minify_init()
{
  // include vendor files
  require_once( NAKED_MINIFY_PATH . 'vendor/closure/php-closure.php' );

  // include classes
  require_once( NAKED_MINIFY_PATH . 'classes/lazy-load-js.class.php' );
  require_once( NAKED_MINIFY_PATH . 'classes/lazy-load-css.class.php' );

  // include template tags
  // require_once( NAKED_MINIFY_PATH . 'template-tags.php' );

  // include options
  require_once( NAKED_MINIFY_PATH . 'settings.php' );

  naked_minify_lazy_load_css::get_instance();
  naked_minify_lazy_load_js::get_instance();
  naked_minify_settings_controller::get_instance();
}

function naked_minify_activation_notice()
{
  if( !defined ( 'NAKED_UTILS' ) ) {
    if( current_user_can( 'install_plugins' ) ) {
      echo '<div class="error"><p>';
      printf( __('Naked Ads requires Naked Utils to work. Please make sure that you have installed and activated <a href="%s">Naked Utils</a>. They are like peas in a pod.', 'naked_ads' ), '#' );
      echo "</p></div>";
    }
  }
}