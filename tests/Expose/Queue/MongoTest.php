<?php

namespace Expose\Queue;

use MongoDB\Driver\Manager;
use PHPUnit\Framework\TestCase;

class MongoTest extends TestCase
{

    /**
     * @var Mongo
     */
    private $test;

    protected function setUp(): void
    {
        $this->test = new Mongo();
    }

    public function ztestGetAdapter()
    {
        $this->assertInstanceOf(Manager::class, $this->test->getAdapter());
    }

    /** @todo remove this */
    public function testDummy()
    {
        $this->assertTrue(true);
    }
}
