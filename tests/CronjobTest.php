<?php

use TheFox\PhpChat\Cronjob;
use TheFox\PhpChat\MsgDb;
use TheFox\PhpChat\Msg;
use TheFox\PhpChat\Settings;
use TheFox\Dht\Kademlia\Table;
use TheFox\Dht\Kademlia\Node;

class CronjobTest extends PHPUnit_Framework_TestCase{
	
	const NODE0_SSL_KEY_PUB = '-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAtIIiZm70ZPEIOd+FIqr7
E7qav8+jNvI08LVhMSmOHE1s9WymLTChv2a10J8fhYY9ipIyc8WnCzN5Amtth9hK
LcZXi11Oi6n6+fBGyREoc9KQamu6ZQ9bVkJ1s4yVLzVF9k3JHyMO4GgdlJ7lZTlf
5GDOffj/KuLmhalO0XzOW49Eng9jV7RK/88iNbJwGm2Mn/66rn1fiEnMXz6kuvNX
785pOvPJH0wzXIBQsZ9m+zURJsbc979peMHrno85Y+ZmVQdwXXRaZcO0QozltULD
4r+1R8raYSH4Nwm5YyMuRONNuyCL9a6Q/AmVGqxcvz7IDKjizptEF1BE2ko9OAmr
NyPaxwK89JPRNXkxN/AnlojFzIg/8MES2O+rPMK5izLxqo8nNVmeCmhfwi0NwMkq
aJcenHXLPG+Hz5cov5cbpwzXIe5nxS4PSpkr+oqpTLWUqAz5hbjTZ96pMuo6huCF
StRMjmmfNXb47TeLhFS+OlCQlLBHwvXaHGl3wZ7f7eumUfhy3AOx5/8tsuUeItfp
CoEHq/SeWAUt4rxD/HW6gRf/cdY0GhbLgqwTIs8keft7BHQokwOvTI/o1sNFEGh1
cL7QNOI5Cv1SHZ0j85N1XmuHuIldXrjYkFRDJXifqgofMzM8M6J+0f74iupQLNwx
X7E/kioxMTLuoxs1R1+aatsCAwEAAQ==
-----END PUBLIC KEY-----';
	const NODE2_SSL_KEY_PUB = '-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAq8TIxa14ktxxDd7nyppI
a5ic4bNDCPyNkzIrps78uH9rALeZGMVqJSt1BizPTCJdL9Uy4BZ4dv2qE5n1IN9E
udBUzqLAhTVK9cZooFRFrqmZv7BUAB/T+7/Gc24mAOl7kGwuIBBTuOiDd5J0QkRQ
cmiVV0p4XThaI2uH8Xp+pUpL1ablkFtKGarhxZuCJAOb2t1U2pxumquGtj5oszZT
1ek+s5LBvqJ0CYrrWGE+T+nboR2YvMsTKG/zbdX2JtDEHuDPZzeSxvw6Hv3aPiU+
2aE2Szj6xTzXrMiskCkkM2i3GF/ZwomJfyszELsbyK5Brkb+wSSoNzU6N/2T+ou/
nEygFuaI1BwpQET/y/t9LATNyandVlC8+BlQoGdrE1dNDA+SKtkLLLZGcu3oG5/L
mST+6WLQP6W2j1TWW7u9FfYb0DXQ9RjUDCOomP3doJPGYK5skV7ikCrnKftKLQhC
C6+rvBhPPnCrK4rd0q9lz0H8SAtu18W+EQB8cc4323jis1vs6vkZ9c6LxnTLe2pO
kvgfS69CROdHHFmsZI8y0Lqlo6aC7JIEW5vtMWPNvBmc/GlsZDerK3I3R17JGnB0
xs0c/uLAMzZRFhqwaH2lOsMrU9RD75dKDPF5o3hV7ZQ8knlkDXhk+5WCL6UK9SVQ
oBtclXATtUzixobkK04g4KMCAwEAAQ==
-----END PUBLIC KEY-----';
	
	protected static $cronjob = null;
	protected static $nodes = array();
	protected static $msgs = array();
	
	public static function setUpBeforeClass(){
		fwrite(STDOUT, __METHOD__.''."\n");
		
		$settings = new Settings('./settings.yml');
		
		$localNode = new Node();
		$localNode->setIdHexStr($settings->data['node']['id']);
		$localNode->setPort($settings->data['node']['port']);
		$localNode->setSslKeyPub(file_get_contents($settings->data['node']['sslKeyPubPath']));
		
		self::$nodes[0] = new Node();
		self::$nodes[0]->setIdHexStr('10000000-1000-4001-8001-100000000000');
		self::$nodes[0]->setSslKeyPub(static::NODE0_SSL_KEY_PUB);
		
		self::$nodes[1] = new Node();
		self::$nodes[1]->setIdHexStr('10000000-1000-4001-8001-100000000001');
		
		self::$nodes[2] = new Node();
		self::$nodes[2]->setIdHexStr('10000000-1000-4001-8001-100000000002');
		self::$nodes[2]->setSslKeyPub(static::NODE2_SSL_KEY_PUB);
		
		self::$nodes[3] = new Node();
		self::$nodes[3]->setIdHexStr('10000000-1000-4001-8001-100000000003');
		
		$table = new Table();
		$table->setDatadirBasePath($settings->data['datadir']);
		$table->setLocalNode($localNode);
		$table->nodeEnclose(self::$nodes[0]);
		$table->nodeEnclose(self::$nodes[1]);
		$table->nodeEnclose(self::$nodes[2]);
		#$table->nodeEnclose(self::$nodes[3]); // Test not in table.
		
		$msgDb = new MsgDb();
		$msgDb->setDatadirBasePath($settings->data['datadir']);
		
		for($nodeNo = 1000; $nodeNo <= 1004; $nodeNo++){
			$msg = new Msg();
			
			$msg->setId('20000000-2000-4002-8002-20000000'.$nodeNo);
			self::assertEquals('20000000-2000-4002-8002-20000000'.$nodeNo, $msg->getId());
			
			$msg->setSrcNodeId($settings->data['node']['id']);
			$msg->setSrcSslKeyPub($table->getLocalNode()->getSslKeyPub());
			$msg->setSrcUserNickname($settings->data['user']['nickname']);
			
			$msg->setText('this is  a test. '.date('Y/m/d H:i:s'));
			$msg->setSslKeyPrvPath($settings->data['node']['sslKeyPrvPath'], $settings->data['node']['sslKeyPrvPass']);
			$msg->setStatus('O');
			
			$msg->setDstNodeId( self::$nodes[0]->getIdHexStr() );
			
			$msg->setEncryptionMode('D');
			$msg->setDstSslPubKey( self::$nodes[0]->getSslKeyPub() );
			
			self::$msgs[$nodeNo] = $msg;
		}
		
		self::$msgs[1001]->setDstNodeId( self::$nodes[1]->getIdHexStr() );
		self::$msgs[1001]->setEncryptionMode('S');
		self::$msgs[1001]->setDstSslPubKey($table->getLocalNode()->getSslKeyPub());
		self::assertEquals('S', self::$msgs[1001]->getEncryptionMode());
		
		self::$msgs[1002]->setDstNodeId( self::$nodes[2]->getIdHexStr() );
		self::$msgs[1002]->setEncryptionMode('S');
		self::$msgs[1002]->setDstSslPubKey($table->getLocalNode()->getSslKeyPub());
		self::assertEquals('S', self::$msgs[1002]->getEncryptionMode());
		
		self::$msgs[1003]->setDstNodeId( self::$nodes[3]->getIdHexStr() );
		self::$msgs[1003]->setEncryptionMode('S');
		self::$msgs[1003]->setDstSslPubKey($table->getLocalNode()->getSslKeyPub());
		self::assertEquals('S', self::$msgs[1003]->getEncryptionMode());
		
		self::$msgs[1004]->setSentNodes(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));
		self::assertEquals(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), self::$msgs[1004]->getSentNodes());
		
		self::$cronjob = new Cronjob();
		self::$cronjob->setMsgDb($msgDb);
		self::$cronjob->setSettings($settings);
		self::$cronjob->setTable($table);
	}
	
	public function testEncrpt(){
		foreach(self::$msgs as $msgId => $msg){
			$encrypted = false;
			try{
				$encrypted = $msg->encrypt();
			}
			catch(Exception $e){
				print 'ERROR: '.$e->getMessage().PHP_EOL;
			}
			$this->assertTrue($encrypted);
			
			$rv = self::$cronjob->getMsgDb()->msgAdd($msg);
		}
	}
	
	public function testMsgDbInitNodes(){
		self::$cronjob->msgDbInitNodes();
		
		$msgs = self::$cronjob->getMsgDb()->getMsgs();
		
		$this->assertGreaterThanOrEqual(3, self::$cronjob->getMsgDb()->getMsgsCount() );
		$this->assertGreaterThanOrEqual(3, count($msgs));
		
		$this->assertEquals(self::$msgs[1000], $msgs['20000000-2000-4002-8002-200000001000']);
		$this->assertEquals(self::$msgs[1001], $msgs['20000000-2000-4002-8002-200000001001']);
		$this->assertEquals(self::$msgs[1002], $msgs['20000000-2000-4002-8002-200000001002']);
		$this->assertEquals(self::$msgs[1003], $msgs['20000000-2000-4002-8002-200000001003']);
		
		$this->assertEquals('D', $msgs['20000000-2000-4002-8002-200000001000']->getEncryptionMode());
		$this->assertEquals('S', $msgs['20000000-2000-4002-8002-200000001001']->getEncryptionMode());
		$this->assertEquals('D', $msgs['20000000-2000-4002-8002-200000001002']->getEncryptionMode());
		$this->assertEquals('S', $msgs['20000000-2000-4002-8002-200000001003']->getEncryptionMode());
	}
	
	public function testMsgDbSendAll(){
		#$this->markTestIncomplete('This test has not been implemented yet.');
		
		$updateMsgs = self::$cronjob->msgDbSendAll();
		ve($updateMsgs);
		
	}
	
}
