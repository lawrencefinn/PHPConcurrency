<?php
namespace PHPConcurrency;

class ForComprehension{ 
	protected $lines;
	protected $order;
	protected $yieldsVariable;
	/**
		inputs are of format
		array(
			array( &variable , callable function, inputs for functions),
			array( &variable , callable function, inputs for functions)
		)
		returns by default the last value
	*/
	static function line($variableName, $function, $inputs) {
		$return = new \stdClass();
		$return->variableName = $variableName;
		$return->function = $function;
		$return->inputs = $inputs;
		return $return;
	}

	private function __construct($lines, $order) {
		$this->lines = $lines;
		$this->order = $order;
	}

	function yields($variableName) {
		$this->yieldsVariable = $variableName;
		return $this;
	}

	function get($timeout) {
		$returnVal = null;
		foreach ($this->order as $calls) {
			$futures = array();
			foreach ($calls as $call) {
				$line = $this->lines[$call];
				$variableName = $line->variableName;
				foreach ($line->inputs as &$input) {
					if (is_string($input) and $input[0] == '$') {
						$input = $$input;
					}
				}
				$futures[] = call_user_func_array($line->function,
						$line->inputs);
			}
			$future = new \PHPConcurrency\Concurrency\Future($futures);
			$future->parallel($futures);
			$futureval = $future->get($timeout);
			foreach ($calls as $call) {
				$line = $this->lines[$call];
				$variableName = $line->variableName;
				$$variableName = array_shift($futureval);
				$returnVal = $$variableName;
			}
		}
		if (isset($this->yieldsVariable)) {
			$var = $this->yieldsVariable;
			return $$var;
		}
		return $returnVal;
	}

	static function build() {
		$lines = func_get_args();
		$variablesDefined = array();
		$variablesUsed = array();
		$lineVariables = array();
		foreach ($lines as $index => $line) {
			$variableName = $line->variableName;
			if (!isset($variablesDefined[$variableName])) {
				$variablesDefined[$variableName] = $index;
			}
			foreach ($line->inputs as $input) {
				if (is_string($input) and $input[0] == '$') {
					$lineVariables[$index][] = $input;
					if (!isset($variablesUsed[$input])) {
						$variablesUsed[$input] = array($index => 1);
					} else {
						$variablesUsed[$input][$index] = 1;
					}
				}
			}
		}
		$order = self::orderCalls($variablesDefined, $variablesUsed,
				$lineVariables);
		return new \PHPConcurrency\ForComprehension($lines, $order);
	}

	static function comprehend() {
		$lines = func_get_args();
		$variablesDefined = array();
		$variablesUsed = array();
		$lineVariables = array();
		foreach ($lines as $index => $line) {
			$variableName = $line->variableName;
			if (!isset($variablesDefined[$variableName])) {
				$variablesDefined[$variableName] = $index;
			}
			foreach ($line->inputs as $input) {
				if (is_string($input) and $input[0] == '$') {
					$lineVariables[$index][] = $input;
					if (!isset($variablesUsed[$input])) {
						$variablesUsed[$input] = array($index => 1);
					} else {
						$variablesUsed[$input][$index] = 1;
					}
				}
			}
		}
		$order = self::orderCalls($variablesDefined, $variablesUsed,
				$lineVariables);
		$returnVal = null;
		foreach ($order as $calls) {
			$futures = array();
			foreach ($calls as $call) {
				$line = $lines[$call];
				$variableName = $line->variableName;
				foreach ($line->inputs as &$input) {
					if (is_string($input) and $input[0] == '$') {
						$input = $$input;
					}
				}
				$futures[] = call_user_func_array($line->function,
						$line->inputs);
			}
			$future = new \PHPConcurrency\Concurrency\Future($futures);
			$future->parallel($futures);
			$futureval = $future->get(40);
			foreach ($calls as $call) {
				$line = $lines[$call];
				$variableName = $line->variableName;
				$$variableName = array_shift($futureval);
				$returnVal = $$variableName;
			}
		}
		return $returnVal;
	}

	static function orderCalls($variablesDefined, $variablesUsed, $lineVariables) {
		$linesLeft = array();
		$variablesKnown = array();
		$calls = array();
		for ($i = 0; $i < count($variablesDefined); $i++) {
			if (!isset($lineVariables[$i])) {
				$calls[0][] = $i;
				$variablesKnown[] = array_search($i, $variablesDefined);
			} else {
				$linesLeft[$i] = 1;
			}
		}
		for ($callIndex = 1; $callIndex == count($calls); $callIndex++) {
			$newVariables = array();
			foreach ($linesLeft as $index => $line){
				foreach ($lineVariables[$index] as $variable){
					if (array_search($variable, $variablesKnown) === false) {
						break 2;
					}
				}
				$newVariables[] = array_search($index, $variablesDefined);
				unset($linesLeft[$index]);
				$calls[$callIndex][] = $index;
			}
			$variablesKnown = array_merge($variablesKnown, $newVariables);
		}
		if (!empty($linesLeft)) {
			throw new Exception("Some dependency issue");
		}
		return $calls;
	}
}
$line = function() {
	return forward_static_call_array(array('\PHPConcurrency\ForComprehension', 'line'), func_get_args());
};
