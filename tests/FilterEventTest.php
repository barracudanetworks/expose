<?php

namespace Expose;

use PHPUnit\Framework\TestCase;

class FilterEventTest extends TestCase
{
    public function testEvent()
    {
        $filters = [
            (new Filter())
            ->setId(1)
            ->setDescription('foo')
            ->setImpact(5)
            ->setRule('bar')
            ->setTags(['bif', 'fif']),
            (new Filter())
            ->setId(2)
            ->setDescription('foo2')
            ->setImpact(15)
            ->setRule('bar2')
            ->setTags(['bif2', 'fif2']),
        ];
        $tst = new FilterEvent($filters);

        $this->assertEquals($filters, $tst->getFilters());
        $this->assertStringContainsString('0000', $tst->getRunTime());
        $this->assertEquals(20, $tst->getTotalImpact());
    }
}
