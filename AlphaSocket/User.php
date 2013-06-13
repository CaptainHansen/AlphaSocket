<?
namespace AlphaSocket;

class User {
	private $stored_data;

	public $socket;
	public $ws_driver;
	
	public $address;
	public $port;
	

	///STORED DATA FUNCTIONS////
	public function getData($fdp){
		if(isset($this -> stored_data[$fdp])){
			return $this -> stored_data[$fdp];
		} else {
			return null;
		}
	}
	
	public function setData($fdp,$data){
		return $this -> stored_data[$fdp] = $data;
	}
	//////////

	
	public function __construct($socket){
		$this -> socket = $socket;
	}
	
	public function checkHandshake(){
		if(!is_null($this->ws_driver)){
			return $this->ws_driver->checkHandshake();
		}
		return false;
	}
	
	private function setVers($vers){
		switch($vers){
		case '13':
			$this->ws_driver = new Versions\V_13($this->socket);
			break;
		case '#76':
			$this->ws_driver = new Versions\V_dr76($this -> socket);
			break;
		default:
			return false;
		}
		return true;
	}
	
	public function doHandshake(){
		$buffer = "";
		$bytes = @socket_recv($this -> socket,$buffer,2048,0);
		
		list($headers,$vers) = VersionDetector::getInfo($buffer);
		if($this -> setVers($vers)){
			if(!is_null($this->ws_driver)){
				return $this->ws_driver->serverRespondHandshake($headers);
			} else {
				return false;
			}
		} else {
			$this -> cancelHandshake($vers);
			return false;
		}
	}
	
	private function cancelHandshake($vers){
		$response = "HTTP/1.1 501 Not Implemented\r\n";
		socket_write($this -> socket,$response.chr(0),strlen($response.chr(0)));
		Log::log($response,3);
		Log::log("Handshake Aborted - WebSocket Version in use by client is not implemented. - ($vers)",2);
	}
	
	public function Send($msg){
		\AlphaSocket\Log::log($this->address.":".$this->port." > ".$msg,1);
		$msg = $this->ws_driver->Send($msg);
	}
	
	public function Receive(){
		$rcvd = $this -> ws_driver -> Receive();
		\AlphaSocket\Log::log($this->address.":".$this->port." < ".$rcvd[0],1);
		return $rcvd;
	}
	
	public function Disconnect($msg){
		return $this -> ws_driver -> Disconnect($msg);
	}
}