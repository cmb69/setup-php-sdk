# Setup PHP-SDK Action

[Github Action](https://github.com/features/actions) to set up an environment
for building and testing PHP extensions on Windows.

## Inputs
- `version`: the PHP version to build for
  (`7.0`, `7.1`, `7.2`, `7.3`, `7.4`, `8.0` or `8.1`)
- `arch`: the architecture to build for (`x64` or `x86`)
- `ts`: thread-safety (`nts` or `ts`)
- `deps`: (optional) dependency libraries to install; for now, only
  [core](https://windows.php.net/downloads/php-sdk/deps/) and 
  [PECL](https://windows.php.net/downloads/pecl/deps/) dependencies are available
- `ignore_vs`: (optional) Ignore the VS version (`crt`) for given PECL dependencies
- `devcmd`: (optional) set to anything other than `'true'` to disable the
  [developer command propmt](https://github.com/ilammy/msvc-dev-cmd).
- `sdkref`: (optional) The tag or SHA of the desired version of the 
  [PHP-SDK binary tools](https://github.com/php/php-sdk-binary-tools);
  e.g. `775cf0dbfafd8f563451f94d0d0a2a5d8a7ec623` or `php-sdk-2.2.0`

## Outputs
- `toolset`: the required toolset version;
  needs to be passed to the ilammy/msvc-dev-cmd action
- `prefix`: the prefix of the PHP installation;
  needs to be passed to configure
- `vs`: the Visual Studio version (`crt`);
  e.g. `vc15` or `vs16` 
- `buildpath`: the build output path;
  one of `x64\Release_TS`, `x64\Release`, `Release_TS` or `Release`
- `file_tag`: a tag that consists of GitHub SHA/release tag, PHP version, ts, VS version and arch;
  useful e.g. for naming files or archives in artifacts and release attachments: `f668a23-8.0-ts-vs16-x64` or `1.0.3-8.0-ts-vs16-x64`

## Example Usage
```yml
- name: "Install PHP SDK"
  id: sdk
  uses: cmb69/setup-php-sdk@v0.7
  with:
    version: 8.0
    arch: x64
    ts: nts
    
# build    
- run: phpize
- run: configure --enable-dbase --with-prefix=${{steps.sdk.outputs.prefix}}
- run: nmake
- run: nmake test TESTS=tests
```

After the build you can use the provided output variables to package the files and attach them to a release or build artifacts:
```yml
- name: "Package"
  run: |
    md .artifact
    copy ${{steps.sdk.outputs.buildpath}}\php_dbase.dll .artifact\php_dbase-${{steps.sdk.outputs.file_tag}}.dll

- name: "Upload artifacts"
  uses: actions/upload-artifact@v3
  if: contains(github.ref_type, 'branch')
  with:
    name: php_dbase-${{github.sha}}
    path: .artifact

- name: "Attach file to release"
  uses: softprops/action-gh-release@v1
  if: contains(github.ref_type, 'tag')
  with:
    files: .artifact\php_dbase-${{steps.sdk.outputs.file_tag}}.dll
```

Note that for PHP versions 7.2 and below, `runs-on: windows-2022` will not work
as the correct toolset is not available. For these versions, you should use
`runs-on: windows-2019`. For example:

```yml
strategy:
  matrix:
    os: [ windows-2019, windows-2022 ]
    php: [ "8.2", "8.1", "8.0", "7.4", "7.3", "7.2", "7.1" ]
    arch: [ x64, x86 ]
    ts: [ ts, nts ]
    exclude:
      - { os: windows-2019, php: "8.2" }
      - { os: windows-2019, php: "8.1" }
      - { os: windows-2019, php: "8.0" }
      - { os: windows-2019, php: "7.4" }
      - { os: windows-2019, php: "7.3" }
      - { os: windows-2022, php: "7.2" }
      - { os: windows-2022, php: "7.1" }
```

Currently, `windows-2019` may be used for all PHP versions, although this may change in future releases.
