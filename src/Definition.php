<?php
/**
 * This file is part of prolic/fpp.
 * (c) 2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fpp;

class Definition
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $name;

    /**
     * @var Constructor[]
     */
    private $constructors = [];

    /**
     * @var Deriving[]
     */
    private $derivings = [];

    /**
     * @var Condition[]
     */
    private $conditions = [];

    /**
     * @var string|null
     */
    private $messageName;

    /**
     * @param string $namespace
     * @param string $name
     * @param Constructor[] $constructors
     * @param Deriving[] $derivings
     * @param Condition[] $conditions
     * @param string|null $messageName
     */
    public function __construct(
        string $namespace,
        string $name,
        array $constructors = [],
        array $derivings = [],
        array $conditions = [],
        string $messageName = null
    ) {
        $this->namespace = $namespace;
        $this->name = $name;

        $allowMessageName = false;

        if (empty($namespace)) {
            throw new \InvalidArgumentException('Namespace cannot be empty string');
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty string');
        }

        if (empty($constructors)) {
            throw new \InvalidArgumentException('At least one constructor required');
        }

        $constructorNames = [];
        foreach ($constructors as $constructor) {
            if (! $constructor instanceof Constructor) {
                throw new \InvalidArgumentException('Invalid constructor given, must be an instance of ' . Constructor::class);
            }

            if (isset($constructorNames[$constructor->name()])) {
                throw new \InvalidArgumentException('Duplicate constructor name given');
            }

            $constructorNames[$constructor->name()] = true;
            $this->constructors[] = $constructor;
        }

        $derivingNames = [];
        foreach ($derivings as $deriving) {
            if (! $deriving instanceof Deriving) {
                throw new \InvalidArgumentException('Invalid deriving given, must be an instance of ' . Deriving::class);
            }

            if (isset($derivingNames[(string) $deriving])) {
                throw new \InvalidArgumentException('Duplicate deriving given');
            }

            $deriving->checkDefinition($this);

            $derivingNames[(string) $deriving] = true;
            $this->derivings[] = $deriving;

            if (in_array((string) $deriving, [
                Deriving\AggregateChanged::VALUE,
                Deriving\Command::VALUE,
                Deriving\DomainEvent::VALUE,
                Deriving\Query::VALUE,
                Deriving\MicroAggregateChanged::VALUE,
            ], true)) {
                $allowMessageName = true;
            }
        }

        foreach ($conditions as $condition) {
            if (! $condition instanceof Condition) {
                throw new \InvalidArgumentException('Invalid condition given, must be an instance of ' . Condition::class);
            }
            $this->conditions[] = $condition;
        }

        $this->messageName = $messageName;

        if (! $allowMessageName && ! empty($messageName)) {
            throw $this->invalid('Message name is only allowed for AggregateChanged, Command, DomainEvent or Query');
        }

        if ('' === $messageName) {
            throw $this->invalid('Message name cannot be empty string');
        }
    }

    /**
     * @return string
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return Constructor[]
     */
    public function constructors(): array
    {
        return $this->constructors;
    }

    /**
     * @return Deriving[]
     */
    public function derivings(): array
    {
        return $this->derivings;
    }

    /**
     * @return Condition[]
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    public function messageName(): ?string
    {
        return $this->messageName;
    }

    private function invalid(string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf(
            'Error on %s%s: %s',
            $this->namespace . '\\',
            $this->name,
            $message
        ));
    }
}
