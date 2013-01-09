<?php

namespace SONBase\Test;

use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\Mvc\MvcEvent;
use Doctrine\ORM\EntityManager;

class TestCase extends \PHPUnit_Framework_TestCase {

    /**
     * @var Zend\ServiceManager\ServiceManager
     */
    protected $serviceManager;

    /**
     * @var EntityManager
     */
    protected $em;
    protected $module;

    public function setup() {

        parent::setup();

        $config = include 'config/application.config.php';
        $config['module_listener_options']['config_static_paths'] = array(getcwd() . '/config/test.config.php');

        if (file_exists(__DIR__ . '/config/test.config.php')) {
            $moduleConfig = include __DIR__ . '/config/test.config.php';
            array_unshift($config['module_listener_options']['config_static_paths'], $moduleConfig);
        }

        $this->serviceManager = new ServiceManager(new ServiceManagerConfig(
                                isset($config['service_manager']) ? $config['service_manager'] : array()
                ));
        $this->serviceManager->setService('ApplicationConfig', $config);
        $this->serviceManager->setFactory('ServiceListener', 'Zend\Mvc\Service\ServiceListenerFactory');

        $moduleManager = $this->serviceManager->get('ModuleManager');
        $moduleManager->loadModules();
        $this->routes = array();
        foreach ($moduleManager->getModules() as $m) {
            if ($m <> "DoctrineModule" and $m <> "DoctrineORMModule" and $m <> "SONBase" and $m <> "DoctrineDataFixtureModule") {

                $moduleConfig = include __DIR__ . '/../../../../../../module/' . ucfirst($m) . '/config/module.config.php';
                if (isset($moduleConfig['router'])) {
                    foreach ($moduleConfig['router']['routes'] as $key => $name) {
                        $this->routes[$key] = $name;
                    }
                }
            }
        }
        $this->serviceManager->setAllowOverride(true);

        $this->application = $this->serviceManager->get('Application');
        $this->event = new MvcEvent();
        $this->event->setTarget($this->application);
        $this->event->setApplication($this->application)
                ->setRequest($this->application->getRequest())
                ->setResponse($this->application->getResponse())
                ->setRouter($this->serviceManager->get('Router'));

        $x = include(getcwd() . '/config/test.config.php');
        $dbName = $x['doctrine']['connection']['orm_default']['params']['dbname'];
        $this->em = $this->serviceManager->get('Doctrine\ORM\EntityManager');
        $this->createDatabase();
    }

    public function createDatabase() {

        if (file_exists('module/' . $this->module . '/db/create.sql')) {
            $sql = file('module/' . $this->module . '/db/create.sql');
            foreach ($sql as $s)
                $this->getEm()->getConnection()->exec($s);
        }
        
        $this->getEm()->getConnection()->exec('SET FOREIGN_KEY_CHECKS = 0;');
    }

    public function tearDown() {
        parent::tearDown();
        if (file_exists('module/' . $this->module . '/db/drop.sql')) {
            $sql = file('module/' . $this->module . '/db/drop.sql');
            foreach ($sql as $s)
                $this->getEm()->getConnection()->exec($s);
        }
    }

    public function getEm() {
        return $this->em = $this->serviceManager->get('Doctrine\ORM\EntityManager');
    }
}