<?php
/*
Plugin Name: Fab Import
Plugin URI: https://www.netedit.it/
Description: Plugin Fab Import
Author: Fabrizio MESTO
Version: 0.0.1
Author URI: https://www.netedit.it/
Text Domain: fabtest
Domain Path: lang
*/


class Fab_Import {
  public $action_name = 'action';
  public $current_action = 'index';

  public function __construct() {
    add_action( 'admin_menu', array( &$this, 'fab_add_admin_menu' ) );
    add_action( 'admin_init', array( &$this, 'fab_settings_init' ) );
  }

  public function fab_add_admin_menu(){
    add_options_page( 'fab-import', 'Fab Import', 'manage_options', 'fab-import', array(&$this, 'fab_options_page') );
  }


  public function fab_settings_init(  ) {

    register_setting( 'pluginPage', 'fab_settings' );

    add_settings_section(
      'fab_pluginPage_section',
      __( 'Importazione custom', 'fabimport' ),
      array(&$this, 'fab_settings_section_callback'),
      'pluginPage'
    );

    add_settings_field(
      'fab_op1',
      __( 'Opzione 1', 'fabimport' ),
      array(&$this, 'fab_op1_render'),
      'pluginPage',
      'fab_pluginPage_section'
    );


  }


  public function fab_op1_render(  ) {

    $options = get_option( 'fab_settings' );
    ?>
    <input type='text' name='fab_settings[fab_op1]' value='<?php echo $options['fab_op1']; ?>'>
    <?php

  }


  public function fab_settings_section_callback(  ) {

    echo __( 'Descrizione sezione', 'fabimport' );

  }


  public function fab_options_page(  ) {

    if ( isset( $_GET[$this->action_name] ) &&  $_GET[$this->action_name]!='' ) :
      $this->current_action = $_GET[$this->action_name];
    endif;

    $this->render($this->current_action);

  }


  public function render($action){
    require_once( plugin_dir_path( __FILE__ ).'/templates/menu.php' );
    $action_file = plugin_dir_path( __FILE__ ).'/actions/'.$action.'.php';
    if(file_exists ( $action_file )){
      require_once( $action_file );
    }else{
      echo "Nessuna azione trovata: ".$action;
    }
  }
}


$fab_import = new Fab_Import();
