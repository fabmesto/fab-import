<h2>Index</h2>
<?php
$rows = $this->origin_pages();
?>

<a href="<?php echo add_query_arg( array( $this->action_name=> 'import') ); ?>">Importa tutto</a>
<table class="table table-condensed">
  <thead>
    <tr>
      <th>id</th>
      <th>id_parent</th>
      <th>title</td>
      <th>img</th>
    </tr>
  </thead>
  <tbody>
    <?foreach($rows as $row):?>
    <tr>
      <td>
        <?php echo $row->id?>
      </td>
      <td>
        <?php echo $row->id_parent?>
      </td>
      <td>
        <?php echo $row->title?>
      </td>
      <td>
        <?php echo $row->img?>
      </td>
    </tr>
    <?endforeach;?>
  </tbody>
</table>
