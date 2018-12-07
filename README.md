# Courier

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travisci]][link-travisci]
[![Coverage Status][ico-codecov]][link-codecov]
[![Style Status][ico-styleci]][link-styleci]
[![Scrutinizer Code Quality][ico-scrutinizer]][link-scrutinizer]

A courier implementation for Postmark.

See [documentation](https://quartzy.github.io/courier/couriers/postmark/) for full details.

## Install

### Via Composer

```bash
composer require camuthig/courier-postmark
```

## Usage

```php
<?php

use Courier\SendGridCourier;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;

$courier = new PostmarkCourier(new PostmarkClient(getenv('POSTMARK_KEY')));

$email = EmailBuilder::email()
    ->withSubject('Welcome!')
    ->withContent(SimpleContent::text('Start your free trial now!!!'))
    ->from('me@test.com')
    ->to('you@yourbusiness.com')
    ->build();

$courier->deliver($email);
```

For details on building the email objects, see [Php Email](https://github.com/quartzy/php-email).


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email a project maintainer instead of using the issue tracker.

## Credits

- [Chris Muthig](https://github.com/camuthig)
- [All Contributors][link-contributors]


## License

The Apache License, v2.0. Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/camuthig/courier-postmark.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202.0-brightgreen.svg?style=flat-square
[ico-travisci]: https://img.shields.io/travis/camuthig/courier-postmark.svg?style=flat-square
[ico-codecov]: https://img.shields.io/scrutinizer/coverage/g/camuthig/courier-postmark.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/160859911/shield
[ico-scrutinizer]: https://img.shields.io/scrutinizer/g/camuthig/courier-postmark.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/camuthig/courier-postmark.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/camuthig/courier-postmark
[link-travisci]: https://travis-ci.org/camuthig/courier-postmark
[link-codecov]: https://scrutinizer-ci.com/g/camuthig/courier-postmark
[link-styleci]: https://styleci.io/repos/160859911
[link-scrutinizer]: https://scrutinizer-ci.com/g/camuthig/courier-postmark
[link-downloads]: https://packagist.org/packages/camuthig/courier-postmark
[link-contributors]: ../../contributors
