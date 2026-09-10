<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

require __DIR__ . '/SpecRenderer.php';

// configure environment
Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Prague');


function normalizeNewlines(string $s): string
{
	return str_replace("\r\n", "\n", $s);
}


/**
 * Loads a committed spec fixture.
 * @return list<array{markdown: string, html: string, example: int, section: string}>
 */
function loadSpec(string $name): array
{
	return json_decode(
		file_get_contents(__DIR__ . '/Markdown/fixtures/' . $name . '.json'),
		true,
		flags: JSON_THROW_ON_ERROR,
	);
}
