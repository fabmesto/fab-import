<form action='options.php' method='post'>

  <h2>Fab Import</h2>

  <?php
  settings_fields( 'pluginPage' );
  do_settings_sections( 'pluginPage' );
  submit_button();
  ?>

</form>
