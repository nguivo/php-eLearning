<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ContainerException;
use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionException;

/**
 * Container — Dependency Injection Container
 */
class Container
{
    // -------------------------------------------------------------------------
    // Registry
    // -------------------------------------------------------------------------

    private array $bindings = []; // abstract → ['factory' => Closure, 'singleton' => bool]
    private array $instances = [];  // resolved singleton instances
    private array $extenders = [];  // modify resolved instances after construction

    /**
     * Tracks abstracts currently being resolved to detect circular dependencies.
     * Keyed by abstract name → true while resolution is in progress.
     *
     * @var array<string, bool>
     */
    private array $resolving = [];


    // -------------------------------------------------------------------------
    // Registration API
    // -------------------------------------------------------------------------

    public function bind(string $abstract, Closure|string $factory): void
    {
        $this->bindings[$abstract] = [
            'factory'   => $this->normaliseFactory($factory),
            'singleton' => false,
        ];

        // Discard any previously cached instance so the new binding takes effect
        unset($this->instances[$abstract]);
    }


    public function singleton(string $abstract, Closure|string $factory): void
    {
        $this->bindings[$abstract] = [
            'factory'   => $this->normaliseFactory($factory),
            'singleton' => true,
        ];

        unset($this->instances[$abstract]);
    }

    /**
     * Register a pre-built object as a permanent instance.
     *
     * @param string $abstract Class or interface name
     * @param object $instance The pre-built object
     */
    public function instance(string $abstract, object $instance): void
    {
        // Apply any extenders that were registered before this instance was set
        $this->instances[$abstract] = $this->applyExtenders($abstract, $instance);
    }

    /**
     * Decorate or modify a resolved instance after it is built.
     *
     * @param string  $abstract The class or interface to extend
     * @param Closure $extender fn(object $instance, Container $c): object
     */
    public function extend(string $abstract, Closure $extender): void
    {
        $this->extenders[$abstract][] = $extender;

        // If a singleton is already cached, apply the extender immediately
        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $extender($this->instances[$abstract], $this);
        }
    }

    /**
     * Returns true if the abstract has an explicit binding or instance.
     * Returns false for classes that will be auto-wired.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Returns true if a singleton for this abstract has been resolved and cached.
     */
    public function resolved(string $abstract): bool
    {
        return isset($this->instances[$abstract]);
    }


    // -------------------------------------------------------------------------
    // Resolution API
    // -------------------------------------------------------------------------

    /**
     * Resolve and return an instance of the given class or interface.
     *
     * Resolution order:
     *   1. Pre-built / cached singleton instance → returned immediately.
     *   2. Circular dependency check → throws ContainerException if detected.
     *   3. Registered binding → factory is called.
     *   4. Auto-wiring → constructor parameters are resolved recursively.
     *   5. Extenders are applied to the built instance.
     *   6. Singleton instances are cached for subsequent calls.
     *
     * @param  string $abstract Class or interface name
     * @return object           Fully resolved and injected instance
     *
     * @throws ContainerException  on circular dependency or unresolvable parameter
     * @throws ReflectionException on invalid class name
     */
    public function make(string $abstract): object
    {
        // 1. Return cached instance (singleton or pre-built)
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Circular dependency guard
        if (isset($this->resolving[$abstract])) {
            throw new ContainerException(
                "Circular dependency detected while resolving [{$abstract}]. " .
                "Stack: " . implode(' -> ', array_keys($this->resolving)) . " -> {$abstract}"
            );
        }

        $this->resolving[$abstract] = true;

        try {
            $instance = $this->build($abstract);
        } finally {
            // Always clean up the resolution stack, even if build() throws
            unset($this->resolving[$abstract]);
        }

        // 3. Apply extenders
        $instance = $this->applyExtenders($abstract, $instance);

        // 4. Cache if singleton
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Resolve a class with runtime parameter overrides.
     *
     * Use when a class has scalar constructor parameters that cannot be
     * auto-wired, without registering a full factory for a one-off construction.
     *
     *   $hasher = $container->makeWith(PasswordHasher::class, ['algo' => 'bcrypt', 'cost' => 12]);
     *
     * @param  string               $abstract
     * @param  array<string, mixed> $overrides Parameter name → value
     * @return object
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function makeWith(string $abstract, array $overrides): object
    {
        $instance = $this->buildWithOverrides($abstract, $overrides);
        return $this->applyExtenders($abstract, $instance);
    }

    /**
     * Call any callable, auto-resolving its type-hinted parameters from the
     * container and optionally merging in runtime overrides.
     *
     * Useful for calling controller methods, closures, or event handlers
     * without manually resolving every dependency.
     *
     *   // Auto-resolve all parameters
     *   $container->call([CourseController::class, 'index']);
     *
     *   // Closure — CourseService is resolved from the container
     *   $container->call(fn(CourseService $svc) => $svc->findPublished());
     *
     *   // 'Class@method' string syntax
     *   $container->call('CourseController@index');
     *
     *   // With a runtime override for a scalar
     *   $container->call([ReportService::class, 'generate'], ['format' => 'pdf']);
     *
     * @param  callable|string      $callable  Anything callable, plus 'Class@method'
     * @param  array<string, mixed> $overrides Additional parameter values
     * @return mixed                           The callable's return value
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function call(callable|string $callable, array $overrides = []): mixed
    {
        // Resolve 'Class@method' string into a real callable
        if (is_string($callable) && str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            $callable = [$this->make($class), $method];
        }

        $reflector  = $this->reflectCallable($callable);
        $parameters = $this->resolveParameters($reflector->getParameters(), $overrides);

        return $callable(...$parameters);
    }


    // -------------------------------------------------------------------------
    // Private — Build Logic
    // -------------------------------------------------------------------------

    /**
     * Build an instance using its registered factory or auto-wiring.
     */
    private function build(string $abstract): object
    {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract]['factory'])($this);
        }

        return $this->autoWire($abstract);
    }

    /**
     * Auto-wire a class by inspecting its constructor with Reflection.
     *
     * Resolves each type-hinted constructor parameter recursively through
     * make(). Fails with a clear message if a parameter cannot be resolved.
     *
     * @throws ContainerException if the class is not instantiable or has
     *                            an unresolvable scalar parameter
     * @throws ReflectionException
     */
    private function autoWire(string $class): object
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Cannot resolve [{$class}]: class does not exist.",
                previous: $e
            );
        }

        if (!$reflector->isInstantiable()) {
            $type = $reflector->isInterface() ? 'interface' : 'abstract class';
            throw new ContainerException(
                "Cannot auto-wire [{$class}]: it is a {$type} and cannot be instantiated directly. " .
                "Register a concrete binding: \$container->bind({$class}::class, ConcreteClass::class)."
            );
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $parameters = $this->resolveParameters($constructor->getParameters(), []);

        return $reflector->newInstanceArgs($parameters);
    }

    /**
     * Build an instance with caller-supplied runtime parameter overrides.
     * Used by makeWith() — bypasses the singleton cache.
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function buildWithOverrides(string $abstract, array $overrides): object
    {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract]['factory'])($this);
        }

        try {
            $reflector   = new ReflectionClass($abstract);
            $constructor = $reflector->getConstructor();
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Cannot resolve [{$abstract}]: class does not exist.",
                previous: $e
            );
        }

        if ($constructor === null) {
            return new $abstract();
        }

        $parameters = $this->resolveParameters($constructor->getParameters(), $overrides);

        return $reflector->newInstanceArgs($parameters);
    }

    /**
     * Resolve an array of ReflectionParameters to concrete values.
     *
     * For each parameter, the resolution priority is:
     *   1. Caller-supplied override keyed by parameter name
     *   2. Class/interface type hint → recursively make()
     *   3. Parameter default value
     *   4. Nullable parameter → null
     *   5. Unresolvable → ContainerException with a clear message
     *
     * @param  ReflectionParameter[]    $parameters
     * @param  array<string, mixed>     $overrides
     * @return array<int, mixed>
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function resolveParameters(array $parameters, array $overrides): array
    {
        $resolved = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // 1. Caller-supplied override
            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];
                continue;
            }

            $type = $parameter->getType();

            // 2. Class/interface type hint — recurse into make()
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Nullable class type and we can't resolve it → pass null
                if ($type->allowsNull() && !$this->has($typeName) && !class_exists($typeName)) {
                    $resolved[] = null;
                    continue;
                }

                $resolved[] = $this->make($typeName);
                continue;
            }

            // 3. Default value
            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            // ── 4. Nullable with no default → null ───────────────────────────
            if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            // ── 5. Unresolvable scalar ───────────────────────────────────────
            $typeName    = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
            $declaringClass = $parameter->getDeclaringClass()?->getName() ?? 'unknown';

            throw new ContainerException(
                "Cannot auto-wire [{$declaringClass}]: parameter \${$name} has type [{$typeName}] " .
                "which cannot be resolved automatically. " .
                "Register the class manually: \$container->singleton({$declaringClass}::class, fn(\$c) => new {$declaringClass}(...))."
            );
        }

        return $resolved;
    }

    /**
     * Apply all registered extenders to a resolved instance in order.
     */
    private function applyExtenders(string $abstract, object $instance): object
    {
        foreach ($this->extenders[$abstract] ?? [] as $extender) {
            $instance = $extender($instance, $this);
        }

        return $instance;
    }

    /**
     * Wrap a concrete class name string in a factory Closure.
     * Leaves Closures untouched.
     */
    private function normaliseFactory(Closure|string $factory): Closure
    {
        if ($factory instanceof Closure) {
            return $factory;
        }

        // String class name — wrap in a factory that auto-wires it
        return fn(Container $c): object => $c->make($factory);
    }

    /**
     * Return a ReflectionFunctionAbstract for any callable form.
     *
     * Handles: Closure, 'function_name', [object/class, 'method'], invokable objects.
     *
     * @throws ReflectionException
     */
    private function reflectCallable(callable $callable): \ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure || is_string($callable)) {
            return new \ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            [$target, $method] = $callable;
            return new \ReflectionMethod($target, $method);
        }

        // Invokable object
        return new \ReflectionMethod($callable, '__invoke');
    }
}