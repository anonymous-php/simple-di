<?php

namespace Anonymous\SimpleDi;


use Psr\Container\ContainerExceptionInterface;

/**
 * Common ContainerException
 * @package Anonymous\SimpleDi
 * @author Anonymous PHP Developer <anonym.php@gmail.com>
 */
class ContainerException extends \Exception implements ContainerExceptionInterface {}