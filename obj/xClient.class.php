<?php
  class xClient {

    use xMask;

    private $root = false;
    private $id;
    private $socket;
    private $pid;
    private $ip;
    private $port;
    private $handshake = false;


    public function __construct($socket) {
      $this->setSocket($socket);
      $this->setId(uniqid());
    }

    public function setRoot($root = true){
      $this->root = $root;
    }

    public function getRoot(){
      return $this->root;
    }

    public function setId($id) {
      $this->id = $id;
    }

    public function getId() {
      return $this->id;
    }

    public function setSocket($socket) {
      $this->socket = $socket;
    }

    public function getSocket() {
      return $this->socket;
    }

    public function setPid($pid) {
      $this->pid = $pid;
    }

    public function getPid() {
      return $this->pid;
    }

    public function getIP() {
      return $this->ip;
    }

    public function setIP($ip) {
      $this->ip = $ip;
    }

    public function getPort() {
      return $this->port;
    }

    public function setPort($port) {
      $this->port = $port;
    }

    public function setHandshake($handshake) {
      $this->handshake = $handshake;
    }

    public function getHandshake() {
      return $this->handshake;
    }
  }