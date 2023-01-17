<?php

namespace ClassTransformer;

use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use ReflectionNamedType;
use ClassTransformer\DTO\Property;
use ClassTransformer\Attributes\NotTransform;
use ClassTransformer\Attributes\ConvertArray;
use ClassTransformer\Attributes\WritingStyle;
use ClassTransformer\Exceptions\ClassNotFoundException;
use ClassTransformer\Exceptions\ValueNotFoundException;

use function sizeof;
use function func_get_args;
use function array_key_exists;

/**
 * Class ClassTransformerService
 *
 * @author yzen.dev <yzen.dev@gmail.com>
 */
final class PropertyTransformer
{
    /**
     * @template T
     * class-string<T> $className
     */
    private string $className;

    /** @var array<mixed> */
    private array $args;

    /**
     * @var ReflectionClass
     */
    private ReflectionClass $refInstance;

    /**
     * @template T
     *
     * @param ReflectionClass|string $class
     * @param array<mixed>|object|null $args
     *
     * @throws ClassNotFoundException
     * @throws ReflectionException
     */
    public function __construct(ReflectionClass|string $class, ...$args)
    {
        if ($class instanceof ReflectionClass) {
            $this->className = $class->getName();
            /** @phpstan-ignore-next-line */
            $this->refInstance = $class;
        } else {
            $this->className = $class;

            $this->validate();

            /** @phpstan-ignore-next-line */
            $this->refInstance = new ReflectionClass($this->className);
        }

        // Arguments transfer as named arguments (for php8)
        // if dynamic arguments, named ones lie immediately in the root, if they were passed as an array, then they need to be unpacked
        $inArgs = sizeof(func_get_args()) === 1 ? $args : $args[0];

        if (is_object($inArgs)) {
            $inArgs = (array)$inArgs;
        }

        $this->args = $inArgs ?? [];
    }

    /**
     * @return void
     * @throws ClassNotFoundException
     */
    private function validate(): void
    {
        if (!class_exists($this->className)) {
            throw new ClassNotFoundException("Class $this->className not found. Please check the class path you specified.");
        }
    }

    /**
     * @template T
     *
     * @return T
     * @throws ClassNotFoundException|ReflectionException
     */
    public function transform()
    {
        // if exist custom transform method
        if (method_exists($this->className, 'transform')) {
            /** @phpstan-ignore-next-line */
            return $this->className::transform($this->args);
        }

        /** @var T $instance */
        $instance = new $this->className();

        foreach ($this->refInstance->getProperties() as $item) {
            $property = new Property($this->refInstance->getProperty($item->name));

            try {
                $value = $this->getValue($item);
            } catch (ValueNotFoundException) {
                continue;
            }

            if ($property->isScalar() || $property->notTransform()) {
                $instance->{$item->name} = $value;
                continue;
            }

            if ($property->isArray()) {
                $arrayTypeAttr = $item->getAttributes(ConvertArray::class);
                if (!empty($arrayTypeAttr)) {
                    $arrayType = $arrayTypeAttr[0]->getArguments()[0];
                } else {
                    $arrayType = PropertyHelper::getClassFromPhpDoc($property->getDocComment());
                }

                if (!empty($arrayType) && !empty($value) && !PropertyHelper::propertyIsScalar($arrayType)) {
                    foreach ($value as $el) {
                        $instance->{$item->name}[] = (new self($arrayType, $el))->transform();
                    }
                    continue;
                }

                $instance->{$item->name} = $value;
                continue;
            }

            if ($property->getType() instanceof ReflectionNamedType) {
                /** @phpstan-ignore-next-line */
                $propertyClass = $property->getType()->getName();
                $childrenRefInstance = new ReflectionClass($propertyClass);
                if ($childrenRefInstance->isEnum()) {
                    $value = constant($propertyClass . '::' . $value);
                    $instance->{$item->name} = $value;
                    continue;
                }

                $instance->{$item->name} = (new self($childrenRefInstance, $value))->transform();
                continue;
            }
            $instance->{$item->name} = $value;
        }
        return $instance;
    }

    /**
     * @param ReflectionProperty $item
     *
     * @return mixed|object|void
     * @throws ValueNotFoundException
     */
    private function getValue(ReflectionProperty $item)
    {
        if (array_key_exists($item->name, $this->args)) {
            return $this->args[$item->name];
        }

        $writingStyle = $item->getAttributes(WritingStyle::class);

        if (empty($writingStyle)) {
            throw new ValueNotFoundException();
        }
        foreach ($writingStyle as $style) {
            $styles = $style->getArguments();
            if (
                (in_array(WritingStyle::STYLE_SNAKE_CASE, $styles) || in_array(WritingStyle::STYLE_ALL, $styles)) &
                array_key_exists(WritingStyleUtil::strToSnakeCase($item->name), $this->args)
            ) {
                return $this->args[WritingStyleUtil::strToSnakeCase($item->name)];
            }
            if (
                (in_array(WritingStyle::STYLE_CAMEL_CASE, $styles) || in_array(WritingStyle::STYLE_ALL, $styles)) &
                array_key_exists(WritingStyleUtil::strToCamelCase($item->name), $this->args)
            ) {
                return $this->args[WritingStyleUtil::strToCamelCase($item->name)];
            }
        }
        throw new ValueNotFoundException();
    }
}
