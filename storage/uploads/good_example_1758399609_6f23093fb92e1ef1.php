<?php

declare(strict_types=1);

namespace PhpOptimizer\Examples;

class GoodExample
{
    private string $property;

    public function __construct(string $property)
    {
        $this->property = $property;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function setProperty(string $value): void
    {
        $this->property = $value;
    }
}