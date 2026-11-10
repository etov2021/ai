<?php

$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    require __DIR__ . '/../../cronjobs/init.php';

    App::start($name);

    $db = App::db();

    $rows = $db->fetchAll('SELECT id, countLots FROM Procurements');

    foreach ($rows as $row) {

        $countContractsRows = $db->fetchAll('SELECT ec.id FROM EopContracts ec INNER JOIN EopContractsCompaniesRel eccr ON ec.id = eccr.contractId WHERE ec.procurementId = ? GROUP BY ec.id', [$row['id']]);

        $countContracts = count($countContractsRows);

        /* $countAutomaticContractsRows = $db->fetchAll('SELECT ec.id FROM EopContracts ec INNER JOIN EopContractsCompaniesRel eccr ON ec.id = eccr.contractId WHERE ec.procurementId = ? AND ec.automatic = 1 GROUP BY ec.id', [$row['id']]);

        $countAutomaticContracts = count($countAutomaticContractsRows); */

        $hasFullContracts = 0;

        $countLots = $row['countLots'];

        if (empty($countLots) && $countContracts) {
            $hasFullContracts = 1;
        } else if (!empty($countLots) && $countContracts >= $countLots) {
            $hasFullContracts = 1;
        }

        $db->update('Procurements', ['hasContracts' => ($countContracts ? 1 : 0), 'hasFullContracts' => $hasFullContracts, 'countContracts' => $countContracts], ['id = ?' => $row['id']]);
    }

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage();
}