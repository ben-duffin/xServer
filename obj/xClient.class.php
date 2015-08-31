<?php
  class xClient {

    use xMask;

    private $id;
    private $socket;
    private $pid;
    private $handshake = false;


    public function __construct($socket) {
      $this->setSocket($socket);
      $this->setId(uniqid());
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


    public function setHandshake($handshake) {
      $this->handshake = $handshake;
    }


    public function getHandshake() {
      return $this->handshake;
    }
  }