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
  $root_pw  = 'password';

  // start the server ...
  $x_server = new xServer($host, $port, $root_pw);

  // Check for verbose config on command line
  if(isset($argv[1])){
    if(strstr($argv[1], '=')){
      $parts = explode('=', $argv[1]);
      if($parts[0] == 'verbose'){
        switch($parts[1]){
          case 'true' :
            $x_server->verbose();
          break;
          case 'false' :
            $x_server->verbose(false);
          break;
        }
      }
    }
  }

  // Run the server
  $x_server->run();