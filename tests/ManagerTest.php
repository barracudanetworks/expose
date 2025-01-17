<?php

namespace Expose;

use Expose\MockListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ManagerTest extends TestCase
{
    /** @var Manager */
    private $manager = null;
    private $sampleFilters = array(
        array(
            "id" => "2",
            "rule" => "testmatch[0-9]+",
            "description" => "hard-coded match string",
            "tags" => array('test', 'sample'),
            "impact" => 2
        ),
    );

    public function setUp(): void
    {
        $filters = new FilterCollection();
        $this->manager = new Manager($filters, $this->createStub(LoggerInterface::class));
    }

    public function executeFilters($data, $queue = false, $notify = false)
    {
        $filterCollection = new FilterCollection();
        $filterCollection->setFilterData($this->sampleFilters);

        $logger = $this->createStub(LoggerInterface::class);
        $manager = new Manager($filterCollection, $logger);
        $manager->setConfig(array('test' => 'foo'));
        $manager->run($data);

        return $manager;
    }

    /**
     * Test that the getter and setter for assigning filters
     *     works correctly
     *
     * @covers \Expose\Manager::getFilters
     * @covers \Expose\Manager::setFilters
     */
    public function testGetSetFilters()
    {
        $filters = new FilterCollection();
        $filters->setFilterData($this->sampleFilters);

        $this->manager->setFilters($filters);

        $this->assertEquals(
            $filters,
            $this->manager->getFilters()
        );
    }

    /**
     * Test hte getter/setter for assigning data
     *
     * @covers \Expose\Manager::getData
     * @covers \Expose\Manager::setData
     */
    public function testGetSetData()
    {
        $data = array('foo' => 'bar');

        $this->manager->setData($data);
        $getData = $this->manager->getData();
        $this->assertTrue(
            $getData instanceof DataCollection
        );
    }

    /**
     * Test the getter/setter for the overall impact value
     *
     * @covers \Expose\Manager::getImpact
     * @covers \Expose\Manager::setImpact
     */
    public function testGetSetImpact()
    {
        $impact = 12;
        $this->manager->setImpact($impact);
        $this->assertEquals(
            $impact,
            $this->manager->getImpact()
        );
    }

    /**
     * Test a successful (found) execution of the filters
     *
     * @covers \Expose\Manager::run
     * @covers \Expose\Manager::getImpact
     * @covers \Expose\Manager::getReports
     */
    public function testRunSuccess()
    {
        $data = array(
            'POST' => array(
                'foo' => 'testmatch1'
            )
        );
        $manager = $this->executeFilters($data);

        $this->assertEquals($manager->getImpact(), 2);
        $this->assertEquals(count($manager->getReports()), 1);
    }

    /**
     * Test a successful (found) execution of the filters
     *
     * @covers \Expose\Manager::run
     * @covers \Expose\Manager::getImpact
     * @covers \Expose\Manager::getReports
     */
    public function testRunSuccessWithImpactLimit()
    {
        $data = array(
            'POST' => array(
                'foo' => 'testmatch1',
                'bar' => 'testmatch1'
            )
        );

        $filterCollection = new FilterCollection();
        $filterCollection->setFilterData($this->sampleFilters);

        $logger = $this->createStub(LoggerInterface::class);
        $manager = new Manager($filterCollection, $logger);
        $manager->setImpactLimit(1);
        $manager->setConfig(array('test' => 'foo'));
        $manager->run($data);

        $this->assertEquals($manager->getImpact(), 2);
        $this->assertEquals(count($manager->getReports()), 1);
    }

    /**
     * Test the use of the "export" method
     *     Loopback just returns the data back, no formatting
     *
     * @covers \Expose\Manager::run
     * @covers \Expose\Manager::export
     */
    public function testRunExportFound()
    {
        $data = array(
            'POST' => array(
                'foo' => 'testmatch1'
            )
        );
        $manager = $this->executeFilters($data);

        $export = $manager->export('loopback');
        $this->assertEquals(count($export), 1);

        $report = array_shift($export);
        $this->assertTrue($report instanceof Report);
    }

    /**
     * Test the null response when the export type isn't found
     *
     * @covers \Expose\Manager::export
     */
    public function testRunExportNotFound()
    {
        $data = array(
            'POST' => array(
                'foo' => 'testmatch1'
            )
        );
        $manager = $this->executeFilters($data);

        $export = $manager->export('notvalid');
        $this->assertNull($export);
    }

    /**
     * Test the getter/setter for exceptions to processing
     *
     * @covers \Expose\Manager::setException
     * @covers \Expose\Manager::isException
     * @covers \Expose\Manager::getExceptions
     */
    public function testGetSetException()
    {
        $this->manager->setException('testme');
        $this->assertTrue($this->manager->isException('testme'));

        $exceptions = $this->manager->getExceptions();
        $this->assertTrue(in_array('testme', $exceptions));
    }

    /**
     * Test the getter/setter for restrictions
     *
     * @covers \Expose\Manager::setRestriction
     * @covers \Expose\Manager::getRestrictions
     */
    public function testGetSetRestriction()
    {
        $restriction = 'POST.bar.testing';
        $this->manager->setRestriction($restriction);
        $this->assertEquals(
            $this->manager->getRestrictions(),
            array($restriction)
        );
    }

    /**
     * Test the getter/setter for the log resource/table
     *
     * @covers \Expose\Manager::setLogResource
     * @covers \Expose\Manager::getLogResource
     */
    public function testGetSetLogResource()
    {
        $resource = 'logs';
        $this->manager->setLogResource($resource);
        $this->assertEquals(
            $this->manager->getLogResource(),
            $resource
        );
    }

    /**
     * Test the getter/setter for the log database option
     *
     * @covers \Expose\Manager::setLogDatabase
     * @covers \Expose\Manager::getLogDatabase
     */
    public function testGetSetLogDatabase()
    {
        $databaseName = 'expose';
        $this->manager->setLogDatabase($databaseName);
        $this->assertEquals(
            $this->manager->getLogDatabase(),
            $databaseName
        );
    }

    /**
     * Test the setup of the config based on an array (not a file)
     *
     * @covers \Expose\Manager::setConfig
     * @covers \Expose\Manager::getConfig
     * @covers \Expose\Config::toArray
     */
    public function testSetupConfigArray()
    {
        $settings = array(
            'test' => 'foo'
        );
        $this->manager->setConfig($settings);
        $config = $this->manager->getConfig();
        $this->assertEquals(
            $config->toArray(),
            $settings
        );
    }

    /**
     * Test that a field marked as an exception is ignored
     *
     * @covers \Expose\Manager::setException
     * @covers \Expose\Manager::run
     */
    public function testExceptionIsIgnored()
    {
        $filterCollection = new FilterCollection();
        $filterCollection->setFilterData($this->sampleFilters);

        $logger = $this->createStub(LoggerInterface::class);
        $manager = new Manager($filterCollection, $logger);
        $manager->setConfig(array('test' => 'foo'));
        $manager->setException('POST.foo');
        $manager->setException('POST.bar.baz');

        $data = array(
            'POST' => array(
                'foo' => 'testmatch1',
                'bar' => array(
                    'baz' => 'testmatch2'
                )
            )
        );

        $manager->run($data);
        $this->assertEquals($manager->getImpact(), 0);
    }

    /**
     * Test that a field marked as an exception based on a regex wildcard is ignored
     *
     * @covers \Expose\Manager::setException
     * @covers \Expose\Manager::run
     */
    public function testExceptionWildcardIsIgnored()
    {
        $filterCollection = new FilterCollection();
        $filterCollection->setFilterData($this->sampleFilters);

        $logger = $this->createStub(LoggerInterface::class);
        $manager = new Manager($filterCollection, $logger);
        $manager->setConfig(array('test' => 'foo'));
        $manager->setException('POST.foo[0-9]+');

        $data = array(
            'POST' => array(
                'foo1234' => 'testmatch1'
            )
        );

        $manager->run($data);
        $this->assertEquals($manager->getImpact(), 0);
    }

    public function testThresholdLowerThenImpact() {

        $filter = new Filter();
        $filter->setImpact(100);

        $collection   = new FilterCollection();
        $collection->addFilter($filter);

        /** @var Manager|MockObject $manager_mock */
        $manager_mock = $this->getMockBuilder('\\Expose\\Manager')
            ->setConstructorArgs(array($collection, $this->createStub(LoggerInterface::class)))
            ->setMethods(array('dispatch'))
            ->getMock();

        $manager_mock
           ->expects($this->once())
           ->method('dispatch')
           ->with(new FilterEvent(array($filter)));

        $manager_mock->setThreshold(7);
        $manager_mock->run(array('test' => 'test'), false, true);
    }

    public function testThresholdHigherThenImpact() {
        $filter = new Filter();
        $filter->setImpact(5);

        $collection   = new FilterCollection();
        $collection->addFilter($filter);

        /** @var Manager|MockObject $manager_mock */
        $manager_mock = $this->getMockBuilder('\\Expose\\Manager')
            ->setConstructorArgs(array($collection, $this->createStub(LoggerInterface::class)))
            ->getMock();

        $manager_mock
            ->expects($this->never())
            ->method('dispatch');

        $manager_mock->setThreshold(100);
        $manager_mock->run(array('test' => 'test'));
    }

    public function testDispatch()
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
        $listenerProvider = $this->createStub(ListenerProviderInterface::class);
        $listenerProvider->method('getListenersForEvent')->willReturn([
            new MockListener(),
            new MockListener(),
            new MockListener(),
        ]);
        $this->manager->setListenerProvider($listenerProvider);

        $event = new FilterEvent($filters);
        $rtn = $this->manager->dispatch($event);
        $this->assertEquals($event, $rtn);
    }

    public function testCache()
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $this->manager->setCache($cache);
        $sig = "3d019fcda1f32b27680aeeb574b55541";
        $data = ['test' => 'test'];
        $rtn = $this->manager->run($data);
        $this->assertTrue($rtn);
        $rtn2 = $cache->get($sig);
        $this->assertEquals([], $rtn2);
    }
}
