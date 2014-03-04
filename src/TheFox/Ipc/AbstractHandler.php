<?php

namespace TheFox\Ipc;

abstract class AbstractHandler{
	
	private $ip;
	private $port;
	private $handle;
	
	private $isConnected = null;
	private $isListening = null;
	private $hasData = false;
	
	private $clientsId = 0;
	private $clients = array();
	private $clientsByHandles = array();
	
	private $recvBufferId = 0;
	private $recvBuffer = array();
	private $recvBufferTmp = '';
	
	
	abstract public function connect();
	abstract public function listen();
	abstract public function run();
	abstract public function handleDataSend($handle, $data);
	abstract public function handleDataRecv($handle);
	
	
	public function send($data, $clientId = null){
		print __CLASS__.'->'.__FUNCTION__.': "'.$data.'"'."\n";
		
		if($this->isListening()){ // Server
			if($clientId !== null && isset($this->clients[$clientId])){
				$client = $this->clients[$clientId];
				$this->handleDataSend($client['handle'], base64_encode($data).$this->getSendDelimiter());
			}
		}
		elseif($this->isConnected()){ // Client
			$this->handleDataSend($this->getHandle(), base64_encode($data).$this->getSendDelimiter());
		}
	}
	
	public function sendId($clientId = null){
		$this->send('ID', $clientId);
	}
	
	public function sendIdOk($clientId = null){
		$this->send('ID_OK', $clientId);
	}
	
	public function sendFunctionExec($name, $args = array(), $rid = 0, $clientId = null){
		$argsOut = array();
		foreach($args as $arg){
			$argsOut[] = serialize($arg);
		}
		
		$json = array(
			'name' => $name,
			'rid' => $rid,
			'args' => $argsOut,
		);
		$jsonStr = json_encode($json);
		
		$this->send('FUNCTION_EXEC '.$jsonStr, $clientId);
	}
	
	public function sendFunctionRetn($value, $rid = 0, $clientId = null){
		$json = array(
			'value' => serialize($value),
			'rid' => $rid,
		);
		$jsonStr = json_encode($json);
		
		$this->send('FUNCTION_RETN '.$jsonStr, $clientId);
	}
	
	public function recv($handle, $data){
		$dataLen = strlen($data);
		print __CLASS__.'->'.__FUNCTION__.': '.$dataLen.''."\n";
		
		if($this->isListening()){
			$client = $this->clientFindByHandle($handle);
			$this->clientHandleRevcData($client, $data);
		}
		elseif($this->isConnected()){
			$this->hasData(true);
			
			do{
				$delimiterPos = strpos($data, $this->getSendDelimiter());
				if($delimiterPos === false){
					#print "data1.1: '$data'\n";
					#$this->recvBuffer[$this->recvBufferId] .= $data;
					$this->recvBufferTmp .= $data;
					$data = '';
				}
				else{
					$msg = $this->recvBufferTmp.substr($data, 0, $delimiterPos);
					$this->recvBufferTmp = '';
					#print "data1.2: '$msg'\n";
					
					$this->recvBufferId++;
					$this->recvBuffer[$this->recvBufferId] = base64_decode($msg);
					
					$data = substr($data, $delimiterPos + 1);
				}
				
			}while($data);
		}
	}
	
	public function recvBuffer(){
		$recvBuffer = array();
		
		if($this->isListening()){
			foreach($this->clients as $clientId => $client){
				if($client['recvBuffer']){
					$recvBuffer[] = array(
						'id' => $client['id'],
						'recvBuffer' => $client['recvBuffer'],
					);
					
					$this->clients[$client['id']]['recvBufferId'] = 0;
					$this->clients[$client['id']]['recvBuffer'] = array();
				}
			}
		}
		elseif($this->isConnected()){
			$recvBuffer = $this->recvBuffer;
			
			$this->recvBufferId = 0;
			$this->recvBuffer = array();
		}
		
		$this->hasData(false);
		
		return $recvBuffer;
	}
	
	public function setIp($ip){
		$this->ip = $ip;
	}
	
	public function getIp(){
		return $this->ip;
	}
	
	public function setPort($port){
		$this->port = (int)$port;
	}
	
	public function getPort(){
		return $this->port;
	}
	
	public function setHandle($handle){
		$this->handle = $handle;
	}
	
	public function getHandle(){
		return $this->handle;
	}
	
	public function isConnected($isConnected = null){
		if($isConnected !== null){
			$this->isConnected = $isConnected;
		}
		return $this->isConnected;
	}
	
	public function isListening($isListening = null){
		if($isListening !== null){
			$this->isListening = $isListening;
		}
		return $this->isListening;
	}
	
	public function hasData($hasData = null){
		if($hasData !== null){
			$this->hasData = $hasData;
		}
		#print __CLASS__.'->'.__FUNCTION__.': '.(int)$this->hasData."\n";
		return $this->hasData;
	}
	
	public function getSendDelimiter(){
		return "\n";
	}
	
	public function getClients(){
		return $this->clients;
	}
	
	public function clientAdd($handle){
		$this->clientsId++;
		$this->clients[$this->clientsId] = array(
			'id' => $this->clientsId,
			'handle' => $handle,
			'recvBufferId' => 0,
			'recvBuffer' => array(),
			'recvBufferTmp' => '',
			#'sendBufferId' => 0,
			#'sendBuffer' => array(),
		);
		
		return $this->clients[$this->clientsId];
	}
	
	public function clientHandleRevcData($client, $data){
		$dataLen = strlen($data);
		if($dataLen){
			$this->hasData(true);
			
			do{
				$clientId = $client['id'];
				
				$delimiterPos = strpos($data, $this->getSendDelimiter());
				if($delimiterPos === false){
					#print "data2.1: ".$clientId.", '$data'\n";
					
					$this->clients[$clientId]['recvBufferTmp'] .= $data;
					$data = '';
				}
				else{
					$msg = $this->clients[$clientId]['recvBufferTmp'].substr($data, 0, $delimiterPos);
					$this->clients[$clientId]['recvBufferTmp'] = '';
					#print "data2.2: ".$clientId.", '$msg'\n";
					
					$this->clients[$clientId]['recvBufferId']++;
					$this->clients[$clientId]['recvBuffer'][$this->clients[$clientId]['recvBufferId']] = base64_decode($msg);
					
					$data = substr($data, $delimiterPos + 1);
				}
				
			}while($data);
		}
	}
	
	public function clientFindByHandle($handle){
		foreach($this->clients as $clientId => $client){
			if($client['handle'] == $handle){
				return $client;
			}
		}
		
		return null;
	}
	
	public function clientRemove($client){
		unset($this->clients[$client['id']]);
	}
	
}