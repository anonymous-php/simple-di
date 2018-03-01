<?php

namespace Anonymous\SimpleDi;


interface FactoryInterface
{

    public function make($id, array $arguments = [], $recreate = false);
    public function instantiate($id, array $arguments = [], $instanceOf = null);

}