<?php
namespace PHPConcurrency\Concurrency;


class Future {
	public $base;
	protected $result;
	protected $runnerFunction;
	protected $resultFunction;

	function __construct($runnerFunction = null, $resultFunction = null) {
		$this->base = event_base_new();
		$this->runnerFunction = $runnerFunction;
		$this->resultFunction = $resultFunction;
	}

	private function waitForBuffer($buffer) {
		$buffer = event_buffer_new($socket, array($this, 'ev_read'), NULL, array($this, 'ev_error'), $index);
		event_buffer_base_set($buffer, $this->base);
		event_buffer_timeout_set($buffer, $timeout, $timeout);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, EV_READ | EV_PERSIST);
	}

	function onComplete($resultFunction) {
		$this->resultFunction = $resultFunction;
	}

	function parallel($futures) {
		foreach ($futures as $fut) {
			$fut->base = $this->base;
		}
		$this->runnerFunction = function($timeout) use ($futures) {
			foreach ($futures as $fut) {
				call_user_func($fut->runnerFunction, $timeout);
			}
		};
		$this->resultFunction = function() use ($futures) {
			$results = array();
			foreach ($futures as $fut) {
				$results[] = call_user_func($fut->resultFunction);
			}
			return $results;
		};
	}

	function getBase() {
		return $this->base;
	}

	function get($timeout) {
		call_user_func($this->runnerFunction, $timeout);
		return $this->value();
	}

	function getAsync($timeout) {
		call_user_func($this->runnerFunction, $timeout);
		event_base_loop($this->base, EVLOOP_NONBLOCK);
	}

	function value() {
		event_base_loop($this->base);
		return call_user_func($this->resultFunction);
	}

	static function getEventFlags ($events) {                                                    
		$eventFlags = array('EV_TIMEOUT', 'EV_SIGNAL', 'EV_READ', 'EV_WRITE', 'EV_PERSIST');
		$returnArray = array();
		foreach ($eventFlags as $flag) {                                                    
			if ($events & constant($flag)) {
				$returnArray[] = $flag;
			}
		}
		return $returnArray;
	}

}
