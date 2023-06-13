<?php

declare(strict_types=1);

namespace ClassTransformer\Reflection;

use ClassTransformer\Reflection\Types\TypeEnums;
use ReflectionType;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionAttribute;
use ClassTransformer\TransformUtils;
use ClassTransformer\Attributes\FieldAlias;
use ClassTransformer\Attributes\NotTransform;

use function sizeof;
use function in_array;
use function method_exists;
use function array_intersect;

/**
 * Class GenericProperty
 */
final class RuntimeReflectionProperty implements \ClassTransformer\Contracts\ReflectionProperty
{
    /** @var ReflectionProperty */
    public ReflectionProperty $property;

    /** @var string */
    public string $type;

    /** @var array|string[] */
    public array $types;

    /** @var class-string|string $propertyClass */
    public string $name;

    /** @var class-string */
    public string $class;

    /** @var bool */
    public bool $isScalar;

    /** @var bool */
    public bool $nullable;

    /** @var array<class-string,array<string, array<ReflectionAttribute>>> */
    private static array $attributesCache = [];

    /**
     * @param ReflectionProperty $property
     */
    public function __construct(ReflectionProperty $property)
    {
        $this->property = $property;
        $this->class = $property->class;
        $type = $this->property->getType();
        
        if ($type === null) {
            $this->type = TypeEnums::TYPE_MIXED;
            $this->nullable = true;
            $this->isScalar = true;
        } else if ($type instanceof ReflectionNamedType) {
            $this->type = $type->getName();
            $this->nullable = $type->allowsNull();
            $this->isScalar = $type->isBuiltin();
        } else {
            $this->isScalar = $type->isBuiltin();
            $this->nullable = $type->allowsNull();
            $this->type = (string)$type;
        }

        $this->name = $this->property->name;

        
    }


    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isEnum(): bool
    {
        if (!function_exists('enum_exists')) {
            return false;
        }
        return !$this->isScalar && enum_exists($this->type);
    }

    /**
     * @return string
     */
    public function getDocComment(): string
    {
        $doc = $this->property->getDocComment();
        return $doc !== false ? $doc : '';
    }

    /**
     * @return bool
     */
    public function notTransform(): bool
    {
        return $this->getAttribute(NotTransform::class) !== null;
    }

    /**
     * @param string $name
     *
     * @template T
     * @return null|ReflectionAttribute
     */
    public function getAttribute(string $name): ?ReflectionAttribute
    {
        if (isset(self::$attributesCache[$this->class][$this->name][$name])) {
            return self::$attributesCache[$this->class][$this->name][$name];
        }

        $attr = $this->property->getAttributes($name);
        if (!empty($attr)) {
            return self::$attributesCache[$this->class][$this->name][$name] = $attr[0];
        }
        return null;
    }

    /**
     * @param string|null $name
     *
     * @return null|array<string>
     */
    public function getAttributeArguments(?string $name = null): ?array
    {
        return $this->getAttribute($name)?->getArguments();
    }

    /**
     * @return bool
     */
    public function hasSetMutator(): bool
    {
        return method_exists($this->class, TransformUtils::mutationSetterToCamelCase($this->name));
    }

    /**
     * @return bool
     */
    public function isScalar(): bool
    {
        return $this->isScalar;
    }

    /**
     * @return bool
     */
    public function transformable(): bool
    {
        return !$this->isScalar && $this->type !== TypeEnums::TYPE_ARRAY;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getAliases(): array
    {
        $aliases = $this->getAttributeArguments(FieldAlias::class);

        if (empty($aliases)) {
            return [];
        }

        $aliases = $aliases[0];

        if (is_string($aliases)) {
            $aliases = [$aliases];
        }
        return $aliases;
    }
}
