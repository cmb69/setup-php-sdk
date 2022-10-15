<?php
/**
 * vars.php
 *
 * @created      11.10.2022
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2022 smiley
 * @license      MIT
 */

require_once __DIR__.'/common.php';

$args = getopt('', ['version:', 'ts:', 'arch:', 'deps:']);

if(empty($args)){
	throw new InvalidArgumentException('invalid arguments');
}

// brrr
foreach($args as $k => $v){
	${strtolower($k)} = strtolower($v);
}

/*
 * determine VS/VC version
 */
$vs_versions = [
	'7.0' => 'vc14',
    '7.1' => 'vc14',
    '7.2' => 'vc15',
    '7.3' => 'vc15',
    '7.4' => 'vc15',
    '8.0' => 'vs16',
    '8.1' => 'vs16',
    '8.2' => 'vs16',
];

if(!isset($vs_versions[$version])){
	throw new RuntimeException('unsupported PHP version');
}

$vs = $vs_versions[$version];

/*
 * determine toolset version
 */

$toolsets = [
	'vc14' => '14.0',
];

// vswhere seems extremely slow, so we're gonna hardcode the last known path and look for it only whn needed
$vswhere = 'C:\\Program Files\\Microsoft Visual Studio\\2022\\Enterprise\\VC\\Tools\\MSVC';

if(!is_dir($vswhere)){
	$vswhere = exec('vswhere -latest -find "VC\\Tools\\MSVC"');

	// "vswhere" is not recognized
	if(strpos($vswhere, 'vswhere') !== false){
		throw new InvalidArgumentException('vswhere.exe error: '.$vswhere);
	}
}

if(!is_dir($vswhere) || !is_readable($vswhere)){
	throw new RuntimeException('Visual Studio dir error');
}

$vsdir = scandir($vswhere);

#print_r([$vswhere, $vsdir]);

foreach($vsdir as $dir){

	if(in_array($dir, ['.', '..'])){
		continue;
	}

	$tsv = array_map('intval', explode('.', $dir));

	// i assume we're referring to this list:
	// @see https://en.wikipedia.org/wiki/Microsoft_Visual_C%2B%2B#Internal_version_numbering
	if($tsv[0] === 14){
		if($tsv[1] < 10){
			$toolsets['vc14'] = $dir;
		}
		elseif($tsv[1] < 20){
			$toolsets['vc15'] = $dir;
		}
		elseif($tsv[1] < 30){
			$toolsets['vs16'] = $dir;
		}
		else{
			$toolsets['vs17'] = $dir;
		}
	}
}

if(!isset($toolsets[$vs])){
	throw new RuntimeException('no matching toolset found');
}

#print_r($toolsets);


/*
 * determine PHP release version
 */

// fetch the latest releases first
$baseurl  = 'https://windows.php.net/downloads/releases';
$releases = fetch_releases($baseurl);

// not found? let's try the QA builds
if(!isset($releases[$version])){
	$baseurl  = 'https://windows.php.net/downloads/qa';
	$releases = fetch_releases($baseurl);

	// still nothing? fall back to EOL
	if(!isset($releases[$version])){
		$baseurl  = 'https://windows.php.net/downloads/releases/archives';
		$releases = [
			'7.0' => '7.0.33',
			'7.1' => '7.1.33',
			'7.2' => '7.2.34',
			'7.3' => '7.3.33',
		];
	}
}

if(!isset($releases[$version])){
	throw new RuntimeException('could not determine PHP release version');
}

#print_r($releases);
$phpversion = $releases[$version];


/*
 * parse the dependency input
 */

// workaround to handle the powershell default input (for now)
if($deps === '@()'){
	$deps = '';
}

$dl_deps = 'false';

if(!empty($deps)){
	// try comma seaprated
	$deps = explode(',', trim($deps));

	// so is it space spearated or just a single argument?
	if(count($deps) === 1){
		$deps = explode(' ', $deps[0]);
	}

	if(is_array($deps) && !empty($deps)){
		$dl_deps = 'true';

		file_put_contents(ACTION_DOWNLOADS.'\\deps.json', json_encode($deps));
	}
}

/*
 * misc
 */

$tspart = ($ts === 'ts' ? '' : 'nts-').'Win32';
$ref    = 'dev';

if(isset($_SERVER['GITHUB_REF_TYPE'])){
	$ref = $_SERVER['GITHUB_REF_TYPE'] === 'tag' ? $_SERVER['GITHUB_REF_NAME'] : substr($_SERVER['GITHUB_SHA'], 0, 7);
}

$buildpath = $ts === 'nts' ? 'Release' : 'Release_TS';

if($arch === 'x64'){
	$buildpath = "x64\\$buildpath";
}


$out_vars = [
	// action outputs
	'prefix'     => WORKSPACE_ROOT.'\\php-bin',
	'toolset'    => $toolsets[$vs],
	'vs'         => $vs,
	'buildpath'  => $buildpath,
	'file_tag'   => "$ref-$version-$ts-$vs-$arch",
	// internal vars
	'phpversion' => $phpversion,
	'tspart'     => $tspart,
	'baseurl'    => $baseurl,
	'dl_deps'    => $dl_deps,
	// paths to add to GITHUB_PATH
	'phpbin'     => WORKSPACE_ROOT.'\\php-bin',
	'sdkbin'     => WORKSPACE_ROOT.'\\php-sdk\\bin',
	'sdkusrbin'  => WORKSPACE_ROOT.'\\php-sdk\\msys2\\usr\\bin',
	'devbin'     => WORKSPACE_ROOT.'\\php-dev',
];

print_r($out_vars);

// @todo https://github.blog/changelog/2022-10-11-github-actions-deprecating-save-state-and-set-output-commands/
foreach($out_vars as $name => $value){
	echo "::set-output name=$name::$value\n";
}

exit(0);
