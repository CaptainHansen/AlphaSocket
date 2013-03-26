<?
namespace AlphaSocket;

class User {
	public $stored_data;

	public $socket;
	public $ws_driver;
	
	public $address;
	public $port;
	
	public function __construct($socket){
		$this -> socket = $socket;
	}
	
	public function checkHandshake(){
		if(!is_null($this->ws_driver)){
			return $this->ws_driver->checkHandshake();
		}
		return false;
	}
	
	public function setVers($vers){
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
	
	public function doHandshake($headers){
		if(!is_null($this->ws_driver)){
			return $this->ws_driver->openHandshake($this->socket,$headers);
		} else {
			return false;
		}
	}
	
	public function send($msg){
		\AlphaSocket\Log::log("> ".$msg,1);
		$msg = $this->wrap($msg);
		socket_write($this->socket,$msg,strlen($msg));
	}
	
	public function unwrap($msg){
		return $this->ws_driver->unwrap($msg);
	}
	
	private function wrap($msg){
		return $this->ws_driver->wrap($msg);
	}
}