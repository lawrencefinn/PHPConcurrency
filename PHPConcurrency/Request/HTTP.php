<?php
namespace PHPConcurrency\Request;
use PHPConcurrency\Concurrency\Future;

class HTTP {
	protected $socket;
	protected $request;
	protected $base;
	protected $host;
	protected $port;
	protected $buffer;
	protected $headers;
	protected $chunkIndexes;
	protected $chunks;

	private function __construct(){
	}

	function post($host, $port = 80, $url = "",  $payload, $headers = null, $connectionTimeout = 5, $timeout = 30) {
		$http = new HTTP();
		$http->host = $host;
		$http->port = $port;
		$future = new \PHPConcurrency\Concurrency\Future(array($http, 'apply'), array($http, 'getResult'));
		$http->base = &$future->base;
		$headersToSend = array(
				"User-Agent" => "phpev_http",
				"Host" => $host,
				"Accept" => "*/*",
				"Content-Length" => strlen($payload),
				"Content-Type" => "application/x-www-form-urlencoded",
				);
		if (isset($headers) && !empty($headers)) {
			$headersToSend = array_merge($headersToSend, $headers);
		}
		$request = "POST /$url HTTP/1.1";
		foreach ($headersToSend as $headerName => $headerValue) {
			$request .= "\r\n$headerName: $headerValue";
		}
		$request .="\r\n\r\n$payload";
		$request .= "\r\n\r\n";
		$http->request = $request;
		return $future;
	}                                                                                                                      

	static function get($host, $port = 80, $url = "",  $headers = null, $connectionTimeout = 5, $timeout = 30) {
		$http = new HTTP();
		$http->host = $host;
		$http->port = $port;
		$future = new \PHPConcurrency\Concurrency\Future(array($http, 'apply'), array($http, 'getResult'));
		$http->base = &$future->base;
		$headersToSend = array(
				"User-Agent" => "phpev_http",
				"Host" => $host,
				"Accept" => "*/*"
				);
		if (isset($headers) && !empty($headers)) {
			$headersToSend = array_merge($headersToSend, $headers);
		}
		$request = "GET /$url HTTP/1.1";
		foreach ($headersToSend as $headerName => $headerValue) {
			$request .= "\r\n$headerName: $headerValue";
		}
		$request .= "\r\n\r\n";
		$http->request = $request;
		return $future;
	}

	function apply($timeout) {
		$event = event_new();
		$this->socket = stream_socket_client("tcp://" . $this->host . ":" . $this->port, $errno, $errstr, $timeout, STREAM_CLIENT_ASYNC_CONNECT);
		stream_set_blocking($this->socket, 0);
		event_set($event, $this->socket, EV_WRITE | EV_PERSIST, array($this, 'connected'), array($event, $timeout, microtime(true)));
		event_base_set($event, $this->base);
		event_add($event, $timeout * 1000000);
	}

	function connected($fd, $events, $arg) {
		$flags = \PHPConcurrency\Concurrency\Future::getEventFlags($events);
		$timeout = $arg[1] - (microtime(true) - $arg[2]);
		if (!empty($flags) && $flags[0] == 'EV_TIMEOUT') {
			event_del($arg[0]); 
			event_free($arg[0]);
			throw new Exception("Timeout");
		} else {
			$this->buffer = event_buffer_new($fd, array($this, 'read'), NULL, array($this, 'error'), null);
			event_buffer_base_set($this->buffer, $this->base);
			event_buffer_timeout_set($this->buffer, $timeout, $timeout);
			event_buffer_watermark_set($this->buffer, EV_READ, 0, 0xffffff);
			event_buffer_priority_set($this->buffer, 10);
			event_buffer_enable($this->buffer, EV_READ | EV_PERSIST);
			fwrite($fd, $this->request);
			event_del($arg[0]); 
			event_free($arg[0]);
		}
	}

	function error($buffer, $error) {
		var_dump($error);
	}


	function read($buffer) {
		while ($read = event_buffer_read($buffer, 4096)) {
			if (!isset($this->headers)) {
				$this->chunkIndexes = 0;
				$this->chunks = array();
				list($response, $read) = explode("\r\n", $read, 2);
				list($header, $read) = explode("\r\n\r\n", $read, 2);
				$headers = explode("\r\n", $header);
				$this->headers = array();
				foreach ($headers as $headerLine) {
					list($headerTitle, $headerValue) = explode(": ", $headerLine);
					$this->headers[$headerTitle] = $headerValue;
				}
				$this->chunks[$this->chunkIndexes] = $read;
			} else {
				$this->chunks[$this->chunkIndexes] .= $read;
			}
			if (isset($this->headers['Transfer-Encoding']) && $this->headers['Transfer-Encoding'] == 'chunked') {
				while ($this->parseChunk());
				if (isset($this->chunkSizes) && $this->chunkSizes == 0) {
					$this->complete();
					break;
				}
			} else {
				if (strlen($this->chunks[$this->chunkIndexes]) == $this->headers['Content-Length']) {
					$this->complete();
					break;
				}
			}
		}                                                                                                                
	}

	function complete() {                                        
		event_buffer_disable($this->buffer, EV_READ | EV_WRITE);
		event_buffer_free($this->buffer);
		$this->chunks = implode("", $this->chunks);
		fclose($this->socket);
	}

	function getResult() {
		return $this->chunks;
	}

	function parseChunk() {
		if (!isset($this->chunkSizes) && strpos($this->chunks[$this->chunkIndexes], "\r\n") !==false ) {
			list($chunkSize, $restChunk) = explode("\r\n", $this->chunks[$this->chunkIndexes], 2);
			$this->chunks[$this->chunkIndexes] = $restChunk;
			if ($chunkSize == "") {
				return true;
			}
			$this->chunkSizes = hexdec($chunkSize);
			if ($this->chunkSizes == 0) {
				return false;
			}
			return true;
		} else if (isset($this->chunkSizes)) {
			if (strlen($this->chunks[$this->chunkIndexes])  >= $this->chunkSizes) {
				$this->chunks[$this->chunkIndexes + 1] = substr($this->chunks[$this->chunkIndexes], $this->chunkSizes + 2);
				$this->chunks[$this->chunkIndexes] = substr($this->chunks[$this->chunkIndexes], 0, $this->chunkSizes);
				$this->chunkIndexes++;
				unset($this->chunkSizes);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
$get = function() {
	return forward_static_call_array(array('\PHPConcurrency\Request\HTTP', 'get'), func_get_args());
};

$post = function() {
	return forward_static_call_array(array('\PHPConcurrency\Request\HTTP', 'post'), func_get_args());
};

