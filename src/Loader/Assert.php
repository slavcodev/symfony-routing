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
}
