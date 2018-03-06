# Simple DI

This library provides the possibility to create instances of classes with auto-wiring of their arguments and to inject 
dependencies to called methods. Actually it's more about injection than definitions and the library works great (I hope)
with no definitions as well. Main class has the name Container by the coincidence and you will understand why if you 
will continue to read this documentation.   

### History

I like PHP-DI very much but when I started new project I understood it needs another approach of determining it's 
definitions and dependencies. I wanted to have the simplest as possible syntax of definitions and powerful, but not 
complex at the same time, mechanism of injection. If I had decided to extend PHP-DI it could been an adventure full of 
new experience and a lot of code with problems of maintenance in a result. So I just wrote my own DI.

### Peculiarities

I didn't want to wrap any definition to unnecessary functions which just marks this definition as an instantiable class. 
I already know what is it while write a code and I want to decide how to use this information in my application. 

My colleague asked me about the primitives injection in my library. I don't think is a good idea. Just make the 
dependency for the Container and get the value you need. Keep this type of values under control by yourself.  

## Installation

```
composer require anonymous-php/simple-di
```

## Methods

Сlass `Container` implements `\Psr\Container\ContainerInterface` interface so it has two methods `has($id)` which works 
as usual and `get($id)` which can has the behavior different from your preferred library. There is the method 
`set($id, $value)` which does exactly the same it means.

### get($id)

The method responds with a primitive with the only one exception - Closures. In case of Closure the library resolves it 
with the injection of it's dependencies. The Closure may to return any primitive or an instance of any class you wish. 
This method caches results of the resolving.

```php
<?php

$container = new \Anonymous\SimpleDi\Container([
    'primitive' => 42,
    'wrapped-primitive' => function (\Psr\Container\ContainerInterface $c) {
        return (string)$c->get('primitive');
    },
]);

var_dump($container->get('primitive'), $container->get('wrapped-primitive'));

// int(42)
// string(2) "42"
```

### instantiate($id, array $arguments = [], $instanceOf = null)

This method creates an instance of the certain class. It tries to resolve the definition or instantiate provided class 
in case of definition absence. The method creates the new one instance on each call. In case of closure as argument 
`instantiate` resolves it each time too. 

```php
<?php

interface I {}
class A implements I {}
class B implements I {}

$container = new \Anonymous\SimpleDi\Container([
    I::class => A::class,
    B::class => function () {
        return new B();
    },
]);

var_dump(
    $container->instantiate(I::class),
    $container->instantiate(A::class),
    $container->instantiate(B::class, [], I::class)
);

$container->instantiate('C');

/*

object(A)#4 (0) {
}
object(A)#5 (0) {
}
object(B)#7 (0) {
}
PHP Fatal error:  Uncaught Anonymous\SimpleDi\FactoryException Unresolvable dependency 'C'

*/
```

### make($id, array $arguments = [], $recreate = false)

Almost the same as previous but with the cache. It means you will get the same instance on each method call. If method
`make` will be called after `instantiate` using the same `$id` you will get already cached instance of class you 
provided.

```php
<?php

class A {}

$container = new \Anonymous\SimpleDi\Container();

var_dump(
    $container->instantiate(A::class),
    $container->make(A::class)
);

/*

object(A)#2 (0) {
}
object(A)#2 (0) {
}

*/
```

### injectOn($callable, array $arguments = [])

Injects arguments to the method of provided **instance** and calls it. If array of arguments doesn't contain all variables 
which called method wait for the library tries to resolve them. Notice: Closure is an object with the method `__invoke` 
so you can use `injectOn` on it.

```php
<?php

class A {
    public function e($v) {
        return $v;
    }
}

class B { 
    public function strtoupper($v) {
        return strtoupper($v);
    }
}

$container = new \Anonymous\SimpleDi\Container();

var_dump(
    $container->injectOn([new A(), 'e'], ['v' => 'value1']),
    $container->injectOn(function (B $b, $v) { return $b->strtoupper($v); }, ['v' => 'value2'])
);

// string(5) "value1"
// string(5) "VALUE2"
```

### call($callable, array $arguments = [])

Almost the same as `injectOn` but provides the possibility to resolve and instantiate provided class.

```php
<?php

interface Filter
{
    public function __invoke($v);
}

class Upper implements Filter
{
    public function __invoke($v)
    {
        return strtoupper($v);
    }
}

interface Output
{
    public function __invoke($v);
}

class StdOutput implements Output
{
    public function __invoke($v)
    {
        echo $v, PHP_EOL;
    }
}

class Printer
{
    protected $filter;

    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }

    public function out(Output $output, $value)
    {
        $filter = $this->filter;
        $output($filter instanceof Filter ? $filter($value) : $value);
    }
}

$container = new \Anonymous\SimpleDi\Container([
    Output::class => StdOutput::class,
    Filter::class => Upper::class,
]);

$container->call([Printer::class, 'out'], ['value' => 'Text to print 1']);
$container->call('Printer::out', ['value' => 'Text to print 2']);

// TEXT TO PRINT 1
// TEXT TO PRINT 2
```

### Todo:
* Documentation
* Tests
* Performance measurement