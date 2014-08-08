<?php

namespace TheFox\Console\Command;

use RuntimeException;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#use Zend\Mail\Message;
#use Zend\Mail\Headers;

use TheFox\Smtp\Server;
use TheFox\Smtp\Event;
use TheFox\Ipc\ConnectionClient;
use TheFox\Ipc\StreamHandler as IpcStreamHandler;
use TheFox\PhpChat\Msg;
use TheFox\Dht\Kademlia\Node;

class SmtpCommand extends BasicCommand{
	
	const LOOP_USLEEP = 10000;
	
	private $server;
	private $settings;
	private $ipcKernelConnection = null;
	private $ipcKernelShutdown = false;
	
	public function getLogfilePath(){
		return 'log/smtp.log';
	}
	
	public function getPidfilePath(){
		return 'pid/smtp.pid';
	}
	
	protected function configure(){
		$this->setName('smtp');
		$this->setDescription('Run the SMTP server.');
		$this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run in daemon mode.');
		$this->addOption('address', 'a', InputOption::VALUE_REQUIRED,
			'The address of the network interface. Default = 127.0.0.1');
		$this->addOption('port', 'p', InputOption::VALUE_REQUIRED,
			'The port of the network interface. Default = 21025');
		$this->addOption('shutdown', 's', InputOption::VALUE_NONE, 'Shutdown.');
	}
	
	private function initIpcKernelConnection(){
		$this->ipcKernelConnection = new ConnectionClient();
		$this->ipcKernelConnection->setHandler(new IpcStreamHandler('127.0.0.1', 20003));
		$this->ipcKernelConnection->functionAdd('shutdown', $this, 'ipcKernelShutdown');
		foreach(array(
			'mailAdd'
		) as $functionName){
			$this->ipcKernelConnection->functionAdd($functionName, $this, $functionName);
		}
		
		if(!$this->ipcKernelConnection->connect()){
			throw new RuntimeException('Could not connect to kernel process.');
		}
	}
	
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->executePre($input, $output);
		$this->initIpcKernelConnection();
		
		$this->settings = $this->ipcKernelConnection->execSync('getSettings');
		$this->log->debug('settings: '.(is_object($this->settings) ? 'OK' : 'failed'));
		
		$address = '127.0.0.1';
		if($input->getOption('address')){
			$address = $input->getOption('address');
		}
		
		$port = 21025;
		if($input->getOption('port')){
			$port = (int)$input->getOption('port');
		}
		
		$this->log->info('server start');
		$this->server = new Server($address, $port);
		$this->server->setLog($this->log);
		
		$eventMailNew = new Event(Event::TRIGGER_MAIL_NEW, $this, 'mailNew');
		$this->server->eventAdd($eventMailNew);
		
		try{
			$this->server->init();
		}
		catch(Exception $e){
			$this->log->error('init: '.$e->getMessage());
			exit(1);
		}
		
		try{
			$this->server->listen();
		}
		catch(Exception $e){
			$this->log->error('listen: '.$e->getMessage());
			exit(1);
		}
		
		while(!$this->getExit()){
			#$this->log->debug('run');
			
			if(!$this->ipcKernelConnection->run()){
				$this->log->info('Connection to kernel process end unexpected.');
				$this->setExit(1);
			}
			
			try{
				$this->server->run();
			}
			catch(Exception $e){
				$this->log->error('run: '.$e->getMessage());
				exit(1);
			}
			
			usleep(static::LOOP_USLEEP);
		}
		
		$this->executePost();
		$this->log->info('exit');
	}
	
	public function signalHandler($signal){
		$this->exit++;
		
		switch($signal){
			case SIGTERM:
				$this->log->notice('signal: SIGTERM');
				break;
			case SIGINT:
				print "\n";
				$this->log->notice('signal: SIGINT');
				break;
			case SIGHUP:
				$this->log->notice('signal: SIGHUP');
				break;
			case SIGQUIT:
				$this->log->notice('signal: SIGQUIT');
				break;
			case SIGKILL:
				$this->log->notice('signal: SIGKILL');
				break;
			case SIGUSR1:
				$this->log->notice('signal: SIGUSR1');
				break;
			default:
				$this->log->notice('signal: N/A');
		}
		
		$this->log->notice('main abort ['.$this->exit.']');
		
		if($this->server){
			$this->server->setExit($this->exit);
			$this->server->shutdown();
		}
		if($this->exit >= 2){
			exit(1);
		}
	}
	
	public function ipcKernelShutdown(){
		$this->log->info('kernel shutdown');
		
		$this->log->info('Connection to kernel process closed.');
		$this->setExit(1);
		$this->ipcKernelShutdown = true;
		
		if($this->server){
			$this->server->setExit($this->exit);
			$this->server->shutdown();
		}
		
		return null;
	}
	
	public function mailNew($event, $from, $rcpt, $mail){
		#fwrite(STDOUT, 'mail new: /'.$from.'/ a/'.join('/ /', $rcpt).'/'."\n");
		
		$table = $this->ipcKernelConnection->execSync('getTable');
		$text = 'Subject: '.$mail->getSubject().PHP_EOL;
		$text .= ''.PHP_EOL;
		$text .= $mail->getBody().PHP_EOL;
		
		foreach($rcpt as $dstNodeId){
			$dstNodeId = substr($dstNodeId, 0, strpos($dstNodeId, '@'));
			
			#fwrite(STDOUT, 'to: /'.$dstNodeId.'/'."\n");
			
			$msg = new Msg();
			$msg->setSrcNodeId($this->settings->data['node']['id']);
			$msg->setSrcSslKeyPub($table->getLocalNode()->getSslKeyPub());
			$msg->setSrcUserNickname($this->settings->data['user']['nickname']);
			
			$dstNode = new Node();
			$dstNode->setIdHexStr($dstNodeId);
			
			$msg->setDstNodeId($dstNode->getIdHexStr());
			if($oDstNode = $table->nodeFindInBuckets($dstNode)){
				#print 'found node in table'.PHP_EOL;
				$msg->setDstSslPubKey($oDstNode->getSslKeyPub());
			}
			#else{ print 'node not found'.PHP_EOL; }
			
			$msg->setText($text);
			$msg->setSslKeyPrvPath($this->settings->data['node']['sslKeyPrvPath'], $this->settings->data['node']['sslKeyPrvPass']);
			$msg->setStatus('O');
			
			$encrypted = false;
			#print 'DstSslPubKey: '.strlen($msg->getDstSslPubKey()).PHP_EOL;
			if($msg->getDstSslPubKey()){
				#print 'use dst key'.PHP_EOL;
				
				$msg->setEncryptionMode('D');
			}
			else{
				// Encrypt with own public key
				// while destination public key is not available.
				#print 'use local key'.PHP_EOL;
				
				$msg->setEncryptionMode('S');
				$msg->setDstSslPubKey($table->getLocalNode()->getSslKeyPub());
			}
			
			try{
				$encrypted = $msg->encrypt();
				
				if($encrypted){
					$this->ipcKernelConnection->execAsync('msgDbMsgAdd', array($msg));
					
					$this->log->debug('OK: msg created '.$msg->getId());
				}
				else{
					$this->log->error('Could not encrypt message.');
				}
			}
			catch(Exception $e){
				$this->log->error('ERROR: '.$e->getMessage());
			}
		}
	}
	
}