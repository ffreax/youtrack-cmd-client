<?php

class Config {
	private $cookie = null;

	public function getCookie() {
		return $this->cookie;
	}

	public function setCookie($cookie) {
		$this->cookie = $cookie;
	}
}
