<?php

namespace Survey\Controller\Plugin;


use Survey\Di\DiWrapper;

/**
 * Controller plugin to access Di via a wrapper with type hinting.
 */
class Di extends AbstractPlugin
{

    public function __construct(protected DiWrapper $diWrapper)
    {
    }

    public function __invoke(): DiWrapper
    {
        return $this->diWrapper;
    }
}