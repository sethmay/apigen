#!/usr/bin/env php
<?php

/**
 * ApiGen 2.2.0 - API documentation generator for PHP 5.3+
 *
 * Copyright (c) 2010 David Grudl (http://davidgrudl.com)
 * Copyright (c) 2011 Jaroslav Hanslík (https://github.com/kukulich)
 * Copyright (c) 2011 Ondřej Nešpor (https://github.com/Andrewsville)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace ApiGen;

use Nette\Diagnostics\Debugger;

// Set dirs
define('PEAR_PHP_DIR', '@php_dir@');
define('PEAR_DATA_DIR', '@data_dir@');

if (false === strpos(PEAR_PHP_DIR, '@php_dir')) {
	// PEAR package

	set_include_path(
		PEAR_PHP_DIR . PATH_SEPARATOR .
		get_include_path()
	);

	require PEAR_PHP_DIR . '/Nette/loader.php';
	require PEAR_PHP_DIR . '/Texy/texy.php';

	define('TEMPLATE_DIR', PEAR_DATA_DIR . '/ApiGen/templates');
} else {
	// Downloaded package

	set_include_path(
		__DIR__ . PATH_SEPARATOR .
		__DIR__ . '/libs/FSHL' . PATH_SEPARATOR .
		__DIR__ . '/libs/TokenReflection' . PATH_SEPARATOR .
		get_include_path()
	);

	require __DIR__ . '/libs/Nette/Nette/loader.php';
	require __DIR__ . '/libs/Texy/texy/texy.php';

	define('TEMPLATE_DIR', __DIR__ . '/templates');
}

// Autoload
spl_autoload_register(function($class) {
	require sprintf('%s.php', str_replace('\\', DIRECTORY_SEPARATOR, $class));
});

try {

	// Check dependencies
	foreach (array('iconv', 'mbstring', 'tokenizer') as $extension) {
		if (!extension_loaded($extension)) {
			printf("Required extension missing: %s\n", $extension);
			die(1);
		}
	}
	if (!class_exists('Nette\\Diagnostics\\Debugger')) {
		echo "Required dependency missing: Nette Framework\n";
		die(1);
	}
	if (!class_exists('Texy')) {
		echo "Required dependency missing: Texy library\n";
		die(1);
	}
	if (!class_exists('FSHL\\Highlighter')) {
		echo "Required dependency missing: FSHL library\n";
		die(1);
	}
	if (!class_exists('TokenReflection\\Broker')) {
		echo "Required dependency missing: TokenReflection library\n";
		die(1);
	}

	Debugger::$strictMode = true;
	Debugger::enable(Debugger::PRODUCTION, false);
	Debugger::timer();

	$config = new Config();
	$generator = new Generator($config);

	// Help
	if ($config->isHelpRequested()) {
		echo $generator->colorize($generator->getHeader());
		echo $generator->colorize($config->getHelp());
		die();
	}

	// Start
	$config->parse();

	if ($config->debug) {
		Debugger::enable(Debugger::DEVELOPMENT, false);
	}

	$generator->output($generator->getHeader());

	// Check for update
	if ($config->updateCheck) {
		ini_set('default_socket_timeout', 5);
		$latestVersion = @file_get_contents('http://apigen.org/version.txt');
		if (false !== $latestVersion && version_compare(trim($latestVersion), Generator::VERSION) > 0) {
			$generator->output(sprintf("New version @header@%s@c available\n\n", $latestVersion));
		}
	}

	// Scan
	if (count($config->source) > 1) {
		$generator->output(sprintf("Scanning\n @value@%s@c\n", implode("\n ", $config->source)));
	} else {
		$generator->output(sprintf("Scanning @value@%s@c\n", $config->source[0]));
	}
	if (count($config->exclude) > 1) {
		$generator->output(sprintf("Excluding\n @value@%s@c\n", implode("\n ", $config->exclude)));
	} elseif (!empty($config->exclude)) {
		$generator->output(sprintf("Excluding @value@%s@c\n", $config->exclude[0]));
	}
	$parsed = $generator->parse();
	$generator->output(vsprintf("Found @count@%d@c classes, @count@%d@c constants, @count@%d@c functions and other @count@%d@c used PHP internal classes\n", array_slice($parsed, 0, 4)));
	$generator->output(vsprintf("Documentation for @count@%d@c classes, @count@%d@c constants, @count@%d@c functions and other @count@%d@c used PHP internal classes will be generated\n", array_slice($parsed, 4, 4)));

	// Generating
	$generator->output(sprintf("Using template config file @value@%s@c\n", $config->templateConfig));

	if ($config->wipeout && is_dir($config->destination)) {
		$generator->output("Wiping out destination directory\n");
		if (!$generator->wipeOutDestination()) {
			throw new Exception('Cannot wipe out destination directory');
		}
	}

	$generator->output(sprintf("Generating to directory @value@%s@c\n", $config->destination));
	$skipping = array_merge($config->skipDocPath, $config->skipDocPrefix);
	if (count($skipping) > 1) {
		$generator->output(sprintf("Will not generate documentation for\n @value@%s@c\n", implode("\n ", $skipping)));
	} elseif (!empty($skipping)) {
		$generator->output(sprintf("Will not generate documentation for @value@%s@c\n", $skipping[0]));
	}
	$generator->generate();

	// End
	$generator->output(sprintf("Done. Total time: @count@%d@c seconds, used: @count@%d@c MB RAM\n", Debugger::timer(), round(memory_get_peak_usage(true) / 1024 / 1024)));

} catch (\Exception $e) {
	$invalidConfig = $e instanceof Exception && Exception::INVALID_CONFIG === $e->getCode();
	if ($invalidConfig) {
		echo $generator->colorize($generator->getHeader());
	}

	if (!empty($config) && $config->debug) {
		do {
			echo $generator->colorize(sprintf("\n@error@%s@c", $e->getMessage()));
			$trace = $e->getTraceAsString();
		} while ($e = $e->getPrevious());

		printf("\n\n%s\n\n", $trace);
	} else {
		echo $generator->colorize(sprintf("\n@error@%s@c\n\n", $e->getMessage()));
	}

	// Help only for invalid configuration
	if ($invalidConfig) {
		echo $generator->colorize($config->getHelp());
	}

	die(1);
}