<?php

/**
 * This should not be used directly. Only extended.
 */

class naked_minify_compile_static 
{
  protected $_log_dir_path    = '';

  // errors that will be output via admin_notices
  protected $_errors          = array();

  // the url and path to the dir where the compiled scripts will be output to
  protected $_static_dir_url  = '';
  protected $_static_dir_path = '';

  // the url and path to the plugins dir
  protected $_plugins_url     = '';
  protected $_plugins_path    = '';

  // the url and path to the themes dir
  protected $_themes_url      = '';
  protected $_themes_path     = '';


  public function __construct()
  {
    // run setup
    $this->_setup();

    // show errors
    add_action( 'admin_notices', array( &$this, 'show_notice') );
  }


  private function _setup()
  {
    global $blog_id;

    $wp_content_path = WP_CONTENT_DIR;
    $wp_content_url = WP_CONTENT_URL;

    // set the path to the static files dir
    $this->_static_dir_path = $wp_content_path . '/static/' . $blog_id . '/';
    $this->_static_dir_url = $wp_content_url . '/static/' . $blog_id . '/';

    // $this->_static_dir_path = NU_PATH . 'tmp/' . $blog_id . '/';
    // $this->_static_dir_url  = NU_URL . 'tmp/' . $blog_id . '/';

    $format = __( 'Could not create the %s directory for %s. Please make sure that wp-content/plugins is writeable', 'naked-utils' );

    if( !wp_mkdir_p( $path = $this->_static_dir_path . 'js/' ) ) {
      $this->_errors[] = sprintf($format, $path, "compiled javascript files");
    }

    if( !wp_mkdir_p( $path = $this->_static_dir_path . 'css/' ) ) {
      $this->_errors[] = sprintf($format, $path, "compiled stylesheets");
    }

    if( !wp_mkdir_p( $path = $this->_static_dir_path . 'cache/' ) ) {
      $this->_errors[] = sprintf($format, $path, "temporary cache files");
    }

    if( !wp_mkdir_p( $path = $this->_static_dir_path . 'log/' ) ) {
      $this->_errors[] = sprintf($format, $path, "log files");
    }

    // set the url and path to the plugins directory
    $this->_plugins_url = trailingslashit( plugins_url() );
    $this->_plugins_path = trailingslashit( WP_PLUGIN_DIR );

    // set the url and path to the themes directory
    $this->_themes_url = dirname( get_template_directory_uri() ) . '/';
    $this->_themes_path = trailingslashit( get_theme_root() );

    // set the path to the log directory
    $this->_log_dir_path = $this->_static_dir_path . 'log/';
  }


  public function show_notice()
  {
    if( current_user_can( 'manage_plugins' ) ) {
      if( !empty( $this->_errors ) ) {
        foreach( $this->_errors as $msg ) {
          echo '<div class="error"><p>';
          echo $msg;
          echo "</p></div>";
        }
      }
    }
  }


  /**
   * Returns the full path to the cache directory
   */
  public function cache_dir_path()
  {
    if( $this->_static_dir_path )
      return $this->_static_dir_path . 'cache/';
    else {
      $this->_setup();
      return $this->_static_dir_path . 'cache/';
    }
  }


  /** 
   * Attempts to convert a full url to a local path.
   *
   * This is done because filemtime() - used in php-closure class to
   * determine if a file has changed - fails to retrieve the time if 
   * php allow_url_fopen is 'Off' or file permissions are not set 
   * correctly
   */ 
  protected function _url_to_path( $src ) 
  {
    $debug = false;

    // plugins dir url and paths
    $purl   = $this->_plugins_url;
    $ppath  = $this->_plugins_path;

    // themes dir url and paths
    $turl   = $this->_themes_url;
    $tpath  = $this->_themes_path;

    if( $debug )
      nu_debug( 'Source Before', $src );

    // check to see if the source can be accessed locally
    if( false !== strpos( $src, home_url() ) ) {
      
      // see if we are looking for a plugin file
      if( false !== strpos( $src, $purl ) ) {
        $src = str_replace( $purl, '', $src );
        $src =  $ppath . $src;
      }
      // see if we are looking for a theme file
      elseif( false !== strpos( $src, $turl ) ) {
        $src = str_replace( $turl, '', $src );
        $src = $tpath . $src;
      }

    }
    // check to see if this is a relative path
    elseif( false === strpos( $src, 'http://' ) 
            && false === strpos( $src, 'https://' ) ) {

      // if a relative path, append the ABSPATH because this will be a js file bundled with wordpress (like jquery)
      $src = untrailingslashit( ABSPATH ) . $src;
    }

    if( $debug )  
      nu_debug( 'Path After', $src );
    

    return $src;
  }


  protected function _is_ie()
  {
    if( eregi("MSIE", getenv( "HTTP_USER_AGENT" ) )
        || eregi("Internet Explorer", getenv("HTTP_USER_AGENT" ) ) ) 
    {
      return true;
    }

    return false;
  }
}