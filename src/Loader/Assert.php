<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;
use function array_diff;
use function array_keys;
use function array_map;
use function gettype;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function stream_is_local;

final class Assert
{
    public static function fileResource($filepath): void
    {
        if (!is_string($filepath)) {
            throw new InvalidArgumentException(sprintf('Got "%s" but expected the string.', gettype($filepath)));
        }

        if (!stream_is_local($filepath)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $filepath));
        }
    }

    public static function definition($config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException('The each definition must be a YAML array.');
        }

        if ($extraKeys = array_diff(array_keys($config), YamlFileLoader::SUPPORTED_KEYS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Definition contains unsupported keys: "%s". Expected one of: "%s".',
                    implode('", "', $extraKeys),
                    implode('", "', YamlFileLoader::SUPPORTED_KEYS)
                )
            );
        }

        if (isset($config['path']) && is_array($config['path'])) {
            throw new InvalidArgumentException('The path should be a string.');
        }
    }

    public static function importDefinition($config): void
    {
        if (isset($config['group']) || isset($config['methods']) || isset($config['methods'])) {
            throw new InvalidArgumentException('The import definition must not specify the "group", "methods" or "locale" keys.');
        }
    }

    public static function definitionWithMethodsSpecification($methods, $config): void
    {
        if (!is_array($methods)) {
            throw new InvalidArgumentException('The definition of the "methods" must be a YAML array.');
        }

        if (isset($config['defaults']['_allowed_methods'])) {
            throw new InvalidArgumentException('The definition with the "methods" must not specify "_allowed_methods".');
        }

        if ($extraMethods = array_diff(array_map('strtoupper', array_keys($methods)), YamlFileLoader::SUPPORTED_METHODS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported methods definition: "%s". Expected one of: "%s".',
                    implode('", "', $extraMethods),
                    implode('", "', YamlFileLoader::SUPPORTED_METHODS)
                )
            );
        }
    }

    public static function methodDefinition($config): void
    {
        if (isset($config['path'])) {
            throw new InvalidArgumentException('The definition of the "methods" must not specify "path".');
        }

        if (isset($config['defaults']['_allowed_methods'])) {
            throw new InvalidArgumentException('The definition of the "methods" must not specify "_allowed_methods".');
        }
    }
}
