I split my programming time between scala/java and php (don't ask).  
I've gotten used to some really awesome conventions in Scala such as
futures and for comprehensions.  They are super useful when connecting
to external APIs.  PHP doesn't really support concurrency
but has a libevent addon.  Using nonblocking sockets and libevent, 
PHPConcurrency gives you a way of using "futures" and for comprehensions.

Grab the libevent addon here - http://pecl.php.net/package/libevent

SAMPLE:
```php
$future = PHPConcurrency\Request\HTTP::get("www.yahoo.com");
$html = $future->get(5);
```

This get function is actually blocking, but we can do multiple
requests at the same time.

SAMPLE:
```php
$futures[] = PHPConcurrency\Request\HTTP::get("www.yahoo.com");
$futures[] = PHPConcurrency\Request\HTTP::get("www.google.com");
$future = new \PHPConcurrency\Concurrency\Future($futures);
$future->parallel($futures);
$htmls = $future->get(5);
```

$htmls here is an array of values.  If you actually want to do nonblocking
single calls, use the getAsync and value functions

SAMPLE: 
```php
$future = PHPConcurrency\Request\HTTP::get("www.yahoo.com"); 
$future->getAsync(5); 
// dont block here
$banana = 1 + 2 + 3; 
$html = $future->value(); 
//block here
```

For comprehensions make it easy to create dependencies without 
a lot of ugly code.  Since this is not built into the core, some
things are a little hacky.  You pass in virtual variable names to
assign the results of a future.  Then you can yield a specific variable.
Kind of functional ...

SAMPLE: 
```php
function futureSubstring() { 
	return new PHPConcurrency\Concurrency\Future\Successful(function($string) { 
		return substr($string, 0, 20); 
	}, func_get_args()); 
} 
echo \PHPConcurrency\ForComprehension::build( 
	$line('$html', $get, array('www.google.com')), 
	$line('$header', 'futureSubstring', array('$html')) 
	)->yields('$header')->get(30); 
```
 
The for comprehension sees the dependency between the substring call
and the web request future and does the calls in the correct
order.  $line, $get, and $post are simple aliases to functions that
can be called directly

