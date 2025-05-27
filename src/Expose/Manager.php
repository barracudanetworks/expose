<?php

namespace Expose;

use ArrayIterator;
use Expose\Converter\Converter;
use Expose\Exception\LoggerNotDefined;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class Manager implements LoggerAwareInterface, EventDispatcherInterface
{
    /**
     * Data to run the filter validation rules on
     * @var array
     */
    private $data = null;

    /**
     * Set of filters to execute
     * @var FilterCollection
     */
    private $filters = null;

    /**
     * Overall impact score of the filter execution
     * @var integer
     */
    private $impact = 0;

    /**
     * If set, stop running filters when reached
     * @var integer
     */
    private $impactLimit = 0;

    /**
     * Report results from the filter execution
     * @var array
     */
    private $reports = array();

    /**
     * Names of varaibles to ignore (exceptions to the rules)
     * @var array
     */
    private $exceptions = array();

    /**
     * Data "paths" to restrict checking to
     * @var array
     */
    private $restrctions = array();

    private LoggerInterface $logger;

    /**
     * Configuration object
     * @var \Expose\Config
     */
    private $config = null;

    private int $threshold = 0;

    private ?CacheInterface $cache = null;
    private ?ListenerProviderInterface $listenerProvider = null;

    /**
     * Init the object and assign the filters
     *
     * @param FilterCollection $filters Set of filters
     */
    public function __construct(
        FilterCollection         $filters,
        LoggerInterface $logger = null
    )
    {
        $this->setFilters($filters);
        if (!is_null($logger)) {
            $this->setLogger($logger);
        }
    }

    /**
     * Run the filters against the given data
     *
     * @param array $data Data to run filters against
     */
    public function run(array $data)
    {
        $this->getLogger()->info('Executing on data '.md5(print_r($data, true)));

        $this->setData($data);
        $data = $this->getData();

        // try to clean up standard filter bypass methods
        $converter = new Converter;
        foreach ($data as $key => $datum){
          if (!is_array($datum)){
            $data[$key] = $converter->runAllConversions($datum);
          }
        }

        $path = array();
        $filterMatches = $this->runFilters($data, $path);
        $impact = $this->impact;

        // Check our threshold to see if we even need to send
        $threshold = $this->getThreshold();

        if ($impact >= $threshold) {
            $this->dispatch(new FilterEvent($filterMatches));
        }
        return true;
    }

    /**
     * Run through the filters on the given data
     *
     * @param array $data Data to check
     * @param array $path Current "path" in the data
     * @param integer $lvl Current nesting level
     * @return Filter[] Set of filter matches
     */
    public function runFilters($data, $path, $lvl = 0)
    {
        $filterMatches = array();
        $restrictions = $this->getRestrictions();
        $sig = md5(print_r($data, true));

        if ($this->cache !== null && $this->cache->has($sig)) {
            return $this->cache->get($sig);
        }

        $data = new ArrayIterator($data);
        $data->rewind();
        while($data->valid() && !$this->impactLimitReached()) {
            $index = $data->key();
            $value = $data->current();
            $data->next();

            if (count($path) > $lvl) {
                $path = array_slice($path, 0, $lvl);
            }

            $path[] = $index;

            // see if it's an exception
            if ($this->isException(implode('.', $path))) {
                $this->getLogger()->info('Exception found on '.implode('.', $path));
                continue;
            }

            if (is_array($value)) {
                $l = $lvl+1;
                $filterMatches = array_merge(
                    $filterMatches,
                    $this->runFilters($value, $path, $l)
                );
                continue;
            }

            $p = implode('.', $path);

            // See if we have restrictions & if the path matches
            if (!empty($restrictions) && !in_array($p, $restrictions)) {
                $this->getLogger()->info(
                    'Restrictions enabled, no match on path '.implode('.', $path),
                    array('restrictions' => $restrictions)
                );
                continue;
            }

            $filterMatches = array_merge(
                $filterMatches,
                $this->processFilters($value, $index, $path)
            );
        }

        if ($this->cache !== null) {
            $this->cache->set($sig, $filterMatches);
        }
        return $filterMatches;
    }

    /**
     * Runs value through all filters
     * @param $value
     * @param $index
     * @param $path
     */
    protected function processFilters($value, $index, $path)
    {
        $filterMatches = array();
        $filters = $this->getFilters();
        $filters->rewind();
        while($filters->valid() && !$this->impactLimitReached()) {
            $filter = $filters->current();
            $filters->next();
            if ($filter->execute($value) === true) {
                $filterMatches[] = $filter;
                $this->getLogger()->info(
                    'Match found on Filter ID '.$filter->getId(),
                    array($filter->toArray())
                );

                $report = new \Expose\Report($index, $value, $path);
                $report->addFilterMatch($filter);
                $this->reports[] = $report;

                $this->impact += $filter->getImpact();
            }
        }
        return $filterMatches;
    }

    /**
     * Tests if the impact limit has been reached
     *
     * @return bool
     */
    protected function impactLimitReached()
    {
        if ($this->impactLimit < 1) {
            return false;
        }

        $reached = $this->impact >= $this->impactLimit;
        if ($reached) {
            $this->getLogger()->info(
                'Reached Impact limit'
            );
        }
        return $reached;
    }

    /**
     * Sets the impact limit
     *
     * @param $value
     */
    public function setImpactLimit($value)
    {
        $this->impactLimit = (int) $value;
    }

    /**
     * Get the current set of reports
     *
     * @return array Set of \Expose\Reports
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * Get the current overall impact score
     *
     * @return integer Impact score
     */
    public function getImpact()
    {
        return $this->impact;
    }

    /**
     * Set the overall impact value of the execution
     *
     * @param integer $impact Impact value
     */
    public function setImpact($impact)
    {
        $this->impact = $impact;
    }

    /**
     * Set the source data for the execution
     *
     * @param array $data Data to validate
     */
    public function setData(array $data)
    {
        $this->data = new \Expose\DataCollection($data);
    }

    /**
     * Get the current source data
     *
     * @return array Source data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the filters for the current validation
     *
     * @param FilterCollection $filters Filter collection
     */
    public function setFilters(FilterCollection $filters)
    {
        $this->filters = $filters;
    }

    /**
     * Get the current set of filters
     *
     * @return FilterCollection Filter collection
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Add a variable name for an exception
     *
     * @param string $varName Variable name
     */
    public function setException($path)
    {
        $path = (!is_array($path)) ? array($path) : $path;
        $this->exceptions = array_merge($this->exceptions, $path);
    }

    /**
     * Get a list of all exceptions
     *
     * @return array Exception list
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * Add a path to restrict the checking to
     *
     * @param string|array $path Path(s) to add to the restrictions
     */
    public function setRestriction($path)
    {
        $path = (!is_array($path)) ? array($path) : $path;
        $this->restrctions = array_merge($this->restrctions, $path);
    }

    /**
     * Get the list of all current restrictions
     *
     * @return array Set of restrictions
     */
    public function getRestrictions()
    {
        return $this->restrctions;
    }

    /**
     * Get the log "resource" (Ex. database table name)
     * @return string Resouce name
     */
    public function getLogResource()
    {
        return $this->logResource;
    }

    /**
     * Set the log "resource" name
     * @param string $resourceName Resource name
     */
    public function setLogResource($resourceName)
    {
        $this->logResource = $resourceName;
    }

    /**
     * Get the logging database name
     * @return string Database name
     */
    public function getLogDatabase()
    {
        return $this->logDatabase;
    }

    /**
     * Set the logging database name
     * @param string $dbname Database name
     */
    public function setLogDatabase($dbname)
    {
        $this->logDatabase = $dbname;
    }

    /**
     * Test to see if a variable is an exception
     *     Checks can be exceptions, so we preg_match it
     *
     * @param string $path Variable "path" (Ex. "POST.foo.bar")
     * @return boolean Found/not found
     */
    public function isException($path)
    {
        $isException = false;
        foreach ($this->exceptions as $exception) {
            if ($isException === false) {
                if ($path === $exception || preg_match('/^'.$exception.'$/', $path) !== 0) {
                    $isException = true;
                }
            }
        }

        return $isException;
    }

    /**
     * Set the current instance's logger object
     *
     * @param object $logger PSR-3 compatible Logger instance
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the configuration for the object
     *
     * @param array|string $config Either an array of config settings
     *     or the path to the config file
     * @throws InvalidArgumentException If config file doesn't exist
     */
    public function setConfig($config)
    {
        if (is_array($config)) {
            $this->config = new Config($config);
        } else {
            // see if it's a file path
            if (is_file($config)) {
                $cfg = parse_ini_file($config, true);
                $this->config = new Config($cfg);
            } else {
                throw new InvalidArgumentException(
                    'Could not load configuration file '.$config
                );
            }
        }
    }

    /**
     * Get the configuration object/settings
     *
     * @return \Expose\Config object
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function setThreshold($threshold)
    {
        if (is_numeric($threshold) === false) {
            throw new InvalidArgumentException(
                'Invalid threshold "'.$threshold.'", must be numeric'
            );
        }
        $this->threshold = $threshold;
    }

    /**
     * Get the current threshold value
     *
     * @return integer Threshold value (numeric)
     */
    public function getThreshold()
    {
        return $this->threshold;
    }

    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Expose the current set of reports in the given format
     *
     * @param string $format Fromat for the export
     * @return mixed Report output (or null if the export type isn't found)
     */
    public function export($format = 'text')
    {
        $className = '\\Expose\\Export\\'.ucwords(strtolower($format));
        if (class_exists($className)) {
            $export = new $className($this->getReports());
            return $export->render();
        }
        return null;
    }

    public function setListenerProvider(ListenerProviderInterface $listenerProvider): void
    {
        $this->listenerProvider = $listenerProvider;
    }

    public function dispatch(object $event): object
    {
        if (!is_null($this->listenerProvider)) {
            foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
                $listener($event);
            }
        }
        return $event;
    }
}
