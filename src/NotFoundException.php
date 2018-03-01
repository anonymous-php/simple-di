<?php

namespace Anonymous\SimpleDi;


use Psr\Container\NotFoundExceptionInterface;

/**
 * Common NotFoundException for case of absent definition
 * @package Anonymous\SimpleDi
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}