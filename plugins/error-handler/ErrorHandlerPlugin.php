<?php

namespace Plugin\Alekitto;

use Fazland\Rabbitd\Plugin\AbstractPlugin;

class ErrorHandlerPlugin extends AbstractPlugin
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'error-handler';
    }

}