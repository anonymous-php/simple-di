<?php

namespace Anonymous\SimpleDi;


use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}