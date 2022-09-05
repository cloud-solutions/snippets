<?php

namespace Survey\Exporter;

use Survey\Exporter\ExportPlugin\AbstractExport;
use Survey\PluginManager\PluginManager;
use Survey\PluginManager\PluginManagerFactory;


class Exports
{
    public PluginManager $pluginManager;

    public function __construct(PluginManagerFactory $pmFactory)
    {
        $this->pluginManager = $pmFactory->create(__NAMESPACE__ . '\ExportPlugin');
    }

    public function getPlugin(string $pluginName, array $pluginConstructorParams = [], string $pluginKey = null): AbstractExport
    {
        return $this->pluginManager->getPlugin($pluginName, $pluginConstructorParams, $pluginKey);
    }
}