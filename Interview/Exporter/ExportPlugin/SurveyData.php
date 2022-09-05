<?php

namespace Survey\Exporter\ExportPlugin;

use Doctrine\ORM\QueryBuilder;
use Survey\Config\Config;
use Survey\Db\Db;
use Survey\Db\Entity\AbstractEntity;
use Survey\Db\Entity\AbstractSurvey;
use Survey\Db\Entity\AbstractUserSurvey;
use Survey\Input\Datatypes;
use Survey\Monitor\MonitorFilters;
use Survey\Stdlib\FileUtils;
use Survey\UserSurveyManager\UserSurveyStatus;


class SurveyData extends AbstractTableExport implements ZipExportInterface
{
    public function __construct(Db $db, Config $config, protected Datatypes $datatypes, protected MonitorFilters $monitorFilters)
    {
        parent::__construct($db, $config);
    }


    public function getExportColNames(array $params = []): array
    {
        $colNames = [
            'waveNumber',
            'status',
            'interruptions',
            'pageNumber',
            'dateFirstAccess',
            'dateCompleted',
            'completionTime',
            'iteration',
            'userID',
            'importKey',
            'userSurveyID',
            'conditionID',
            'dateStart',
            'locale',
            'userIsActive',
            'groupID',
        ];

        $survey = $params[self::PARAM_ENTITY];
        $this->addItemExportColNames($survey, $colNames);

        return $colNames;
    }

    public function populateRow(AbstractEntity $userSurvey, array $row, array $params = []): array
    {
        /** @var AbstractUserSurvey $userSurvey */

        $wave                   = $userSurvey->getWave();
        $firstAccessStatusEntry = $userSurvey->getFirstAccessStatusEntry();
        $surveyCondition        = $userSurvey->getSurveyCondition();
        $user                   = $userSurvey->getUser();
        $userGroup              = $user->getUserGroup();
        $statusConfig           = UserSurveyStatus::findStatusConfig($userSurvey->getStatus());

        $row['waveNumber']      = $wave?->getNumber();
        $row['status']          = $statusConfig[UserSurveyStatus::KEY_NAME];
        $row['interruptions']   = $userSurvey->getInterruptionCounter();
        $row['pageNumber']      = $userSurvey->getPageNumber();
        $row['dateFirstAccess'] = $firstAccessStatusEntry ? $firstAccessStatusEntry->getTimestamp() : '';
        $row['dateCompleted']   = $userSurvey->getDateCompleted();
        $row['completionTime']  = $userSurvey->getCompletionTime();
        $row['iteration']       = $userSurvey->getSurveyIteration();
        $row['userID']          = $user->getId();
        $row['importKey']       = $user->getImportKey();
        $row['userSurveyID']    = $userSurvey->getId();
        $row['conditionID']     = $surveyCondition?->getId();
        $row['dateStart']       = $userSurvey->getDateStart();
        $row['locale']          = $userSurvey->getLocale();
        $row['userIsActive']    = $user->isActive();
        $row['groupID']         = $userGroup?->getId();

        $this->populateItemCols($userSurvey, $row);

        return $row;
    }

    public function getEntitySelect(array $params = []): QueryBuilder
    {
        /** @var AbstractSurvey $survey */
        $survey = $params[self::PARAM_ENTITY];

        $select = $this->db->getUserSurveyRepo()->createQueryBuilder('userSurvey')
            // Since the entity manager is cleared after a batch of rows, entities must be specifically
            // selected if their properties are to be exported.
            ->select('userSurvey', 'wave', 'surveyCondition')
            ->leftJoin('userSurvey.user', 'user')
            ->leftJoin('userSurvey.wave', 'wave')
            ->leftJoin('userSurvey.survey', 'survey')
            ->leftJoin('userSurvey.surveyCondition', 'surveyCondition')
            ->andWhere('userSurvey.survey = :survey')->setParameter('survey', $survey)
            ->andWhere('userSurvey.pageIndex > 0 OR userSurvey.dateCompleted IS NOT NULL')
            ->addOrderBy('wave.number', 'DESC')
            ->addOrderBy('userSurvey.dateCompleted', 'DESC')
            ->addOrderBy('userSurvey.surveyIteration', 'DESC')
            ->addOrderBy('user.id', 'DESC')
            ->addOrderBy('userSurvey.status', 'ASC');

        if (isset($params[AbstractExport::PARAM_FILTERS])) {
            $this->monitorFilters->applyFiltersToSelect(MonitorFilters::USER_MONITOR_ID, $select, $params[self::PARAM_FILTERS]);
        }

        $this->applyUserSurveyTestDataFilter($select);
        $this->applyUserHasConsentFilter($select, !empty($params[self::PARAM_IS_EXPORT_FROM_USER]));

        return $select;
    }

    /**
     * @param AbstractSurvey $survey
     * @param string[]       $colNames
     */
    protected function addItemExportColNames(AbstractSurvey $survey, array &$colNames): void
    {
        $items = $this->db->getSurveyItemRepo()->getExportItems($survey);

        foreach ($items as $item) {
            $datatypePlugin = $this->datatypes->getPlugin($item->getChoice()->getDatatypePlugin());
            foreach ($datatypePlugin->getItemExportColNames($item) as $exportColName) {
                $colNames[] = $exportColName;
            }
        }
    }

    protected function populateItemCols(AbstractUserSurvey $userSurvey, array &$row): void
    {
        foreach ($userSurvey->getItemDataStores() as $store) {
            $item           = $store->getItem();
            $datatypePlugin = $this->datatypes->getPlugin($item->getChoice()->getDatatypePlugin());
            foreach ($datatypePlugin->getItemExportColNames($item) as $storeKey => $exportColName) {
                $row[$exportColName] = $store->getValue($storeKey);
            }
        }
    }

    public function getEntityClassForZip(): string
    {
        return AbstractSurvey::class;
    }

    public function getArchivedFilename(AbstractEntity $entity): string
    {
        /** @var AbstractSurvey $entity */
        $surveyLabel = $entity->getLabelText()->getSourceText();
        return !empty($surveyLabel) ? FileUtils::filterFileName($surveyLabel) : 'survey-' . $entity->getId();
    }
}