<?php

namespace Survey\Exporter\ExportPlugin;

use Doctrine\ORM\QueryBuilder;
use Survey\Config\Config;
use Survey\Constants\Role;
use Survey\Db\Db;
use Survey\Db\Entity\AbstractEntity;
use Survey\Db\Entity\AbstractUser;
use Survey\Monitor\MonitorFilters;

class Users extends AbstractTableExport
{
    public function __construct(Db $db, Config $config, protected MonitorFilters $monitorFilters)
    {
        parent::__construct($db, $config);
    }

    public function getExportColNames(array $params = []): array
    {
        return [
            'userID',
            'importKey',
            'username',
            'email',
            'firstname',
            'lastname',
            'sex',
            'title',
            'locale',
            'dateRegistered',
            'lastLogin',
            'lastIP',
            'isActive',
        ];
    }

    public function populateRow(AbstractEntity $user, array $row, array $params = []): array
    {
        /** @var AbstractUser $user */

        $row['userID']         = $user->getId();
        $row['importKey']      = $user->getImportKey();
        $row['username']       = $user->getUsername();
        $row['email']          = $user->getEmail();
        $row['firstname']      = $user->getFirstname();
        $row['lastname']       = $user->getLastname();
        $row['sex']            = $user->getSex();
        $row['title']          = $user->getTitle();
        $row['locale']         = $user->getLocale();
        $row['dateRegistered'] = $user->getDateRegistered();
        $row['lastLogin']      = $user->getLastLogin();
        $row['lastIP']         = $user->getLastIP();
        $row['isActive']       = $user->isActive();

        return $row;
    }

    public function getEntitySelect(array $params = []): QueryBuilder
    {
        $select = $this->db->getUserRepo()->createQueryBuilder('user')
            ->leftJoin('user.role', 'role')
            ->andWhere('role.name IN (:exportRoles)')->setParameter('exportRoles', [Role::USER, Role::TESTUSER])
            ->andWhere('user.isDeleted = :notDeleted')->setParameter('notDeleted', AbstractUser::DELETED_NO)
            ->orderBy('user.id', 'DESC');

        $this->monitorFilters->applyFiltersToSelect(MonitorFilters::USER_MONITOR_ID, $select, $params[self::PARAM_FILTERS]);

        return $select;
    }
}