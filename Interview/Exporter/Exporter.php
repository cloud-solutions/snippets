<?php

namespace Survey\Exporter;

use Box\Spout\Common\Type;
use Survey\Config\Config;
use Survey\Db\Db;
use Survey\Exporter\ExportPlugin\AbstractExport;
use Survey\Exporter\ExportPlugin\ZipExportInterface;
use Survey\Stdlib\FileUtils;


class Exporter
{
    public const PARAM_INCLUDE_ENTITY_IDS = 'includeEntityIDs';    // int[]: optional, if specified include only the entity with that ID
    public const ZIP_FILE_EXT             = 'zip';

    public function __construct(protected Exports $exports, protected Db $db, protected Config $config)
    {
    }

    public function export(string $exportPluginName, array $params = []): void
    {
        $exportPlugin = $this->exports->getPlugin($exportPluginName);
        $exportPlugin->validateParams($params);
        $exportPlugin->export($params);
    }

    public function exportZippedData(string $exportPluginName, string $zipFileName, array $params = []): void
    {
        /** @var AbstractExport|ZipExportInterface $exportPlugin */
        $exportPlugin = $this->exports->getPlugin($exportPluginName);

        $zipFileName = sprintf(
            '%s-%s-%s.%s',
            $this->config->project->shortname,
            $zipFileName,
            date('d-M-Y'),
            self::ZIP_FILE_EXT
        );

        // Create empty zip directory.
        $zipPath = FileUtils::createZipFolder(
            sprintf('%s/%s', $this->config->directories->temp, $zipFileName)
        );

        $entities = $this->db->getEntities($exportPlugin->getEntityClassForZip());
        // Empty means include all entities
        $includeEntityIDs = $params[self::PARAM_INCLUDE_ENTITY_IDS] ?? [];
        // Write survey data files.
        foreach ($entities as $entity) {
            if (!empty($includeEntityIDs) && !in_array($entity->getId(), $includeEntityIDs)) {
                continue;
            }
            $params[AbstractExport::PARAM_FILE_EXT]  = $params[AbstractExport::PARAM_FILE_EXT] ?? Type::XLSX;
            $params[AbstractExport::PARAM_FILE_NAME] = sprintf(
                '%s/%s.%s',
                $zipPath,
                $exportPlugin->getArchivedFilename($entity),
                $params[AbstractExport::PARAM_FILE_EXT]
            );

            $params[AbstractExport::PARAM_ENTITY] = $entity;

            $this->export($exportPluginName, $params);
        }

        FileUtils::getZipAsStream($zipPath, $zipFileName, true);
    }
}
