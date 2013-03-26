AlphaSocket
===========

AlphaSocket is a PHP library allowing implementation of a WebSocket server without the need of any Apache extensions or the like.

I know this definitely isn't the right way to do WebSockets, however, I learn by doing and wanted to learn about the WebSocket protocol.  This started as a single functional PHP script.  Then I learned about namespaces and grew fond of Object Oriented (roughly a few weeks ago) which brought me to make AlphaSocket.

Right now, it supports both draft 76 and version 13 of the WebSocket protocol, but is missing a lot of the V.13 features like ping/pong, a proper disconnect implementation, support for the sending of binary data, etc.  In the V.13 protocol, the only opcodes currently supported are 1 and 8 (text data and disconnect, respectively).

Here is a simple implementation of a WebSocket echo server operating on port 8000:

	<?
	include("autoload.php");

	class WS_Echo extends \AlphaSocket\Server {
		//IP address of client =				$this -> address;
		//hostname of client (if enabled) =		$this -> hostname;
		//array of ALL connected users = 		$this -> users;
		
		protected function Process($user,$msg){
			/** to send to all connected clients:
			 * foreach($this->users as $user) $user->send($msg);
			 */
			$user -> send($msg");
		}
	}

	/** right now, there are three 4 debug levels:
	 * 0 - Log NOTHING
	 * 1 - Log Messages and some errors
	 * 2 - Log some Handshaking, wrap and unwrap info
	 * 3 - Log handhake information (including the actual headers), and the hex data received/sent
	 */
	 
	\AlphaSocket\Log::setDebugLevel(1);	//Log Messages and some errors

	/** creating the server:
	 * "0.0.0.0"	<- address to listen on (this means all interfaces and addresses)
	 * 8000			<- the port number to listen on
	 * true			<- resolve hostnames (remove this attribute or set it to false to disable hostname resolution
	 */
	$ws_echo = new WS_Echo("0.0.0.0",8000,true);
	$ws_echo -> Run();

The Process method is called after a successful handshake on any data received from a client.  I chose to create a server with custom Process methods to allow the programmer access to the users variable within the Server class.  I implemented a chat server using this (when data is submitted, send it out to all connected clients).

I really need to add phpdocs to this and my EasyJax project...