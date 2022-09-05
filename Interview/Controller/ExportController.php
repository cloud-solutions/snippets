<?php

namespace Survey\Controller;

use Box\Spout\Common\Type;
use Laminas\Http\Response as HttpResponse;
use Survey\Exporter\ExportPlugin;
use Survey\Exporter\ExportPlugin\AbstractExport;
use Survey\Monitor\MonitorFilters;


class ExportController extends AbstractActionController
{
    public const NAME = 'export'; // Controller name

    // Url param
    public const PARAM_APPLY_FILTERS = 'applyFilters';


    public function postInit(): HttpResponse|bool
    {
        set_time_limit(1200);

        return true;
    }


    public const EXPORT_SURVEY_DATA = 'export-survey-data';

    public function exportSurveyDataAction(): void
    {
        $params = [AbstractExport::PARAM_FILE_EXT => Type::CSV];
        $this->exportZipFile(ExportPlugin\SurveyData::name(), 'excel-data', $params);
    }


    public const EXPORT_USERS = 'export-users';

    public function exportUsersAction(): void
    {
        $applyFilters                          = $this->getUrlParam(self::PARAM_APPLY_FILTERS, false);
        $params[AbstractExport::PARAM_FILTERS] = $applyFilters ? $this->di()->monitorFilters()->getFilters(MonitorFilters::USER_MONITOR_ID) : [];

        $this->exportExcelTable(ExportPlugin\Users::name(), $params);
    }


    protected function exportExcelTable(string $exportPluginName, array $params = []): void
    {
        $params[AbstractExport::PARAM_FILE_EXT] = $params[AbstractExport::PARAM_FILE_EXT] ?? Type::XLSX;
        $this->di()->exporter()->export($exportPluginName, $params);
        exit;
    }

    protected function exportZipFile(string $exportPluginName, string $zipFileName, array $params = []): void
    {
        $this->di()->exporter()->exportZippedData($exportPluginName, $zipFileName, $params);
        exit;
    }
}




