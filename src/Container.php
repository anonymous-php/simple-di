<?php

namespace Anonymous\SimpleDi;


/**
 * Dependency Resolving and Injection Container
 * @package Anonymous\SimpleDi
 * @author Anonymous PHP Developer <anonym.php@gmail.com>
 */
class Container implements ContainerInterface, FactoryInterface, InvokerInterface, \ArrayAccess
{

    protected $definitions = [];
    protected $resolved = [];
    protected $reflection = [];

    protected $useAutowiring = true;
    protected $resolveClosures = true;


    /**
     * Container constructor
     * @param array $definitions
     * @param bool $compatibilityMode
     */
    public function __construct(array $definitions = [], $useAutowiring = false, $resolveClosures = true)
    {
        $this->definitions = $definitions;
        $this->useAutowiring = $useAutowiring;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        return $this->resolve($id);
    }

    /**
     * Gets raw definition
     * @param $id
     * @return mixed
     * @throws NotFoundException
     */
    public function getRaw($id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException("There is no definition for '{$id}'");
        }

        return $this->definitions[$id];
    }

    /**
     * @inheritdoc
     */
    public function has($id)
    {
        return is_string($id) && array_key_exists($id, $this->definitions);
    }

    /**
     * Sets or replaces definitions
     * @param $id
     * @param $value
     */
    public function set($id, $value)
    {
        $this->definitions[$id] = $value;
        unset($this->resolved[$id], $this->reflection[$id]);
    }

    /**
     * Resolves and creates new instance on each call and checks instance's type if type provided
     * @param $id
     * @param array $arguments
     * @param null $instanceOf
     * @return mixed|string
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    public function instantiate($id, array $arguments = [], $instanceOf = null)
    {
        $definition = $this->has($id) ? $this->getRaw($id) : $id;

        if ($definition instanceof \Closure) {
            return $this->resolved[$id] = $definition = call_user_func_array(
                $definition,
                $this->getInjections($definition, '__invoke', [], $id)
            );
        }

        if (is_string($definition) && class_exists($definition)) {
            // Create an instance of the existing class
            $injections = $this->getInjections($definition, '__construct', $arguments);
            $definition = new $definition(...$injections);
        }

        // Check for instance of specified type
        if (is_object($definition) && ($instanceOf === null || $definition instanceof $instanceOf)) {
            return $this->resolved[$id] = $definition;
        }

        throw new FactoryException("Unresolvable dependency '{$id}' or type mismatch");
    }

    /**
     * Creates an instance or gets it from cache of already resolved
     * @param $id
     * @param array $arguments
     * @param bool $recreate
     * @return mixed|string
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    public function make($id, array $arguments = [], $recreate = false)
    {
        // Check cache of already resolved
        if (!$recreate && array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        return $this->instantiate($id, $arguments);
    }

    /**
     * Injects argument to method of instance or resolves callable if just class name provided
     * @param $callable
     * @param array $arguments
     * @return mixed
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    public function call($callable, array $arguments = [])
    {
        // No static calls are possible
        if (is_string($callable) && strpos($callable, '::') !== false) {
            $callable = explode('::', $callable, 2);
        }

        if (is_array($callable) && count($callable) >= 2) {
            $object = array_shift($callable);
            $method = array_shift($callable);

            if (is_string($object)) {
                // Create or get an instance from cache if name of class provided
                $object = $this->make($object);
            }

            return $this->injectOn([$object, $method], $arguments);
        }

        if ($callable instanceof \Closure) {
            return $this->injectOn($callable, $arguments);
        }

        if (is_string($callable) && method_exists($callable, '__invoke')) {
            return $this->call([$callable, '__invoke'], $arguments);
        }

        throw new FactoryException("Unresolvable dependency");
    }

    /**
     * Injects dependencies to the method of instance or closure
     * @param $callable
     * @param array $arguments
     * @return mixed
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    public function injectOn($callable, array $arguments = [])
    {
        if (is_array($callable) && count($callable) >= 2) {
            $array = $callable;
            $callable = array_shift($array);
            $method = array_shift($array);
        }

        if ($callable instanceof \Closure) {
            $method = '__invoke';
        }

        // Method works only with instance, no resolving provides here
        if (empty($method) || !is_object($callable) || !method_exists($callable, $method)) {
            throw new InvokerException('Invalid object or undefined method provided');
        }

        $injections = $this->getInjections($callable, $method, $arguments);

        return call_user_func_array([$callable, $method], $injections);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @inheritdoc
     * @throws NotFoundException
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        unset(
            $this->definitions[$offset],
            $this->resolved[$offset],
            $this->reflection[$offset]
        );
    }

    /**
     * Toggle autowiring
     * @param bool $autowiring
     */
    public function useAutowiring($autowiring = true)
    {
        $this->useAutowiring = $autowiring;
    }

    /**
     * Toggle closures resolving
     * @param bool $resolveClosures
     */
    public function resolveClosures($resolveClosures = true)
    {
        $this->resolveClosures = $resolveClosures;
    }

    /**
     * Gets ordered method/function arguments and injects the missing
     * @param string|object $definition
     * @param string $method
     * @param array $arguments
     * @param string $closureCacheKey
     * @return array
     * @throws InvokerException
     * @throws FactoryException
     * @throws NotFoundException
     */
    protected function getInjections($definition, $method, array $arguments = [], $closureCacheKey = null)
    {
        $injections = [];
        $parameters = $this->getReflectionParameters($definition, $method, $closureCacheKey);

        if (!$parameters) {
            return $injections;
        }

        // Different behaviors in different cases
        $isArgumentsAssoc = $this->isArrayAssoc($arguments);

        // Add self to the array of arguments
        $arguments[] = $this;

        foreach ($parameters as $index => $parameter) {
            /** @var \ReflectionParameter $parameter */
            if ($isArgumentsAssoc && array_key_exists($parameter->name, $arguments)) {
                // Argument matched by name
                $injection = $arguments[$parameter->name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Default value, - yes it's lazy
                $injection = $parameter->getDefaultValue();
            } elseif ($parameter->getClass()) {
                // Is there an addition of specified type
                $argument = $this->getTypedArgument($arguments, $parameter->getClass()->name);
                // Try to resolve dependency
                $injection = $argument !== null ? $argument : $this->make($parameter->getClass()->name);
            } else {
                throw new FactoryException("Unresolvable dependency '{$parameter->name}'");
            }

            $injections[] = $injection;
        }

        return $injections;
    }

    /**
     * Checks array is associative (not only numeric keys)
     * @param array $array
     * @return bool
     */
    protected function isArrayAssoc(array $array)
    {
        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets and caches reflection parameters for the specified method
     * @param string|object $callable
     * @param string $method
     * @param string $closureCacheKey
     * @return \ReflectionParameter[]
     * @throws InvokerException
     */
    protected function getReflectionParameters($callable, $method, $closureCacheKey = null)
    {
        $className = is_object($callable) ? get_class($callable) : $callable;

        // Check cache for the resolved reflection
        if (isset($this->reflection[$className][$method])) {
            return $this->reflection[$className][$method];
        }

        // ReflectionObject in case of closure and ReflectionClass in case of constructor,
        // doesn't matter for other cases
        try {
            // ReflectionClass throws an exception in case of class absence
            $reflectionObject = is_object($callable)
                ? new \ReflectionObject($callable)
                : new \ReflectionClass($className);

            // getMethod throws an exception in case of method absence
            $reflectionMethod = $method == '__construct'
                ? $reflectionObject->getConstructor()
                : $reflectionObject->getMethod($method);
        } catch (\ReflectionException $e) {
            throw new InvokerException("There is no class '{$className}' or callable method '{$method}'", 0, $e);
        }

        $reflectionParameters = $reflectionMethod
            ? $reflectionMethod->getParameters()
            : [];

        // In case of definition it will be added to the cache of resolved,
        // in other cases we don't know how to cache it
        if (!$callable instanceof \Closure || $closureCacheKey) {
            $this->reflection[$closureCacheKey ?: $className][$method] = $reflectionParameters;
        }

        return $reflectionParameters;
    }

    /**
     * Gets injectable additions, this container for example
     * @param array $arguments
     * @param $className
     * @return mixed|null
     */
    protected function getTypedArgument(array $arguments, $className)
    {
        foreach ($arguments as $index => $argument) {
            // Additions can't have assoc keys
            if (is_numeric($index) && $argument instanceof $className) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * Resolve definition
     * @param $id
     * @return mixed|string
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    protected function resolve($id)
    {
        if ($this->useAutowiring) {
            return $this->resolveAutowire($id);
        }

        $definition = $this->getRaw($id);

        if ($this->resolveClosures && $definition instanceof \Closure) {
            $definition = call_user_func_array(
                $definition,
                $this->getInjections($definition, '__invoke', [], $id)
            );
        }

        $this->resolved[$id] = $definition;

        return $this->resolved[$id];
    }

    /**
     * Resolve definition with auto-wiring
     * @param $id
     * @return mixed|string
     * @throws FactoryException
     * @throws InvokerException
     * @throws NotFoundException
     */
    protected function resolveAutowire($id)
    {
        $definition = $id;

        if ($this->has($definition)) {
            $definition = $this->getRaw($definition);
        }

        if (is_string($definition) && class_exists($definition)) {
            return $this->make($definition);
        }

        if ($definition instanceof \Closure) {
            $definition = call_user_func_array(
                $definition,
                $this->getInjections($definition, '__invoke', [], $id)
            );
        } elseif (!$this->has($id)) {
            throw new NotFoundException("There is no definition for '{$id}'");
        }

        $this->resolved[$id] = $definition;

        return $this->resolved[$id];
    }

}