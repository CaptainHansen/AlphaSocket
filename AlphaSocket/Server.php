<?
namespace AlphaSocket;

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

abstract class Server {
	private $master;
	private $sockets = array();
	protected $users = array();
	
	public function __construct($address,$port,$resolve_hostname = false) {
		$this -> resolve_hostname = $resolve_hostname;
		$master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
		socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
		socket_bind($master, $address, $port)                    or die("socket_bind() failed");
		socket_listen($master,20)                                or die("socket_listen() failed");
		echo "Server Started : ".date('Y-m-d H:i:s')."\n";
		echo "Master socket  : ".$master."\n";
		echo "Listening on   : ".$address." port ".$port."\n\n";
		$this -> master = $master;
		
		array_push($this->sockets,$master);
	}
	
	public function Run() {
		while(true){
			$changed = $this -> sockets;
			socket_select($changed,$write=NULL,$except=NULL,NULL);
			foreach($changed as $socket){
				if($socket==$this->master){
					$client=socket_accept($this -> master);
					if($client<0){
						Log::log("socket_accept() failed",0);
						continue;
					} else {
						$this -> Connect($client);
					}
				} else {
					$bytes = @socket_recv($socket,$buffer,2048,0);
					if($bytes==0){
						Log::log("We got nothing from client, they just disconnected.",1);
						$this -> Disconnect($socket);
					} else {
						$user = $this -> getuserbysocket($socket);
						if(!$user->checkHandshake()){
							list($headers,$vers) = VersionDetector::getInfo($buffer);
							
							if($user -> setVers($vers)){
								if(!($user -> doHandshake($headers))) $this->Disconnect($user->socket);
							} else {
								$this -> cancel_handshake($user->socket,$vers);
							}
							
						} else {
							$action = $user->unwrap($buffer);
							Log::log("< ".$action,1);
							$this -> Process($user,$action);
						}
					}
				}
			}
		}
	}
	
	private function cancel_handshake($socket,$vers){
		$response = "HTTP/1.1 501 Not Implemented\r\n";
		socket_write($socket,$response.chr(0),strlen($response.chr(0)));
		Log::log($response,3);
		Log::log("Handshake Aborted - WebSocket Version in use by client is not implemented. - ($vers)",2);
		$this -> Disconnect($socket);
	}
	
	private function Connect($socket){
		$user = new User($socket);
		socket_getpeername($socket,&$user->address,&$user->port);

		if($this->resolve_hostname){
			$host = exec("host {$user->address}");
			$hostar = explode(" ",$host);
			$user->hostname = $hostar[(count($hostar)-1)];
		}		

		array_push($this->users,$user);
		array_push($this->sockets,$socket);
		Log::log($socket." CONNECTED!",1);
	}
	
	abstract protected function Process($user,$action);

	private function Disconnect($socket){
		$found=null;
		$n=count($this->users);
		for($i=0;$i<$n;$i++){
			if($this->users[$i]->socket==$socket){
				$found=$i;
				break;
			}
		}
		if(!is_null($found)){
			array_splice($this->users,$found,1);
		}
		$index = array_search($socket,$this->sockets);
		socket_close($socket);
		Log::log($socket." DISCONNECTED!",1);
		if($index>=0){
			array_splice($this->sockets,$index,1);
		}
	}
	
	private function getuserbysocket($socket){
		$found=null;
		foreach($this->users as $user){
			if($user->socket==$socket){
				$found=$user;
				break;
			}
		}
		return $found;
	}
}