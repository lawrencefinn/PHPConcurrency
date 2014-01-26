<?php
require_once("PHPConcurrency/Concurrency/Future.php");
require_once("PHPConcurrency/Concurrency/Future/Successful.php");
require_once("PHPConcurrency/Request/HTTP.php");
require_once("PHPConcurrency/ForComprehension.php");
echo microtime(true) . "\n";
$comp = \PHPConcurrency\ForComprehension::build(
		$line('$a', 'futureAddFour', array(1, 2, 0, 0)),
		\PHPConcurrency\ForComprehension::line('$b', 'futureAddFour', array(1, 2, '$a', 0)),
		\PHPConcurrency\ForComprehension::line('$d', 'futureAddFour', array(1, 2, 3, 0)),
		\PHPConcurrency\ForComprehension::line('$c', 'futureAddFour', array(1, '$d', '$b', '$a')),
		\PHPConcurrency\ForComprehension::line('$e', 'futureAddFour', array(5, '$d', '$b', '$a'))
)->yields('$b');
echo $comp->get(30) .  "\n";

echo \PHPConcurrency\ForComprehension::build(
	$line('$html', $get, array('www.google.com')),
	$line('$header', 'futureSubstring', array('$html'))
	)->yields('$header')->get(30) . "\n";

$futures = array();
for ($i = 0; $i < 100; $i++) {
	$futures[] = PHPConcurrency\Request\HTTP::get("www.yahoo.com");
}
$future = new \PHPConcurrency\Concurrency\Future($futures);
$future->parallel($futures);
$val = $future->get(40);
echo microtime(true) . "\n";
function futureAddFour() {
	return new PHPConcurrency\Concurrency\Future\Successful(function($one, $two, $three, $four) {
			return $one + $two + $three + $four;
	}, func_get_args());
}

function futureSubstring() {
	return new PHPConcurrency\Concurrency\Future\Successful(function($string) {
		return substr($string, 0, 20);
	}, func_get_args());
}
