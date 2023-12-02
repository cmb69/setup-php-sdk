param (
    [Parameter(Mandatory)] [String] $version,
    [Parameter(Mandatory)] [String] $arch,
    [Parameter(Mandatory)] [String] $ts,
    [Parameter(Mandatory)] [AllowEmptyCollection()] [Array] $deps
)

$ErrorActionPreference = "Stop"

$versions = @{
    "7.0" = "vc14"
    "7.1" = "vc14"
    "7.2" = "vc15"
    "7.3" = "vc15"
    "7.4" = "vc15"
    "8.0" = "vs16"
    "8.1" = "vs16"
    "8.2" = "vs16"
    "8.3" = "vs16"
}
$vs = $versions.$version
if (-not $vs) {
    throw "unsupported version"
}

$toolsets = @{
    "vc14" = "14.0"
}
$dir = vswhere -latest -find "VC\Tools\MSVC"
foreach ($toolset in (Get-ChildItem $dir)) {
    $tsv = "$toolset".split(".")
    if ((14 -eq $tsv[0]) -and (9 -ge $tsv[1])) {
        $toolsets."vc14" = $toolset
    } elseif ((14 -eq $tsv[0]) -and (19 -ge $tsv[1])) {
        $toolsets."vc15" = $toolset
    } elseif ((14 -eq $tsv[0]) -and (29 -ge $tsv[1])) {
        $toolsets."vs16" = $toolset
    } elseif (14 -eq $tsv[0]) {
        $toolsets."vs17" = $toolset
    }
}
$toolset = $toolsets.$vs
if (-not $toolset) {
    throw "toolset not available"
}

Write-Output "Install PHP SDK ..."

$temp = New-TemporaryFile | Rename-Item -NewName {$_.Name + ".zip"} -PassThru
$url = "https://github.com/php/php-sdk-binary-tools/archive/refs/heads/master.zip"
Invoke-WebRequest $url -OutFile $temp
Expand-Archive $temp -DestinationPath "."
Rename-Item "php-sdk-binary-tools-master" "php-sdk"

$baseurl = "https://windows.php.net/downloads/releases/archives"
$releases = @{
    "7.0" = "7.0.33"
    "7.1" = "7.1.33"
    "7.2" = "7.2.34"
    "7.3" = "7.3.33"
}
$phpversion = $releases.$version
if (-not $phpversion) {
    $baseurl = "https://windows.php.net/downloads/releases"
    $url = "$baseurl/releases.json"
    $releases = Invoke-WebRequest $url | ConvertFrom-Json
    $phpversion = $releases.$version.version
    if (-not $phpversion) {
        $baseurl = "https://windows.php.net/downloads/qa"
        $url = "$baseurl/releases.json"
        $releases = Invoke-WebRequest $url | ConvertFrom-Json
        $phpversion = $releases.$version.version
        if (-not $phpversion) {
            throw "unknown version"
        }
    }
}

$tspart = if ($ts -eq "nts") {"nts-Win32"} else {"Win32"}

Write-Output "Install PHP $phpversion ..."

$temp = New-TemporaryFile | Rename-Item -NewName {$_.Name + ".zip"} -PassThru
$fname = "php-$phpversion-$tspart-$vs-$arch.zip"
$url = "$baseurl/$fname"
Invoke-WebRequest $url -OutFile $temp
Expand-Archive $temp "php-bin"

Write-Output "Install development pack ..."

$temp = New-TemporaryFile | Rename-Item -NewName {$_.Name + ".zip"} -PassThru
$fname = "php-devel-pack-$phpversion-$tspart-$vs-$arch.zip"
$url = "$baseurl/$fname"
Invoke-WebRequest $url -OutFile $temp
Expand-Archive $temp "."
Rename-Item "php-$phpversion-devel-$vs-$arch" "php-dev"

if ($deps.Count -gt 0) {
    $baseurl = "https://windows.php.net/downloads/php-sdk/deps"
    $series = Invoke-WebRequest "$baseurl/series/packages-$version-$vs-$arch-staging.txt"
    $remainder = @()
    $installed = $false
    foreach ($dep in $deps) {
        foreach ($line in ($series.Content -Split "[\r\n]+")) {
            if ($line -match "^$dep") {
                Write-Output "Install $line"
                $temp = New-TemporaryFile | Rename-Item -NewName {$_.Name + ".zip"} -PassThru
                Invoke-WebRequest "$baseurl/$vs/$arch/$line" -OutFile $temp
                Expand-Archive $temp "../deps"
                $installed = $true
                break
            }
        }
        if (-not $installed) {
            $remainder += $dep
        }
    }
    if ($remainder.Count -gt 0) {
        foreach ($dep in $remainder) {
            Write-Output "$dep not available"
            exit 1
        }
    }
}

Add-Content $Env:GITHUB_PATH "$pwd\php-sdk\bin"
Add-Content $Env:GITHUB_PATH "$pwd\php-sdk\msys2\usr\bin"
Add-Content $Env:GITHUB_PATH "$pwd\php-bin"
Add-Content $Env:GITHUB_PATH "$pwd\php-dev"

Write-Output "toolset=$toolset" >> $Env:GITHUB_OUTPUT
Write-Output "prefix=$pwd\php-bin" >> $Env:GITHUB_OUTPUT
Write-Output "vs=$vs" >> $Env:GITHUB_OUTPUT
