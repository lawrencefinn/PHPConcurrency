<?php
require_once("PHPConcurrency/Concurrency/Future.php");
require_once("PHPConcurrency/Concurrency/Future/Successful.php");
require_once("PHPConcurrency/Request/HTTP.php");
require_once("PHPConcurrency/ForComprehension.php");
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
$futures = array();
for ($i = 0; $i < 100; $i++) {
	$futures[] = PHPConcurrency\Request\HTTP::get("www.woot.com");
}
$future = new \PHPConcurrency\Concurrency\Future($futures);
$future->parallel($futures);
$val = $future->get(40);
if (count($val) != 100) {
	throw new Exception("Should have 100 results for futures");
}
foreach ($val as $singleVal) {
	if (strpos($singleVal, '<html') === false) {
		throw new Exception("Did not retrieve html correctly $singleVal");
	}
	if (strpos($singleVal, '</html') === false) {
		throw new Exception("Did not retrieve html correctly $singleVal");
	}
}
$comp = \PHPConcurrency\ForComprehension::build(
		$line('$a', 'futureAddFour', array(1, 2, 0, 0)),
		\PHPConcurrency\ForComprehension::line('$b', 'futureAddFour', array(1, 2, '$a', 0)),
		\PHPConcurrency\ForComprehension::line('$d', 'futureAddFour', array(1, 2, 3, 0)),
		\PHPConcurrency\ForComprehension::line('$c', 'futureAddFour', array(1, '$d', '$b', '$a')),
		\PHPConcurrency\ForComprehension::line('$e', 'futureAddFour', array(5, '$d', '$b', '$a'))
)->yields('$e');
$compVal = $comp->get(30);
if ($compVal != 20) {
	throw new Exception("Fail for comprehension " . $compVal);
}

$compVal =  \PHPConcurrency\ForComprehension::build(
	$line('$html', $get, array('www.google.com')),
	$line('$header', 'futureSubstring', array('$html'))
	)->yields('$header')->get(30);
if ($compVal != "<!doctype html><html") {
	throw new Exception("Fail for comprehension " . $compVal);
}
echo "PASSED BOYEEE\n";
