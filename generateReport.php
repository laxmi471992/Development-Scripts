 /* CHANGELOG:
  * -----------------------------------------------------------------------------------------------------------
  * Version | Date       | Author              | Description
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-07-25 | LK25072026          | Added command-line execution using BatchId for scheduled report processing.
  *         |            |                     | Added separate report execution to prevent one report failure from stopping other reports.
  *         |            |                     | Added fatal error logging and consolidated error email notification.
  *         |            |                     |Added report execution summary showing Generated, Failed, and Fatal statuses.    
  * -----------------------------------------------------------------------------------------------------------
  */

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();
date_default_timezone_set('America/New_York');

include('PHPMailer/compliancemailcode.php');
// include('PHPMailer/donotReply.php');
chdir('/var/www/html/bi/dist');

if (file_exists('pdoconn.php')) {
	include('pdoconn.php');
	// echo "pdoconn.php included successfully!";
} else {
	// echo "Error: pdoconn.php not found";
	exit;
}

$username = @$_SESSION['fullName'] . ' ' . @$_SESSION['LastName'];
$reportBasePath = "/var/www/html/bi/dist/";
if (!empty($_POST)) {

	$reportingId = $_POST['reportingId'];
	$query = $pdo->prepare("SELECT * FROM Batch_Report WHERE BatchId = '" . $reportingId . "'");

	$query->execute();
	$rows = $query->fetchAll();
	if (count($rows) != 0) {
		$path = trim($rows[0]['directoryPath']);
		$id = trim($rows[0]['BatchId']);
		$reportName = trim($rows[0]['ReportName']);
		$fileType = trim($rows[0]['fileType']);
		$code_name = trim($rows[0]['code_name']);
		$userType = trim($rows[0]['UserType']);
		$userReportName = trim($rows[0]['user_report_name']);
		$outputName = trim($rows[0]['output_name']);
		$reportDescription = trim($rows[0]['report_description']);
		$mailNotification = $rows[0]['mail_notification'];
		$sftpId = $rows[0]['sftp_id'];
		$mode = trim($rows[0]['recurrence_pattern']);
		$run_by = $username;
		if ($fileType == 'excel') {
			$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';

			openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		}
	}
} else if (isset($argv[1])) {  //added LK25072026 to run the report from command line with batchId as argument

	$reportingId = $argv[1];
	$currentReportName = 'Unknown';

	register_shutdown_function(function () use (&$currentReportName, $reportingId) {
		$error = error_get_last();
		if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
		$logFile = '/home/pipewayweb/log/fatal_catch.log';
		$timestamp = date('d-m-Y H:i:s');

		$logMsg = "\n===== START FATAL CAUGHT " . $timestamp . " =====\n"
			. "Report: " . $currentReportName . " (BatchId " . $reportingId . ")\n"
			. "Error: " . $error['message'] . "\n"
			. "File: " . $error['file'] . "\n"
			. "Line: " . $error['line'] . "\n"
			. "Time: " . date('Y-m-d H:i:s') . "\n"
			. "=====================================================\n"
			. "===== END: " . date('d-m-Y H:i:s') . " =====\n";
		file_put_contents($logFile, $logMsg, FILE_APPEND);


		echo $logMsg;
			
		}
	});

	$query = $pdo->prepare("SELECT * FROM Batch_Report WHERE BatchId = '" . $reportingId . "'");
	$query->execute();
	$rows = $query->fetchAll();
	if (count($rows) != 0) {
		$path = trim($rows[0]['directoryPath']);
		$id = trim($rows[0]['BatchId']);
		$reportName = trim($rows[0]['ReportName']);
		$currentReportName = $reportName;
		$fileType = trim($rows[0]['fileType']);
		$code_name = trim($rows[0]['code_name']);
		$userType = trim($rows[0]['UserType']);
		$userReportName = trim($rows[0]['user_report_name']);
		$outputName = trim($rows[0]['output_name']);
		$reportDescription = trim($rows[0]['report_description']);
		$mailNotification = $rows[0]['mail_notification'];
		$sftpId = $rows[0]['sftp_id'];
		$mode = trim($rows[0]['recurrence_pattern']);
		$run_by = 'Scheduled';

		if ($fileType == 'excel') {
			$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
			openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath, false);
		}
	}
	exit();
} //ended LK25072026 
else {
     
    $GLOBALS['fatalErrors'] = [];   //LK25072026 - ADDED: global array to store fatal errors
    $GLOBALS['reportRunSummary'] = [];  //LK25072026 - ADDED: global array to store summary of report runs
	$current_date = date('Y-m-d');
	$day = date('l', strtotime($current_date));

	if ($day == 'Monday') {
		$day = " AND FIND_IN_SET('1',Day)";
	} else if ($day == 'Tuesday') {
		$day = " AND FIND_IN_SET('2',Day)";
	} else if ($day == 'Wednesday') {
		$day = " AND FIND_IN_SET('3',Day)";
	} else if ($day == 'Thursday') {
		$day = " AND FIND_IN_SET('4',Day)";
	} else if ($day == 'Friday') {
		$day = " AND FIND_IN_SET('5',Day)";
	} else if ($day == 'Saturday') {
		$day = " AND FIND_IN_SET('6',Day)";
	} else if ($day == 'Sunday') {
		$day = " AND FIND_IN_SET('7',Day)";
	}
	if (date("h:i a") == '12:00 am') {
		exit();
	}

	//***********************************for SCHEDULER_DAILY_REPORT_LOGS*********************************//

	dailyReportDetails();

	//*****************************************end*****************************************************//       

	// Daily if EndDate is null
	$statement = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Daily' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 ";

	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchAll();

	if (count($rows) != 0) {
		foreach ($rows as $row) {

			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = $row['mail_notification'];
			$sftpId = trim($row['sftp_id']);
			$mode = 'Daily';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	// ******************************************end******************************************* //

	// Daily if both StartDate and EndDate are available
	$statement2 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Daily' AND IsDelete = 0 AND schedule_status = 0 AND status = 0";

	$query2 = $pdo->prepare($statement2);
	$query2->execute();
	$rows2 = $query2->fetchAll();
	if (count($rows2) != 0) {

		foreach ($rows2 as $row) {

			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = $row['mail_notification'];
			$sftpId = trim($row['sftp_id']);
			$mode = 'Daily SE';
			$run_by = 'Scheduled';


			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	// Weekly if EndDate is null
	$statement3 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Weekly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0  " . $day;

	$query3 = $pdo->prepare($statement3);
	$query3->execute();
	$rows3 = $query3->fetchAll();
	if (count($rows3) != 0) {
		foreach ($rows3 as $row) {

			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Weekly';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	// Weekly if both StartDate and EndDate are available
	$statement4 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Weekly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $day;

	$query4 = $pdo->prepare($statement4);
	$query4->execute();
	$rows4 = $query4->fetchAll();
	if (count($rows4) != 0) {
		foreach ($rows4 as $row) {

			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Weekly SE';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {

				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';

				openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	$current_month = fetchMonths($current_date);

	// Monthly if EndDate is null
	$statement5 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Monthly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0";
	$query5 = $pdo->prepare($statement5);
	$query5->execute();
	$rows5 = $query5->fetchAll();

	if (count($rows5) != 0) {
		foreach ($rows5 as $row) {

			$total_days = trim($row['monthly_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Monthly';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	// Monthly if both StartDate and EndDate are available
	$statement6 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Monthly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 ";

	$query6 = $pdo->prepare($statement6);
	$query6->execute();
	$rows6 = $query6->fetchAll();

	if (count($rows6) != 0) {
		foreach ($rows6 as $row) {

			$total_days = trim($row['monthly_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Monthly SE';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {

				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	$current_month = fetchMonths($current_date);

	//Quarterly if EndDate is null
	$statement7 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Quarterly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0 " . $current_month;

	$query7 = $pdo->prepare($statement7);
	$query7->execute();
	$rows7 = $query7->fetchAll();
	if (count($rows7) != 0) {
		foreach ($rows7 as $row) {

			$total_days = trim($row['quarterly_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Quatarly';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {

				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}
	//Quarterly if both StartDate and EndDate are available
	$statement8 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Quarterly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query8 = $pdo->prepare($statement8);
	$query8->execute();
	$rows8 = $query8->fetchAll();

	if (count($rows8) != 0) {
		foreach ($rows8 as $row) {

			$total_days = trim($row['quarterly_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Monthly SE';
			$run_by = 'Scheduled';


			if ($fileType == 'excel') {

				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	//Semi Monthly if EndDate is null
	$statement9 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'semi_monthly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0 " . $current_month;

	$query9 = $pdo->prepare($statement9);
	$query9->execute();
	$rows9 = $query9->fetchAll();

	if (count($rows9) != 0) {
		foreach ($rows9 as $row) {

			$total_days = trim($row['semi_month_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Semi Monthly';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	//Semi Monthly if both StartDate and EndDate are available
	$statement10 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'semi_monthly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query10 = $pdo->prepare($statement10);
	$query10->execute();
	$rows10 = $query10->fetchAll();

	if (count($rows10) != 0) {
		foreach ($rows10 as $row) {

			$total_days = trim($row['semi_month_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Semi Monthly SE';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	//Annually if EndDate is null
	$statement11 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Annually' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query11 = $pdo->prepare($statement11);
	$query11->execute();
	$rows11 = $query11->fetchAll();

	if (count($rows11) != 0) {
		foreach ($rows11 as $row) {

			$total_days = trim($row['annually_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Annually';
			$run_by = 'Scheduled';


			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	//Annually if both StartDate and EndDate are available
	$statement12 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Annually' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query12 = $pdo->prepare($statement12);
	$query12->execute();
	$rows12 = $query12->fetchAll();

	if (count($rows12) != 0) {
		foreach ($rows12 as $row) {

			$total_days = trim($row['annually_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Annually SE';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}
	//Customization if EndDate is null
	$statement13 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Customization' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 AND Time = '" . date("H:i") . "'";

	$query13 = $pdo->prepare($statement13);
	$query13->execute();
	$rows13 = $query13->fetchAll();

	if (count($rows13) != 0) {
		foreach ($rows13 as $row) {

			$total_days = trim($row['no_of_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$custom_start_date = trim($row['custom_start_date']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Customization';
			$run_by = 'Scheduled';


			if ($fileType == 'excel') {
				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchBusinessDay($total_days, $id, $path, $reportName, $custom_start_date, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	//Customization if both StartDate and EndDate are available
	$statement14 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Customization' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 AND Time = '" . date("H:i") . "'";

	$query14 = $pdo->prepare($statement14);
	$query14->execute();
	$rows14 = $query14->fetchAll();

	if (count($rows14) != 0) {

		foreach ($rows14 as $row) {

			$total_days = trim($row['no_of_days']);
			$path = trim($row['directoryPath']);
			$id = trim($row['BatchId']);
			$custom_start_date = trim($row['custom_start_date']);
			$reportName = trim($row['ReportName']);
			$fileType = trim($row['fileType']);
			$code_name = trim($row['code_name']);
			$userType = trim($row['UserType']);
			$userReportName = trim($row['user_report_name']);
			$outputName = trim($row['output_name']);
			$reportDescription = trim($row['report_description']);
			$mailNotification = trim($row['mail_notification']);
			$sftpId = trim($row['sftp_id']);
			$mode = 'Customization SE';
			$run_by = 'Scheduled';

			if ($fileType == 'excel') {

				$outputName = str_replace(" ", "_", $outputName) . '_' . date('Ymd') . '.xlsx';
				fetchBusinessDay($total_days, $id, $path, $reportName, $custom_start_date, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	}

	// Changing Job status at 12:00 am on everyday aaca. to USA timezone. 

	//lk20072026 commented below code to update report status between 11:30 PM and 11:59 PM according to USA/New York timezone.
    // $time = date("h:i a");
	// if ($time == '11:59 pm') {

	// 	update_report_status();
	// }

	//end

	// LK20072026 - Start: Update report status daily between 11 PM and 11:30 PM according to USA/New York timezone.
	$hour = (int) date('H'); 
	$minute = (int) date('i'); 
	
	if ($hour == 23 && $minute >= 0 && $minute <= 30) 
	{ 
		update_report_status(); 
	
	} 

	//LK20072026 - End: Update report status daily between 11 PM and 11:30 PM according to USA/New York timezone.


	compareDailyReport();

	//LK25072026 - send  fatal error mail if any fatal error occurs during the report generation
	if (!empty($GLOBALS['fatalErrors'])) {
		sendConsolidatedFatalMail($GLOBALS['fatalErrors']);
	}

	if (!empty($GLOBALS['reportRunSummary'])) {
		sendDailySummaryMail($GLOBALS['reportRunSummary']);   
	}
	//End LK25072026
	
	exit();
}
//***************************************end********************************************//

function openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath,$isolated = true)
{  
	//LK25072026 - Start: Run the report in isolated process to catch fatal errors
	if ($isolated) {
	$cmd = "/usr/bin/php " . escapeshellarg(__FILE__) . " " . escapeshellarg($id) . " 2>&1";
	$output = shell_exec($cmd);
	echo $output;

	$statusLabel = 'Unknown';
	if (strpos($output, '===== START FATAL CAUGHT') !== false) {
		$statusLabel = 'Fatal';
		if (preg_match('/===== START FATAL CAUGHT (.*?) =====\r?\nReport: (.*?) \(BatchId (.*?)\)\r?\nError: (.*?)\r?\n/s', $output, $m)) {
			$GLOBALS['fatalErrors'][] = [
				'time' => $m[1], 'report' => $m[2], 'batchId' => $m[3], 'error' => trim($m[4]),
			];
		}
	} elseif (strpos($output, '"status_msg":"generated"') !== false) {
		$statusLabel = 'Generated';
	} elseif (strpos($output, '"status_msg":"Failed"') !== false) {
		$statusLabel = 'Failed';
	}

	$GLOBALS['reportRunSummary'][] = [
		'batchId'    => $id,
		'reportName' => $reportName,
		'status'     => $statusLabel,
		'date'       => date('d-m-Y'),
	];

	return;
}

//LK25072026 - End: 
	echo $reportName . "\n";

	global $pdo;
	if ($reportName == 'Costs Where Suit Not Allowed') {
		$output = costs_Where_Suit_Not_Allowed($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Cost Payment Trans hold File') {
		$output = cost_Payment_Trans_Hold_File($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Firm Soft List To AACA Compliance') {
		$output = firm_Soft_List_To_AACA_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Direct Pays Loaded') {
		$output = direct_Pays_Loaded($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Recall List To AACA Compliance') {
		$output = client_Recall_List_To_AACA_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Recall List Review') {
		$output = Client_Recall_List_Review($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Cost Payment Trans hold File All') {
		$output = Cost_Payment_Trans_Hold_File_All($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Trans Reject Report') {
		$output = trans_Reject_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Recalls Compliance Keyed Rejects') {
		$output = client_Recalls_Compliance_Keyed_Rejects($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Firm Cost Check Detail To MyDownload') {
		$output = firm_Cost_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath, $reportBasePath);
	} else if ($reportName == 'Firm Fee Check Detail To MyDownload') {
		$output = firm_Fee_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath, $reportBasePath);
	} else if ($reportName == 'Collection Report NCA') {
		$output = collection_Report_NCA($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Convergence Collections Report') {
		$output = convergence_Collections_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Denied Recalls to My Downloads') {
		$output = client_Denied_Recalls_to_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Denied Recalls Notice to Client') {
		$output = client_Denied_Recalls_Notice_to_Client($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'DP BAL Adjustment Notice To Firm Personnel') {
		$output = Dp_bal_Adjustment_Notice_To_Firm_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Client Placement Acknowledgement MYD') {
		$output = client_Placement_Acknowledgment_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Agency Fee Check Detail To MyDownload') {
		$output = agency_Fee_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Agency Cost Check Detail To MyDownload') {
		$output = agency_Cost_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'DP BAL Adjustment Notice To Agency Personnel') {
		$output = DP_BAL_Adjustment_Notice_To_Agency_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	}
	// ================================================================================================================================= working//

	else if ($reportName == 'File Inquiry Report/Client to My Downloads') {
		$output = file_Inquiry_Report_Client_to_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Reports for AACA - AACA Report') {
		$output = file_Inquiry_Reports_for_AACA_AACA_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Reports For AACA - AACA Request') {
		$output = file_Inquiry_Reports_For_AACA_AACA_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report/Request to MYD - Firm Request') {
		$output = file_Inquiry_Report_Request_to_MYD_Firm_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report/Request to MYD - Firm Report') {
		$output = file_Inquiry_Report_Request_to_MYD_Firm_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report/Request to MYD - Client Request') {
		$output = file_Inquiry_Report_Request_to_MYD_Client_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Notice to Firm Use') {
		$output = file_Inquiry_Notice_to_Firm_Use($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report/Request to MYD - Agency Request') {
		$output = file_Inquiry_Report_Request_to_MYD_Agency_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report/Request to MYD - Agency Report') {
		$output = file_Inquiry_Report_Request_to_MYD_Agency_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Notice to Agency Use') {
		$output = file_Inquiry_Notice_to_Agency_Use($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Remiting Dept Mannual Entry') {
		$output = remiting_Dept_Mannual_Entry($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Placement Accts Hold For Docs Compliance') {
		$output = placement_Accts_Hold_For_Docs_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Placement Accts Hold Notice Client') {
		$output = placement_Accts_Hold_Notice_Client($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report Late Notice – Compliance') {
		$output = file_Inquiry_Report_Late_Notice_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Closing Report Clients Weekly MYD') {
		$output = closing_Report_Clients_Weekly_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Closing Report Clients Weekly Select Clients') {
		$output = closing_Report_Clients_Weekly_Select_Clients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report Late/My Downloads') {
		$output = file_Inquiry_Report_Late_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report Late/My Downloads to Agency') {
		$output = file_Inquiry_Report_Late_My_Downloads_to_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Late Notice To Firms') {
		$output = file_Inquiry_Late_Notice_To_Firms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Late Notice To Agency') {
		$output = file_Inquiry_Late_Notice_To_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'File Inquiry Report Notice to Clients USE') {
		$output = File_Inquiry_Report_Notice_to_Clients_USE($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	}
	// // =======================================================================Monthly Report=====================================================================//
	else if ($reportName == 'Closing Report-Clients') {
		$output = closing_Report_Clients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Closing Report - Clients to Library') {
		$output = closing_Report_Clients_to_Library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Closing Report - FCO SIF/PIF to Library') {
		$output = closing_Report_FCO_SIF_PIF_to_Library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Closing Report - FCO SIF/PIF') {
		$output = closing_Report_FCO_SIF_PIF($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Complaint Log Late Notice Firms') {
		$output = complaint_Log_Late_Notice_Firms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Complaint Log Late Notice Agency') {
		$output = complaint_Log_Late_Notice_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Recon Results Firm MYD') {
		$output = recon_Results_Firm_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Recon Results Agency MYD') {
		$output = recon_Results_Agency_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'PDAR report Firm MYD') {
		$output = PDAR_report_firm_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'PDAR report to library') {
		$output = PDAR_report_to_library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'PDAR Report Agency MYD') {
		$output = PDAR_Report_Agency_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Remit Schedule Month End to firm personnel copy to AACA remitting') {
		$output = remit_Schedule_Month_End_to_firm_personnel_copy_to_AACA_remitting($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Remit Schedule Month End to firm Personnel') {
		$output = remit_Schedule_Month_End_to_firm_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Remit Schedule Month End to Agency Personnel') {
		$output = remit_Schedule_Month_End_to_Agency_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Complaint Log Reminder') {
		$output = complaint_Log_Reminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Call Log Reminder') {
		$output = call_Log_Reminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Call Log Reminder Agency') {
		$output = call_Log_Reminder_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'Complaint Log Reminder Agency') {
		$output = complaint_Log_Reminder_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	} else if ($reportName == 'PDAR reports compliance') {
		$output = PDAR_reports_compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	}

	// ==========================================================================Unifund Monthly Update==========================================//

	else if ($reportName == "Unifund Monthly Update") {
		$checkResult = unifund_inventory_open($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_inventory_close($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_account_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_consumer_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_legal_case_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_placement_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		$checkResult = unifund_paymentandrecon_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		if ($checkResult['status'] == 1) {
			$output = ['status' => $checkResult['status'], 'status_msg' => $checkResult['status_msg'], 'new_status' => $checkResult['new_status']];
		}
		if (empty($output)) {
			$output = array(array('status' => 0, 'status_msg' => "Failed", 'new_status' => 0));
		}
		CreateZipFile($path, $reportBasePath);
	} else {

		$output = MaintFirmSoftClose($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	}

	date_default_timezone_set('America/New_York');
	$update_query2 = "UPDATE `Batch_Report` SET status = '" . $output['new_status'] . "', run_by='$run_by', last_run_date = '" . date("Y-m-d H:i:s") . "' WHERE BatchId ='" . $id . "' ";

	$result2 = $pdo->prepare($update_query2);
	$result2->execute();
	echo "=============================" . "\n";
	echo json_encode(array('status' => $output['status'], 'status_msg' => $output['status_msg']));
	// exit(0);
}

// ===================================================================Daily Reports==========================================================//
function costs_Where_Suit_Not_Allowed($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('CostsWhereSuitNotAllowed.php');
	$result = costsWhereSuitNotAllowed($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function cost_Payment_Trans_Hold_File($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('CostPaymentTransHoldFile.php');
	$result = CostPaymentTransHoldFile($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function firm_Soft_List_To_AACA_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FirmSoftListToAACACompliance.php');
	$result = FirmSoftListToAACACompliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function direct_Pays_Loaded($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('DirectPaysLoaded.php');
	$result = DirectPaysLoaded($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function client_Recall_List_To_AACA_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClientRecallListToAACACompliance.php');
	$result = clientRecallListToAACACompliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function Client_Recall_List_Review($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClientRecallListReview.php');
	$result = ClientRecallListReview($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function Cost_Payment_Trans_Hold_File_All($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('CostPaymentTransHoldFileAll.php');
	$result = costPaymentTransHoldFileAll($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function trans_Reject_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('TransRejectReport.php');
	$result = transRejectReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function client_Recalls_Compliance_Keyed_Rejects($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClientRecallsComplianceKeyedRejects.php');
	$result = ClientRecallsComplianceKeyedRejects($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function firm_Cost_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FirmCostCheckDetailToMyDownload.php');
	$result = firmCostCheckDetailToMyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function firm_Fee_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	require_once('FirmFeeCheckDetailToMyDownload.php');
	$result = firmFeeCheckDetailToMyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function collection_Report_NCA($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('CollectionReportNCA.php');
	$result = collectionreportNCA($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function convergence_Collections_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('ConvergenceCollectionsReport.php');
	$result = convergenceCollectionsReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function Client_Denied_Recalls_to_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('ClientDeniedRecallstoMyDownloads.php');
	$result = clientdeniedrecallstoMyDownloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function client_Denied_Recalls_Notice_to_Client($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('ClientDeniedRecallsNoticetoClient.php');
	$result = clientDeniedRecallsNoticetoClient($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function Dp_bal_Adjustment_Notice_To_Firm_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('DpbalAdjustmentNoticeToFirmPersonnel.php');
	$result = DPbalAdjustmentNoticeToFirmPersonnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function client_Placement_Acknowledgment_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClientPlacementAcknowledgmentMYD.php');
	
	$result = clientPlacementAcknowledgmentMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function agency_Fee_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('AgencyFeeCheckDetailToMyDownload.php');
	$result = agencyFeeCheckDetailToMyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function agency_Cost_Check_Detail_To_MyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('AgencyCostCheckDetailToMyDownload.php');
	$result = agencyCostCheckDetailToMyDownload($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function DP_BAL_Adjustment_Notice_To_Agency_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include('DPBALAdjustmentNoticeToAgencyPersonnel.php');
	$result = DPBALAdjustmentNoticeToAgencyPersonnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

// ===========================================================================================================Weekly Report=========================================//


function file_Inquiry_Reports_for_AACA_AACA_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportsforAACAAACAReport.php');
	$result = fileInquiryReportsforAACAAACAReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Reports_For_AACA_AACA_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportsForAACAAACARequest.php');
	$result = fileInquiryReportsForAACAAACARequest($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Report_Request_to_MYD_Firm_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportRequestToMyDownloadfirmrequest.php');
	$result = fileInquiryReportRequesttoMYDFirmRequest($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Report_Request_to_MYD_Firm_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportRequesttoMYDFirmReport.php');
	$result = fileInquiryReportRequesttoMYDFirmReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Report_Request_to_MYD_Client_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportRequesttoMYDClientRequest.php');
	$result = fileInquiryReportRequesttoMYDClientRequest($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Notice_to_Firm_Use($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryNoticetoFirmUse.php');
	$result = fileInquiryNoticetoFirmUse($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Report_Request_to_MYD_Agency_Request($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportRequesttoMYDAgencyRequest.php');
	$result = fileInquiryReportRequesttoMYDAgencyRequest($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}


function file_Inquiry_Report_Request_to_MYD_Agency_Report($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportRequesttoMYDAgencyReport.php');
	$result = fileInquiryReportRequesttoMYDAgencyReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function file_Inquiry_Notice_to_Agency_Use($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryNoticetoAgencyUse.php');
	$result = FileInquiryNoticetoAgencyUse($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function remiting_Dept_Mannual_Entry($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('RemitingDeptMannualEntry.php');
	$result = remitingDeptMannualEntry($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function placement_Accts_Hold_For_Docs_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('PlacementAcctsHoldForDocsCompliance.php');
	$result = placementAcctsHoldForDocsCompliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function placement_Accts_Hold_Notice_Client($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('PlacementAcctsHoldNoticeClient.php');
	$result = placementAcctsHoldNoticeClient($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function file_Inquiry_Report_Late_Notice_Compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportLateNoticeCompliance.php');
	$result = fileInquiryReportLateNoticeCompliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function closing_Report_Clients_Weekly_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportClientsWeeklyMYD.php');
	$result = closingreportclientsweeklyMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}

function closing_Report_Clients_Weekly_Select_Clients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportClientsWeeklySelectClients.php');
	$result = closingReportClientsWeeklySelectClients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function file_Inquiry_Report_Late_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportLateMyDownloads.php');
	$result = fileInquiryReportLateMyDownloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function file_Inquiry_Report_Late_My_Downloads_to_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportLateMyDownloadstoAgency.php');
	$result = fileInquiryReportLateMyDownloadstoAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
// // ========================================================================Monthly Report=====================================================//
function Closing_Report_Clients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportClients.php');
	
	$result = closingReportClients($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function unifund_Monthly_Update($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundMonthlyUpdate.php');
	$result = unifundMonthlyUpdate($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function closing_Report_Clients_to_Library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportClientstoLibrary.php');
	$result = closingReportClientstoLibrary($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function closing_Report_FCO_SIF_PIF_to_Library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportFCOSIFPIFtoLibrary.php');
	$result = closingReportFCOSIFPIFtoLibrary($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function closing_Report_FCO_SIF_PIF($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ClosingReportFCOSIFPIF.php');
	$result = closingReportFCOSIFPIF($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function complaint_Log_Late_Notice_Firms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ComplaintLogLateNoticeFirms.php');
	$result = complaintLogLateNoticeFirms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function complaint_Log_Late_Notice_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('ComplaintLogLateNoticeAgency.php');
	$result = complaintLogLateNoticeAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function recon_Results_Firm_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('ReconresultsfirmMYD.php');
	$result = reconResultsFirmMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function recon_Results_Agency_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('ReconResultsAgencyMYD.php');
	$result = reconResultsAgencyMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}

function PDAR_report_firm_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('PDARReportFirmMYD.php');
	$result = pdarReportFirmMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function PDAR_report_to_library($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('PDARReporttolibrary.php');
	$result = pdarReporttolibrary($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function PDAR_Report_Agency_MYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('PDARReportAgencyMYD.php');
	$result = pdarReportAgencyMYD($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function remit_Schedule_Month_End_to_firm_personnel_copy_to_AACA_remitting($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('RemitScheduleMonthEndtofirmpersonnelcopytoAACAremitting.php');
	$result = remitScheduleMonthEndtofirmpersonnelcopytoAACAremitting($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function remit_Schedule_Month_End_to_firm_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('RemitScheduleMonthEndtofirmPersonnel.php');
	$result = remitScheduleMonthEndtofirmPersonnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function remit_Schedule_Month_End_to_Agency_Personnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('RemitScheduleMonthEndtoAgencyPersonnel.php');
	$result = remitScheduleMonthEndtoAgencyPersonnel($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}

function complaint_Log_Reminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	global $pdo;
	include_once('ComplaintLogReminder.php');
	$result = complaintLogReminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}

function call_Log_Reminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('CallLogReminder.php');
	$result = callLogReminder($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
	return $result;
}
function call_Log_Reminder_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('CallLogReminderAgency.php');
	$result = callLogReminderAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function complaint_Log_Reminder_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('ComplaintLogReminderAgency.php');
	$result = complaintLogReminderAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}

function MaintFirmSoftClose($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('MaintFirmSoftClose.php');
	$result = maint_firm_soft_close($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function file_Inquiry_Late_Notice_To_Firms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('FileInquiryLateNoticeToFirms.php');
	$result = fileinquirylatenoticeTofirms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function file_Inquiry_Late_Notice_To_Agency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('FileInquiryLateNoticeToAgency.php');
	$result = fileInquiryLateNoticeToAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function File_Inquiry_Report_Notice_to_Clients_USE($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	include_once('FileInquiryReportNoticetoClientsUSE.php');
	$result = FileInquiryReportNoticetoClientsUSE($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function PDAR_reports_compliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('PDARReportsCompliance.php');
	$result = PDARreportscompliance($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function file_Inquiry_Report_Client_to_My_Downloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('FileInquiryReportClienttoMyDownloads.php');
	$result = fileInquiryReportClienttoMyDownloads($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_inventory_open($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundInventoryOpen.php');
	$result = unifundInventoryOpen($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_inventory_close($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundInventoryClose.php');
	$result = unifundInventoryClose($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_account_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundAccountDetail.php');
	$result = unifundAccountDetail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}

function unifund_consumer_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundConsumerDetail.php');
	$result = unifundConsumerDetail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_legal_case_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundLegalCaseDetail.php');
	$result = unifundLegalCaseDetail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_placement_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundPlacementDetail.php');
	$result = unifundPlacementDetail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
function unifund_paymentandrecon_detail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	include_once('UnifundPaymentandreconDetail.php');
	$result = unifundPaymentandreconDetail($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);

	return $result;
}
//************************************************** Common Functions ******************************************//
function getFolderName($file)
{
	if ($file == 'UD') {
		$result = 'ACCOUNT UPDATES';
	} else if ($file == 'AC') {
		$result = 'ACCOUNTING';
	} else if ($file == 'AD') {
		$result = 'ADMINISTRATION';
	} else if ($file == 'AF') {
		$result = 'AFFIDAVITS';
	} else if ($file == 'DP') {
		$result = 'BALANCE/ADJUSTMENT NOTIFICATION';
	} else if ($file == 'RM') {
		$result = 'CLIENT REMITTANCE';
	} else if ($file == 'CS') {
		$result = 'CLIENT SKIP RESULTS';
	} else if ($file == 'CO') {
		$result = 'COMPANY';
	} else if ($file == 'CP') {
		$result = 'COMPLIANCE';
	} else if ($file == 'CM') {
		$result = 'CONTRACT MASTER';
	} else if ($file == 'RD') {
		$result = 'DENIED RECALLS';
	} else if ($file == 'FT') {
		$result = 'FINANCIAL TRANSACTION';
	} else if ($file == 'KH') {
		$result = 'HOLD LIST REVIEW';
	} else if ($file == 'JC') {
		$result = 'JUDGMENT INFORMATION FILE';
	} else if ($file == 'JD') {
		$result = 'JUDGMENT REPORT';
	} else if ($file == 'KP') {
		$result = 'KEEPER LIST REQUEST';
	} else if ($file == 'MD') {
		$result = 'MEDIA';
	} else if ($file == 'IT') {
		$result = 'MIS DEPT';
	} else if ($file == 'PH') {
		$result = 'PHONE REPORT';
	} else if ($file == 'PA') {
		$result = 'PLACEMENT ALLOCATION';
	} else if ($file == 'PL') {
		$result = 'PLACEMENTS';
	} else if ($file == 'RA') {
		$result = 'REASSIGNED PLACEMENTS';
	} else if ($file == 'RC') {
		$result = 'RECALL';
	} else if ($file == 'RJ') {
		$result = 'REJECT';
	} else if ($file == 'RO') {
		$result = 'REOPENED PLACEMENTS';
	} else if ($file == 'WS') {
		$result = 'WEB SERVICES';
	} else if ($file == 'DP') {
		$result = 'DIRECT PAYMENT';
	} else if ($file == 'TR') {
		$result = 'TEST REPORT';
	} else {
		$result = 'MY DOWNLOADS';
	}
	return $result;
}

function getEmailId($code, $userType, $folderName, $path)
{
	global $pdo;
	$result = [];
	if ($userType == 1) {
		if ($folderName == 'MY DOWNLOADS') {
			$split = explode("/", $path);
			$newFileName = $split[count($split) - 1];

			$fetchQuery = $pdo->prepare("SELECT DISTINCT RGEMAIL FROM WSREGUSR WHERE RGUSER = '" . $newFileName . "'");
			$fetchQuery->execute();
			$rows_data = $fetchQuery->fetchAll();

			if (count($rows_data)) {
				foreach ($rows_data as $row)
					$result[] = $row['RGEMAIL'];
			} else {

				$result[] = $newFileName;
			}
		} else {
			$statement = "SELECT DISTINCT email from tbl_login WHERE FIND_IN_SET('" . $folderName . "',UserGroup) AND userType ='" . $userType . "' AND company_status != 4 AND bit_deleted_flag = 0";

			$query = $pdo->prepare($statement);
			$query->execute();
			$rows = $query->fetchAll();

			if (count($rows)) {
				foreach ($rows as $row)
					$result[] = $row['email'];
			}
		}
	}

	if ($userType == 2) {
		if ($folderName == 'MY DOWNLOADS') {
			$split = explode("/", $path);
			$newFileName = $split[count($split) - 1];
			$fetchQuery = $pdo->prepare("SELECT DISTINCT RGEMAIL FROM WSREGUSR WHERE RGUSER = '" . $newFileName . "'");
			$fetchQuery->execute();
			$rows_data = $fetchQuery->fetchAll();
			if (count($rows_data)) {
				foreach ($rows_data as $row)
					$result[] = $row['RGEMAIL'];
			} else {
				$result[] = $newFileName;
			}
		} else {
			$statement = "SELECT DISTINCT email from tbl_login WHERE ((FIND_IN_SET('" . $code . "',firmCode))>0 OR firmCode='ALL') AND FIND_IN_SET('" . $folderName . "',UserGroup) AND userType ='" . $userType . "' AND company_status != 4 AND bit_deleted_flag = 0 ";

			$query = $pdo->prepare($statement);
			$query->execute();
			$rows = $query->fetchAll();

			if (count($rows)) {
				foreach ($rows as $row)
					$result[] = $row['email'];
			}
		}
	} else if ($userType == 3) {
		if ($folderName == 'MY DOWNLOADS') {
			$split = explode("/", $path);
			$newFileName = $split[count($split) - 1];
			$fetchQuery = $pdo->prepare("SELECT DISTINCT RGEMAIL FROM WSREGUSR WHERE RGUSER = '" . $newFileName . "'");
			$fetchQuery->execute();
			$rows_data = $fetchQuery->fetchAll();
			if (count($rows_data)) {
				foreach ($rows_data as $row)
					$result[] = $row['RGEMAIL'];
			} else {
				$result[] = $newFileName;
			}
		} else {
			$statement = "SELECT DISTINCT email from tbl_login  WHERE ((FIND_IN_SET('" . $code . "',clientCode))>0 OR clientCode='ALL') AND FIND_IN_SET('" . $folderName . "',UserGroup) AND userType ='" . $userType . "' AND company_status != 4 AND bit_deleted_flag = 0 ";
			$query = $pdo->prepare($statement);
			$query->execute();
			$rows = $query->fetchAll();

			if (count($rows)) {
				foreach ($rows as $row)
					$result[] = $row['email'];
			}
		}
	} else if ($userType == 4) {
		if ($folderName == 'MY DOWNLOADS') {
			$split = explode("/", $path);
			$newFileName = $split[count($split) - 1];


			$fetchQuery = $pdo->prepare("SELECT DISTINCT RGEMAIL FROM WSREGUSR WHERE RGUSER = '" . $newFileName . "'");
			$fetchQuery->execute();
			$rows_data = $fetchQuery->fetchAll();
			if (count($rows_data)) {
				foreach ($rows_data as $row)
					$result[] = $row['RGEMAIL'];
			} else {
				$result[] = $newFileName;
			}
		} else {
			$statement = "SELECT DISTINCT email from tbl_login  WHERE ((FIND_IN_SET('" . $code . "',firmCode))>0 OR firmCode='ALL') AND FIND_IN_SET('" . $folderName . "',UserGroup) AND userType ='" . $userType . "' AND company_status != 4 AND bit_deleted_flag = 0 ";

			$query = $pdo->prepare($statement);
			$query->execute();
			$rows = $query->fetchAll();

			if (count($rows)) {
				foreach ($rows as $row)
					$result[] = $row['email'];
			}
		}
	}
	return $result;
}

function update_report_status()
{
	global $pdo;
	$statement = "SELECT BatchId FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND IsDelete = 0 ";
	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchAll();
	$query->closeCursor();

	if (count($rows) != 0) {
		foreach ($rows as $row) {
			$id = $row['BatchId'];
			upload_data($id);
		}
	}
	$statement2 = "SELECT BatchId FROM Batch_Report WHERE EndDate = '' AND IsDelete = 0";
	$query2 = $pdo->prepare($statement2);
	$query2->execute();
	$rows2 = $query2->fetchAll();
	$query2->closeCursor();
	if (count($rows2) != 0) {
		foreach ($rows2 as $row) {
			$id = $row['BatchId'];
			upload_data($id);
		}
	}
}

function upload_data($id)
{
	global $pdo;
	$statement = "UPDATE `Batch_Report` SET status = 0 WHERE BatchId ='" . $id . "' ";
	$query = $pdo->prepare($statement);
	$query->execute();
	$query->closeCursor();
}

function compareDailyReport()
{
	global $pdo;
	$current_date = date("Y-m-d");
	$statement = "SELECT batchId FROM SCHEDULER_DAILY_REPORT_LOGS WHERE createdAt = '" . $current_date . "' ";
	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchAll();
	if (count($rows) != 0) {
		foreach ($rows as $row) {
			$ids[] = $row['batchId'];
		}
	}

	if (isset($ids)) {
		$str = implode(',', $ids);
		$statement2 = "SELECT * FROM Batch_Report WHERE BatchId IN ($str) AND (SELECT count(1) FROM Batch_Report WHERE BatchId IN ($str)) = (SELECT count(1) FROM Batch_Report WHERE BatchId IN ($str) AND (status = 2 OR status = 3))";

		$query2 = $pdo->prepare($statement2);
		$query2->execute();
		$rows2 = $query2->fetchAll();

		if (count($rows2) != 0) {
			dailyReportMail();
		}
	}
}

function dailyReportMail()
{
	global $pdo;
	$current_date = date("Y-m-d");

	$statement = "SELECT * FROM SCHEDULER_LOGS WHERE createdAt = '" . $current_date . "' AND is_sent = 0";
	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchAll();
	$query->closeCursor();
	if (count($rows) != 0) {

		$message = '<div>Hi,</div><br>';
		$message .= 'Please find the daily scheduled report status.<br><br>';
		$message .= '<table rules="all" style="border-color: #666; table-layout: fixed; width: 450px;" cellpadding="10">';
		$message .= "<tr style='background: #eee;'><th>Report Name:</th><th style='width: 78px;'>Recipients:</th><th>Start Time:</th><th>End Time:</th><th>Report Status:</th></tr>";

		foreach ($rows as $value) {
			if ($value['status'] == 1) {
				$status = "Success";
			} else {
				$status = "No Data available";
				//$status = "Failed";
			}

			if ($value['code'] == "ALL") {
				$code = "AACA";
			} else {
				$code = $value['code'];
			}
			$message .= "<tr style='background: #eee;'><td>" . $value['report_name'] . "</td><td>" . $code . "</td><td>" . $value['start_time'] . "</td><td>" . $value['end_time'] . "</td><td>" . $status . "</td></tr>";
		}
		$message .= "</table>";
		$message .= "Thank you.";
		$date = date("m/d/Y");
		$subject = 'Scheduled Report Status ' . $date . '';
		$contacts = array("pipeway.support@goolean.tech", "twright@aacanet.org", "Daniel@aacanet.org");
		foreach ($contacts as $contact) {
			Send_email($subject, $message, $contact);
		}
		$statement2 = "UPDATE SCHEDULER_LOGS SET is_sent = 1 WHERE createdAt = '" . $current_date . "' ";
		$query2 = $pdo->prepare($statement2);
		$query2->execute();
		DailyReportCount();
	}
}

function dailyReportDetails()
{
	global $pdo;
	$current_date = date('Y-m-d');
	$day = date('l', strtotime($current_date));

	if ($day == 'Monday') {
		$day = " AND FIND_IN_SET('1',Day)";
	} else if ($day == 'Tuesday') {
		$day = " AND FIND_IN_SET('2',Day)";
	} else if ($day == 'Wednesday') {
		$day = " AND FIND_IN_SET('3',Day)";
	} else if ($day == 'Thursday') {
		$day = " AND FIND_IN_SET('4',Day)";
	} else if ($day == 'Friday') {
		$day = " AND FIND_IN_SET('5',Day)";
	} else if ($day == 'Saturday') {
		$day = " AND FIND_IN_SET('6',Day)";
	} else if ($day == 'Sunday') {
		$day = " AND FIND_IN_SET('7',Day)";
	}

	// Daily if EndDate is null
	$statement = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Daily' AND IsDelete = 0 AND schedule_status = 0 AND status =0";
	$query2 = $pdo->prepare($statement);
	$query2->execute();
	$rows1 = $query2->fetchAll();
	if ($rows1 != 0) {
		foreach ($rows1 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			insertReportData($id, $reportName);
		}
	}

	// Daily if both StartDate and EndDate are available
	$statement2 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Daily' AND IsDelete = 0 AND schedule_status = 0 AND status = 0";
	$query2 = $pdo->prepare($statement2);
	$query2->execute();
	$rows2 = $query2->fetchAll();

	if (count($rows2) != 0) {
		foreach ($rows2 as $row) {
			$id = $row['BatchId'];;
			$reportName = $row['ReportName'];
			insertReportData($id, $reportName);
		}
	}

	// Weekly if EndDate is null
	$statement3 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Weekly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $day;
	$query3 = $pdo->prepare($statement3);
	$query3->execute();
	$rows3 = $query3->fetchAll();
	if (count($rows3) != 0) {
		foreach ($rows3 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			insertReportData($id, $reportName);
		}
	}

	// Weekly if both StartDate and EndDate are available
	$statement4 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Weekly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0" . $day;
	$query4 = $pdo->prepare($statement4);
	$query4->execute();
	$rows4 = $query4->fetchAll();
	if (count($rows4) != 0) {
		foreach ($rows4 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			insertReportData($id, $reportName);
		}
	}
	$current_month = fetchMonths($current_date);

	// Monthly if EndDate is null
	$statement5 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Monthly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0";
	$query5 = $pdo->prepare($statement5);
	$query5->execute();
	$rows5 = $query5->fetchAll();

	if (count($rows5) != 0) {
		foreach ($rows5 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['monthly_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}

	// Monthly if both StartDate and EndDate are available
	$statement6 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Monthly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 ";
	$query6 = $pdo->prepare($statement6);
	$query6->execute();
	$rows6 = $query6->fetchAll();
	if (count($rows6) != 0) {
		foreach ($rows6 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['monthly_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}
	$current_month = fetchMonths($current_date);

	//Quarterly if EndDate is null
	$statement7 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Quarterly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0 " . $current_month;
	$query7 = $pdo->prepare($statement7);
	$query7->execute();
	$rows7 = $query7->fetchAll();

	if (count($rows7) != 0) {
		foreach ($rows7 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['quarterly_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}

	//****************Quarterly if both StartDate and EndDate are available**********************//
	$statement8 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Quarterly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query8 = $pdo->prepare($statement8);
	$query8->execute();
	$rows8 = $query8->fetchAll();
	if (count($rows8) != 0) {
		foreach ($rows8 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['quarterly_days'];

			fetchDayNew($total_days, $id, $reportName);
		}
	}
	//Semi Monthly if EndDate is null
	$statement9 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'semi_monthly' AND IsDelete = 0 AND status = 0 AND schedule_status = 0 " . $current_month;

	$query9 = $pdo->prepare($statement9);
	$query9->execute();
	$rows9 = $query9->fetchAll();

	if (count($rows9) != 0) {
		foreach ($rows9 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['semi_month_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}
	//Semi Monthly if both StartDate and EndDate are available
	$statement10 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'semi_monthly' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query10 = $pdo->prepare($statement10);
	$query10->execute();
	$rows10 = $query10->fetchAll();

	if (count($rows10) != 0) {
		foreach ($rows10 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['semi_month_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}
	//Annually if EndDate is null
	$statement11 = "SELECT * FROM Batch_Report WHERE EndDate = '' AND recurrence_pattern = 'Annually' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query11 = $pdo->prepare($statement11);
	$query11->execute();
	$rows11 = $query11->fetchAll();

	if (count($rows11) != 0) {
		foreach ($rows11 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['annually_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}
	//Annually if both StartDate and EndDate are available
	$statement12 = "SELECT * FROM Batch_Report WHERE DATE(NOW()) BETWEEN StartDate AND EndDate AND recurrence_pattern = 'Annually' AND IsDelete = 0 AND schedule_status = 0 AND status = 0 " . $current_month;

	$query12 = $pdo->prepare($statement12);
	$query12->execute();
	$rows12 = $query12->fetchAll();

	if (count($rows12) != 0) {
		foreach ($rows12 as $row) {
			$id = $row['BatchId'];
			$reportName = $row['ReportName'];
			$total_days = $row['annually_days'];
			fetchDayNew($total_days, $id, $reportName);
		}
	}
}
function insertReportData($id, $report_name)
{

	global $pdo;
	$current_date = date("Y-m-d");
	$report_end_time = date("H:i:s");
	$statement = "SELECT * FROM SCHEDULER_DAILY_REPORT_LOGS WHERE batchId = '" . $id . "' AND createdAt = '" . $current_date . "' ";

	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchColumn();
	if ($rows == 0) {
		$insert_logs = "INSERT INTO `SCHEDULER_DAILY_REPORT_LOGS` (reportName,batchId,createdAt) VALUES ('" . $report_name . "','" . $id . "','" . $current_date . "') ";

		$log_query = $pdo->prepare($insert_logs);
		$log_query->execute();
		$log_query->closeCursor();
	}
}
function fetchDayNew($total_days, $id, $reportName)
{
	$day = date('d');
	$exp = explode(",", $total_days);

	// Last day(int) of the current month
	$lastday = date('t', strtotime(date('Y-m-d')));
	if ($day == $lastday) {
		if (in_array("32", $exp)) {
			insertReportData($id, $reportName);
		} else {
			if (in_array($day, $exp)) {
				insertReportData($id, $reportName);
			}
		}
	} else {
		if (in_array($day, $exp)) {
			insertReportData($id, $reportName);
		}
	}
}

function fetchDay($total_days, $id, $path, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{

	$day = date('d');
	$exp = explode(",", $total_days);

	// Last day(int) of the current month
	$lastday = date('t', strtotime(date('Y-m-d')));
	if ($day == $lastday) {
		if (in_array("32", $exp)) {
			openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		} else {
			if (in_array($day, $exp)) {
				openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
			}
		}
	} else {
		if (in_array($day, $exp)) {
			openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
		}
	}
}

function fetchMonths($current_date)

{
	$month = date('M', strtotime($current_date));

	if ($month == 'Jan') {
		$month = " AND FIND_IN_SET('Jan',months)";
	} else if ($month == 'Feb') {
		$month = " AND FIND_IN_SET('Feb',months)";
	} else if ($month == 'Mar') {
		$month = " AND FIND_IN_SET('Mar',months)";
	} else if ($month == 'Apr') {
		$month = " AND FIND_IN_SET('Apr',months)";
	} else if ($month == 'May') {
		$month = " AND FIND_IN_SET('May',months)";
	} else if ($month == 'Jun') {
		$month = " AND FIND_IN_SET('Jun',months)";
	} else if ($month == 'Jul') {
		$month = " AND FIND_IN_SET('Jul',months)";
	} else if ($month == 'Aug') {
		$month = " AND FIND_IN_SET('Aug',months)";
	} else if ($month == 'Sep') {
		$month = " AND FIND_IN_SET('Sep',months)";
	} else if ($month == 'Oct') {
		$month = " AND FIND_IN_SET('Oct',months)";
	} else if ($month == 'Nov') {
		$month = " AND FIND_IN_SET('Nov',months)";
	} else if ($month == 'Dec') {
		$month = " AND FIND_IN_SET('Dec',months)";
	}

	return $month;
}

function fetchBusinessDay($total_days, $id, $path, $reportName, $custom_start_date, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
	try {


		$result = new stdClass();
		global $pdo;
		$days = explode(",", $total_days);

		foreach ($days as $row) {
			if ($row == 32) {
				$holidays_list = "SELECT * FROM HOLIDAYS_LIST";
				$query = $pdo->prepare($holidays_list);
				$query->execute();

				$rows = $query->fetchAll();
				$query->closeCursor();

				$lastdateofthemonth = date("Y-m-t");

				$lastworkingday = date('l', strtotime($lastdateofthemonth));

				if ($lastworkingday == "Saturday") {
					$newdate = strtotime('-1 day', strtotime($lastdateofthemonth));
					$lastworkingday = date('Y-m-j', $newdate);

					foreach ($rows as $row) {
						if ($lastworkingday == $row['DATE']) {
							$newdate = strtotime('-1 day', strtotime($lastworkingday));
							$lastworkingday = date('Y-m-j', $newdate);
						}
					}
				} elseif ($lastworkingday == "Sunday") {
					$newdate = strtotime('-2 day', strtotime($lastdateofthemonth));
					$lastworkingday = date('Y-m-j', $newdate);

					foreach ($rows as $row) {
						if ($lastworkingday == $row['DATE']) {
							$newdate = strtotime('-1 day', strtotime($lastworkingday));
							$lastworkingday = date('Y-m-j', $newdate);
						}
					}
				} else {
					foreach ($rows as $row) {
						if ($lastdateofthemonth == $row['DATE']) {
							$newdate = strtotime('-1 day', strtotime($lastdateofthemonth));
							$lastworkingday = date('Y-m-j', $newdate);
						}
					}

					$lastworkingday2 = date('l', strtotime($lastworkingday));
					if ($lastworkingday2 == "Saturday") {
						$newdate = strtotime('-1 day', strtotime($lastworkingday));
						$lastworkingday = date('Y-m-j', $newdate);
					} elseif ($lastworkingday2 == "Sunday") {
						$newdate = strtotime('-2 day', strtotime($lastworkingday));
						$lastworkingday = date('Y-m-j', $newdate);
					}
				}

				if (date('Y-m-d') == $lastworkingday) {

					openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
				}
			} else {
				$statement = "SELECT BUSINESSDATES('" . $custom_start_date . "',$row) as business_date";
				$query = $pdo->prepare($statement);
				$query->execute();

				$rows = $query->fetchAll();
				$query->closeCursor();

				if ($rows[0]['business_date'] == date('Y-m-d')) {

					openInventoryReport($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath);
				}
			}
		}
	} catch (Exception $e) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
		echo 'Line no.',  $e->getLine();
	}
}

function new_SMTP($userReportName, $outputName, $reportDescription, $folderName, $email, $mailBody)
{
	global $pdo;
	// include('PHPMailer/compliancemailcode.php');
	global  $mail;
	$mail->clearAddresses();
	$mail->addAddress($email);
	$mail->isHTML(true);
	$mail->Subject = $userReportName;

	if ($folderName == 'MY DOWNLOADS') {
		$mail->Body = "<div style='margin-bottom:10px'>A new report called " . $outputName . " has been placed in your " . $folderName . " folder. The report " . $reportDescription . ". Please proceed to Pipeway to retrieve this report.<br> Thank you.</div>";
	} else {
		if ($mailBody) {
			$mail->Body = $mailBody;
		} else {
			$mail->Body = "<div style='margin-bottom:10px'>A new report called " . $outputName . " has been placed in your my downloads under " . $folderName . " folder. The report " . $reportDescription . ". Please proceed to Pipeway to retrieve this report.<br> Thank you.</div>";
		}
	}

	if (!$mail->send()) {

		echo "Email not sent. ", $mail->ErrorInfo, PHP_EOL;
	} else {

		echo "Email sent successfully";
	}
	return $result;
}

function sftpCredentials($sftpId, $paths, $filename, $code, $status)
{
	global $pdo;

	$statement = "SELECT * FROM SCHEDULER_SFTP WHERE id IN ($sftpId)";
	$query = $pdo->prepare($statement);
	$query->execute();
	$rows = $query->fetchAll();
	if (count($rows) != 0) {
		foreach ($rows as $row) {
			if ($row['code'] == $code) {
				$conn = ssh2_connect($row['host_name'], 22);
				$sftp = ssh2_auth_password($conn, $row['username'], base64_decode($row['password']));

				if (!$sftp) {

					$msg = "Login failed for " . $row['host_name'] . " server, please check the credentials";
					$status = 0;
					$status_msg = "login failed";
					copy('/var/www/html/bi/dist/' . $paths . DIRECTORY_SEPARATOR . $filename, '/var/www/html/bi/dist/Mako/downloadfile/AACA/RF/' . $filename);
				} else {
					if (ssh2_scp_send($conn, "/var/www/html/bi/dist/" . $paths . DIRECTORY_SEPARATOR . $filename, $row['path'] . $filename, 0644)) {
						$msg = "Report generated successfully for " . $row['host_name'] . " server";
						$status = 1;
						$status_msg = "generated";
					} else {

						$msg = "Uploading error for " . $row['host_name'] . " server";
						$status = 0;
						$status_msg = "uploading error";

						copy('/var/www/html/bi/dist/' . $paths . DIRECTORY_SEPARATOR . $filename, '/var/www/html/bi/dist/Mako/downloadfile/AACA/RF/' . $filename);
					}
				}
				$result = array('msg' => $msg, 'status' => $status, 'status_msg' => $status_msg);
			} else {
				$msg = "Report generated successfully";
				$status = 1;
				$status_msg = "generated";
				$result = array('msg' => $msg, 'status' => $status, 'status_msg' => $status_msg);
			}
		}
	}
	return $result;
}

function Send_email($subject, $body, $email)
{

	global $mail;
	$mail->clearAddresses();
	$mail->addAddress($email);
	$mail->isHTML(true);
	$mail->From = '';
	$mail->FromName = '';

	// $mail->From = 'donotreply@aacanet.org';
	$mail->From = 'donotreply@gatewayportfolio.com';
	
	$mail->FromName = 'Pipeway 2.0';
	$mail->Subject = $subject;
	$mail->Body = $body;

	if (!$mail->send()) {
		echo "Email not sent. ", $mail->ErrorInfo, PHP_EOL;
	}
}

function scheduler_logs($status, $reportName, $report_start_time, $msg, $new_row, $sftpStatus, $FileSizeKB, $filePath, $run_by)
{

	global $pdo;
	$current_date = date("Y-m-d");
	$report_end_time = date("H:i:s");
	$insert_logs = "INSERT INTO SCHEDULER_LOGS(report_name,start_time,end_time,status,message,code,file_path,sftp,createdAt,file_size,run_by)VALUES ('" . $reportName . "','" . $report_start_time . "','" . $report_end_time . "','" . $status . "','" . $msg . "','" . $new_row . "','" . $filePath . " ','" . $sftpStatus . "','" . $current_date . "','" . $FileSizeKB . "','" . $run_by . "') ";

	$log_query = $pdo->prepare($insert_logs);
	$log_query->execute();

	if ($status == 0) {
		if ($new_row == 'ALL') {
			$new_row = 'AACA';
		}
		$message = '<div>Hi,</div><br>';
		$message .= 'The report "' . $reportName . '" has been failed for ' . $new_row . ' recepients.<br>';
		$message .= 'Error message : "' . $msg . '" <br>';
		$message .= 'For further information please go through <b>/home/PipewayWeb/log/scheduler_cron.txt</b> <br>';
		$message .= "Thank you.";

		$subject = 'Scheduler Report Failure';
		$contacts = array("pipeway.support@goolean.tech");
		foreach ($contacts as $contact) {
			// Send_email($subject, $message, $contact);
		}
	}
}




function getResult($query)
{
	global $pdo;
	$queryResult = $pdo->prepare($query);
	$queryResult->execute();
	$rows = $queryResult->rowCount();
	$queryResult = $queryResult->fetchAll(PDO::FETCH_ASSOC);
	return ['numRows' => $rows, 'results' => $queryResult];
}

function filterReportName($date, $reportName)
{
	$current_date = $date->format("m-d-Y H:i:s.v");
	$reportName = str_replace("/", " ", $reportName);
	$reportName = trim(str_replace('-', '_', $reportName));
	$reportName = trim(str_replace(' ', '_', $reportName));

	$filename = $reportName . "(" . $current_date . ").xlsx";
	return $filename;
}
function filterReportNameForTextExtension($date, $reportName)
{

	$current_date = $date->format("m-d-Y H:i:s.v");
	$reportName = str_replace("/", " ", $reportName);
	$reportName = trim(str_replace('-', '_', $reportName));
	$reportName = trim(str_replace(' ', '_', $reportName));

	$filename = $reportName . "(" . $current_date . ").txt";
	return $filename;
}


function createDirgetCompanyName(string $fileDir, $reportBasePath)
{
	if (!is_dir($reportBasePath . $fileDir)) {
		mkdir($reportBasePath . $fileDir, 0777, true);
		chmod($reportBasePath . $fileDir, 0777);
	}

	$parts = explode('downloadfile/', $fileDir);
	if (isset($parts[1])) {
		$getName = explode('/', $parts[1]);
		if (isset($getName[0])) {
			return $getName[0];
		}
	}
}
function checkCompanynameinResultArray($array, $firmName)
{
	foreach ($array as $item) {
		if (isset($item['FIRM']) && $item['FIRM'] === $firmName) {
			return true;
		}
	}
	return false;
}
function returnPath($array, $str)
{
	foreach ($array as $item) {
		if (strpos($item, $str) !== false) {
			$foundValue = $item;
			break; // Stop once we find the value
		}
	}
	if ($foundValue !== null) {
		return str_replace(["'", " "], "", $foundValue);
	}
	return false;
}
function ifDataNotPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $path, $run_by, $userType)
{
	ReportStatus($companyStatus, $companyName, $reportName, $userType);
	$msg = 'No Data Present';
	$sftpStatus = 0;
	$status = 0;
	scheduler_logs($status, $reportName, $report_start_time, $msg, $companyName, $sftpStatus, $FileSizeKB, $path, $run_by);
}

function ifDataPresent($companyStatus, $companyName, $reportName, $report_start_time, $sftpStatus, $FileSizeKB, $path, $run_by, $userType)
{
	$msg = 'Report generated successfully';
	$sftpStatus = 0;
	$status = 1;
	scheduler_logs($status, $reportName, $report_start_time, $msg, $companyName, $sftpStatus, $FileSizeKB, $path, $run_by);
}
function getCompanyStatus($companyName)
{
	global $pdo;
	$checkCompanyStatus = $pdo->prepare("SELECT * FROM `CC_REGSTR` WHERE Isdeleted = 0 AND CCODE = '" . $companyName . "'");
	$checkCompanyStatus->execute();
	$data = $checkCompanyStatus->fetchAll();
	$cmpstatus = $data[0]['CSTATUS'];
	return $cmpstatus;
}
function ReportStatus($companyStatus, $companyName, $reportName, $userType)
{

	global $mail;
	if (isset($companyName)) {
		$cmpStatus = '';
		if ($companyStatus == '1') {
			$cmpStatus = 'Acitve';
		}
		if ($companyStatus == '2') {
			$cmpStatus = 'Panding';
		}
		if ($companyStatus == '3') {
			$cmpStatus = 'Inactive';
		}
		if ($companyStatus == '4') {
			$cmpStatus = 'Terminate';
		}
		$mailer = "pipeway.support@goolean.tech";
		$mail->clearAddresses();
		$mail->addAddress($mailer);
		$mail->Subject = $reportName;
		if ($userType == '1') {
			$mail->Body = "<div style='margin-bottom:10px'>The " . $companyName . " has  no data. <br>Thank you.</div>";
		}
		if ($userType == '2') {
			$mail->Body = "<div style='margin-bottom:10px'>The Firm " . $companyName . " is currently in " . $cmpStatus . " status, but there is no data. <br>Thank you.</div>";
		}
		if ($userType == '3') {
			$mail->Body = "<div style='margin-bottom:10px'>The Client " . $companyName . " is currently in " . $cmpStatus . " status, but there is no data. <br>Thank you.</div>";
		}
		if ($userType == '4') {
			$mail->Body = "<div style='margin-bottom:10px'>The Agency " . $companyName . " is currently in " . $cmpStatus . " status, but there is no data. <br>Thank you.</div>";
		}
		$mail->send();
	}
}
function hitquerywithnoResult($query)
{
	global $pdo;
	$queryResult = $pdo->prepare($query);
	if ($queryResult->execute()) {
		return  true;
	} else {
		return false;
	}
}
function mailNotifaction($mailNotification, $reportpath, $companyName, $userType, $userReportName, $reportDescription)
{

	if ($mailNotification == 1) {
		$split = explode("/", $reportpath);
		$fileName = $split[count($split) - 1];
		$folderName = getFolderName(trim($fileName));
		$emailId = getEmailId($companyName, $userType, $folderName, $reportpath);

		foreach ($emailId as $email) {
			$outputName = $fileName;
			// new_SMTP($userReportName, $outputName, $reportDescription, $folderName, $email);
		}
	}
}
function getDisFolder(string $fileDir, $reportBasePath)
{
	if (!is_dir($reportBasePath . $fileDir)) {

		mkdir($reportBasePath . $fileDir, 0777, true);
		chmod($reportBasePath . $fileDir, 0777);
	}

	$parts = explode('downloadfile/', $fileDir);
	if (isset($parts[1])) {
		$getName = explode('/', $parts[1]);
		if (isset($getName[1])) {
			return $getName[1];
		}
	}
}
function getfileSize(string $filepath)
{
	$fileSize = filesize($filepath);
	$FileSizeKB = round($fileSize / 1024) . "KB";
	return $FileSizeKB;
}


function CreateZipFile($dirPath, $basePath)
{
	date_default_timezone_set('America/New_York');
	$currentDate = date('m-d-Y');
	$directory = $basePath . $dirPath;
	if (!is_dir($directory)) {
		echo "Directory does not exist: " . $directory;
		return;
	}

	chdir($directory);

	$files = scandir($directory);

	$matchedFiles = [];

	foreach ($files as $file) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		if (is_file($directory . DIRECTORY_SEPARATOR . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {

			if (strpos($file, $currentDate) !== false) {

				$matchedFiles[] = $file;
			}
		}
	}
	if (count($matchedFiles) > 0) {

		$zip = new ZipArchive();
		$zipFile = trim($basePath . $dirPath . DIRECTORY_SEPARATOR . 'UnifundMonthlyUpdate' . $currentDate . '.zip');


		if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {

			foreach ($matchedFiles as $file) {

				$zip->addFile($directory . DIRECTORY_SEPARATOR . $file, $file);
			}
			$zip->close();

			echo "Zip file created successfully: " . $zipFile;
		} else {
			echo "Failed to create zip file.";
		}
	} else {
		echo "No matching .txt files found for the current date.";
	}
	foreach ($files as $file) {
		if ($file == '.' || $file == '..') {
			continue;
		}
		if (is_file($directory . DIRECTORY_SEPARATOR . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
			if (strpos($file, $currentDate) !== false) {
				unlink($file);
			}
		}
	}
}


// function dailyReportStatus()
// {
	
// 	date_default_timezone_set('America/New_York');
// 	$currentDate = date('m-d-Y');
// 	global $mail;
// 	global $pdo;
// 	$query = "SELECT report_name,start_time,end_time,code,status,message,run_by from SCHEDULER_LOGS  where createdAt='2025-01-06' order by createdAt desc";
// 	$queryResult = $pdo->prepare($query);
// 	$queryResult->execute();
// 	$totalReports = $queryResult->fetchAll(PDO::FETCH_ASSOC);

// 	$mailer = "pipeway.support@goolean.tech";
// 	$mail->clearAddresses();
// 	$mail->addAddress($mailer);
// 	$mail->Subject = 'Daily Report Status  : ' . $currentDate;

// 	$message = "
// 	<table class='table'>
//     	<thead>
//         	<tr>
//             	<th scope='col'>#</th>
//             	<th scope='col'>Report Name</th>
//             	<th scope='col'>Start Time</th>
//             	<th scope='col'>End Time</th>
// 				<th scope='col'>Code</th>
// 				<th scope='col'>Status</th>
// 				<th scope='col'>Run By</th>
//         	</tr>
//     	</thead>
//     	<tbody>";
// 	foreach ($totalReports as $key => $report) {

// 		$message .= "<tr>
// 				<td>" . ($key + 1) . "</td>
// 				<td>" . $report['report_name'] . "</td>
// 				<td>" . $report['start_time'] . "</td>
// 				<td>" . $report['end_time'] . "</td>
// 				<td>" . $report['code'] . "</td>
// 				<td>" . $report['status'] . "</td>
// 				<td>" . $report['run_by'] . "</td>
// 			</tr>";
// 	}

// 	$message .= "
//     	</tbody>

// 	</table>";
// 	$mail->Body = $message;
// 	$mail->send();
	
// }
function DailyReportCount()
{
	date_default_timezone_set('America/New_York');
	$currentDate = date('m-d-Y');
	global $mail;
	global $pdo;
	echo "Daily Report Count.....";
	$query = "SELECT recurrence_pattern, COUNT(ReportName) AS 'Report Count'
	FROM Batch_Report
	WHERE status<>0
	AND schedule_status <> '1'
	AND (Day = (CASE WHEN recurrence_pattern = 'Daily' THEN ' ' ELSE NULL END)
	OR (CASE WHEN recurrence_pattern = 'Weekly' THEN FIND_IN_SET((weekday(CURRENT_DATE)+1), Day) > 0 ELSE NULL END)
	OR (CASE WHEN recurrence_pattern = 'Monthly' THEN FIND_IN_SET(day(CURRENT_DATE()), monthly_days) > 0 ELSE NULL END))
	GROUP BY recurrence_pattern";
	$queryResult = $pdo->prepare($query);
	$queryResult->execute();
	$totalReportsCounts = $queryResult->fetchAll(PDO::FETCH_ASSOC);
	$message = '';
	$totalCount = 0;
	$mailer = "pipeway.support@goolean.tech";
	$mail->clearAddresses();
	$mail->addAddress($mailer);
	$mail->addAddress("pipeway.support@goolean.tech");
	$mail->Subject = 'Reports Count: ' . $currentDate;
	$message .="<br> Daily Report Run Count <br>";
	$message .= "<br>-------------------------------------------------------------<br>";
	foreach ($totalReportsCounts as $count) {
		if ($count['recurrence_pattern'] === "Daily") {
			$message .= "Daily Report Counts : " . $count['Report Count'] . "<br>";
		}
		if ($count['recurrence_pattern'] === "Weekly") {
			$message .= "Weekly Report Counts : " . $count['Report Count'] . "<br>";
		}

		if ($count['recurrence_pattern'] === "Monthly") {
			$message .= "Monthly Report Counts : " . $count['Report Count'] . "<br>";
		}
		$totalCount += $count['Report Count'];
	}
	$message .= "<br>------------------------------------------------------------<br>";
	$message .= "<br>Total Report Count : " . $totalCount;
	$mail->Body = $message;
	$mail->send();
}


//Added by LK25072026 for fatal error mail and report status mail
//******************************for fatal error LK25072026******************************* */
function sendConsolidatedFatalMail($errors)
{
	global $mail;
	if (empty($errors)) {
		return;
	}
	$date = date('d-m-Y');
	$subject = "Scheduler Fatal Errors - " . $date;

	$body = "<p>Following reports failed with fatal errors during today's (" . $date . ") scheduler run:</p>";
	$body .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>";
	$body .= "<tr style='background:#eee;'><th>Report Name</th><th>BatchId</th><th>Error</th></tr>";
	foreach ($errors as $e) {
		$body .= "<tr><td>" . htmlspecialchars($e['report']) . "</td><td>" . htmlspecialchars($e['batchId']) . "</td><td>" . htmlspecialchars($e['error']) . "</td></tr>";
	}
	$body .= "</table>";

	try {
		$mail->clearAddresses();
		$mail->addAddress('techassistance@gatewayportfolio.com');
		$mail->CharSet = 'UTF-8';
		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $body;
		
		 if ($mail->send()) {
           echo "Fatal mail sent successfully.\n";
		} else {
			echo "Summary mail failed: " . $mail->ErrorInfo . "\n";
		}
		} catch (Exception $ex) {
		echo "Summary mail failed: " . $ex->getMessage() . "\n";
		}
}


//******************************end fatal error LK25072026******************************* */

//******************************for Report Status LK25072026******************************* */
function sendDailySummaryMail($summary)
{
	global $mail;
	if (empty($summary)) {
		return;
	}

	$dateHeading = date('d F Y (l)');
	$subject = "Scheduled Reports Status - " . date('d-m-Y');

	// Display-label mapping — 
	$displayLabel = [
		'Generated' => 'Generated',
		'Failed'    => 'No Data Found',
		'Fatal'     => 'Skip',
		'Unknown'   => 'Unknown',
	];


	$generatedCount = 0;
	$failedCount = 0;
	$fatalCount = 0;
	$rows = '';
	$i = 1;

	foreach ($summary as $r) {
		if ($r['status'] === 'Generated') $generatedCount++;
		elseif ($r['status'] === 'Failed') $failedCount++;
		elseif ($r['status'] === 'Fatal') $fatalCount++;

		$label = $displayLabel[$r['status']] ?? $r['status'];

		$rows .= "<tr>"
			. "<td>" . $i++ . "</td>"
			. "<td>" . htmlspecialchars($r['batchId']) . "</td>"
			. "<td>" . htmlspecialchars($r['reportName']) . "</td>"
			// . "<td>" . htmlspecialchars($r['status']) . "</td>"
			. "<td>" . htmlspecialchars($label) . "</td>"
			. "<td>" . htmlspecialchars($r['date']) . "</td>"
			. "</tr>";
	}

	// $body = "<h3>" . $dateHeading . " — Scheduled Reports Status</h3>";
	$body = "<h3>" . $dateHeading . " - Scheduled Reports Status</h3>";
	$body .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;'>";
	$body .= "<tr style='background:#eee;'><th>#</th><th>BatchId</th><th>Report Name</th><th>Status</th><th>Date</th></tr>";
	$body .= $rows;
	$body .= "</table>";

	$body .= "<br><p><b>Summary:</b><br>";
	$body .= "Generated: " . $generatedCount . "<br>";
	$body .= "No Data Found: " . $failedCount . "<br>";
	$body .= "Skip: " . $fatalCount . "<br>";
	$body .= "Total: " . count($summary) . "</p>";
	try {
		$mail->clearAddresses();
		$mail->addAddress('techassistance@gatewayportfolio.com');
		$mail->CharSet = 'UTF-8';
		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->send();
	} catch (Exception $ex) {
	}
}

//******************************end Report Status LK25072026******************************* */



//*************************************************Common Functions end*****************************************//
