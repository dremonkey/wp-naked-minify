/**
 * This file should be turned into a jquery plugin and made available to
 * all plugins / themes as an easy way to add ajax buttons to a plugin or
 * theme admin options/settings page
 */

// document ready + closure
jQuery(function($){


  /**
   * Displays the message at the top of the page indicating whether or not the ajax
   * call successfully executed or not
   */
  function display_msg( msg, type )
  {
    var html = '<div class="message ' + type + '"><p style="text-align: center;"><strong>' + msg + '</strong></p></div>';

    // fade out any existing message
    $body = $('#wpbody-content');
    $msg  = $body.find('.message');

    if( $msg.length ) {
      $msg.fadeOut( 'fast', function(){
        $msg.remove();
        $body.find('form').before( html );
      });
    }
    else {
      $body.find('form').before( html );
    }
  }


  /**
   * Retrieves the action name. This will be passed on to admin-ajax.php and used 
   * by wordpress to call the correct function that will handle this ajax request.
   *
   * @return (string) the action hook name
   */
  function get_action( id )
  {
    var action = id.replace('naked_minify_options[', '');
    action = action.replace(']', '');

    var re = /[_]/gi;
    action = action.replace( re, '-' );

    return action;
  }


  /**
   * Retrieves the nonce set in settings.php and assigned to the naked_minify_settings var
   *
   * @return (int) the nonce value
   */
  function get_nonce( id )
  {
    var nonce_id = id.replace('naked_minify_options[', '');
    nonce_id = nonce_id.replace(']', '');
    nonce_id = '_' + nonce_id;

    return naked_minify_settings[nonce_id];
  }


  /**
   * Sets up the click handler for all ajax-buttons on the settings page.
   */
  $('.ajax-button').each(function(){
    var id      = $(this).attr('id'),
        action  = get_action( id ),
        nonce   = get_nonce( id );

    $(this).click(function(e){
      e.preventDefault();

      var data = {
        action  : action,
        nonce   : nonce
      }

      console.log(data);

      var msg = 'In progress...';
      display_msg( msg, 'updated' );

      // ajaxurl is automatically set in the admin part of wordpress
      $.post(ajaxurl, data, function( response ) {
        response = $.parseJSON( response );
        console.log( response );
        if( response.success ) {
          display_msg( response.success.msg, 'updated' );
        }
        else if( response.error ) {
          display_msg( response.error.msg, 'error' );
        }
      });
    });
    
  });

});