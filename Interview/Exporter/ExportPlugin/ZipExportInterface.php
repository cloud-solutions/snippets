<?php

namespace Survey\Exporter\ExportPlugin;

use Survey\Db\Entity\AbstractEntity;

interface ZipExportInterface
{
    /**
     * Returns the entity class to retrieve the entities to loop to generate the different archived files
     *
     * @return string
     */
    public function getEntityClassForZip(): string;

    /**
     * It returns the name of the files in the zipped archive
     *
     * @param AbstractEntity $entity
     *
     * @return string
     */
    public function getArchivedFilename(AbstractEntity $entity): string;
}