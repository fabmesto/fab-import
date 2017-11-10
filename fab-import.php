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
  public $table_origin = "db_pages";

  public function __construct() {
    add_action( 'admin_menu', array( &$this, 'fab_add_admin_menu' ) );
    add_action( 'admin_init', array( &$this, 'fab_settings_init' ) );
    add_action( 'admin_enqueue_scripts', array( &$this,'enqueue_scripts' ) );
  }

  public function enqueue_scripts(){
    wp_enqueue_script( 'tether', "https://npmcdn.com/tether@1.2.4/dist/js/tether.min.js", array(), '1.2.4', true );
    wp_enqueue_script( 'bootstrap-js', plugins_url( 'bootstrap/js/bootstrap.min.js', __FILE__ ), array('tether', 'jquery'), '4.0.0-alpha.6', true );
    wp_enqueue_style( 'bootstrap-css', plugins_url( 'bootstrap/css/bootstrap.min.css', __FILE__ ), '4.0.0-alpha.6' );
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

  public function origin_pages(){
    global $wpdb;

    $posts_per_page = 1000;
    $start = 0;

    $current_page = (isset($_GET['paged'])?$_GET['paged']:1);
    $start = ($current_page-1)*$posts_per_page;

    $sql_count = "SELECT COUNT(*) FROM ".$this->table_origin." WHERE deleted='0' ORDER BY id DESC";
    $total_posts = $wpdb->get_var($sql_count);

    $sql = "SELECT * FROM ".$this->table_origin." WHERE deleted='0' ORDER BY id DESC LIMIT ".$start.", ".$posts_per_page;
    $rows = $wpdb->get_results($sql);

    $total_page = ceil( $total_posts / $posts_per_page); // Calculate Total pages
    //echo $total_page;
    $args = array(
      'format'             => '?paged=%#%',
      'total'              => $total_page,
      'current'            =>  $current_page,
    );
    echo "<div>".paginate_links( $args )."</div>";
    return $rows;
  }

  public function import_all(){
    ini_set('max_execution_time', 300);
    $rows = $this->origin_pages();
    foreach ($rows as $key => $row) {
      $this->import_row($row);
    }
  }

  public function Generate_Featured_Image( $image_url, $post_id  ){
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if(wp_mkdir_p($upload_dir['path']))     $file = $upload_dir['path'] . '/' . $filename;
    else                                    $file = $upload_dir['basedir'] . '/' . $filename;
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => sanitize_file_name($filename),
      'post_content' => '',
      'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
    $res2= set_post_thumbnail( $post_id, $attach_id );
  }

  public function import_row($row){
    global $wpdb;
    $query = $wpdb->prepare(
      "SELECT ID FROM " . $wpdb->posts . " WHERE post_title = %s",
      wp_strip_all_tags( $row->title )
    );
    $wpdb->query( $query );

    if ( $wpdb->num_rows ) {
      $post_id = $wpdb->get_var( $query );
      echo "<div> -- Già importato ".$post_id."</div>";
    }else{
      if($row->id_parent>0){
        $query = $wpdb->prepare(
          "SELECT * FROM " . $this->table_origin . " WHERE id = %d",
          $row->id_parent
        );
        $wpdb->query( $query );
      }

      $my_post = array(
        'post_title'    => wp_strip_all_tags( $row->title ),
        'post_content'  => $row->html_main,
        'post_type'  => 'post',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_category' => array(),
      );

      // Insert the post into the database
      $post_id = wp_insert_post( $my_post );
      if (is_wp_error($post_id)) {
        $errors = $post_id->get_error_messages();
        echo "NON Importato <br />";
      }else{
        $this->__update_post_meta($post_id, "old_id", $row->id);
        $this->__update_post_meta($post_id, "old_id_parent", $row->id_parent);

        $row->img = str_replace('http://www.dragonballgt.it/', '', $row->img);
        $row->img = str_replace('https://www.dragonballgt.it/', '', $row->img);

        $pos = strpos($row->img, 'files/');

        if($pos===0){
          $row->img = str_replace('/tn/', '/', $row->img);
          $row->img="https://www.dragonballsuper.it/".$row->img;
        }else if($pos==1){
          $row->img="https://www.dragonballsuper.it/".substr($row->img, $pos);
        }
        if($row->img!=''){
          $this->__update_post_meta($post_id, "old_img", $row->img);
          //$this->Generate_Featured_Image($row->img, $post_id);
        }
        echo "<div>".$row->id." Importato </div>";
      }
    }
  }

  protected function __update_post_meta( $post_id, $field_name, $value = '' ){
    if ( empty( $value ) OR ! $value )
    {
      delete_post_meta( $post_id, $field_name );
    }
    elseif ( ! get_post_meta( $post_id, $field_name ) )
    {
      add_post_meta( $post_id, $field_name, $value );
    }
    else
    {
      update_post_meta( $post_id, $field_name, $value );
    }
  }

}


$fab_import = new Fab_Import();
