<?php
  class xServer {

    use xMask;

    private $root = false; // buggy - reading from terminal would be nice
    private $run  = true;
    private $pid;

    private $verbose = true; // if false no terminal output
    private $mem;

    private $host;
    private $port;

    private $sockets;
    private $clients;
    private $master;


    function __construct($host, $port) {
      ob_start();

      $this->host = $host;
      $this->port = $port;

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


    private function nPush($data) {
      $stack   = $this->mem->get('notification-stack');
      $stack[] = $data;
      $this->mem->set('notification-stack', $stack);
    }


    private function nPop($idx = 'all') {
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
      $this->console('Started server on process #' . getmypid());
      $this->pid = getmypid();
      $this->br();

      if($this->root == true){
        $this->console('Command List: "stop", "start", "list", "exit", "memo:xxxxx", "push:xxxxx", "pop:xxxxx"');
        $stdin = trim(fgets(STDIN, 1024));
        if($stdin != ''){

          if(strstr($stdin, ':')){
            $parts   = explode(':', $stdin);
            $action  = $parts[0];
            $payload = $parts[1];
            switch($action){
              case 'memo' :
                $payload = str_replace('memo:', '', $stdin);
                foreach($this->clients as $send_client){
                  $this->emit($send_client, $payload);
                }
              break;

              case 'push' :
                $this->nPush($payload);
                $this->console('Successfully added (' . $payload . ') to the Stack.');
              break;

              case 'pop' :
                $payload = (int)trim($payload);
                foreach(self::nPop() as $idx => $msg){
                  if($payload == $idx){
                    $this->console($msg);
                  }
                }
              break;

              default:
                $this->console("echo({$stdin})");
              break;
            }
          }else{
            switch($stdin){
              case 'start' :
                $this->console('The Server issued a start command');
                foreach($this->clients as $send_client){
                  $this->emit($send_client, 'The Server issued a a start command');
                }
                $this->run = true;
              break;

              case 'stop' :
                $this->console('The Server issued a stop command');
                foreach($this->clients as $send_client){
                  $this->emit($send_client, 'The Server issued a stop command');
                }
                $this->run = false;
              break;

              case 'exit' :
                $this->console('The Server issued an exit command');
                foreach($this->clients as $connected_client){
                  $this->emit($connected_client, 'The Server issued an exit command');
                  $this->disconnect($connected_client);
                }
                $this->console('Closing down xServer . . . ');
                die();
              break;

              case 'list' :
                foreach(self::nPop() as $stack_pop){
                  $this->console($stack_pop);
                }
              break;

              default :
                $this->console('stdin: ' . $stdin);
              break;
            }
          }
          self::br();
        }
      }

      while(true){

        if(!$this->run){
          continue;
        }

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
                  if($this->handshake($client, $data)){
                    $this->start_client_socket($client);
                  }else{
                    $this->disconnect($client);
                  }
                }elseif($bytes === 0){
                  $this->disconnect($client);
                }else{
                  $this->incoming_data($client, $data);
                }
              }
            }
          }
        }
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
        $upgrade   = $this->generateHandshakeAcceptKey($acceptKey);
        $this->emit($client, $upgrade);
        $client->setHandshake(true);

        return true;
      }else{
        $this->console("WebSocket version 13 required (the client supports version {$version})");

        return false;
      }

    }


    function connect($socket) {
      $client_id                 = uniqid();
      $client                    = new xClient($socket);
      $this->clients[$client_id] = $client;
      $this->sockets[]           = $socket;
      $this->console("Client ID# {$client->getId()} is successfully created!");
    }


    function disconnect($client) {
      if($client->getSocket()){
        @socket_shutdown($client->getSocket(), 2);
        @socket_close($client->getSocket());
        unset($this->sockets[array_search($client->getSocket(), $this->sockets)]);
      }

      unset($this->clients[array_search($client, $this->clients)]);

      $this->console("Client {$client->getId()} disconnected");

      // Killing the clients process kills the parent?
      $socket_pid = $client->getPid();
      posix_kill($socket_pid, SIGKILL);
      $this->console('Client Process #' . $socket_pid . ' terminated, Total Clients(' . count($this->clients . '): Socket has shutdown.');
      $this->br();
    }


    function incoming_data($client, $data) {
      $data = $this->unmask($data);

      if($data == ''){
        return true;
      }

      if($data == '"exit"'){
        $this->disconnect($client);

        return false;
      }

      if($data == '"list"'){
        foreach(self::nPop() as $idx => $msg){
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
          // push data into the stack
          case 'push' :
            self::nPush($payload);
            $this->emit($client, 'Successfully added (' . $payload . ') to the Stack.');
          break;

          // tell all other connected clients
          case 'memo' :
            foreach($this->clients as $send_client){
              $this->emit($send_client, $payload);
            }
          break;

          case 'pop' :
            $payload = (int)trim($payload);
            foreach(self::nPop() as $mdx => $msg){
              if($payload == $mdx){
                $this->emit($client, $msg);
              }
            }
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
        die('Could not spawn new Socket.');
      }elseif($pid != 0){
        $client->setPid($pid);
      }else{
        // dettach from parent signals - allow zombie process to be reaped
        pcntl_signal(SIGCHLD, SIG_IGN);

        if(posix_setsid() == -1){
          $this->console("Error: Unable to detach from the terminal window.");
          exit;
        }

        // Close STDIN
        if($this->root == true){
          fclose(STDIN);
          fclose(STDERR);

          $stdIn  = fopen('/dev/null', 'r'); // set fd/0
          $stdErr = fopen('php://stdout', 'w'); // a hack to duplicate fd/1 to 2
        }

        $this->console('#' . getmypid() . ' forked from #' . $this->pid . ', Total Clients(' . count($this->clients) . '): Socket Listening . . .');
        $this->br();

        // we are the child
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

      // Auto Client drop
      if(@socket_write($client->getSocket(), $payload, strlen($payload)) === false){
        $this->console("Unable to write to client #{$client->getId()}'s socket");
        $this->disconnect($client);
      }
    }
  }