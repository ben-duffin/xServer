<?php
  // Daemon should not expire
  set_time_limit(0);

  // lib
  include('obj/xMask.trait.php');
  include('obj/xServer.class.php');
  include('obj/xClient.class.php');

  // Local connection - havent tried connecting to an external server and making it listen!
  $host     = '0.0.0.0';
  $port     = 4444;

  // start the server ...
  $x_server = new xServer($host, $port);
  $x_server->run();