<?php

namespace Anonymous\SimpleDi;


interface ContainerInterface extends \Psr\Container\ContainerInterface
{

    public function set($id, $value);

}