<?
namespace AlphaSocket\Versions;

abstract class VersionTemplate {
	protected $version;
	protected $handshake = false;
	protected $socket;
	
	public function __construct($socket,$version){
		$this -> socket = $socket;
		$this -> version = $version;
	}
	
	public function getVersion(){
		return $this -> version;
	}
	
	public function checkHandshake(){
		return $this -> handshake;
	}
	
	abstract public function openHandshake($socket,$headers);
	abstract public function unwrap($msg);
	abstract public function wrap($msg);
}
		