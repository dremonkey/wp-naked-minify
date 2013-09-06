<?php

require_once( 'compile-static.class.php' );

class naked_minify_compile_js extends naked_minify_compile_static
{
  // a nested array of script handles organized by the section of the site that the script is to be used on. this is a complete list of scripts and includes all dependencies
  private $_queue = array();

  // a nested array of script handles organized by the section of the site that the script is to be used on. this is a complete list of scripts, including all dependencies, BUT with duplicates removed
  private $_cleaned_queue = array();

  // an array of the compiled js scripts
  private $_compiled = array();

  // an array of scripts that have already been added to the page
  private $_done = array();

  public function __construct( $scripts = null, $rebuild = false )
  {
    // run the parent constructor
    parent::__construct();

    // abort if there was a problem with setup
    if( !empty( $this->_errors ) )
      return;

    if( $rebuild && !empty( $scripts ) ) {
      // delete all existing cache files
      $this->clear_js_cache();

      // build the cache files
      $this->_build_queue( $scripts );
    }

    if( !is_admin() && !empty( $scripts ) ) {

      global $wp_scripts;
      nu_debug('All Scripts', $wp_scripts);

      // build the queue
      $this->_build_queue( $scripts );

      add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_compiled_scripts' ), 1 );
      add_action( 'login_enqueue_scripts', array( &$this, 'enqueue_compiled_scripts' ), 1 );

      // remove compiled scripts from the wordpress queue
      add_filter( 'print_scripts_array', array( &$this, 'clean_scripts_array' ) );

      // make sure the localized data is printed
      add_filter( 'script_loader_src', array( &$this, 'print_l10n' ), 10, 2 );
    }
  }


  /**
   * Builds the $_queue
   *
   * @param $scripts (array) 
   *  a nested array of script handles organized by the section of the site 
   *  that the script is to be used on. this list is incomplete because it is
   *  missing dependencies
   */
  private function _build_queue( $scripts )
  {
    nu_debug('Build Queue', $scripts);

    foreach( $scripts as $section=>$handles ) {
      if( 'admin' == $section ) continue;
      foreach( $handles as $handle ) {
        $this->_get_deps( $handle, $section );
      }
    }

    $this->_clean_queue();
  }


  /**
   * Recursively gets dependencies for the script ($handle) and adds them to 
   * the $_queue. Also adds the $handle after all its dependencies have 
   * been added.
   *
   * @param $handle (string) 
   *  The registration handle for a script
   * @param $section (string)
   *  The section of the site that the script is to be used on
   */
  private function _get_deps( $handle, $section ) 
  {
    $debug = false;

    global $wp_scripts;

    // retrieve the script object using the handle
    $script = $wp_scripts->registered[ $handle ];

    if( $debug) 
      echo 'handle | ' . $handle . '<br/><br/>';

    // script has dependencies
    if( $script->deps ) {
      foreach( (array) $script->deps as $dep ) {

        if( $debug) 
          echo '* dep | ' . $dep . '<br/></br/>';

        $this->_get_deps( $dep, $section );
      }
    }

    if( $debug) 
      echo '** add self | ' . $handle . '<br/></br/>';

    // add the handle. will add if (1) this script has no dependencies or (2) all of this scripts dependencies have been added
    $this->_enqueue( $handle, $section );

    return;
  }


  /**
   * Adds $handle to the $_queue if it has not already been added
   *
   * @param $handle (string) 
   *  The registration handle for a script
   * @param $section (string)
   *  The section of the site that the script is to be used on
   */
  private function _enqueue( $handle, $section ) 
  {
    $debug = false;

    if( !$this->_queued( $handle, $section ) ) { 
      $this->_queue[ $section ][] = $handle;
    }

    return;
  }


  /**
   * Checks to see if the $handle has already been queued in the $section
   *
   * @param $handle (string) 
   *  The registration handle for a script
   * @param $section (string)
   *  The section of the site that the script is to be used on
   *
   * @return bool
   *  returns true if queued, false if not
   */
  private function _queued( $handle, $section ) 
  {
    if( !empty( $this->_queue ) && isset( $this->_queue[ $section ] ) )
      return in_array( $handle, $this->_queue[ $section ] );
  
    return false;
  }


  /**
   * Cleans the $_queue so that duplicate scripts are removed. 
   * 
   * Duplicates are only removed if a subsection (archive, singular) contains 
   * files that will already be loaded in the sitewide script. 
   */
  private function _clean_queue()
  {
    global $wp_scripts;

    $queue = $this->_queue;

    // do some cleanup / duplicate removal
    foreach( $queue['sitewide'] as $handle ) {
      if( isset( $queue['archive'] )
        && false !== ( $key = array_search( $handle, $queue['archive'] ) ) )
        unset( $queue['archive'][ $key ] );

      if( isset( $queue['singular'] )
        && false !== ( $key = array_search( $handle, $queue['singular'] ) ) )
        unset( $queue['singular'][ $key ] );
    }


    // attempt to turn the the src url into a local path. this is done because filemtime() - used in php-closure class - fails to retrieve the time if php allow_url_fopen is 'Off' or file permissions are not set correctly. even when these settings seem to be correct it someimes fails.
    $tmp = array();
    foreach( $queue as $section=>$handles ) {
      foreach( $handles as $handle ) {
        $src = $wp_scripts->registered[ $handle ]->src;
        $tmp[ $section ][ $handle ] = $this->_url_to_path( $src );
      }
    }

    $this->_cleaned_queue = $tmp;

    // compile the scripts
    $this->_compile();
  }


  private function _compile()
  { 
    $queue = $this->_cleaned_queue;
    
    foreach( $queue as $section=>$scripts ) {
      $this->_do_closure( $scripts, $section );
    }
  }


  /**
   * Uses Google's Closure Library on the passed in scripts. The 
   * scripts will be concatenated, minified, and output to a new file
   *
   * @param $scripts The scripts to concatenate and minify
   * @param $name ( string ) The basename of the new javascript file
   */
  private function _do_closure( $scripts, $name ) 
  {
    // set to true to output debug info
    $debug = true;

    if( $debug )
      $log = new KLogger( $this->_log_dir_path, KLogger::INFO );

    // flag indicating whether or not we need to modify the compiled script
    $bust = false;

    // file path to be used for the compiled js output
    $dpath = $this->_static_dir_path . 'js/';
    $fpath = $dpath . $name . '.min.js';

    // path to the cache directory
    $cpath  = $this->cache_dir_path();

    // abort if there are no scripts
    if( empty( $scripts ) )
      return;

    $c = new PhpClosure();

    // run closure on the scripts
    foreach( $scripts as $handle=>$path ) {
      $c->add( $path );
    }

    // jQuery doesn't play nice with Advanced Optimization so use SimpleMode
    $c->simpleMode()
      ->quiet()
      // ->hideDebugInfo()
      ->cacheDir( $cpath )
      ->logDir( $this->_log_dir_path );

    // See if the cache file is being recompiled. if it is we will need to do some cachebusting.
    $cachefn  = $c->_getCacheFileName();
    $bust     = $c->_isRecompileNeeded( $cachefn );

    if( $debug ) {
      if( $bust ) {
        $dump_k = implode( ', ', array_keys( $scripts ) );
        $dump_v = implode( ', ', $scripts );
        $log->LogInfo("Cache Filename: $cachefn");
        $log->LogInfo("$name script handles to be compiled: $dump_k");
        $log->LogInfo("$name script paths to be compiled: $dump_v");
      }
    }

    // if the contents have changed or the file does not exist, create and/or write the contents to the js file. cachebusting will be done by changing the filename in the header, but the actual file name will remain the same. we have a rule in the .htaccess file that will make sure the correct file is loaded.
    if( !is_readable( $fpath )|| $bust ) {
    
      // grab the output. this will be written to a separate js file
      ob_start();
      $c->writeSansHeaders();
      $out = ob_get_clean();

      // write the output to the new js file
      file_put_contents( $fpath, $out );
    }

    // file url for the compiled script. this is what will be printed in the document when the page is generated, i.e. <script src=[$furl]></script>
    $fmod = filemtime( $fpath );
    $durl = $this->_static_dir_url . 'js/';
    $furl = $durl . $name . '.' . $fmod . '.min.js';

    // add the $fpath to the $compiled array
    $this->_compiled[ $name ] = $furl;
  }


  /**
   * Enqueues all the compiled scripts so that they are output to the page
   *
   * @uses self::_enqueue_section_scripts()
   */
  public function enqueue_compiled_scripts()
  {
    // Load IE scripts
    if ( $this->_is_ie() ) {
      $section = 'ie';
      $this->_enqueue_section_scripts( $section );
    }

    // Load login scripts
    if( nu_is_login() ) {
      $section = 'login';
      $this->_enqueue_section_scripts( $section );
    }

    // Load sitewide scripts (if not login or admin)
    if( !nu_is_login() && !is_admin() ) {
      $section = 'sitewide';
      $this->_enqueue_section_scripts( $section );
    }

    // Load archive scripts ( includes homepage )
    if( is_archive() || is_home() ) {
      $section = 'archive';
      $this->_enqueue_section_scripts( $section, array( 'compiled-sitewide' ) );
    }

    // Load singular scripts (post, page, etc)
    if( is_singular() ) {
      $section = 'singular';
      $this->_enqueue_section_scripts( $section, array( 'compiled-sitewide' ) );
    }
  }


  /**
   * Helper function used to enqueue the compiled script for a particular section. In addition
   * to enqueing the enqueued scripts are added to the $done array to keep track of which files 
   * have been added and which have not
   */
  private function _enqueue_section_scripts( $section, $deps = null )
  {
    $scripts = $this->_compiled;

    // make sure that scripts have actually been compiled before enqueing
    if( isset( $this->_queue[ $section ] ) ) {
      wp_enqueue_script( 'compiled-' . $section, $scripts[ $section ], $deps, null );

      // add the scripts in this section to the $_done array
      $done = array_diff( $this->_queue[ $section ], $this->_done );
      $this->_done = array_merge( $this->_done, $done );
    }
  }

  /**
   * Removes all scripts that were compiled from the print_scripts_array to make 
   * sure that duplicate scripts are not loaded
   */
  public function clean_scripts_array( $scripts )
  {
    foreach( $this->_done as $handle ) {
      
      if( in_array( $handle, $scripts ) ) {
        $index = array_search( $handle, $scripts );
        unset( $scripts[ $index ] );
      }
    }

    return $scripts;
  }


  /**
   * Not changing the source. Just using this filter as a convenient place to check
   * to see if we need to output any localizations. If so, then we do.
   */
  public function print_l10n( $src, $handle ) 
  {
    global $wp_scripts;

    // if the script that is about to be printed is compiled...
    if( false !== strpos( $handle, 'compiled') ) {
      // get the list of scripts that were compiled into this script
      $handle = str_replace( 'compiled-', '', $handle );
      $scripts = $this->_queue[ $handle ];

      // print the localized variables  
      foreach( $scripts as $handle ) {
        $wp_scripts->print_extra_script( $handle );
      }
    }
    
    return $src;
  }


  public function clear_js_cache()
  {
    $cpath = $this->cache_dir_path();

    if ( $handle = opendir( $cpath ) ) {

      // loop over the directory and delete the js cache files
      while ( false !== ( $entry = readdir( $handle ) ) ) {
        if( false !== strpos( $entry, '.js' ) ) {
          unlink( $cpath . $entry );
        }
      }

      closedir($handle);
    }

    $msg = array( 'msg' => 'Javascript cache successfully cleared' );

    return $msg;
  }
}


/**
 Template Tags
 */

function naked_minify_rebuild_scripts_cache()
{
  $lazy_load = naked_minify_lazy_load_js::get_instance();
  $scripts = $lazy_load->scripts;

  // rebuild the scripts
  new naked_minify_compile_js( $scripts, true );
}