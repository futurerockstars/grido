<?php

/**
 * Test initialization and helpers.
 */
use Tester\Environment;
use Tester\Helpers;
use Tracy\Debugger;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

// configure environment
Environment::setup();
date_default_timezone_set('Europe/Prague');
Debugger::$maxDepth = 5;
Tracy\Debugger::$maxLength = 500;

// create temporary directory
define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());
@mkdir(dirname(TEMP_DIR)); // @ - directory may already exist
Helpers::purge(TEMP_DIR);

ini_set('session.save_path', TEMP_DIR);

function id($val)
{
	return $val;
}

function before(?Closure $function = null)
{
	static $val;
	if (!func_num_args()) {
		return $val ? $val() : null;
	}

	$val = $function;
}

function test(Closure $function)
{
	before();
	$function();
}

function mock()
{
	return call_user_func_array('Mockery::mock', func_get_args());
}

function run($path, $name = null)
{
	$name = $name
		? $name
		: ($_SERVER['argv'][1] ?? null);

	$code = file_get_contents($path);
	$tokens = token_get_all($code);
	$count = count($tokens);
	for ($i = 2; $i < $count; $i++) {
		if ($tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
			$class = '\Grido\Tests\\' . $tokens[$i][1];
			$test = new $class();
			if ($name) {
				$test->runTest($name);
			} else {
				method_exists($test, 'run') && $test->run();
			}
		}
	}
}
