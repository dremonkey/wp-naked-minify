<?php

require_once( 'compile-static.class.php' );

class naked_minify_compile_css extends naked_minify_compile_static
{
  // array of all the styles to be compiled
  private $_styles = array();


  // array of the compiled css files
  private $_compiled = array();


  // an array of styles that have already been added to the page
  private $_done = array();


  public function __construct( $styles = null, $rebuild = false )
  {
    // run the parent constructor
    parent::__construct(); 

    // abort if there was a problem with setup
    if( !empty( $this->_errors ) )
      return;

    if( $rebuild && !empty( $styles ) ) {
      $this->_styles = $styles;

      // delete all existing cache files
      $this->clear_compiled_styles();

      // build the cache files
      $this->_compile();
    }

    if( !is_admin() && !empty( $styles ) ) {

      $this->_styles = $styles;

      $this->_compile();

      add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_compiled_styles' ), 1 );
      add_action( 'login_enqueue_scripts', array( &$this, 'enqueue_compiled_styles' ), 1 );

      // remove compiled styles from the wordpress queue
      add_filter( 'print_styles_array', array( &$this, 'clean_styles_array' ) );
    }
  }


  private function _compile()
  {
    global $wp_styles;

    $debug = false;

    $durl = $this->_static_dir_url . 'css/';
    $dpath = $this->_static_dir_path . 'css/';

    $styles = $this->_styles;
    foreach( $styles as $section => $handles ) {

      // don't worry about the admin files
      if( 'admin' == $section ) continue;

      // file path to be used for the compiled css output
      $fpath = $dpath . $section . '.css';

      // grab the css file path using the registered handle
      $section_styles = array();
      foreach( $handles as $handle ) {
        $src = $wp_styles->registered[ $handle ]->src;
        $section_styles[ $handle ] = $this->_url_to_path( $src );
      }

      // if the contents have changed or the file does not exist, create and/or write the contents to the file. cachebusting will be done by changing the filename in the header, but the actual file name will remain the same. we have a rule in the .htaccess file that will make sure the correct file is loaded.
      if( $this->_is_recompile_needed( $fpath, $section_styles ) ) {
        
        if( $debug )
          nu_debug( $section . ' CSS Files To Be Concatenated', $section_styles );

        $out = $this->_concatenate_styles( $section_styles );
        file_put_contents( $fpath, $out );
      }

      // file url for the compiled script. this is what will be printed in the document when the page is generated, i.e. <script src=[$furl]></script>
      $fmod = filemtime( $fpath );
      $furl = $durl . $section . '.' . $fmod . '.css';

      if( '' != file_get_contents( $fpath ) )
        $this->_compiled[ $section ] = $furl;
    }

    if( $debug )
      nu_debug( 'CSS Compiled Files', $this->_compiled );
  }


  private function _is_recompile_needed( $compiled_file, $styles ) 
  {
    // If there is no cache file, we obviously need to recompile.
    if ( !is_readable( $compiled_file ) ) { 
      return true;
    }

    $mtime = filemtime( $compiled_file );

    // If any of the source files are newer than the cache file, recompile.
    foreach ( $styles as $handle=>$src ) {
      if ( filemtime( $src ) > $mtime ) { 
        return true;
      }
    }

    // Compiled file is up to date.
    return false;
  }


  private function _concatenate_styles( $styles ) 
  {
    $debug = false;

    $code = "";

    foreach ( $styles as $handle=>$src ) {

      $contents = file_get_contents( $src );

      if( '' != $contents )
        $code .= file_get_contents( $src );
    }
    return $code;
  }


  public function enqueue_compiled_styles() 
  {
    $compiled = $this->_compiled;

    // load sitewide styles
    if( !nu_is_login() || nu_is_profile() ) {
      $section = 'sitewide';

      if( isset( $compiled[ $section ] ) )
        wp_enqueue_style( 'compiled-' . $section, $compiled[ $section ], null, null );

      // add the styles in this section to the $_done array
      $done = array_diff( $this->_styles[ $section ], $this->_done );
      $this->_done = array_merge( $this->_done, $done );
    }

    // load login styles
    if( nu_is_login() ) {
      $section = 'login';

      if( isset( $compiled[ $section ] ) )
        wp_enqueue_style( 'compiled-' . $section, $compiled[ $section ], null, null );

      // add the styles in this section to the $_done array
      $done = array_diff( $this->_styles[ $section ], $this->_done );
      $this->_done = array_merge( $this->_done, $done );
    }

    // load ie styles
    if ( $this->_is_ie() ) {
      $section = 'ie';

      if( isset( $compiled[ $section ] ) )
        wp_enqueue_style( 'compiled-' . $section, $compiled[ $section ], null, null );

      // add the styles in this section to the $_done array
      $done = array_diff( $this->_styles[ $section ], $this->_done );
      $this->_done = array_merge( $this->_done, $done );
    }
  }


  /**
   * Removes all styles that were compiled from the print_styles_array to make 
   * sure that duplicate styles are not loaded
   */
  public function clean_styles_array( $styles )
  {
    foreach( $this->_done as $handle ) {
      
      if( in_array( $handle, $styles ) ) {
        $index = array_search( $handle, $styles );
        unset( $styles[ $index ] );
      }
    }

    return $styles;
  }


  public function clear_compiled_styles()
  {
    $dpath = $this->_static_dir_path . 'css/';

    if ( $handle = opendir( $dpath ) ) {

      // loop over the directory and delete the css cache files
      while ( false !== ( $entry = readdir( $handle ) ) ) {
        if( false !== strpos( $entry, '.css' ) ) {
          unlink( $dpath . $entry );
        }
      }

      closedir($handle);
    }

    $msg = array( 'msg' => 'CSS cache successfully cleared' );

    return $msg;
  }
}


/**
 Template Tags
 */

function naked_minify_recompile_styles()
{
  $lazy_load = naked_minify_lazy_load_css::get_instance();
  $styles = $lazy_load->styles;

  // rebuild the styles
  new naked_minify_compile_css( $styles, true );
}