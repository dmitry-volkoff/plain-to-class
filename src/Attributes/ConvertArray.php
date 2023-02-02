<?php

declare(strict_types=1);

namespace ClassTransformer\Attributes;

/**
 * An attribute for properties that are an array that allows you to specify the type of element
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class ConvertArray
{
    /**
     * @param string $type
     */
    public function __construct(
        public string $type
    ) {
    }
}
