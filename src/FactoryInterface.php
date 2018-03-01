<?php

namespace Anonymous\SimpleDi;


/**
 * Interface FactoryInterface
 * @package Anonymous\SimpleDi
 * @author Anonymous PHP Developer <anonym.php@gmail.com>
 */
interface FactoryInterface
{

    public function make($id, array $arguments = [], $recreate = false);
    public function instantiate($id, array $arguments = [], $instanceOf = null);

}