<?php

require __DIR__ . '/vendor/autoload.php';

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
