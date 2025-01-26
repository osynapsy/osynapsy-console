<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Console;

use Osynapsy\Kernel\ConfigLoader;
use Osynapsy\Routing\Route;
use Osynapsy\Kernel;

/**
 * Description of Cron
 *
 * @author Pietro Celeste <p.celeste@osynapsy.net>
 */
class Cron
{
    private $argv;
    private $script;
    private $vendorDir;
    private $rootDir;
    private $appDirs = [];

    public function __construct($vendorDir, array $argv)
    {
        $this->vendorDir = $vendorDir;        
        $this->rootDir = realpath($this->vendorDir . '/../');
        $this->script = array_shift($argv);
        $this->argv = $argv;        
        $this->discoverOsyApplicationDirectories();
    }

    /**
     * Metodo che scopre i file di configurazione delle app registrate nei file istanza
     */
    protected function discoverOsyApplicationDirectories()
    {
        $instanceConfigurationDir = $this->rootDir. '/etc/';
        $d = dir($instanceConfigurationDir);
        do {
            $file = $d->read();
            $instanceFilePath = $instanceConfigurationDir . '/' . $file;
            $xml = $this->loadInstanceConfiguration($instanceFilePath);
            if (empty($xml)) {
                continue;
            }
            $appId = $xml->app->children()->getName();
            $appPath = $this->vendorDir . (!empty($xml->app->{$appId}->path) ? $this->appRelativePathFactory($appId, $xml->app->{$appId}->path) : '/'. str_replace('_', '/', $appId));            
            $this->appDirs[$instanceFilePath] = [$appId, realpath($appPath . '/etc')];
        } while ($file);        
        $d->close();
    }

    protected function appRelativePathFactory($appId, $appPath)
    {
        $relativePath = '/../' . $appPath. '/';
        $relativePath .= str_replace('_', '/', $appId) . '/';
        return $relativePath;
    }

    protected function loadInstanceConfiguration($instanceFilePath)
    {
        return is_file($instanceFilePath) ? simplexml_load_file($instanceFilePath) : false;
    }

    public function run()
    {        
        foreach($this->appDirs as $instanceFile => list($appId, $appDir)) {            
            $appConfiguration = $this->loadAppConfiguration($appDir . '/config.xml') ?: [];                        
            if (empty($appConfiguration['cron'])) {
                continue;
            }
            $cronJobs = $this->loadCronJobs($appConfiguration);            
            if (!empty($cronJobs)) {
                $this->exec($appId, $instanceFile, $cronJobs);
            }
        }
    }

    private function loadAppConfiguration($appConfFilePath)
    {        
        return (new ConfigLoader($appConfFilePath))->get();
    }

    private function loadCronJobs($appConfiguration)
    {
        return array_map(function($job) {
            $job['controller'] = $job['@value'];
            unset($job['@value']);
            return $job;
        }, $appConfiguration['cron']['job']);
    }

    private function exec($appId, $instanceFile, $appJobs)
    {
        $now = date('G:i');
        foreach($appJobs as $job) {            
            if (!empty($job['disabled']) || !$this->isExecutable($now, $job['schedules'] ?? $now)) {
                continue;
            }            
            $jobRoute = new Route($job['id'], null, $appId, $job['controller']);
            $kernel = new Kernel($instanceFile);
            $request = $kernel->requestFactory();             
            echo $kernel->runApplication($jobRoute, $request);
        }
    }

    private function isExecutable($now, $rawScheduler)
    {
        $scheduler = explode(',', $rawScheduler);
        $result = in_array($now, $scheduler);
        //var_dump($scheduler, $result, $now);
        return $result;
    }
}
