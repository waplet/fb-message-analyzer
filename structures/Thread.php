<?php

namespace w\MessageParser\Structures;

class Thread
{
    /**
     * @var string[]
     */
    public $participants = [];

    /**
     * @var Message[]
     */
    public $messages = [];
}