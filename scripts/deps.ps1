param (
    [Parameter(Mandatory)] [String] $version,
    [Parameter(Mandatory)] [String] $vs,
    [Parameter(Mandatory)] [String] $arch,
    [Parameter(Mandatory)] [AllowEmptyCollection()] [Array] $deps
)

$ErrorActionPreference = "Stop"

if ($deps.Count -gt 0) {
    $baseurl = "https://windows.php.net/downloads/php-sdk/deps"
    $series = Invoke-WebRequest "$baseurl/series/packages-$version-$vs-$arch-staging.txt"
    $remainder = @()
    $installed = $false
    foreach ($dep in $deps) {
        foreach ($line in ($series.Content -Split "[\r\n]+")) {
            if ($line -match "^$dep") {
                Write-Output "Installing $line"
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
