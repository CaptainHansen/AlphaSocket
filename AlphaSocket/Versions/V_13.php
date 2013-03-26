<?
namespace AlphaSocket\Versions;

class V_13 extends VersionTemplate {

	public function __construct($socket){
		parent::__construct($socket,'13');
	}

	public function openHandshake($socket,$headers){
		list($resource,$host,$origin,$key) = $headers;
		\AlphaSocket\Log::log("Handshaking - Version 13 ...",2);
		
		$key.="258EAFA5-E914-47DA-95CA-C5AB0DC85B11";  //WebSocket version 13 GUID
		$key = sha1($key);
		$key = pack("H*",$key);
		$key = base64_encode($key);
		
		$upgrade =	"HTTP/1.1 101 WebSocket Protocol Handshake\r\n" .
					"Upgrade: WebSocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: {$key}\r\n" .
					"Server: AlphaSocket WebSocket Server\r\n\r\n";
		
		socket_write($socket,$upgrade,strlen($upgrade));
		$this -> handshake = true;
		\AlphaSocket\Log::log($upgrade,3);
		\AlphaSocket\Log::log("Done handshaking...",2);
		return true;
	}
	
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
	
	public function unwrap($msg){
		\AlphaSocket\Log::log("\n\nUNWRAPPING V.13 DATA",2);
		//doing bitwise stuff with PHP is going to SUUUCCCCCKKKKKKKKKK
		$test = unpack("H*",$msg);
		\AlphaSocket\Log::log("Data: ".$test[1],3);
		
		$next = 0;
		$fin = ord(substr($msg,$next,1));
		if(($fin >> 7) != 1){
			\AlphaSocket\Log::log("This is NOT the last frame....",2);
		}
		switch($fin & 15) {
		case 1:
			//continue as normal, this is a text frame.
			break;
			
		case 8:
			//client wishes to disconnect.
			console("Disconnect request received - must disconnect (not implemented!).");
			$this -> disconnect($msg);
			return false;
			
		default:
			\AlphaSocket\Log::log("A different opcode was selected other than TEXT. - ".($fin & 15)." - must disconnect (not implemented!)",2);
			return false;
		}
		
		$next ++;
		$mpay = ord(substr($msg,$next,1));
		if(($mpay >> 7) != 1){
			$mask = true;
			\AlphaSocket\Log::log("Data is NOT masked - this is NOT OK.",0);
		} else {
			$mask = false;
		}
		$paylen = $mpay & 127;
		
		$next ++;
		if($paylen > 125) {
			if($paylen == 126){
				$len = 2;	//16-bits
				$paylen = unpack("N",substr($msg,$next,$len));
			} elseif($paylen == 127){
				$len = 8;	//64-bits
				$paylen = (unpack("N",substr($msg,$next,4)) << 32);
				$paylen += unpack("N",substr($msg,$next,4));
			}
			$next += $len;
		}
		\AlphaSocket\Log::log("Detected payload length = $paylen",2);
		
		$mask_d = array();	//putting the mask into an array to make decoding easier
		for($i = 0; $i <= 3; $i ++){
			$mask_d[$i] = ord(substr($msg,$next+$i,1));
		}
		
		$next+=4;
		
		$unwrapped = "";
		for($i = 0; $i < $paylen; $i ++){	//unmask the data
			$char = ord(substr($msg,$next+$i,1));
			$unwrapped .= chr( $mask_d[$i&3] ^ $char );
		}
		
		return $unwrapped;
	}
	
	public function wrap($msg){
		//when sending, not using a mask, text data only, one frame.
		$header = "\x81";
		$len = strlen($msg);
		if($len > 125){
			if($len > 65535){
				$header.="\x7F";
				$header.=pack("N",$len>>32);
				$header.=pack("N",$len & "\xFF\xFF\xFF\xFF");
			} else {
				$header.="\x7E";
				$header.=pack("n",$len);
			}
		} else {
			$header .= pack("C",$len);
		}
		\AlphaSocket\Log::log("Length of response text - $len",2);
		$hex_head = unpack("H*",$header.$msg);
		\AlphaSocket\Log::log("Data: ".$hex_head[1],3);
		
		return($header.$msg);
	}
}