<?php

namespace Fazland\Rabbitd\Events;

class Events
{
    const PRE_START = 'application.pre_start';
    const START = 'application.start';
    const STOP = 'application.stop';
    const EVENT_LOOP = 'application.loop';

    const CHILD_START = 'child.start';
    const CHILD_EVENT_LOOP = 'child.loop';
}