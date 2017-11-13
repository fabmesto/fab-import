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
  public $urlsOriginFake = array('http://www.dragonballgt.it/', 'https://www.dragonballgt.it/');
  public $urlOrigin = "https://www.dragonballsuper.it/";

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

    $posts_per_page = 500;
    $start = 0;

    $current_page = (isset($_GET['paged'])?$_GET['paged']:1);
    $start = ($current_page-1)*$posts_per_page;

    $sql_count = "SELECT COUNT(*) FROM ".$this->table_origin." WHERE deleted='0' ORDER BY id DESC";
    $total_posts = $wpdb->get_var($sql_count);

    $sql = "SELECT
    origin.id, csv.title, origin.html_main, origin.img, origin.id_parent, origin.date_creation
    FROM ".$this->table_origin." AS origin, db_pages_csv AS csv
    WHERE origin.id=csv.id AND origin.deleted='0' ORDER BY origin.id_parent ASC, origin.id ASC LIMIT ".$start.", ".$posts_per_page;
    //echo $sql;
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

  public function generate_featured_images(){
    global $wpdb;

    $posts_per_page = 50;
    $start = 0;

    $current_page = (isset($_GET['paged'])?$_GET['paged']:1);
    $start = ($current_page-1)*$posts_per_page;

    $sql_count = "SELECT COUNT(*) FROM ".$wpdb->postmeta." WHERE meta_key='old_img'";
    $total_posts = $wpdb->get_var($sql_count);

    $sql = "SELECT post_id, meta_value FROM ".$wpdb->postmeta." WHERE meta_key='old_img' LIMIT ".$start.", ".$posts_per_page;
    $rows = $wpdb->get_results($sql);

    $total_page = ceil( $total_posts / $posts_per_page); // Calculate Total pages
    $args = array(
      'format'             => '?paged=%#%',
      'total'              => $total_page,
      'current'            =>  $current_page,
    );
    echo "<div>".paginate_links( $args )."</div>";


    foreach($rows as $row){
      $image_url = $row->meta_value;
      $post_id = $row->post_id;
      echo "<div>".$row->post_id." - ".$row->meta_value."</div>";
      $this->generate_featured_image( $image_url, $post_id );
    }
  }

  public function generate_featured_image( $image_url, $post_id  ){
    // Get an array containing the current upload directory’s path and url.
    $upload_dir = wp_upload_dir();
    // image
    $image_data = @file_get_contents($image_url);
    if($image_data === FALSE){
      echo "ERRORE: ".$image_url;
      return false;
    }
    $filename = basename($image_url);
    $filename = str_replace('%20', '-', $filename);
    $filename = sanitize_file_name($filename);
    if(wp_mkdir_p($upload_dir['path']))     $file = $upload_dir['path'] . '/' . $filename;
    else                                    $file = $upload_dir['basedir'] . '/' . $filename;
    //echo $file;
    if(file_exists($file)){
      // controlla se il file esiste
      echo $file.": esiste già<br>";
      $attach_id = $this->get_attach_id_by_filename($file);
      if($attach_id) $res2= set_post_thumbnail( $post_id, $attach_id );
    }else{
      file_put_contents($file, $image_data);

      $wp_filetype = wp_check_filetype($filename, null );
      $attachment = array(
        'guid'           => $upload_dir['url'] . '/' . basename( $filename ),
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
  }

  public function get_attach_id_by_filename($filename){
    global $wpdb;
    $upload_dir = wp_upload_dir();
    $image_src = $upload_dir['url'] . '/' .basename( $filename );
    $query = "SELECT id FROM ".$wpdb->posts." WHERE guid='".$image_src."'";
    $id = $wpdb->get_var($query);
    return $id;
  }

  public function import_category($row){
    $cat = term_exists($row->title, 'category');
    if($cat!==0 && $cat!==null){
      $id_cat = $cat['term_id'];
      echo "<div>Categoria già importata ".$id_cat."</div>";
    }else{
      $args = array(
        'cat_name' => $row->title,
        'category_description'=>$row->html_main,
        'category_parent' => $this->get_category_parent($row),
      );
      $id_cat = wp_insert_category($args);
      echo "<div>Categoria importata ".$id_cat."</div>";
    }
    return $id_cat;
  }

  public function get_category_parent ($row){
    global $wpdb;
    if($row->id_parent>0){
      $query = $wpdb->prepare(
        "SELECT
        origin.id, csv.title, origin.html_main, origin.img, origin.id_parent, origin.date_creation
        FROM ".$this->table_origin." AS origin, db_pages_csv AS csv
        WHERE origin.id=csv.id AND origin.deleted='0' AND origin.id = %d",
        $row->id_parent
      );
      $parent_row = $wpdb->get_row( $query );
      $id_cat = $this->import_category($parent_row);
    }else{
      $id_cat = 0;
    }
    return $id_cat;
  }

  public function page_is_category ($row){
    global $wpdb;
    $sql = "SELECT id FROM ".$this->table_origin." WHERE deleted='0' AND id_parent='".$row->id."' ORDER BY id_parent ASC, id ASC LIMIT 0,1";
    $row = $wpdb->get_row($sql);
    if($row==null) return false;
    return true;
  }

  public function import_row($row){
    if($row->title =='') return;

    if($this->page_is_category($row)){
      $this->import_category($row);
    }else{
      $this->import_post($row);
    }
  }

  public function import_post($row){
    global $wpdb;

    $post_id = post_exists($row->title);
    if($post_id==0){

      $id_cat = $this->get_category_parent($row);

      $row->gallery = $this->get_gallery($row);
      if($row->gallery){
        $row->html_main .= '[ngg_images gallery_ids="'.$row->gallery->ngg_gallery_id.'" display_type="photocrati-nextgen_basic_thumbnails"]';
      }

      $my_post = array(
        'post_title'    => wp_strip_all_tags( $row->title ),
        'post_content'  => $row->html_main,
        'post_type'     => 'post',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_date'     => $row->date_creation,
        'post_category' => array($id_cat),
      );

      // Insert the post into the database
      $post_id = wp_insert_post( $my_post );
      if (is_wp_error($post_id)) {
        $errors = $post_id->get_error_messages();
        echo "<div>ERRORE - NON Importato </div>";
      }else{
        echo "<div>".$row->id." Importato - Cat(".$id_cat.") ".($row->gallery?'GALLERY':'')."</div>";
        $this->import_post_meta( $post_id, $row);
      }
    }else{
      echo "<div> -- Già importato ".$post_id."</div>";
    }
  }

  public function import_post_meta($post_id, $row){
    if($row->gallery) $this->__update_post_meta($post_id, "old_gallery", $row->gallery->dir_path);
    $this->__update_post_meta($post_id, "old_id", $row->id);
    $this->__update_post_meta($post_id, "old_id_parent", $row->id_parent);

    foreach($this->urlsOriginFake as $url){
      $row->img = str_replace($url, '', $row->img);
    }
    $pos = strpos($row->img, 'files/');

    if($pos===0){
      $row->img = str_replace('/tn/', '/', $row->img);
      $row->img=$this->urlOrigin.$row->img;
    }else if($pos==1){
      $row->img=$this->urlOrigin.substr($row->img, $pos);
    }
    if($row->img!=''){
      $this->__update_post_meta($post_id, "old_img", $row->img);
      // $this->generate_featured_image($row->img, $post_id);
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

  function get_gallery($row){
    global $wpdb;
    $sql = "SELECT ig.* FROM db_modules_installation AS mi, db_mod_images_gallery AS ig WHERE id_page='".$row->id."' AND module_name='images_gallery' AND mi.module_id=ig.id";
    $row = $wpdb->get_row($sql);
    if($row==null){
      return false;
    }else{
      $wpdb->insert(
        $wpdb->prefix."ngg_gallery",
        array(
          'name'=>sanitize_title($row->name),
          'slug'=>sanitize_title($row->name),
          'path'=>$row->dir_path,
          'title'=>$row->name,
          'previewpic'=>1,
          'author'=>1,
          'extras_post_id'=>0
        )
      );
      $row->ngg_gallery_id = $wpdb->insert_id;
      return $row;
    }
  }

  public function update_all_title(){
    global $wpdb;
    $sql = "SELECT * FROM db_pages_csv";
    $rows = $wpdb->get_results($sql);
    foreach ($rows as $key => $row) {
      $post_id = $this->get_post_by_old_id($row->id);
      if($post_id>0){
        $my_post = array(
          'ID'           => $post_id,
          'post_title'   => $row->title,
        );
        wp_update_post( $my_post );
        echo "<div>Update: ".$row->title."</div>";
      }else{
        echo "<div>Non trovato: ".$row->title."</div>";
      }
    }
  }

  public function get_post_by_old_id($old_id){
    global $wpdb;
    $sql = "SELECT post_id FROM ".$wpdb->postmeta." WHERE meta_key='old_id' AND meta_value='".$old_id."' ";
    //echo $sql."<br>";
    $row = $wpdb->get_row($sql);
    if($row){
      return $row->post_id;
    }
    return 0;
  }

  public function import_nggallery($gid){
    global $wpdb;
    $this->gid = $gid;
    $gallerypath = $wpdb->get_var("SELECT path FROM $wpdb->nggallery WHERE gid = '$this->gid' ");
    nggAdmin::import_gallery($gallerypath, $this->gid);
  }

  /* RESET DB
  TRUNCATE TABLE `wpner_posts`;
  TRUNCATE TABLE `wpner_postmeta`;
  TRUNCATE TABLE `wpner_termmeta`;
  TRUNCATE TABLE `wpner_terms`;
  TRUNCATE TABLE `wpner_term_relationships`;
  TRUNCATE TABLE `wpner_term_taxonomy`;
  TRUNCATE TABLE `wpner_ngg_gallery`

  TRUNCATE TABLE `wpdb_posts`;
  TRUNCATE TABLE `wpdb_postmeta`;
  TRUNCATE TABLE `wpdb_termmeta`;
  TRUNCATE TABLE `wpdb_terms`;
  TRUNCATE TABLE `wpdb_term_relationships`;
  TRUNCATE TABLE `wpdb_term_taxonomy`;
  TRUNCATE TABLE `wpdb_ngg_gallery`;
  TRUNCATE TABLE `wpdb_ngg_pictures`;
  */

}


$fab_import = new Fab_Import();
