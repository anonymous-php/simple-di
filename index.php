<?php

require __DIR__ . '/vendor/autoload.php';

class A {}
class B extends A {};

$container = new \Anonymous\SimpleDi\Container([
    A::class => B::class,
]);

var_dump($container->get(A::class));