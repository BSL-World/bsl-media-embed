$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$output = Join-Path $repo 'dist'
New-Item -ItemType Directory -Force $output | Out-Null
$chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
$profilePath = Join-Path $output 'chrome-test-profile'
$browserProcess = Start-Process -FilePath $chromePath -WindowStyle Hidden -PassThru -Wait -ArgumentList @(
    '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    "--user-data-dir=$profilePath", '--virtual-time-budget=5000', '--dump-dom',
    'http://127.0.0.1:8769/tests/browser.html'
) -RedirectStandardOutput (Join-Path $output 'browser-dom.html') -RedirectStandardError (Join-Path $output 'browser-errors.log')
$dom = Get-Content (Join-Path $output 'browser-dom.html') -Raw
if ($browserProcess.ExitCode -ne 0 -or $dom -notmatch 'PASS [0-9]+ checks') {
    throw "Browser checks did not pass. See dist/browser-dom.html and browser-errors.log"
}
([regex]::Match($dom, 'PASS [^<]+')).Value
