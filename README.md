> :warning: Deprecated: Laravel scout now supports database and collection drivers for testing purpose

<h1 align="center">Laravel Scout Database Driver</h1>

<div align="center">

[![Latest Stable Version](https://poser.pugx.org/sanjabteam/laravel-scout-database-driver/v/stable)](https://packagist.org/packages/sanjabteam/laravel-scout-database-driver)
[![Total Downloads](https://poser.pugx.org/sanjabteam/laravel-scout-database-driver/downloads)](https://packagist.org/packages/sanjabteam/laravel-scout-database-driver)
[![Build Status](https://github.com/sanjabteam/laravel-scout-database-driver/workflows/tests/badge.svg)](https://github.com/sanjabteam/laravel-scout-database-driver/actions)
[![Code Style](https://github.styleci.io/repos/356009663/shield?style=flat)](https://github.styleci.io/repos/356009663)
[![Code Coverage](https://codecov.io/gh/sanjabteam/laravel-scout-database-driver/branch/master/graph/badge.svg?sanitize=true)](https://codecov.io/gh/sanjabteam/laravel-scout-database-driver)
[![License](https://poser.pugx.org/sanjabteam/laravel-scout-database-driver/license)](https://packagist.org/packages/sanjabteam/laravel-scout-database-driver)

</div>

Database driver for Laravel scout.

## Why?

Of-course search engines like algolia are faster and more accurate.

But you can use database driver for these purposes.

1. You don't want to spend money on services like algolia now but you want your web app be flexible to use a search engine in future!
2. You want to test your app in local environment without running a search engine on your local machine or use a online search engine service.

## Installation

You can install the package via composer:

```bash
composer require sanjabteam/laravel-scout-database-driver
```
* Requirements
    * Laravel 8 >=
    * Laravel Scout 8 >=

Run migrations:
```bash
php artisan migrate
```

And [change scout default driver](#configuration).

## Configuration
You should change your default scout driver to `database`.

.env:
```env
SCOUT_DRIVER=database
```

-- Or --

config/scout.php:

```php
'driver' => 'database',
```

Add database specific configurations at end of scout config file.

config/scout.php:
```php
<?php

return [
    // ...
    'driver' => ...

    // ..

    'database' => [
        // All optional database configurations here
        'connection' => ...,
        'mode' => ...,
    ],
];

```

> All following configurations are optional

`scout.database.connection`:

Database connection to store search data. Change this if you don't want use your default database for search data.

`scout.database.mode`:

Search mode.

Possible values:
* `LIKE`  More strict, faster
* `LIKE_EXPANDED`  Less strict, slower

`scout.database.relevance`:

Relevance value for search query.

Check the [source code](./src/DatabaseEngine.php) to understand above configurations.


## Limits
* Order is not supported.

## Contributing

Contributions are welcome!

* Fork the Project
* Clone your project (git clone https://github.com/your_username/laravel-scout-database-driver.git)
* Create new branch (git checkout -b your_feature)
* Commit your Changes (git commit -m 'new feature')
* Push to the Branch (git push origin your_feature)
* Open a Pull Request

## Donation

<a href="https://github.com/amir9480/amir9480/blob/master/donation.md">
    <img src="https://raw.githubusercontent.com/amir9480/amir9480/master/donate.png" width="300">
</a>

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
