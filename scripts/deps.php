<?php
/**
 * deps.php
 *
 * PHP 7.0 compatible, just in case someone manages to run it with something else
 * than the php shipped with the GH actions runner...
 *
 * (we have cURL and OpenSSL, so all good!)
 * @see https://github.com/actions/runner-images/blob/main/images/win/scripts/Installers/Install-PHP.ps1
 *
 * c:\tools\php\php.exe scripts/deps.php --version 8.2 --vs vs16 --arch x64 --deps "liblzma libzip zlib "
 *
 * @created      11.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

require_once __DIR__.'/common.php';

$args = getopt('', ['version:', 'vs:', 'arch:', 'deps:', 'ignore_vs:']);

if(empty($args)){
	throw new InvalidArgumentException('invalid arguments');
}

// brrr
foreach($args as $k => $v){
	${strtolower($k)} = strtolower($v);
}

// @todo: check argument values...

$deps      = trim($deps);
$ignore_vs = $ignore_vs !== 'false';

if(!empty($deps)){
	// try comma seaprated
	$deps = explode(',', $deps);

	// so is it space spearated or just a single argument?
	if(count($deps) === 1){
		$deps = explode(' ', $deps[0]);
	}
}

// no deps given? ok bye!
if(!is_array($deps) || count($deps) === 0 || $deps[0] === '@()'){
	exit(0);
}

// we're on Windows, this shouldn't happen so easily
if(!is_writable(__DIR__)){
	throw new RuntimeException('directory is not writable');
}

// check core dependencies first
$baseurl  = 'https://windows.php.net/downloads/php-sdk/deps';
$deplist  = fetch_deplist("$baseurl/series/packages-$version-$vs-$arch-staging.txt");
$download = [];

foreach($deps as $dep){
	foreach($deplist as $dep_available){
		if(strpos($dep_available, $dep) === 0){
			$download[$dep] = "$baseurl/$vs/$arch/$dep_available";
		}
	}
}

$diff = array_diff($deps, array_keys($download));

// didn't catch all? try PECL
if(count($diff) > 0){
	$baseurl = 'https://windows.php.net/downloads/pecl/deps';
	$deplist = fetch_deplist($baseurl.'/packages.txt');

	foreach($diff as $dep){
		$dep_versions = [];

		foreach($deplist as $dep_available){

			if(strpos(strtolower($dep_available), $dep) === 0 && strpos($dep_available, '-'.$arch.'.zip') > 0){

				if(!$ignore_vs && strpos($dep_available, '-'.$vs) === false){
					continue;
				}

				$dep_versions[] = $dep_available;
			}
		}

		// hoping for the best tbh
		sort($dep_versions, SORT_NATURAL);
		$count = count($dep_versions);

		if($count > 0){
			$download[$dep] = $baseurl.'/'.$dep_versions[$count - 1];
		}
	}
}

$diff = array_diff($deps, array_keys($download));

/*
// still not complete? try winlibs? https://github.com/winlibs
if(count($diff) > 0){
	// @todo ...
}

$diff = array_diff($deps, array_keys($download));
*/

// oop!
if(count($diff) > 0){
	throw new RuntimeException('could not fetch the following libraries: '.implode(', ', $diff));
}

$downloaded = [];
foreach($download as $dep => $url){
	// IDGAF
	$data = download_file($url);

	if(empty($data)){
		throw new RuntimeException('download error: '.$url);
	}

	$file = substr($url, strrpos($url, '/') + 1);
	file_put_contents(ACTION_DOWNLOADS.'\\'.$file, $data);

	echo "downloaded: $url\n";
	$downloaded[$dep] = $file;
}

$extracted = [];

foreach($downloaded as $file){

	if(!unzip_file(ACTION_DOWNLOADS.'\\'.$file, SDK_BUILD_DEPS)){
		continue;
	}

	$extracted[] = $file;
}

$diff = array_diff($downloaded, $extracted);

if(count($diff) > 0){
	throw new RuntimeException('could not extract the following libraries: '.implode(', ', $diff));
}

print_r(scandir(SDK_BUILD_DEPS));

// we made it!
exit(0);


/**
 * fetch one of the dependency lists from php.net
 *
 * @see https://windows.php.net/downloads/php-sdk/deps/series/
 * @see https://windows.php.net/downloads/pecl/deps/packages.txt
 */
function fetch_deplist(string $package_txt):array{
	$deplist =  download_file($package_txt);

	if(empty($deplist)){
		throw new RuntimeException('invalid package list http response');
	}

	return array_map('trim', explode("\n", trim($deplist)));
}
