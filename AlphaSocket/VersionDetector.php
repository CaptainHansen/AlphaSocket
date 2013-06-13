<?
namespace AlphaSocket;

class VersionDetector {
	static function getInfo($req){
		$ret = array();
		if(!preg_match("/Sec-WebSocket-Version: (.*)\r\n/",$req,$match)){
//			$vers = '#76';
//			$regexes = array(
//				"/GET (.*) HTTP/",
//				"/Host: (.*)\r\n/",
//				"/Origin: (.*)\r\n/",
//				"/Sec-WebSocket-Key1: (.*)\r\n/",
//				"/Sec-WebSocket-Key2: (.*)\r\n/",
//				"/\r\n(.*?)\$/");
			return false;
		} else {
			$vers = $match[1];
			$regexes = array(
				"/GET (.*) HTTP/",
				"/Host: (.*)\r\n/",
				"/Origin: (.*)\r\n/",
				"/Sec-WebSocket-Key: (.*)\r\n/");
		}
		foreach($regexes as $pattern){
			if(preg_match($pattern,$req,$match)) array_push($ret,$match[1]);
		}
	
		if(count($regexes) == count($ret)){
			return array($ret,trim($vers));
		} else {
			return false; //version of WebSocket not recognized.
		}
	}
}