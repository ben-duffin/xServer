<?php

  class xServer {

    use xMask;
    private $root_pw;
    private $run      = true;
    private $pid;
    private $pid_file = '/var/www/sockets.genasystems.co.uk/pid_lock/socket.pid';
    private $enforce  = false;
    private $pids     = array();

    private $verbose = true; // if false no terminal output
    private $mem;

    private $host;
    private $port;

    private $sockets;
    private $clients;
    private $master;

    private $admin_id;


    function __construct($host, $port, $root_pw = false) {
      ob_start();
      $this->admin_id = false;
      $this->host     = $host;
      $this->port     = $port;

      if($root_pw === false){
        $this->root_pw = uniqid();
      }else{
        $this->root_pw = $root_pw;
      }

      $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

      if(!is_resource($socket)){
        $this->console("socket_create() failed: " . socket_strerror(socket_last_error()), true);
        exit;
      }

      if(!socket_bind($socket, $this->host, $this->port)){
        $this->console("socket_bind() failed: " . socket_strerror(socket_last_error()), true);
        exit;
      }

      if(!socket_listen($socket, 20)){
        $this->console("socket_listen() failed: " . socket_strerror(socket_last_error()), true);
        exit;
      }

      $this->master  = $socket;
      $this->sockets = array($socket);
      $this->mem     = new Memcached();
      $this->mem->addServer('127.0.0.1', 11211, 1);
      $this->mem->set('notification-stack', array());
    }


    public function __destruct() {
      if(file_exists($this->pid_file)){
        unlink($this->pid_file);
      }

      if(getmypid() == $this->pid){
        if(!empty($this->clients)){
          foreach($this->clients as $client){
            posix_kill($client->getPid(), SIGTERM);
          }
        }
      }
    }


    public function verbose($mode = true) {
      $this->verbose = $mode;
    }


    public function enforce($status = true) {
      $this->enforce = $status;
    }


    private function push($data) {
      $stack   = $this->mem->get('notification-stack');
      $stack[] = $data;
      $this->mem->set('notification-stack', $stack);
    }


    private function pop($idx = 'all') {
      switch($idx){
        case 'all' :


          return $this->mem->get('notification-stack');
        break;

        default :
          $stack = $this->mem->get('notification-stack');
          if(isset($stack[$idx])){


            return $stack[$idx];
          }


          return null;
        break;
      }
    }


    function console($text, $end_of_line = PHP_EOL) {
      if($this->verbose){
        $text = trim($text);
        echo "{$text}{$end_of_line}";
        ob_flush();
      }
    }


    function br() {
      $this->console('--------------------------------------------------------------------------------');
    }


    function run() {
      $this->console('Started TCP server at ' . $this->host . ' on process #' . getmypid() . ', listening on port #' . $this->port);
      $this->pid = getmypid();
      $this->br();

      if($this->enforce){
        $pid = (int)trim(file_get_contents($this->pid_file));
        posix_kill($pid, SIGKILL);
        unlink($this->pid_file);
      }

      if(!file_exists($this->pid_file)){
        umask(0);
        touch($this->pid_file);
        $contents = $this->pid;
        $fp       = fopen($this->pid_file, 'w');
        fwrite($fp, $contents, strlen($contents));
        fclose($fp);
      }else{
        die('Socket Server instance already running on process #' . trim(file_get_contents($this->pid_file)) . '! Please terminate it to try again.');
      }

      while(true){
        $changed_sockets = $this->sockets;
        if($changed_sockets){
          $socket_result = @socket_select($changed_sockets, $write = array(), $except = array(), 5);
          if($socket_result === false){
            die('Socket Error - closing process because: ' . socket_strerror(socket_last_error()));
          }else{
            if($socket_result < 1){
              continue;
            }
          }
          foreach($changed_sockets as $socket){
            if($socket == $this->master){
              if(($acceptedSocket = socket_accept($this->master)) < 0){
                $this->console("Socket error: " . socket_strerror(socket_last_error($acceptedSocket)));
              }else{
                $this->connect($acceptedSocket);
              }
            }else{
              $client = $this->get_client_by_socket($socket);
              if($client){
                $data = null;
                while($bytes = @socket_recv($socket, $r_data, 2048, MSG_DONTWAIT)){
                  $data .= $r_data;
                }
                socket_getsockname($socket, $client_address, $client_port);
                if(!$client->getHandshake()){
                  if(!$this->run && ($client->getId() != $this->admin_id)){
                    usleep(1000);
                    continue;
                  }
                  if($this->handshake($client, $data)){
                    $this->start_client_socket($client);
                  }else{
                    $this->disconnect($client);
                  }
                }elseif($bytes === 0){
                  $this->disconnect($client);
                }else{
                  if(!$this->run && ($client->getId() != $this->admin_id)){
                    if(!$this->run){
                      usleep(1000);
                      continue;
                    }
                  }
                  $this->incoming_data($client, $data);
                }
              }
            }
          }
        }
        usleep(1000);
      }
    }


    function handshake($client, $data) {
      if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $data, $match)){
        $version = $match[1];
      }else{
        $this->console("The client doesn't support WebSocket");


        return false;
      }

      if($version == 13){
        $root   = false;
        $host   = false;
        $origin = false;
        $key    = false;

        if(preg_match("/GET (.*) HTTP/", $data, $match)){
          $root = $match[1];
        }
        if(preg_match("/Host: (.*)\r\n/", $data, $match)){
          $host = $match[1];
        }
        if(preg_match("/Origin: (.*)\r\n/", $data, $match)){
          $origin = $match[1];
        }
        if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $match)){
          $key = $match[1];
        }
        if($root == false || $host == false || $origin == false || $key == false){
          $this->console("WebSocket Headers are missing - cannot continue with handshake!");


          return false;
        }

        $this->console("Generating Sec-WebSocket-Accept key...");
        $acceptKey = $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $acceptKey = base64_encode(sha1($acceptKey, true));
        $upgrade   = $this->generate_handshake_acceptKey($acceptKey);
        $this->emit($client, $upgrade);
        $client->setHandshake(true);


        return true;
      }else{
        $this->console("WebSocket version 13 required (the client supports version {$version})");


        return false;
      }
    }


    function connect($socket) {
      socket_getpeername($socket, $address, $port);
      $client_id = md5($address . $port);
      $client    = new xClient($socket);
      $client->setIP($address);
      $client->setPort($port);
      $this->clients[$client_id] = $client;
      $this->sockets[]           = $socket;
      $this->console('Successfully created client ID#' . $client->getId() . '@' . $address . ':' . $port);
    }


    function disconnect($client) {
      @socket_shutdown($client->getSocket(), 2);
      @socket_close($client->getSocket());
      unset($this->sockets[array_search($client->getSocket(), $this->sockets)]);
      unset($this->clients[array_search($client, $this->clients)]);
      $console = 'Client ' . $client->getId() . '@' . $client->getIP() . ':' . $client->getPort() . ' on process #'.$client->getPid().' disconnected';
      if($client->getHandshake()){
        $console .= ' and process #'.$client->getPid().' was reaped ... ';
        $socket_pid = $client->getPid();
        posix_kill($socket_pid, SIGTERM);
      }
      $this->console($console);
      $this->console('Total Clients(' . count($this->clients) . ')');
      $this->br();
    }


    function root_functions($client, $data) {
      if(stristr($data, ':')){
        $data = substr($data, 1, (strlen($data) - 1));

        if(substr($data, 0, 1) == '"'){
          $data = substr($data, 1, strlen($data));
        }
        if(substr($data, (strlen($data) - 1), 1) == '"'){
          $data = substr($data, 0, (strlen($data) - 1));
        }

        $parts   = explode(':', $data);
        $action  = trim($parts[0]);
        $payload = trim($parts[1]);
        switch($action){
          case 'kill' :
            foreach($this->clients as $ck){
              if($ck->getId() == $payload){
                $this->console('Found client ID #' . $payload . ', killing connection and process!');
                $this->emit($client, 'Found client ID #' . $payload . ', killing connection and process!');
                $this->emit($ck, $client->getId() . ' killed your connection');
                $this->disconnect($ck);
              }
            }
          break;
        }
      }else{
        switch($data){
          case '"start"' :
            $this->console('The Server issued a start command, via client #' . $client->getId());
            foreach($this->clients as $connected_client){
              $this->emit($connected_client, 'The Server issued a a start command, via client #' . $client->getId());
            }
            $this->run = true;


            return true;
          break;

          case '"stop"' :
            $this->console('The Server issued a stop command, via client #' . $client->getId());
            foreach($this->clients as $send_client){
              $this->emit($send_client, 'The Server issued a stop command, via client #' . $client->getId());
            }
            $this->run = false;


            return true;
          break;

          case '"shutdown"' :
            $this->console('The Server issued an exit command, via client #' . $client->getId());
            foreach($this->clients as $connected_client){
              $this->emit($connected_client, 'The Server issued an exit command, via client #' . $client->getId());
              $this->disconnect($connected_client);
            }
            $this->console('Closing down xServer . . . ');
            die();
          break;

          case '"info"' :
            $info = '';
            $info .= count($this->clients) . ' Connected Clients' . PHP_EOL;
            if($this->admin_id != ''){
              $info .= 'There is one Administrator in control of the socket, ID #' . $this->admin_id . PHP_EOL;
            }
            $info .= 'Client names are: ';
            foreach($this->clients as $c){
              $info .= $c->getId() . '@' . $c->getIP() . ':' . $c->getPort() . ', ';
            }
            $info = rtrim($info, ', ');
            $info .= PHP_EOL;
            $status = ($this->run) ? 'Running' : 'Not Running';
            $info .= 'The server is currently ' . $status . PHP_EOL;
            $this->emit($client, $info);

            return true;
          break;
        }
      }


      return false;
    }


    function incoming_data($client, $data) {
      $data = $this->unmask($data);

      if($data == ''){


        return true;
      }

      if($client->getRoot()){
        if($this->root_functions($client, $data)){


          return true;
        }
      }

      if($data == '"exit"'){
        $this->disconnect($client);


        return false;
      }

      if($data == '"list"'){
        foreach(self::pop() as $idx => $msg){
          $this->emit($client, "[{$idx}] - $msg");
        }


        return true;
      }

      if(stristr($data, ':')){
        $data = substr($data, 1, (strlen($data) - 1));

        if(substr($data, 0, 1) == '"'){
          $data = substr($data, 1, strlen($data));
        }
        if(substr($data, (strlen($data) - 1), 1) == '"'){
          $data = substr($data, 0, (strlen($data) - 1));
        }

        $parts   = explode(':', $data);
        $action  = trim($parts[0]);
        $payload = trim($parts[1]);
        switch($action){
          case 'push' :
            self::push($payload);
            $this->emit($client, 'Successfully added (' . $payload . ') to the Stack.');
            $this->console('Client #' . $client->getId() . ' added "' . $payload . '" the stack');
          break;

          case 'memo' :
            foreach($this->clients as $send_client){
              $this->emit($send_client, $client->getId() . ' says: ' . $payload);
            }
            $this->console('Client #' . $client->getId() . ' says: ' . $payload);
          break;

          case 'pop' :
            $payload = (int)trim($payload);
            foreach(self::pop() as $mdx => $msg){
              if($payload == $mdx){
                $this->emit($client, $msg);
              }
            }
            $this->console('Client #' . $client->getId() . ' viewed the stack');
          break;

          case 'elevate' :
            if($payload == $this->root_pw){
              $client->setRoot();
              $this->admin_id = $client->getId();
              $this->emit($client, 'Elevated to root status - Server Commands: start, stop, shutdown, info, kill:xxx (xxx = client id)');
              $this->console('Elevated client #' . $client->getId() . ' to Administrator - they now control the server');
              foreach($this->clients as $send_client){
                if($send_client->getId() != $client->getId()){
                  $this->emit($send_client, 'Client "' . $client->getId() . '" was elevated to Server Administrator"');
                }
              }
            }else{
              $this->emit($client, 'Root authentication failed - password incorrect');
            }
          break;

          case 'name' :
            if($client->getRoot()){
              $this->admin_id = $payload;
            }
            $this->console('Setting Client name (#' . $client->getId() . ', #' . $payload . ')');
            $client->setId($payload);
            $this->emit($client, 'Changed your ID to "' . $payload . '"');
          break;

          default:
            $this->emit($client, "action({$action}) " . $payload);
          break;

        }
      }else{
        $this->emit($client, 'Bounced: ' . $data);
      }

      return true;
    }


    function start_client_socket($client) {
      umask(0);
      $pid = pcntl_fork();

      if($pid == -1){
        die('Could not spawn new process.');
      }elseif($pid != 0){
        $client->setPid($pid);
        $this->pids[] = getmypid();
        pcntl_signal(SIGCHLD, SIG_IGN);

        return;
      }else{
        if(posix_setsid() == -1){
          $this->console("Error: Unable to detach from the terminal window.");
          exit;
        }

        $this->console('#' . getmypid() . ' forked from #' . $this->pid . ', Total Clients(' . count($this->clients) . '): Socket Listening . . .');
        $this->br();

        while(true){
          usleep(1000);
        }
      }
    }


    function get_client_by_socket($socket) {
      foreach($this->clients as $client){
        if($client->getSocket() == $socket){
          return $client;
        }
      }


      return false;
    }


    public function emit($client, $payload) {
      if($client->getHandshake()){
        $payload = $this->mask($payload);
      }

      if(@socket_write($client->getSocket(), $payload, strlen($payload)) === false){
        $this->console("Unable to write to client #{$client->getId()}'s socket");
        $this->disconnect($client);
      }
    }
  }