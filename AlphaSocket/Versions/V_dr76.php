<?
namespace AlphaSocket\Versions;

class V_dr76 extends VersionTemplate {

	public function __construct($socket){
		parent::__construct($socket,'dr76');
	}

	public function openHandshake($headers){
		list($resource,$host,$origin,$strkey1,$strkey2,$data) = $headers;
		\AlphaSocket\Log::log("Handshaking - Version #76 ...",2);
		
		$pattern = '/[^\d]*/';
		$replacement = '';
		$numkey1 = preg_replace($pattern, $replacement, $strkey1);
		$numkey2 = preg_replace($pattern, $replacement, $strkey2);
		
		$pattern = '/[^ ]*/';
		$replacement = '';
		$spaces1 = strlen(preg_replace($pattern, $replacement, $strkey1));
		$spaces2 = strlen(preg_replace($pattern, $replacement, $strkey2));
	
		if ($spaces1 == 0 || $spaces2 == 0 || $numkey1 % $spaces1 != 0 || $numkey2 % $spaces2 != 0) {
			socket_close($this->socket);
			\AlphaSocket\Log::log('Handshake Failed!',0);
			return false;
		}
	
		$ctx = hash_init('md5');
		hash_update($ctx, pack("N", $numkey1/$spaces1));
		hash_update($ctx, pack("N", $numkey2/$spaces2));
		hash_update($ctx, $data);
		$hash_data = hash_final($ctx,true);
		
		$upgrade  = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" .
				  "Upgrade: WebSocket\r\n" .
				  "Connection: Upgrade\r\n" .
				  "Sec-WebSocket-Origin: " . $origin . "\r\n" .
				  "Sec-WebSocket-Location: ws://" . $host . $resource . "\r\n" .
				  "\r\n" .
				  $hash_data;
		
		socket_write($this -> socket,$upgrade.chr(0),strlen($upgrade.chr(0)));
		$this->handshake=true;
		\AlphaSocket\Log::log($upgrade,3);
		\AlphaSocket\Log::log("Done handshaking...",2);
		return true;
	}
	
	public function unwrap($msg=""){
		return substr($msg,1,strlen($msg)-2);
	}
	
	public function wrap($msg="") {
		return chr(0).$msg.chr(255);
	}
}
