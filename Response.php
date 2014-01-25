<?php

class Response {
	private $headers = array();
	private $body = '';

	public function __construct($response) {
		$responseArray = explode(HTTP_EOL.HTTP_EOL, $response);
		$headers = explode(HTTP_EOL, $responseArray[0]);

		for ($i=1; $i < count($headers); $i++) { 
			$delimiterPosition = strpos($headers[$i], ':');
			$name = substr($headers[$i], 0, $delimiterPosition);
			if(strlen($headers[$i]) > $delimiterPosition) {
				$value = trim(substr($headers[$i], $delimiterPosition + 1));
			} else {
				$value = '';
			}
			
			$this->setHeader($name, $value);
		}

		if(isset($responseArray[1])) {
			if($this->getHeader('Transfer-Encoding') === 'chunked') {
				$this->body = self::decode_chunked($responseArray[1]); 
			} else {
				$this->body = $responseArray[1]; 
			}
		}
	}

	private static function decode_chunked($str) {
		for ($res = ''; !empty($str); $str = trim($str)) {
			$pos = strpos($str, HTTP_EOL);
			$len = hexdec(substr($str, 0, $pos));
			$res.= substr($str, $pos + 2, $len);
			$str = substr($str, $pos + 2 + $len);
		}

		return $res;
	}

	public function setHeader($name, $value) {
		$this->headers[$name] = $value;
	}

	public function setBody($body) {
		$this->body = $body;
	}

	public function getBody() {
		return $this->body;
	}

	public function getHeader($name) {
		return $this->headers[$name];
	}
}

