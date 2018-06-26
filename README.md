# The Conventional Symfony Routes Loader

[![Software License][ico-license]][link-license]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]

YamlFileLoader loads Yaml routing files.

## Breaking changes

- [x] No route name, it's autogenerated by the convention. It's usually the same as the path (without leading slashes), but in some cases has prefix (see `group`, `methods`).
- [x] No `name_prefix` for the imports, it's autogenerated by the convention too.
- [x] No `prefix` for the imports, we can use the same `path`. 
- [x] No pointless `type` key, because only one type is supported, that's `yaml`
- [x] No `trailing_slash_on_root` key (at least at the moment)
- [x] The `methods` has different meaning than in the original format. It groups the routes by HTTP method (useful in REST API). The allowed methods for the route is specified as `_allowed_methods` in the route `defaults` now, along with `_controller`.
- [x] The `path` does not support array anymore. To define routes per locales use new `locales` key. 

## New features

- [x] [The `group` key, to group multiple routes with ability to define common values for group](#group-routes)
- [x] [The `methods` key, similar to `group`, but with specific behavior to add routes per locale](#define-methods-routes)
- [x] [The `locales` key, similar to `group`, but with specific behavior to add routes per locale](#define-localized-routes)
- [x] [Support custom keys, they are automatically moved to defaults key](#support-custom-keys)

## TL;DR

### Group routes

Group routes mainly to share the settings and avoid writing full path again and again
~~~yaml
- path: '/api'
  defaults:
    _allowed_methods: ['GET', 'PUT']
  requireemnts:
    id: '[-\w\d]+'
  group:
    - path: '/posts/{id}'
      defaults:
        _controller: App\Controller\BlogApiController
    - path: '/comments{id}'
      defaults:
        _controller: App\Controller\CommentApiController
~~~

its original representation
~~~yaml
api/posts/{id}:
  path: '/api/posts/{id}'
  defaults:
    _controller: App\Controller\BlogApiController
  methods: ['GET', 'PUT']
  requireemnts:
    id: '[-\w\d]+'
api/comments/{id}:
  path: '/api/comments/{id}'
  defaults:
    _controller: App\Controller\CommentApiController
  methods: ['GET', 'PUT']
  requireemnts:
    id: '[-\w\d]+'
~~~

### Define methods routes

Extend shared definition to specify method routes
~~~yaml
- path: '/posts/{id}'
  requireemnts:
    id: '[-\w\d]+'
  methods:
    GET:
      defaults:
        _controller: App\Controller\BlogApiController::get
    PUT:
      defaults:
        _controller: App\Controller\BlogApiController::put
~~~

its original representation
~~~yaml
posts/{id}/get:
  path: '/api/posts/{id}'
  defaults:
    _controller: App\Controller\BlogApiController::get
  methods: ['GET']
  requireemnts:
    id: '[-\w\d]+'
posts/{id}/put:
  path: '/api/posts/{id}'
  defaults:
    _controller: App\Controller\BlogApiController::put
  methods: ['PUT']
  requireemnts:
    id: '[-\w\d]+'
~~~

### Define localized routes

Specify localized routes
~~~yaml
- path: '/posts/{id}'
  requireemnts:
    id: '[-\w\d]+'
  defaults:
    _controller: App\Controller\BlogApiController
  locales:
    en: '/en/posts/{id}'
    es: '/es/posts/{id}'
~~~

its original representation
~~~yaml
posts/{id}:
  path:
    en: '/en/posts/{id}'
    es: '/es/posts/{id}'
  requireemnts:
    id: '[-\w\d]+'
  defaults:
    _controller: App\Controller\BlogApiController
~~~

### Support custom keys

Custom keys are automatically moved to defaults key 
~~~yaml
- path: '/posts/{id}'
  title: "Hello world!"
~~~

its original representation
~~~yaml
posts/{id}:
  path: '/posts/{id}'
  requireemnts:
    id: '[-\w\d]+'
  defaults:
    title: "Hello world!"
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
