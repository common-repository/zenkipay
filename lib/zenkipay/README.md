# Zenkipay SDK for PHP

[![Latest Stable Version](http://poser.pugx.org/zenki/zenkipay/v)](https://packagist.org/packages/zenki/zenkipay) [![Total Downloads](http://poser.pugx.org/zenki/zenkipay/downloads)](https://packagist.org/packages/zenki/zenkipay) [![License](http://poser.pugx.org/zenki/zenkipay/license)](https://packagist.org/packages/zenki/zenkipay) [![PHP Version Require](http://poser.pugx.org/zenki/zenkipay/require/php)](https://packagist.org/packages/zenki/zenkipay)

The Zenkipay SDK for PHP provides convenient access to the Zenkipay API from applications written in the PHP language.

## Requirements

-   PHP >= 7.4
-   A [PSR-17 implementation](https://packagist.org/providers/psr/http-factory-implementation)
-   A [PSR-18 implementation](https://packagist.org/providers/psr/http-client-implementation)

## Getting started

### Install

To install the SDK you will need to be using [Composer]([https://getcomposer.org/) in your project. To install it please see the [docs](https://getcomposer.org/download/).

This package (`zenki/zenkipay`) is not tied to any specific library that sends HTTP messages. Instead, it uses [Httplug](https://github.com/php-http/httplug) to let users choose whichever PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
composer require zenki/zenkipay php-http/curl-client
```

This will install the library itself along with an HTTP client adapter that uses cURL as transport method (provided by Httplug). You do not have to use those packages if you do not want to. The SDK does not care about which transport method you want to use because it's an implementation detail of your application. You may use any package that provides [`php-http/async-client-implementation`](https://packagist.org/providers/php-http/async-client-implementation) and [`http-message-implementation`](https://packagist.org/providers/psr/http-message-implementation).

## License

`zenkipay-sdk-php` is licensed under the Apache 2.0 License - see the LICENSE file for details.
