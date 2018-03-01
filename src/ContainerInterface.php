<?php

namespace Anonymous\SimpleDi;


/**
 * Interface ContainerInterface
 * @package Anonymous\SimpleDi
 * @author Anonymous PHP Developer <anonym.php@gmail.com>
 */
interface ContainerInterface extends \Psr\Container\ContainerInterface
{

    public function set($id, $value);

}