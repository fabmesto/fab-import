<nav class="navbar navbar-toggleable-md navbar-light bg-faded">
  <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <a class="navbar-brand" href="#">Fab Import</a>
  <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav">
      <li class="nav-item <?php echo ($this->current_action=='index'?'active':'')?>">
        <a class="nav-link" href="<?php echo add_query_arg( array( $this->action_name=> 'index') ); ?>">Index</a>
      </li>
      <li class="nav-item <?php echo ($this->current_action=='settings'?'active':'')?>">
        <a class="nav-link" href="<?php echo add_query_arg( array( $this->action_name=> 'settings') ); ?>">Impostazioni</a>
      </li>

    </ul>
  </div>
</nav>
