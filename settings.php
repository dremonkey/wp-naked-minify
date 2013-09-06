<?php


class naked_minify_settings_controller extends nu_settings
{
  /*** private static variables ***/
  private static $_debug      = false; // toggle debug information
  private static $_instance   = null; // stores class instance


  public $options_page_key; // used to create the page slug
  public $options_group;    // ... can't remember ... - Andre
  public $options_key;      // var name used to store the options in the db
  public $cap_level;        // the user capability level required for access
  public $page_title;       // the settings page title
  public $menu_title;       // the settings page menu title


  /**
   * get_instance
   *
   * Retrieves an instance of this class or creates a new one if it doesn't exist
   */
  static function get_instance() {

    if( null === self::$_instance )
      self::$_instance = new self();

    return self::$_instance;
  } 


  private function __construct()
  {
    // initialize the settings page
    $this->init_settings();

    // load javascript
    add_action( 'admin_init', array( &$this, 'reg_js' ) );
    add_filter( 'naked_minify_load_js', array( &$this, 'load_js' ) );
    add_action( 'naked_minify_after_load_js', array( &$this, 'set_js_vars' ) );

    // set up ajax
    add_action( 'wp_ajax_recompile-styles', array( &$this, 'ajax_recompile_styles' ) );
    add_action( 'wp_ajax_clear-compiled-styles', array( &$this, 'ajax_clear_compiled_styles' ) );
    add_action( 'wp_ajax_build-js-cache', array( &$this, 'ajax_build_js_cache' ) );
    add_action( 'wp_ajax_clear-js-cache', array( &$this, 'ajax_clear_js_cache' ) );
  }


  /**
   * Sets the values of all class variables. Called by self::init_settings().
   */
  public function set_class_vars()
  {
    $this->options_page_key = 'naked_minify';
    $this->options_group    = 'naked_minify_options_group';
    $this->options_key      = 'naked_minify_options';
    $this->cap_level        = 'manage_options';
    $this->page_title       = __( 'Naked Minify Settings', 'naked_minify' );
    $this->menu_title       = __( 'Naked Minify', 'naked_minify' );
  }


  /**
   * Called by self::reg_setting_sections().
   *
   * @return (array) A list of setting sections.
   */
  public function get_setting_sections()
  {
    $sections = array(
      'section_optimize' => array(
        'title'     => 'Optimization',
        'callback'  => array( &$this, 'get_section_desc' ),
        'page'      => $this->options_page_key,
      ),
    );

    $sections = apply_filters( 'naked_minify_setting_sections', $sections );

    return $sections;
  }


  /**
   * Called by self::reg_setting_fields().
   *
   * @return (array) A list of setting fields
   */
  public function get_setting_fields()
  {
    $fields = array(
      // if the css should be compiled
      'compile_css' => array(
        'title'     => 'Compile Stylesheets',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'compile_css',
          'type' => 'checkbox',
          'desc' => __( 'Check to enable stylesheet concatenation', 'naked_minify' ),
        ) 
      ),
       // if the js should be compiled
      'compile_js' => array(
        'title'     => 'Compile Javascript',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'compile_js',
          'type' => 'checkbox',
          'desc' => __( 'Check to enable javascript concatenation and minification', 'naked_minify' ),
        ) 
      ),
      'clear_compiled_styles' => array(
        'title'     => 'Delete Compiled CSS',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'clear_compiled_styles',
          'type' => 'button',
          'desc' => __( 'Delete Compiled CSS' ),
        )
      ),
      'clear_js_cache' => array(
        'title'     => 'Delete Javascript Cache',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'clear_js_cache',
          'type' => 'button',
          'desc' => __( 'Delete JS Cache' ),
        ) 
      ),
      'recompile_styles' => array(
        'title'     => 'Recompile CSS Files',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'recompile_styles',
          'type' => 'button',
          'desc' => __( 'Recompile CSS' ),
        ) 
      ),
      'build_js_cache' => array(
        'title'     => 'Build Javascript Cache',
        'callback'  => array( &$this, 'build_form_fields' ),
        'page'      => $this->options_page_key,
        'section'   => 'section_optimize',
        'args'      => array(
          'id'   => 'build_js_cache',
          'type' => 'button',
          'desc' => __( 'Build JS Cache' ),
        ) 
      ),
    );

    return $fields;
  }


  /**
   Helpers
   */


  /**
   * @return (array) the default options values 
   */
  public function get_default_option_values()
  {
    return array(
      'compile_css' => 0,
      'compile_js'  => 0,
    );
  }


  /**
   * Retrieves a section description
   *
   * We don't need a section desc so just returning an empty string
   */
  public function get_section_desc( $args )
  {
    return '';
  }


  /**
   Javascript and AJAX 
   */


  public function reg_js()
  {
    $bn = 'naked-minify-';
    $deps = array('jquery');
    wp_register_script($bn.'admin-settings', NAKED_MINIFY_JS_URL . 'admin/jquery.naked-minify-settings.js', $deps, NAKED_MINIFY_PLUGIN_VERSION, false );

  }


  public function load_js( $scripts )
  {
    $bn = 'naked-minify-';
    $page = 'settings_page_' . $this->options_page_key;
    $scripts['admin'][$page][] = $bn.'admin-settings';
    return $scripts;
  }


  public function set_js_vars()
  {
    $bn = 'naked-minify-';

    // create a nonce
    $ccss_nonce = wp_create_nonce( 'ajax_clear_compiled_styles_nonce' );
    $rcss_nonce = wp_create_nonce( 'ajax_recompile_styles_nonce' );
    $cjs_nonce  = wp_create_nonce( 'ajax_clear_js_cache_nonce' );
    $bjs_nonce  = wp_create_nonce( 'ajax_build_js_cache_nonce' ); 

    $data = array(
      '_clear_compiled_styles'  => $ccss_nonce,
      '_recompile_styles'       => $rcss_nonce,
      '_clear_js_cache'         => $cjs_nonce,
      '_build_js_cache'         => $bjs_nonce
    );

    wp_localize_script( $bn.'admin-settings', 'naked_minify_settings', $data );
  }

  public function ajax_recompile_styles()
  {
    $r = new stdClass();
    $nonce = $_POST['nonce'];

    if ( !wp_verify_nonce( $nonce, 'ajax_recompile_styles_nonce' ) ) {
      $r->error = array( 'msg' => 'Incorrect nonce. Cannot build styles cache.' );
    }
    else {
      require_once( NAKED_MINIFY_CLASSES_DIR . 'compile-css.class.php' );
      naked_minify_recompile_styles();
      $class = new naked_minify_compile_css();
      $r->success = array( 'msg' => 'Finished recompiling styles.' );
    }

    echo json_encode( $r );
    exit;
  }


  public function ajax_clear_compiled_styles()
  {
    $r = new stdClass();
    $nonce = $_POST['nonce'];

    if ( !wp_verify_nonce( $nonce, 'ajax_clear_compiled_styles_nonce' ) ) {
      $r->error = array( 'msg' => 'Incorrect nonce. Nothing deleted.' );
    }
    else {
      require_once( NAKED_MINIFY_CLASSES_DIR . 'compile-css.class.php' );
      $class = new naked_minify_compile_css();
      $r->success = $class->clear_compiled_styles();
    }

    echo json_encode( $r );
    exit;
  }

  public function ajax_build_js_cache()
  {
    $r = new stdClass();
    $nonce = $_POST['nonce'];

    if ( !wp_verify_nonce( $nonce, 'ajax_build_js_cache_nonce' ) ) {
      $r->error = array( 'msg' => 'Incorrect nonce. Cannot build scripts cache.' );
    }
    else {
      require_once( NAKED_MINIFY_CLASSES_DIR . 'compile-js.class.php' );
      naked_minify_rebuild_scripts_cache();
      $r->success = array( 'msg' => 'Scripts cache built.' );
    }

    echo json_encode( $r );
    exit;
  }

  public function ajax_clear_js_cache()
  {
    $r = new stdClass();
    $nonce = $_POST['nonce'];

    if ( !wp_verify_nonce( $nonce, 'ajax_clear_js_cache_nonce' ) ) {
      $r->error = array( 'msg' => 'Incorrect nonce. Nothing deleted.' );
    }
    else {
      require_once( NAKED_MINIFY_CLASSES_DIR . 'compile-js.class.php' );
      $class = new naked_minify_compile_js();
      $r->success = $class->clear_js_cache();
    }

    echo json_encode( $r );
    exit;
  }
}


/**
 Template Tags
 */

function naked_minify_get_option( $option )
{
  $settings = naked_minify_settings_controller::get_instance();
  return $settings->get_option_value( $option );
}