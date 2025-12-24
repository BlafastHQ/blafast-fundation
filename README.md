# This is the technical and functional foundation of the BlaFast ERP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blafasthq/blafast-fundation.svg?style=flat-square)](https://packagist.org/packages/blafasthq/blafast-fundation)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blafasthq/blafast-fundation/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/blafasthq/blafast-fundation/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/blafasthq/blafast-fundation/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/blafasthq/blafast-fundation/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/blafasthq/blafast-fundation.svg?style=flat-square)](https://packagist.org/packages/blafasthq/blafast-fundation)

The blafast-fundation module is the technical and functional foundation of the BlaFast ERP. It provides all cross-cutting, non–business-specific features on which other modules (e.g. billing, inventory) will be built. It is developed in PHP 8.4 as a module for Laravel 12.

## Installation

You can install the package via composer:

```bash
composer require blafasthq/blafast-fundation
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="blafast-fundation-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="blafast-fundation-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="blafast-fundation-views"
```

## Usage

```php
$blafast = new Blafast\Blafast();
echo $blafast->echoPhrase('Hello, Blafast!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sébastien Denooz](https://github.com/SebastienDenooz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
