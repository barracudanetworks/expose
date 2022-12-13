<?php

namespace Expose;

use ReturnTypeWillChange;

/**
 * Mock MongoCursor used for testing
 */
class MockMongoCursor implements \Iterator
{
    private $data = array();
    private $position = 0;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function __call($name, $args)
    {
        return $this;
    }

    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->data[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
}
