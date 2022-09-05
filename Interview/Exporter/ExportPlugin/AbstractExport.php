<?php

namespace Survey\Exporter\ExportPlugin;

use Survey\Config\Config;
use Survey\Db\Db;
use Survey\Param\ParamValidateInterface;
use Survey\PluginManager\Plugin\IPlugin;
use Survey\PluginManager\Plugin\PluginTrait;


abstract class AbstractExport implements IPlugin, ParamValidateInterface
{
    use PluginTrait;

    public const PARAM_IS_EXPORT_FROM_USER = 'isExportFromUser';

    public const PARAM_FILTERS = 'filters';

    public const PARAM_ENCODING = 'encoding'; // string: optional, e.g. 'UTF-8', 'ISO-8859-15'
    public const ENCODING_UTF8  = 'UTF-8';

    public const PARAM_ENTITY = 'entity';

    public const PARAM_FILE_NAME = 'fileName';
    public const PARAM_FILE_EXT  = 'fileExt';

    protected string $encoding = self::ENCODING_UTF8;

    public function __construct(protected Db $db, protected Config $config)
    {
    }

    abstract public function export(array $params = []): void;

    protected function getEncoding(): string
    {
        return $this->encoding;
    }

    protected function getFileName(array $params = []): string
    {
        return $params[self::PARAM_FILE_NAME] ?? sprintf(
                '%s-%s-%s.%s',
                $this->config->project->shortname,
                strtolower(self::name()),
                date('d-M-Y'),
                $params[self::PARAM_FILE_EXT]
            );
    }
}