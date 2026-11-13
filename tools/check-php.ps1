$ErrorActionPreference = 'Stop'
foreach ($phpVersion in @('8.1', '8.2', '8.4')) {
    $phpPath = "C:\OSPanel\modules\PHP-$phpVersion\php.exe"
    & $phpPath -v | Select-Object -First 1
    & $phpPath tests/shortcode-rendering.test.php
    if ($LASTEXITCODE -ne 0) { throw "Rendering tests failed: PHP $phpVersion" }
    & $phpPath tests/button-registration.test.php
    if ($LASTEXITCODE -ne 0) { throw "Button registration failed: PHP $phpVersion" }
    & $phpPath tests/editor-library.test.php
    if ($LASTEXITCODE -ne 0) { throw "Editor library failed: PHP $phpVersion" }
    & $phpPath tests/editor-dispatcher.test.php
    if ($LASTEXITCODE -ne 0) { throw "Editor dispatcher failed: PHP $phpVersion" }
    & $phpPath tests/media-stream.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media streaming failed: PHP $phpVersion" }
    & $phpPath tests/media-library.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media library failed: PHP $phpVersion" }
    & $phpPath tests/upload-limits.test.php
    if ($LASTEXITCODE -ne 0) { throw "Upload limits failed: PHP $phpVersion" }
    & $phpPath -d post_max_size=160M -d upload_max_filesize=128M tests/upload-controller.test.php
    if ($LASTEXITCODE -ne 0) { throw "Upload controller failed: PHP $phpVersion" }
    & $phpPath -d post_max_size=70K -d upload_max_filesize=128M tests/upload-controller.test.php
    if ($LASTEXITCODE -ne 0) { throw "Upload aggregate bypass failed: PHP $phpVersion" }
    & $phpPath tests/media-depth.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media depth failed: PHP $phpVersion" }
    & $phpPath tests/media-acl.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media ACL failed: PHP $phpVersion" }
    & $phpPath tests/media-template.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media template failed: PHP $phpVersion" }
    & $phpPath tests/media-browse.test.php
    if ($LASTEXITCODE -ne 0) { throw "Media folder picker failed: PHP $phpVersion" }
    foreach ($constructorMode in @('old', 'new')) {
        & $phpPath tests/provider-compat.test.php $constructorMode
        if ($LASTEXITCODE -ne 0) { throw "Provider test failed: PHP $phpVersion, $constructorMode" }
    }
    foreach ($sourceFile in (Get-ChildItem com_bslmedia,plg_content_bslmediaembed,plg_editors-xtd_bslmedia,tests -Recurse -Filter *.php)) {
        & $phpPath -l $sourceFile.FullName
        if ($LASTEXITCODE -ne 0) { throw "PHP syntax failed: $($sourceFile.FullName)" }
    }
}
