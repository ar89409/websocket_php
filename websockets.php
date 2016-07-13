<?php

//require_once('./daemonize.php');
require_once('./users.php');

abstract class WebSocketServer {

  protected $userClass = 'WebSocketUser'; // redefine this if you want a custom user class.  The custom user class should inherit from WebSocketUser.
  protected $maxBufferSize;        
  protected $master;
  protected $interactive                        	= true;
  protected $sockets								= array();
  protected $users									= array();
  protected $heldMessages							= array();
  protected $headerOriginRequired					= false;
  protected $headerSecWebSocketProtocolRequired		= false;
  protected $headerSecWebSocketExtensionsRequired	= false;	
  

  function __construct($addr, $port, $bufferLength = 2048) {
    $this->maxBufferSize = $bufferLength;
    $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
    socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
    socket_bind($this->master, $addr, $port)                      or die("Failed: socket_bind()");
    socket_listen($this->master,20)                               or die("Failed: socket_listen()");
    $this->sockets['m'] = $this->master;
    $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: ".$this->master);
	
	}
		
    public function run() {
		while(true) {
			$read = $this->sockets;
			$write = $except = null;
			@socket_select($read, $write, $except, 1);
			foreach($read as $socket) {
				if ($socket == $this->master) {
					$client = socket_accept($socket);
					$this->connect($client);
				}
				else {
					$numBytes = @socket_recv($socket, $buffer, $this->maxBufferSize, 0);
					$user = $this->getUserBySocket($socket);
					if (!$user->handshake) {
						$tmp = str_replace("\r", '', $buffer);
						$this->doHandshake($user,$buffer);
					}
				}
			}
		}
	}
	
	protected function doHandshake($user, $buffer) {
		$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
		$headers = array();
		$lines = explode("\n", $buffer);
		echo "\nheaders: \n";
		print_r($buffer);
		foreach ($lines as $line) {
			if (strpos($line,":") !== false) {
				$header = explode(":", $line, 2);
				$headers[strtolower(trim($header[0]))] = trim($header[1]);
			}
			
			elseif (stripos($line, "get ") !== false) {
				preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
				$headers['get'] = trim($reqResource[1]);
			}
		}
		
		if (isset($headers['get'])) {
			$user->requestedResource = $headers['get'];
		}
		
		$user->headers = $headers;
		$user->handshake = $buffer;
		
		$webSocketKeyHash = sha1($headers['sec-websocket-key'].$magicGUID);
		
		$rawToken = "";
		for($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash, $i*2, 2)));
		}
		
		$handshakeToken = base64_encode($rawToken). "\r\n";
		
		$subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
		$extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";
		
		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
		socket_write($user->socket, $handshakeResponse, strlen($handshakeResponse));
		echo "handshakeResponse: \n";
		print_r($handshakeResponse);
	}
	
	protected function connect($socket) {
		$user = new $this->userClass(uniqid('u'), $socket);
		$this->users[$user->id] = $user;
		$this->sockets[$user->id] = $socket;
	}
	
	protected function getUserBySocket($socket) {
		foreach ($this->users as $user) {
			if ($user->socket == $socket) {
				return $user;
			}
		}
	}
	
	public function stdout($message) {
		if ($this->interactive) {
			echo "$message\n";
		}
	}
	
	protected function processProtocol($protocol) {
		return "";
	}

	protected function processExtensions($extensions) {
		return "";
	}
}

?>


