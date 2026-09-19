<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Container;

use Closure;
use RuntimeException;

/**
 * Minimal service container.
 *
 * Explicit factories rather than reflection-based autowiring. Autowiring is
 * convenient until something is mis-wired, at which point the error surfaces
 * deep inside a reflection call with no useful trace. Here the wiring is a
 * readable list in one file, and a missing binding fails immediately with the
 * name of what was asked for.
 *
 * Everything is a singleton by default: one PDO connection, one translator,
 * one logger per request, which is what a short-lived PHP request wants.
 */
final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> guards against a circular definition */
    private array $resolving = [];

    /**
     * Register a lazily-constructed singleton.
     *
     * @param Closure(self): mixed $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Register a factory that builds a fresh instance on every get().
     *
     * @param Closure(self): mixed $factory
     */
    public function factory(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->instances['__transient__' . $id] = true;
    }

    /** Store an already-constructed object. */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances) && !isset($this->instances['__transient__' . $id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Service '{$id}' is not registered in the container.");
        }

        if (isset($this->resolving[$id])) {
            throw new RuntimeException(
                "Circular dependency detected while resolving '{$id}'. Chain: "
                . implode(' -> ', array_keys($this->resolving)) . " -> {$id}"
            );
        }

        $this->resolving[$id] = true;

        try {
            $instance = ($this->factories[$id])($this);
        } finally {
            unset($this->resolving[$id]);
        }

        if (!isset($this->instances['__transient__' . $id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }

    /** @return list<string> */
    public function registered(): array
    {
        return array_values(array_unique([
            ...array_keys($this->factories),
            ...array_filter(array_keys($this->instances), static fn (string $k): bool => !str_starts_with($k, '__transient__')),
        ]));
    }
}
