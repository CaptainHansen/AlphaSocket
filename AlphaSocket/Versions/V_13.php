<?
namespace AlphaSocket\Versions;

define('WS_V13_GUID','258EAFA5-E914-47DA-95CA-C5AB0DC85B11');

class V_13 extends VersionTemplate {
	private $disconnecting = false;
	private $opcode = false;

	//CLIENT SPECIFIC VARIABLES
	private $client_key;	//client-generated key
	//////////

	public function __construct($socket){
		parent::__construct($socket,'13');
	}
	
	
	////CLIENT SPECIFIC METHODS
	public function clientInitHandshake($host){
		\AlphaSocket\Log::log("Client-side Handshaking - Version 13 ...",2);
		$vals = array(0,1,2,3,4,5,6,7,8,9,'a','b','c','d','e','f');
		$key = "";
		srand(microtime(true));
		for($i = 0; $i < 16; $i ++){
			$key .= dechex(rand(0,255));
		}
		$key = base64_encode(pack('H*',$key));
		
		$headers = "GET / HTTP/1.1\r\n";
		$headers .= "Upgrade: websocket\r\n";
		$headers .= "Host: {$host}\r\n";
		$headers .= "Origin: n/a\r\n";
		$headers .= "Connection: Upgrade\r\n";
		$headers .= "Sec-WebSocket-Version: 13\r\n";
		$headers .= "Sec-WebSocket-Key: {$key}\r\n\r\n";
		
		$this -> key = $key;
		
		\AlphaSocket\Log::log("Sending to server:\n\n".$headers,3);
		
		socket_write($this -> socket,$headers,strlen($headers));
		return true;
	}
	
	public function clientVerifyHandshake($upg){
		\AlphaSocket\Log::log("Response from server:\n\n".$upg,3);
		$regexes = array(
			'/HTTP\/1.1 (\d+) .*\r\n/',
			'/Upgrade: (.*)\r\n/',
			'/Connection: (.*)\r\n/',
			'/Sec-WebSocket-Accept: (.*)\r\n/',
			'/Server: (.*)\r\n/',
		);
		
		$match = array();
		$matches = array();
		foreach($regexes as $id => $pattern){
			if(preg_match($pattern,$upg,$match)) $matches[$id] = $match[1];
		}
		if($matches[0] != '101') return false;
		$ret_key = $matches[3];

		$key = $this -> key;
		$key.= WS_V13_GUID;  //WebSocket version 13 GUID
		$key = sha1($key);
		$key = pack("H*",$key);
		$key = base64_encode($key);
		
		if($ret_key != $key) {
			\AlphaSocket\Log::log("Client-side handshake FAIL - Key = {$this->key}\nRet Key = $ret_key",3);
			return false;
		}
		
		\AlphaSocket\Log::log("Client-side handshaking complete!",2);
		$this -> handshake = true;
		return true;
	}
	//////////////


	/////SERVER SPECIFIC METHODS
	public function serverRespondHandshake($headers){
		list($resource,$host,$origin,$key) = $headers;
		\AlphaSocket\Log::log("Server-side Handshaking - Version 13 ...",2);
		
		$key.= WS_V13_GUID;
		$key = sha1($key);
		$key = pack("H*",$key);
		$key = base64_encode($key);
		
		$upgrade =	"HTTP/1.1 101 WebSocket Protocol Handshake\r\n" .
					"Upgrade: WebSocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: {$key}\r\n" .
					"Server: AlphaSocket WebSocket Server\r\n\r\n";
		
		socket_write($this -> socket,$upgrade,strlen($upgrade));
		$this -> handshake = true;
		\AlphaSocket\Log::log($upgrade,3);
		\AlphaSocket\Log::log("Done handshaking...",2);
		return true;
	}
	//////////////
		
/**

  0                   1                   2                   3
  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 +-+-+-+-+-------+-+-------------+-------------------------------+
 |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
 |I|S|S|S|  (4)  |A|     (7)     |             (16/63)           |
 |N|V|V|V|       |S|             |   (if payload len==126/127)   |
 | |1|2|3|       |K|             |                               |
 +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
 |     Extended payload length continued, if payload len == 127  |
 + - - - - - - - - - - - - - - - +-------------------------------+
 |                               |Masking-key, if MASK set to 1  |
 +-------------------------------+-------------------------------+
 | Masking-key (continued)       |          Payload Data         |
 +-------------------------------- - - - - - - - - - - - - - - - +
 :                     Payload Data continued ...                :
 + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
 |                     Payload Data continued ...                |
 +---------------------------------------------------------------+
  1 0 0 0|0 0 0 1|1 0 0 0|0 1 0 0 
	//81843180423963ef2152
*/

	public function Receive(){
		$alldata = "";
		$tlen = 0;
		$lframe = false;

		if($this -> disconnecting) {
			list($lframe,$opcode,$len,$mask) = $this -> getHeader();
			\AlphaSocket\Log::log("Receiving response after a disconnect request.",1);
			socket_close($this -> socket);
			return array($this -> getData($mask,$len),$len,true);
		}
			
		while(!$lframe){
			list($lframe,$opcode,$len,$mask) = $this -> getHeader();
			$tlen += $len;
			switch($opcode){
			case 0: //continuation frame.
				if($len == 0){	//received length of 0 in a continuation frame.  this is a big problem...  disconnect the client
					$this -> Disconnect("0 length in a continuation frame.  This a problem - Disconnecting.");
					return array(false,false,false);
				}
				\AlphaSocket\Log::log("Receiving another frame",2);
				$alldata .= $this -> getData($mask,$len);
				break;
			case 1: //text frame
				\AlphaSocket\Log::log("Receiving a text frame",2);
				$alldata .= $this -> getData($mask,$len);
				break;
			case 2:	//binary data
				\AlphaSocket\Log::log("Receiving a binary frame",2);
				$alldata .= $this -> getData($mask,$len);
				break;
			case 8: //close frame received, send one back
				\AlphaSocket\Log::log("Disconnect frame received.  Sending one back and closing the connection.",1);
				$frame = $this -> wrap("OK","\x88",false);
				$response = $this -> getData($mask,$len);
				
				socket_send($this -> socket,$frame,strlen($frame),MSG_EOF);
				socket_close($this -> socket);
				return array($response,$len,true);
				break;
			case 9:	//ping
				if(!$lframe) throw new Exception('fuck');
				$data = $this -> getData($mask,$len);
				$frame = $this -> wrap($data,"\x8A",false);
				return array(false,false,false);
				
			case 10:	//pong
				if(!$lframe) throw new Exception("Afuck");
				$data = $this -> getData($mask,$len);
				return array(false,false,false);
				
			default: //opcode not recognized, send a disconnect
				$this -> Disconnect("Opcode {$opcode} not recognized or implemented.  Closing the connection.");
				return array(false,false,false);
				break;
			}
		}
		return array($alldata,$tlen,false);
	}
	
	public function Send($msg){
		$frame = $this -> wrap($msg,"\x81");
		return socket_send($this -> socket,$frame,strlen($frame),0);
	}
	
	public function Disconnect($msg){
		\AlphaSocket\Log::log($msg,1);
		if(!$this -> checkHandshake()){
			socket_close($this -> socket);
			return true;
		}
		$this -> disconnecting = true;
		$frame = $this -> wrap($msg,"\x88",false);
		return socket_send($this -> socket,$frame,strlen($frame),0);
	}
		
		
	
	private function getHeader(){
		$buffer = "";
		\AlphaSocket\Log::log("Receiving header bytes 0 and 1",3);
		$bytes = @socket_recv($this -> socket,$buffer,2,0);
		$opc = ord(substr($buffer,0,1));
		
		$lframe = ($opc >> 7) == 1;
		$opcode = $opc & 15;
		
		$len1 = ord(substr($buffer,1,1));
		$has_mask = ($len1 >> 7) == 1;
		
		$len1 = $len1 & 127;
		switch($len1){
			case 126:
				\AlphaSocket\Log::log("Receiving 16-bit payload length",3);
				$bytes = @socket_recv($this -> socket,$buffer,2,0);
				$d = unpack("n",$buffer);
				$len = $d[1];
				break;
			case 127:
				\AlphaSocket\Log::log("Receiving 64-bit payload length",3);
				$bytes = @socket_recv($this -> socket,$buffer,8,0);
				$d = unpack("N",substr($buffer,0,4));
				$len = ($d[1] << 32);
				$d = unpack("N",substr($buffer,4,4));
				$len += $d[1];
				break;
			default:
				$len = $len1;
				break;
		}

		$mask_d=null;		
		if($has_mask){
			\AlphaSocket\Log::log("Data is masked, receiving 4 byte mask",3);
			$bytes = @socket_recv($this -> socket,$buffer,4,0);
			$mask_d = array();
			for($i = 0; $i < 4; $i++){
				$mask_d[$i] = ord(substr($buffer,$i,1));
			}
		}
		
		\AlphaSocket\Log::log("Header Successfully received!",2);
		if($has_mask) \AlphaSocket\Log::log("Lframe = {$lframe}, Opcode = {$opcode}, Length = {$len}, Mask = ".ord($mask_d[0])."-".ord($mask_d[1])."-".ord($mask_d[2])."-".ord($mask_d[3]),3);
		return array($lframe,$opcode,$len,$mask_d);
	}
	
	private function getData($mask,$len){
		if($len == 0) return "";
		$bytes = @socket_recv($this -> socket,$buffer,$len+1,0);
		if($bytes != $len) {
			\AlphaSocket\Log::log("Data bytes received does not match data bytes expected!",3);
			return false;
		} else {
			$msg = "";
			if(is_array($mask)){
				for($i = 0; $i < $len; $i++){
					$char = ord(substr($buffer,$i,1));
					$msg .= chr($mask[$i&3] ^ $char);
				}
			} else {
				$msg = $buffer;
			}
			return $msg;
		}
	}
	
	private function wrap($msg,$header="\x81",$mask=true){
		//when sending, not using a mask, text data only, one frame.
		$mask = (AS_TYPE == 'CLIENT');
		if($mask){
			$mbit = 128;
		} else {
			$mbit = 0;
		}
		$len = strlen($msg);
		if($len > 125){
			if($len > 65535){
				$header.=chr(127 | $mbit);
				$header.=pack("N",$len>>32);
				$header.=pack("N",$len & "\xFF\xFF\xFF\xFF");
			} else {
				$header.=chr(126 | $mbit);
				$header.=pack("n",$len);
			}
		} else {
			$header .= chr($len | $mbit);
		}
		
		if($mask){
			$mask_d = array();
			for($i = 0; $i < 4; $i ++){
				$mask_d[$i] = rand(0,255);
				$header.= chr($mask_d[$i]);	//add mask to the header
			}
			
			$msk_msg = "";
			for($i = 0; $i < strlen($msg); $i ++){
				$char = ord(substr($msg,$i,1));
				$msk_msg.= chr( $mask_d[$i&3] ^ $char);
			}
			$msg = $msk_msg;
		}

		$hex_head = unpack("H*",$header.$msg);
		\AlphaSocket\Log::log("Data: ".$hex_head[1]." Length = ".$len,3);
		
		return ($header.$msg);
	}
}
