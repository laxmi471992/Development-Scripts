<?php
include_once('PHP_XLSXWriter/xlsxwriter.class.php');
// use XLSXWriter;

/**
 * RECOVERY VERSION - two affected dates (this report is Weekly).
 * ===================================================================
 * The original query uses a rolling "7 days back from CURRENT_DATE()"
 * window. For recovery, that anchor is changed to $recoveryDate, with an
 * added upper bound so we only see data that would have existed as of
 * that historical day.
 *
 * Same safety measures as the other recovery scripts (no mail, no
 * timeout, real-time filename, SCHEDULER_LOGS.createdAt correction,
 * suffix-on-filename-collision). After running, REVERT to the original file.
 */

function recoveryDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $path, $run_by, $userType)
{
    $msg = 'No Data Present';
    $sftpStatus = 0;
    $status = 0;
    scheduler_logs($status, $reportName, $report_start_time, $msg, $companyName, $sftpStatus, $FileSizeKB, $path, $run_by);
}

function closingReportClientsWeeklySelectClients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	set_time_limit(0);

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

	// ---- Two affected dates for this Weekly report ----
	// $recoveryDates = array('2026-08-21', '2026-08-28');
	$recoveryDates = array('2026-05-23', '2026-08-28');

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
			$query = "SELECT
				case when ORGCODE = 'MAA' THEN RIGHT(ACCT_NUM,'16') ELSE ACCT_NUM end AS 'Acct Number',
				WFNAME AS Name, CURR_STS_CD AS 'Closing Code', CURR_STS_DESC AS 'Description', DATE_FORMAT(LSTSTATCHG , '%Y/%m/%d') AS 'Closing_Date', CURR_ATTY_NME AS Firm, CLT_CDE AS 'Client Code',
				DATE_FORMAT(CURRENT_DATE() ,'%Y%m%d')AS 'Process Date', ORGCODE AS 'Org Code', WFORGNM AS 'Org Name'
				FROM HSFLCLNTWF
				WHERE CURR_STS_CD LIKE '9%'
				AND LSTSTATCHG >= DATE_FORMAT(DATE_SUB('$recoveryDate', INTERVAL 7 day), '%Y%m%d')
				AND LSTSTATCHG < DATE_FORMAT(DATE_ADD('$recoveryDate', INTERVAL 1 DAY), '%Y%m%d')
				AND ORGCODE ='" . $companyName . "'";

			$results = getResult($query);
			if ($results['numRows'] > 0) {
				$excelPrefix = getExcelPrefix39();
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

	return array('status' => (!empty($dataPresents) ? 1 : 0), 'status_msg' => (!empty($dataPresents) ? 'generated' : 'Failed'), 'new_status' => (!empty($dataPresents) ? 2 : 3));
}
function getExcelPrefix39()
{

	$header = array(
		'Acct Number' => 'string',
		'Name' => 'string',
		'Closing Code' => 'string',
		'Description' => 'string',
		'ClosingDate' => 'string',
		'Firm' => 'string',
		'ClientCode' => 'string',
		'ProcessDate' => 'string',
		'Org Code' => 'string',
		'Org Name' => 'string'
	);
	$style = array(
		'font-style' => 'bold',
		'fill' => '#eee',
		'halign' => 'center',
		'border' => 'left, right, top, bottom',
		'widths' => [15, 20, 15, 20, 15, 40, 15, 15, 20, 30]

	);
	$sheetName = 'ClosingReportClientsWeeklySelectClients';

	return (['headers' => $header, 'style' => $style, 'sheetName' => $sheetName]);
}