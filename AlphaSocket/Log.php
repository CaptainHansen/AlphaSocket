<?
namespace AlphaSocket;

class Log {
	private static $debug = 0;
	
	public static function setDebugLevel($level){
		self::$debug = $level;
	}
	
	public static function log($message,$level){	//setting level = to 0 has NO effect.
		if(self::$debug == 0) return;	//all messages disabled by default
		if(self::$debug >= $level){
			if(self::$debug >= 2){
				$trace = debug_backtrace();
				echo $trace[1]['class']."::".$trace[1]['function']." - ".$message."\n";
			} else {
				echo $message."\n";
			}
		}
	}
}