<?php

namespace Anonymous\SimpleDi;


interface InvokerInterface
{

    public function call($callable, array $arguments = []);
    public function injectOn($callable, array $arguments = []);

}