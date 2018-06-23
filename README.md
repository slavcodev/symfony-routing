# The Conventional Symfony Routes Loader

[![Software License][ico-license]][link-license]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]

*TBD*

## TL;DR

Just compare the conventional Yaml file
~~~yaml
# Using route for all methods
/api/status:
  controller: App\Controller\StatusApiController:handle
  methods: ['GET', 'HEAD']
# Using method as action name with shared controller
# Also override shared route info with specific
/api/posts/{id}:
  controller: App\Controller\BlogApiController
  defaults:
    foo: foo
  methods:
    GET:
      defaults:
        bar: bar
    PUT: ~
# Using one controller action for many methods
/api/comments:
  controller: App\Controller\CommentsApiController::handle
  methods:
    GET: ~
    POST: ~
~~~

And its original representation
~~~yaml
/api/status:
  path: /api/status
  controller: App\Controller\StatusApiController:handle
  methods: ['GET', 'HEAD']
/api/posts/{id}:
  path: /api/posts/{id}
  controller: App\Controller\BlogApiController::get
  defaults:
    bar: bar
  methods:
    - GET
put::/api/posts/{id}:
  path: /api/posts/{id}
  controller: App\Controller\BlogApiController::put
  defaults:
    foo: foo
  methods:
    - PUT
/api/comments:
  path: /api/comments
  controller: App\Controller\CommentsApiController::handle
  methods:
    - GET
post::/api/comments:
  path: /api/comments
  controller: App\Controller\CommentsApiController::handle
  methods:
    - POST
~~~

## Install

Using [Composer](https://getcomposer.org)

~~~bash
composer require slavcodev/symfony-routing
~~~

## Testing

~~~bash
# install required files
composer self-update
composer install

# run the test (from project root)
phpunit
~~~

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE OF CONDUCT](CODE_OF_CONDUCT.md) for details.

## Credits

[To All Awesome Contributors](../../contributors)

## License

The BSD 2-Clause License. Please see [LICENSE][link-license] for more information.

[RFC-7807]: https://tools.ietf.org/html/rfc7807

[ico-license]: https://img.shields.io/badge/License-BSD%202--Clause-blue.svg?style=flat-square
[ico-version]: https://img.shields.io/packagist/v/slavcodev/symfony-routing.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/slavcodev/symfony-routing/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/slavcodev/symfony-routing.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/slavcodev/symfony-routing.svg?style=flat-square

[link-license]: LICENSE
[link-packagist]: https://packagist.org/packages/slavcodev/symfony-routing
[link-travis]: https://travis-ci.org/slavcodev/symfony-routing
[link-scrutinizer]: https://scrutinizer-ci.com/g/slavcodev/symfony-routing/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/slavcodev/symfony-routing
