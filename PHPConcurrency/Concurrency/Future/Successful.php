<?php
namespace PHPConcurrency\Concurrency\Future;

class Successful extends \PHPConcurrency\Concurrency\Future {
	protected $function;
	protected $inputs;
	protected $retval;
	function __construct($function, $inputs) {
		$this->function = $function;
		$this->inputs = $inputs;
		$this->runnerFunction = array($this, 'run');
		$this->resultFunction = arraY($this, 'result');
	}

	function run($timeout) {
		$this->retval = call_user_func_array($this->function, $this->inputs);
	}

	function result() {
		return $this->retval;
	}


}
