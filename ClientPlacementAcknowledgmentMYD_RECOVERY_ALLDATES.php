<?php
require_once('PHP_XLSXWriter/xlsxwriter.class.php');
// use XLSXWriter;

/**
 * ONE-SHOT RECOVERY VERSION - runs ALL missing dates in a single
 * "Manual Report Run" click, with ALL emails suppressed.
 * ============================================================
 * IMPORTANT DISCOVERY: the shared ifDataNotPresent() function (defined in
 * generateReport.php) internally calls ReportStatus(), which tries to send
 * an email to pipeway.support@goolean.tech for EVERY "no data" case. Since
 * this recovery loop checks 13 dates x many clients, that caused hundreds
 * of SMTP attempts and eventually a "Maximum execution time of 300 seconds
 * exceeded" fatal error.
 *
 * FIX: this file defines its own recoveryDataNotPresent() below, which
 * does exactly what ifDataNotPresent() does EXCEPT it skips the
 * ReportStatus() mail call. All calls to ifDataNotPresent() in the loop
 * have been replaced with recoveryDataNotPresent().
 *
 * Mail via mailNotifaction() (the "data present" email) is also commented
 * out below, same as before.
 *
 * After this single run finishes (all 13 dates processed), REVERT to the
 * original file (normal ifDataNotPresent()/ifDataPresent() calls,
 * CURRENT_DATE()-based filter, new DateTime() with no argument, and
 * mailNotifaction() uncommented) so daily runs go back to normal.
 */

// Local replacement for ifDataNotPresent() that skips the ReportStatus()
// mail call but still logs to SCHEDULER_LOGS exactly the same way.
function recoveryDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $path, $run_by, $userType)
{
    $msg = 'No Data Present';
    $sftpStatus = 0;
    $status = 0;
    scheduler_logs($status, $reportName, $report_start_time, $msg, $companyName, $sftpStatus, $FileSizeKB, $path, $run_by);
}

function clientPlacementAcknowledgmentMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
    global $pdo; // needed to correct SCHEDULER_LOGS.createdAt after each date's block

    set_time_limit(0); // TEMP RECOVERY: remove the 300-second execution limit,
                        // since this run processes 13 dates x many clients.
    $report_start_time = date("H:i:s");
    $paths = explode(",", $path);
    $dataPresents = array();
    $noDataPresents = array();
    $new_status = 3;
    $msg = 'No Data Present';
    $status_msg = 'Failed';
    $sftpStatus = 0;
    $status = 0;
    $FileSizeKB = 0;

    // ---- All the missing dates to recover, in order ----
    // $recoveryDates = array(
    //     '2026-08-19', '2026-08-20', '2026-08-21', '2026-08-23', '2026-08-24',
    //     '2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-29',
    //     '2026-08-30', '2026-08-31', '2026-09-01',
    // );

      // ---- All the missing dates to recover, in order ----
    // $recoveryDates = array(
    //  '2026-05-26',
    // );


    $recoveryDates = array(
    '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-31', '2026-09-01',
);

    foreach ($recoveryDates as $recoveryDate) {
        // Filename stamped with this specific recovery date, but using the
        // REAL current time-of-day (not 00:00:00) so it looks like a
        // naturally generated file - matching the format/feel of a normal
        // report run (e.g. "..._08-04-2026_23_06_33_707_.xlsx").
        // NOTE: date('H:i:s.u') always returns ".000000" - PHP's date()
        // function has no microsecond precision. Use microtime() properly
        // so the filename gets REAL, varying milliseconds (like normal
        // report files do), not a suspicious ".000" every time.
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $timeOfDay = $now->format('H:i:s.u');
        $date = new DateTime($recoveryDate . ' ' . $timeOfDay);
        $fileName = filterReportName($date, $reportName);

        // Capture the highest existing SCHEDULER_LOGS id BEFORE this date's
        // block runs, so we can correct createdAt for exactly the rows
        // inserted during this block (see the UPDATE after the inner loop).
        $beforeId = 0;
        $idCheckStmt = $pdo->query("SELECT MAX(id) AS maxId FROM SCHEDULER_LOGS");
        $idCheckRow = $idCheckStmt->fetch();
        if ($idCheckRow && $idCheckRow['maxId'] !== null) {
            $beforeId = (int) $idCheckRow['maxId'];
        }

        echo "<pre>----- Recovering date: $recoveryDate -----\n";

        // Replicate the ORIGINAL report's weekend catch-up logic: on a
        // Monday, the report normally looks back 3 days (covering
        // Fri/Sat/Sun since it does not run over the weekend), otherwise
        // it only looks at the single day itself. Using an exact-date
        // match for a recovered Monday would silently miss the weekend's
        // data, so we widen the filter to a range on Mondays.
        $recoveryDateObj = new DateTime($recoveryDate);
        $isMonday = ($recoveryDateObj->format('N') == 1); // ISO-8601: 1 = Monday

        if ($isMonday) {
            $rangeStart = (clone $recoveryDateObj)->modify('-3 days')->format('Y-m-d');
            $dateFilterClause = "CAST(P.RMSDATERCV AS DATE) >= '$rangeStart' AND CAST(P.RMSDATERCV AS DATE) <= '$recoveryDate'";
            echo "  (Monday detected - using range $rangeStart to $recoveryDate, matching original weekend catch-up logic)\n";
        } else {
            $dateFilterClause = "CAST(P.RMSDATERCV AS DATE) = '$recoveryDate'";
        }

        foreach ($paths as $companyPath) {
            $companyPath = str_replace(["'", " "], "", $companyPath);
            $companyName = createDirgetCompanyName($companyPath, $reportBasePath);

            // SAFETY CHECK: the sheet must ALWAYS be generated. If a file
            // with the exact same name (same date AND same time) already
            // exists for this company, do NOT overwrite it - instead,
            // append a numeric suffix (_1, _2, ...) to the filename until
            // a free name is found, then use that unique name.
            $companyFolder = rtrim($reportBasePath . $companyPath, '/');
            $actualFileName = $fileName;
            $suffix = 1;
            while (file_exists($companyFolder . '/' . $actualFileName)) {
                $suffix++;
                $ext = strrchr($fileName, '.'); // e.g. ".xlsx"
                $base = substr($fileName, 0, -strlen($ext));
                $actualFileName = $base . '_' . $suffix . $ext;
            }
            if ($actualFileName !== $fileName) {
                echo "  $companyName ($recoveryDate): filename collision - using '$actualFileName' instead\n";
            }

            $writer = new XLSXWriter();
            $companyStatus = getCompanyStatus($companyName);
            $query = "WITH HOLDACKACCT AS (
					WITH ACKACCT AS(
					SELECT
					P.RMSDATERCV as 'DATE_RCV', DATEDIFF(current_date(),P.RMSDATERCV) AS 'DAYS_RCV',
					S.RMSBRGLVL2 AS 'Client',
					P.RMSACCTNUM AS 'Acct_Number' ,P.RMSCORPNM1 AS 'Last_Name', P.RMSSTATECD AS 'State',
					P.RMSDATERCV AS 'Receive_Dt',
					P.RMSOFFCRCD AS 'Client_Cd'
					FROM RMSPMASTRP P  
					INNER JOIN RMSPSYSASN S
					ON P.RMSOFFCRCD = S.RMSOFFCRCD
					WHERE $dateFilterClause
					)
                    SELECT T.Client , T.Acct_Number , T.Last_Name , T.State,
                    T.Receive_Dt , T.Client_Cd,E.RMSBRGLVL2
                    from ACKACCT T
                    INNER JOIN RMSPSYSASN E
                    ON T.Client_Cd = E.RMSOFFCRCD
                    WHERE E.RMSBRGLVL2 = '$companyName '
                    )
                    SELECT CASE WHEN RIGHT(Client_Cd,3) = 'MAA' THEN LEFT(Acct_Number,16) ELSE Acct_Number END AS 'Acct Number',
                        Last_Name as 'Last Name', State,
                        Receive_Dt as 'Receive Dt', Client_Cd as 'Client Cd'
                        FROM HOLDACKACCT
                        ORDER BY Client";

            $results = getResult($query);
            if ($results['numRows'] > 0) {
                $excelPrefix = getExcelPrefix();
                $writer->writeSheetHeader($excelPrefix['sheetName'], $excelPrefix['headers'], $excelPrefix['style']);
                foreach ($results['results'] as $resultRow) {
                    $writer->writeSheetRow($excelPrefix['sheetName'], $resultRow);
                }
                $writer->writeToFile(str_replace(__FILE__, $reportBasePath . $companyPath . '/' . $actualFileName, __FILE__));
                if (file_exists(str_replace(__FILE__, $reportBasePath . $companyPath . '/' . $actualFileName, __FILE__))) {
                    $FileSizeKB = getfileSize($reportBasePath . $companyPath . '/' . $actualFileName);

                    // TEMP RECOVERY: mail suppressed - client should not
                    // receive backdated/duplicate emails while recovering
                    // missing days. Uncomment once back to the normal file.
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
                // TEMP RECOVERY: use recoveryDataNotPresent() instead of
                // ifDataNotPresent() - same SCHEDULER_LOGS logging, but
                // WITHOUT the hidden ReportStatus() mail call.
                recoveryDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $companyPath, $run_by,$userType);

                array_push($noDataPresents, array(
                    'paths' => $companyPath,
                    'filename' => $actualFileName,
                    'clientcode' => $companyName
                ));
                echo "  $companyName ($recoveryDate): no data\n";
            }
        }

        // Correct SCHEDULER_LOGS.createdAt for every row inserted during
        // this date's block - scheduler_logs() always stamps createdAt
        // with the server's actual current date, so we fix it here to
        // reflect the recovery date instead. Time portions (start_time/
        // end_time) are left untouched, as requested.
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
function getExcelPrefix()
{

    $header = array(
        'Acct Number' => 'string',
        'Last Name' => 'string',
        'State' => 'string',
        'Receive Date' => 'string',
        'Client Cd' => 'string'
    );

    $style = array(
        'font-style' => 'bold',
        'fill' => '#eee',
        'halign' => 'center',
        'border' => 'left, right, top, bottom',
        'widths' => [20, 20, 20, 20, 20]
    );
    $style1 = array(
        'font-style' => 'bold',
        'fill' => '#eee',
        'font-size' => '10.5',
        'height' => '16.5'
    );
    $sheetName = 'ClientPlacemenetAcknowledgmentMYD';

    return (['headers' => $header, 'style' => $style, 'style1' => $style1, 'sheetName' => $sheetName]);
}