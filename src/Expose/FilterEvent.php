<?php

namespace Expose;
class FilterEvent
{
    /** @var Filter[] */
    private array $filters;
    private int $totalImpact = 0;

    /**
     * @var false|string
     */
    private $runTime;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->process();
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return int
     */
    public function getTotalImpact(): int
    {
        return $this->totalImpact;
    }

    /**
     * @return false|string
     */
    public function getRunTime()
    {
        return $this->runTime;
    }

    private function process()
    {
        foreach ($this->filters as $filter) {
            $this->totalImpact += $filter->getImpact();
        }
        $this->runTime = date('r');
    }
}
