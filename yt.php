<?php

require_once('Config.php');
require_once('Response.php');

$settings = include('settings.php');

define('DOMAIN', $settings['DOMAIN']);
define('LOGIN', $settings['LOGIN']);
define('PASSWORD', $settings['PASSWORD']);
define('HTTP_EOL', "\r\n");

define('CONFIG_FILE','config.bin');

date_default_timezone_set('UTC');

if(file_exists(CONFIG_FILE)) {
	$config = unserialize(file_get_contents(CONFIG_FILE));
} else {
	$config = new Config();
}

if(!$config->getCookie()) {
	$login = post('/rest/user/login','',array(
		'login' => LOGIN, 
		'password' => PASSWORD
	));
	print_r($login->getHeader('Set-Cookie'));
	$config->setCookie($login->getHeader('Set-Cookie'));
}

file_put_contents(CONFIG_FILE, serialize($config));

$reduction = array(
	'p' => 'project',
	's' => 'summary',
	'd' => 'description',
	'c' => 'command',
	'f' => 'filter',
	'i' => 'issue'
);

$data = array();
for ($i=1; $i < count($argv); $i++) { 
	$cmd = explode('=',$argv[$i]);
	$key = substr($cmd[0], 1);
	if(isset($reduction[$key])) {
		$key = $reduction[$key];
	}

	$data[$key] = $cmd[1];
}

$affectedIssues = isset($data['issue']) ? explode(',', $data['issue']) : array();

if(isset($data['filter'])) {
	$issuesResponse = get('/rest/issue',$config->getCookie(), array(
		'filter' => $data['filter']
	));
	$responseObject = new SimpleXMLElement($issuesResponse->getBody());
	print('-------------+------+--------+----------+--------------------------------------'.PHP_EOL);
	print(' id          | for  | state  | due date | summary '.PHP_EOL);
	print('-------------+------+--------+----------+--------------------------------------'.PHP_EOL);
	foreach ($responseObject->issue as $issue) {
		$fields = parseFields($issue->field);
		printf('%-12s | %-4.4s | %-6.6s | %-8.8s | %-s'.PHP_EOL, 
			$issue['id'], 
			$fields['Assignee'], 
			$fields['State'], 
			empty($fields['Due Date']) ? '' : date("d.m.y", $fields['Due Date'] / 1000), 
			$fields['summary']);

		$affectedIssues[] = $issue['id'];
	}
}

if(empty($data['issue']) && !empty($data['summary']) && !empty($data['project'])) {
	$issue = put('/rest/issue',$config->getCookie(), array(
		'project' => $data['project'],
		'summary' => $data['summary'],
		'description' => empty($data['description']) ? '' : $data['description']
	));
	
	$affectedIssues[] = getIssueName($issue->getHeader('Location'));
	
}

if(isset($data['command'])) {
	foreach ($affectedIssues as $currentIssue) {
		post('/rest/issue/' . $currentIssue . '/execute', $config->getCookie(), array(
			'command' => $data['command']
		));
	}
}

function parseFields($fields) {
	$result = array();
	foreach ($fields as $current) {
		$currentName = (string)$current['name'];
		$result[$currentName] = iconv('UTF-8', 'windows-1251', $current->value);
	}

	return $result;
}

function url($suffix) {
	return 'http://' . DOMAIN . $suffix;
}

function paramString(array $params) {
	$variables = '';
	foreach ($params as $key => $value) {
		$value = iconv('windows-1251', 'UTF-8', $value);
		$value = urlencode($value);
		$variables[] = $key.'='.$value;
	}
		
	return implode('&', $variables);
}

function fullUrl($suffix, array $params) {
	$url = url($suffix);

	if(count($params) > 0) {
		$url .= '?';
		$url .= paramString($params);
	}

	return $url;
}

function put($suffix, $cookie, array $params = array()) {
	$request = '';
	$request .= 'PUT '. fullUrl($suffix, $params) .' HTTP/1.1'.HTTP_EOL;
	$request .= 'Host: '. DOMAIN .HTTP_EOL;
	$request .= 'Cookie:'. $cookie .HTTP_EOL;
	$request .= 'Connection: close'.HTTP_EOL.HTTP_EOL;
	
	return query($request);	
}

function get($suffix, $cookie, array $params = array()) {
	$request = '';
	$request .= 'GET '. fullUrl($suffix, $params) .' HTTP/1.1'.HTTP_EOL;
	$request .= 'Host: '. DOMAIN .HTTP_EOL;
	$request .= 'Cookie:'. $cookie .HTTP_EOL;
	$request .= 'Connection: close'.HTTP_EOL.HTTP_EOL;
	
	return query($request);	
}

function query($request) {

	$fh = fopen('query.log', 'a');

	fwrite($fh, '>>>>>>'.HTTP_EOL);
	fwrite($fh, $request);
	fwrite($fh, HTTP_EOL);

	$fp = fsockopen(DOMAIN, 80);
	fputs($fp, $request);

	$response = '';
	while(!feof($fp)) {
		$response .= fgets($fp, 128);
	}

	fclose($fp);

	fwrite($fh, '<<<<<'.HTTP_EOL);
	fwrite($fh, $response);
	fwrite($fh, HTTP_EOL);

	fclose($fh);

	return new Response($response);
}

function post($suffix, $cookie, array $params = array()) {
	$requestBody = paramString($params);

	$request = '';
	$request .= 'POST '.$suffix.' HTTP/1.1'.HTTP_EOL;
	$request .= 'Host: '. DOMAIN .HTTP_EOL;
	$request .= 'Content-length: '. strlen($requestBody) .HTTP_EOL;
	$request .= 'Connection: close'.HTTP_EOL;
	$request .= 'Content-Type: application/x-www-form-urlencoded'.HTTP_EOL;
	$request .= 'Cookie:' . $cookie . HTTP_EOL;

	$request .= HTTP_EOL. $requestBody;

	return query($request);	
}

function strlastpos($haystack, $needle) { 
	return strlen($haystack) - strlen($needle) - strpos(strrev($haystack), strrev($needle)); 
}

function getIssueName($url) {
	return substr($url, strlastpos($url, '/') + 1);
}

