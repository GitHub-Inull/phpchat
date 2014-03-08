<?php

namespace TheFox\PhpChat;

use TheFox\Ipc\Connection;
use TheFox\Ipc\StreamHandler;

class Thread{
	
	private $exit = 0;
	
	public function __construct(){
		
	}
	
	public function setExit($exit = 1){
		$this->exit = $exit;
	}
	
	public function getExit(){
		return (int)$this->exit;
	}
	
}
