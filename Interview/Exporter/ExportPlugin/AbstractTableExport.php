<?php

namespace Survey\Exporter\ExportPlugin;

use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Doctrine\ORM\QueryBuilder;
use Survey\Constants\Environment;
use Survey\Db\Entity\AbstractEntity;
use Survey\Stdlib\DateUtils;


abstract class AbstractTableExport extends AbstractExport
{
    public const ENTITY_BATCH_SIZE = 500;

    public const PARAM_FILE_PATH = 'filePath';

    protected WriterInterface $writer;


    abstract public function getExportColNames(array $params = []): array;

    abstract public function populateRow(AbstractEntity $entity, array $row, array $params = []): array;

    abstract public function getEntitySelect(array $params = []): QueryBuilder;


    public function export(array $params = []): void
    {
        $filePath        = $this->getFileName($params);
        // possible types defined here \Box\Spout\Common\Type
        $this->writer = WriterEntityFactory::createWriter($params[self::PARAM_FILE_EXT]);
        // if it's a zip we need to write all the files in the temp folder
        // otherwise if it's a single file we wirte it as stream
        if ($this instanceof ZipExportInterface) {
            $this->writer->openToFile($filePath);
        } else {
            $this->writer->openToBrowser($filePath);
        }
        if ($this->writer instanceof CSV\Writer) {
            $this->writer->setFieldDelimiter("\t");
        }
        $this->writeSpreadsheetData($params);
        $this->writer->close();
    }

    protected function writeSpreadsheetData(array $params = []): void
    {
        $exportColNames    = $this->getExportColNames($params);
        $this->writer->addRow(WriterEntityFactory::createRowFromArray($exportColNames));
        $entityManager = $this->db->getEntityManager();
        $query         = $this->getEntitySelect($params)->getQuery();
        // As long as there are no fetch-joins, iterating one-by-one works and consumes less memory
        // see http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html#id1
        $entityCount       = 0;
        // https://www.doctrine-project.org/projects/doctrine-orm/en/2.8/reference/batch-processing.html#iterating-results
        foreach ($query->toIterable() as $entity) {
            if ($entityCount % self::ENTITY_BATCH_SIZE == 0) {
                // Prevent memory overload by regularly clearing the entity manager.
                // However, entities connected to $entity must be selected explicitly, otherwise their properties
                // cannot be retrieved anymore.
                $entityManager->clear();
            }
            $this->processEntity($entity, $exportColNames, $params);

            $entityCount++;
        }
    }

    protected function processEntity(AbstractEntity $entity, array $exportColNames, array $params = []): void
    {
        $this->writer->addRow($this->getSpreadsheetRow($entity, $exportColNames, $params));
    }

    public function getSpreadsheetRow(AbstractEntity $entity, array $exportColNames, array $params = []): \Box\Spout\Common\Entity\Row
    {
        $emptyRow = array_fill_keys($exportColNames, null);

        $emptyRowCount = count($emptyRow);

        $row = $this->populateRow($entity, $emptyRow, $params);
        $this->processRow($row, $params);

        assert($emptyRowCount == count($row), 'The number of elements in a row must remain the same after populating and processing it.');

        return WriterEntityFactory::createRowFromArray($row);
    }

    /**
     * Filter illegal chars, convert types, etc.
     *
     * @param array $row
     * @param array $params
     */
    protected function processRow(array &$row, array $params = []): void
    {
        foreach ($row as &$value) {
            if ($value instanceof \DateTime) {
                $value = DateUtils::toDbString($value);
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                // Export 1/0 instead of TRUE/FALSE
                $value = (int)$value;
            } elseif (is_string($value)) {
                // Filters new line values, otherwise some column may be shifted in the exported file
                $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
                /** @noinspection CascadeStringReplacementInspection */
                $value = str_replace("\t", '    ', $value);

                // Excel truncates texts after about 260 chars, if the first char is a dash,
                // probably because the value is interpreted as a formula. The easiest way to fix the problem
                // is prefixing with a space. Negative numeric values must not be touched.
                // https://github.com/cloud-solutions/surveylab/issues/974
                if (!is_numeric($value) && str_starts_with($value, '-')) {
                    $value = ' ' . $value;
                }
            }
        }
    }

    /**
     * Data from test userSurveys (assessments) should only be exported during testing.
     *
     * @param QueryBuilder $select
     */
    protected function applyUserSurveyTestDataFilter(QueryBuilder $select): void
    {
        // Never export temporary userSurveys
        $select->andWhere('userSurvey.isTemporary = 0');

        // Include test survey data in development and staging, but not in production
        if (Environment::isProduction()) {
            $select->andWhere('userSurvey.isTest = 0');
        }
    }

    protected function applyUserHasConsentFilter(QueryBuilder $select, bool $isExportFromUser): void
    {
        if ($this->config->userManager->useConsent && !$isExportFromUser) {
            $select->andWhere('user.hasConsent = 1');
        }
    }
}