# Setup PHP-SDK Action

[Github Action](https://github.com/features/actions) to set up an environment
for building and testing PHP extensions on Windows.

## Example Usage

````.yml
- id: setup-php-sdk
  uses: cmb69/setup-php-sdk@v0.1
  with:
    version: 8.0
    arch: x64
    ts: nts
- uses: ilammy/msvc-dev-cmd@v1
  with:
    arch: x64
    toolset: ${{steps.setup-php-sdk.outputs.toolset}}
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
- `deps`: dependency libraries to install; for now, only
  [core dependencies](https://windows.php.net/downloads/php-sdk/deps/) are available

## Outputs

- `toolset`: the required toolset version;
  needs to be passed to the ilammy/msvc-dev-cmd action
- `prefix`: the prefix of the PHP installation;
  needs to be passed to configure
