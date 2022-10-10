# Setup PHP-SDK Action

[Github Action](https://github.com/features/actions) to set up an environment
for building and testing PHP extensions on Windows.

## Example Usage

````.yml
- id: setup-php-sdk
  uses: cmb69/setup-php-sdk@v0.5
  with:
    version: 8.0
    arch: x64
    ts: nts
- run: phpize
- run: configure --enable-dbase --with-prefix=${{steps.setup-php-sdk.outputs.prefix}}
- run: nmake
- run: nmake test TESTS=tests
````

## Inputs

- `version`: the PHP version to build for
  (`7.0`, `7.1`, `7.2`, `7.3`, `7.4`, `8.0` or `8.1`)
- `arch`: the architecture to build for (`x64` or `x86`)
- `ts`: thread-safety (`nts` or `ts`)
- `deps`: (optional) dependency libraries to install; for now, only
  [core dependencies](https://windows.php.net/downloads/php-sdk/deps/) are available
- `devcmd`: (optional) set to anything other than `'true'` to disable the
  [developer command propmt](https://github.com/ilammy/msvc-dev-cmd).

Note that for PHP versions 7.2 and below, `runs-on: windows-2022` will not work
as the correct toolset is not available. For these versions, you should use
`runs-on: windows-2019`. For example:

```yml
strategy:
  matrix:
    os: [ windows-2019, windows-2022 ]
    php: [ "8.1", "8.0", "7.4", "7.3", "7.2", "7.1" ]
    arch: [ x64, x86 ]
    ts: [ ts, nts ]
    exclude:
      - { os: windows-2019, php: "8.1" }
      - { os: windows-2019, php: "8.0" }
      - { os: windows-2019, php: "7.4" }
      - { os: windows-2019, php: "7.3" }
      - { os: windows-2022, php: "7.2" }
      - { os: windows-2022, php: "7.1" }
```

Currently, `windows-2019` may be used for all PHP versions, although this may
change in future releases.

## Outputs

- `toolset`: the required toolset version;
  needs to be passed to the ilammy/msvc-dev-cmd action
- `prefix`: the prefix of the PHP installation;
  needs to be passed to configure
- `vs`: the Visual Studio version (`crt`)
