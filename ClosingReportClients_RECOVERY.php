<?php
require_once('PHP_XLSXWriter/xlsxwriter.class.php');
// use XLSXWriter;

/**
 * RECOVERY VERSION - single affected date (this report is Monthly).
 * ===================================================================
 * The original query uses a rolling "1 month back from CURRENT_DATE()"
 * window. For recovery, that anchor is changed to $recoveryDate, with an
 * added upper bound so we only see data that would have existed as of
 * that historical day (nothing closed after it).
 *
 * Same safety measures as the other recovery scripts:
 * - mailNotifaction() commented out (no client email)
 * - ifDataNotPresent() replaced with a local version that skips the
 *   hidden ReportStatus() mail call
 * - set_time_limit(0) to avoid timeouts
 * - filename uses the recovery date + REAL current time (via microtime)
 * - SCHEDULER_LOGS.createdAt corrected to the recovery date after the block
 * - if an identical filename already exists, a numeric suffix is added
 *   instead of overwriting (the file is always generated)
 *
 * After running, REVERT to the original file.
 */

function recoveryDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $path, $run_by, $userType)
{
    $msg = 'No Data Present';
    $sftpStatus = 0;
    $status = 0;
    scheduler_logs($status, $reportName, $report_start_time, $msg, $companyName, $sftpStatus, $FileSizeKB, $path, $run_by);
}

function closingReportClients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
    global $pdo;
    set_time_limit(0);

    $report_start_time = date("H:i:s");
    $paths = explode(",", $path);
    $code_names = explode(",", $code_name);
    $dataPresents = array();
    $noDataPresents = array();
    $new_status = 3;
    $msg = 'No Data Present';
    $status_msg = 'Failed';
    $sftpStatus = 0;
    $status = 0;
    $FileSizeKB = 0;

    // ---- Only one affected date for this Monthly report ----
    $recoveryDates = array('2026-09-01');

    //  $recoveryDates = array('2026-07-03');

    foreach ($recoveryDates as $recoveryDate) {
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $timeOfDay = $now->format('H:i:s.u');
        $date = new DateTime($recoveryDate . ' ' . $timeOfDay);
        $fileName = filterReportName($date, $reportName);

        $beforeId = 0;
        $idCheckStmt = $pdo->query("SELECT MAX(id) AS maxId FROM SCHEDULER_LOGS");
        $idCheckRow = $idCheckStmt->fetch();
        if ($idCheckRow && $idCheckRow['maxId'] !== null) {
            $beforeId = (int) $idCheckRow['maxId'];
        }

        echo "<pre>----- Recovering date: $recoveryDate -----\n";

        foreach ($paths as $companyPath) {
            $companyPath = str_replace(["'", " "], "", $companyPath);
            $companyName = createDirgetCompanyName($companyPath, $reportBasePath);

            $companyFolder = rtrim($reportBasePath . $companyPath, '/');
            $actualFileName = $fileName;
            $suffix = 1;
            while (file_exists($companyFolder . '/' . $actualFileName)) {
                $suffix++;
                $ext = strrchr($fileName, '.');
                $base = substr($fileName, 0, -strlen($ext));
                $actualFileName = $base . '_' . $suffix . $ext;
            }
            if ($actualFileName !== $fileName) {
                echo "  $companyName ($recoveryDate): filename collision - using '$actualFileName' instead\n";
            }

            $writer = new XLSXWriter();
            $companyStatus = getCompanyStatus($companyName);
            $query = "SELECT RMSACCTNUM AS 'Acct Number', CONCAT_WS(' ',DEBTOR_LAST_NME, DEBTOR_FIRST_NME) AS 'Name',
                CUR_STATUS_CDE AS 'Closing Code', CUR_STATUS_CDE_DESC AS 'Description', DATE_FORMAT(CLOSED_DT,'%Y/%m/%d') AS 'ClosingDate',
                ATTY_NAME AS 'Firm', PORTFOLIO_CDE AS 'ClientCode', date_format(CURRENT_DATE(),'%Y%m%d') AS 'ProcessDate',
                CLIENT_CDE AS 'Org Code', CONCAT_WS(' ',CLIENT_NME,CLIENT_NME2) AS 'Org Name'
                FROM `MASTER_DATA_DB`
                where CLIENT_CDE != 'FRIC' AND CLIENT_CDE='" . $companyName . "'
                and CLOSED_DT >= DATE_SUB('$recoveryDate', INTERVAL 1 MONTH)
                and CLOSED_DT <= '$recoveryDate'
                ORDER BY CLIENT_CDE";

            $results = getResult($query);

            if ($results['numRows'] > 0) {
                $excelPrefix = getExcelPrefix42();
                $writer->writeSheetHeader($excelPrefix['sheetName'], $excelPrefix['headers'], $excelPrefix['style']);
                foreach ($results['results'] as $resultRow) {
                    $writer->writeSheetRow($excelPrefix['sheetName'], $resultRow);
                }
                $writer->writeToFile(str_replace(__FILE__, $reportBasePath . $companyPath . '/' . $actualFileName, __FILE__));
                if (file_exists(str_replace(__FILE__, $reportBasePath . $companyPath . '/' . $actualFileName, __FILE__))) {
                    $FileSizeKB = getfileSize($reportBasePath . $companyPath . '/' . $actualFileName);

                    // TEMP RECOVERY: mail suppressed
                    // mailNotifaction($mailNotification, $companyPath, $companyName, $userType, $userReportName, $reportDescription);

                    array_push($dataPresents, array(
                        'paths' => $companyPath,
                        'filename' => $actualFileName,
                        'clientcode' => $companyName
                    ));
                    ifDataPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $companyPath, $run_by,$userType);
                    echo "  $companyName ($recoveryDate): DATA FOUND, file written\n";
                }
            } else {
                recoveryDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $companyPath, $run_by,$userType);

                array_push($noDataPresents, array(
                    'paths' => $companyPath,
                    'filename' => $actualFileName,
                    'clientcode' => $companyName
                ));
                echo "  $companyName ($recoveryDate): no data\n";
            }
        }

        $updateStmt = $pdo->prepare(
            "UPDATE SCHEDULER_LOGS SET createdAt = :recoveryDate
             WHERE id > :beforeId AND report_name = :reportName"
        );
        $updateStmt->execute(array(
            ':recoveryDate' => $recoveryDate,
            ':beforeId' => $beforeId,
            ':reportName' => $reportName,
        ));
        echo "(SCHEDULER_LOGS createdAt corrected to $recoveryDate for this block)\n";

        echo "</pre>";
    }

    if (!empty($dataPresents)) {
        $new_status = 2;
        $status_msg = 'generated';
        $status = 1;
    } else {
        $new_status = 3;
        $status_msg = 'Failed';
        $status = 0;
    }
    return array('status' => $status, 'status_msg' => $status_msg, 'new_status' => $new_status);
}


function getExcelPrefix42()
{

    $header = array(

        'Account Number' => 'string',
        'Name' => 'string',
        'Closing Code' => 'string',
        'Description' => 'string',
        'Closing Date' => 'string',
        'Firm' => 'string',
        'Client Code' => 'string',
        'Process Date' => 'string',
        'Org Code' => 'string',
        'Org Name' => 'string'
    );

    $style = array(
        'font-style' => 'bold',
        'fill' => '#eee',
        'halign' => 'center',
        'border' => 'left, right, top, bottom',
        'widths' => [20, 30, 20, 30, 20, 30, 30, 30, 20, 30]
    );
    $style1 = array(
        'font-style' => 'bold',
        'fill' => '#eee',
        'font-size' => '10.5',
        'height' => '16.5'
    );
    $sheetName = 'ClosingReportClient';

    return (['headers' => $header, 'style' => $style, 'style1' => $style1, 'sheetName' => $sheetName]);
}
