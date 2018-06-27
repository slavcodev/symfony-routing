<?php
/**
 * This file is part of {@see https://github.com/slavcodev/ Slavcodev Projects}.
 */

declare(strict_types=1);

namespace Slavcodev\Symfony\Routing\Loader;

use InvalidArgumentException;

final class Assert
{
    public static function isArray($value, $key)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('The %s must be a YAML array.', $key));
        }
    }

    public static function isString($value, $key)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('The %s must be a string.', $key));
        }
    }

    public static function noAllowedMethods(array $config, $key)
    {
        if (isset($config['defaults']['_allowed_methods'])) {
            throw new InvalidArgumentException(sprintf('The %s must not contain "_allowed_methods".', $key));
        }
    }

    public static function noPath(array $config, $key)
    {
        if (isset($config['path'])) {
            throw new InvalidArgumentException(sprintf('The %s must not contain "path".', $key));
        }
    }

    public static function containCanonicalPath(array $config, $key)
    {
        if (!isset($config['path'])) {
            throw new InvalidArgumentException(sprintf('Missing canonical path for the %s.', $key));
        }
    }

    public static function isLocalStream($filepath)
    {
        if (!stream_is_local($filepath)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $filepath));
        }
    }
}
