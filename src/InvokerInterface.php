<?php

namespace Anonymous\SimpleDi;


/**
 * Interface InvokerInterface
 * @package Anonymous\SimpleDi
 * @author Anonymous PHP Developer <anonym.php@gmail.com>
 */
interface InvokerInterface
{

    public function call($callable, array $arguments = []);
    public function injectOn($callable, array $arguments = []);

}