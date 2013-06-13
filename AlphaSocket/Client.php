<?
namespace AlphaSocket;

define('AS_TYPE','CLIENT');

#error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

class Client {
	private $ws_conn;
	private $lsock;
	private $ws_driver;
	private $client;
		
	public function __construct($address,$port,$lsock_f = './ws_client.sock',$ws_vers='13') {
		echo "Start\n";
		$ws_conn=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)		or exit(1);
		socket_connect($ws_conn,$address,$port)						or exit(2);
		switch($ws_vers){
		case "13":
			$this -> ws_driver = new Versions\V_13($ws_conn);
			break;
		case "#76":
			$this -> ws_driver = new Versions\V_dr76($ws_conn);
			break;
		default:
			exit(10);
		}
		$this -> ws_conn = $ws_conn;
		$this -> ws_driver -> clientInitHandshake($address.":".$port)						or exit(3);
		
		$sockets = array($this -> ws_conn);	//wait for handshake response
		$changed = socket_select($sockets,$write=NULL,$except=NULL,5);
		if(count($changed) == 0){
			Log::log("Handshake 5 second timeout ended.");
			exit (3);
		}
		
		$buffer = "";
		$bytes = @socket_recv($this -> ws_conn,$buffer,65535,0);
		$this -> ws_driver -> clientVerifyHandshake($buffer)									or exit(3);
		
		//now that the websocket connection has been established, we need to make a socket connection here on the localhost
		$lsock = socket_create(AF_UNIX,SOCK_STREAM,0)				or exit(1);
		if(file_exists($lsock_f)) unlink($lsock_f);
		socket_bind($lsock,$lsock_f)						or exit(2);
		socket_listen($lsock);
		
		$this -> lsock = $lsock;
		
		$this -> Run();
	}
	
	public function SendMessage($message){
		$msg = $this -> ws_driver -> wrap($message);
		socket_send($this -> ws_conn, $msg, strlen($msg),0);
	}
	
	public function WaitReceive(){
		$sockets = array($this -> ws_conn);
		socket_select($sockets,$write=NULL,$except=NULL,NULL);
		return $this -> ReceiveMessage();
	}
	
	private function ReceiveMessage(){
		$buffer = "";
		$bytes = @socket_recv($this -> ws_conn,$buffer,2048,0);
		return $this -> ws_driver -> unwrap($buffer);
	}
	
	

	public function Run() {
		while(true){
			if($this -> client) {
				$changed = array($this -> lsock,$this -> ws_conn,$this -> client);
			} else {
				$changed = array($this -> lsock,$this -> ws_conn);
			}
			socket_select($changed,$write=NULL,$except=NULL,NULL);
			foreach($changed as $socket){
				if($socket== $this->lsock){
					$client=socket_accept($this -> lsock);
					if($client<0){
						Log::log("socket_accept() failed",0);
						continue;
					} else {
						Log::log("Client process connected!",1);
						if($this -> client) socket_close($this -> client);
						$this -> client = $client;
					}
				} elseif($socket == $this->ws_conn) {
					list($msg,$len,$disconnect) = $this -> ws_driver -> Receive();
					if($disconnect) {
						socket_close($this -> ws_conn);
						die("WebSocket Server Disconnected.\n");
					}
					Log::log("lsock < WS ".$msg,1);
					$bytes = socket_send($this -> client,$msg,strlen($msg),0);
				} else if($socket == $this -> client){
					$bytes = @socket_recv($socket,$buffer,2048,0);
					if($bytes == 0){
						Log::log("Client process disconnected.",1);
						socket_close($this -> client);
						$this -> client = null;
					} else {
						Log::log("lsock > WS ".$buffer,1);
						$relay = $this -> ws_driver -> Send($buffer);
					}
				}
			}

		}
	}

	
//	abstract protected function Process($user,$action);

/*	private function Disconnect($socket){
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
*/
}
