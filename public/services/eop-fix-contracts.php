<?php

/**
 *
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `EopCompanies`;
TRUNCATE TABLE `EopContracts`;
TRUNCATE TABLE `EopContractsCompaniesRel`;
SET FOREIGN_KEY_CHECKS = 1;
 */

$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    require __DIR__ . '/../../cronjobs/init.php';

    App::start($name);

    $db = App::db();

    $rows = $db->fetchAll('SELECT id, contractStartDate, contractDeadline, contractorCompany FROM Procurements WHERE contractorCompany IS NOT NULL AND contractorCompany <> ""');

    foreach ($rows as $row) {

        $row['contractorCompany'] = trim($row['contractorCompany']);

        if (empty($row['contractorCompany'])) {
            continue;
        }

        $price = $db->fetchOne('SELECT price FROM ProcurementsPrices WHERE procurementId = ' . $row['id'] . ' LIMIT 1');
        $row['price'] = $price ?: null;

        $db->delete('EopContracts', ['procurementId = ?' => $row['id']]);

        $db->insert('EopContracts', [
            'procurementId' => $row['id'],
            'dateStart' => $row['contractStartDate'],
            'deadline' => $row['contractDeadline'],
            'budget' => $row['price'],
            'automatic' => 0,
        ]);

        $contractId = $db->lastInsertId();

        $db->insert('EopContractsCompaniesRel', [
            'contractId' => $contractId,
            'companyId' => getCompanyId($row['contractorCompany']),
        ]);

        $db->update('Procurements', ['hasContracts' => 1], ['id = ?' => $row['id']]);

        echo 'Contract #' . $contractId . ': ' . $row['contractorCompany'] . NL;
    }

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage();
}

function getCompanyId($name)
{
    $db = App::db();

    $company = $db->fetchRow('SELECT id FROM EopCompanies WHERE name = ?', [$name]);

    if (empty($company)) {
        $db->insert('EopCompanies', [
            'name' => $name,
        ]);
        $companyId = $db->lastInsertId();
    } else {
        $companyId = $company['id'];
    }

    return $companyId;
}