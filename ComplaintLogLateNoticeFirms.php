<?php

/* CHANGELOG:
  * -----------------------------------------------------------------------------------------------------------
  * Version | Date       | Author              | Description
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-08-08 | LK08082026          | Added else condition for no data present and data present
  *         |            |                     | inserted for scheduler logs
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  * 
  * 1.1     | 2026-18-08 | LK18082026          | Replaced AACANet with GPS wherever it appeared in the text
  *         |            |                     | 
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  */
function complaintLogLateNoticeFirms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
    $report_start_time = date("H:i:s");
    $date = new DateTime();
    $codeNames = explode(",", $code_name);
    $dataPresents = array();
    $noDataPresents = array();
    $new_status = 3;
    $status_msg = 'Failed';
    $status = 0;
    $folder = explode(",", $path);
    $distFolder = getDisFolder($folder[0], $reportBasePath);
    $distinationFolder = getFolderName($distFolder);

    //LK08082026 added for scheduler logs
    $sftpStatus = 0;
    $FileSizeKB = 0;
    //LK08082026 end

    $query = "SELECT DISTINCT  B.CURR_ATTY_CD
		FROM CMPLOGNR A
		INNER JOIN HSFLCLNTWF B 
		ON A.FIRMCD = B.CURR_ATTY_CD
		WHERE B.HACL = 0
		AND B.CURR_STS_CD NOT IN ('12R','123','122','12E','12S','120','12C')
		AND B.CURR_ATTY_CD <> ''
		ORDER BY B.CURR_ATTY_NME";
    $results = getResult($query);
    if ($results['numRows'] > 0) {

        foreach ($results['results'] as $result) {
            if (in_array($result['CURR_ATTY_CD'], $codeNames)) {
                $emailid = array();
                $emailid = getEmailId($result['CURR_ATTY_CD'], $userType, $distinationFolder, $path = '');
                if ($emailid) {
                    foreach ($emailid as $email) {
                        Sendmail10($email);
                    }
                    array_push($dataPresents, array(
                        'clientcode' => $result['CURR_ATTY_CD']
                    ));

                    
                    //LK08082026 added for scheduler logs
                    scheduler_logs(1, $reportName, $report_start_time, 'Notice sent successfully', $result['CURR_ATTY_CD'], $sftpStatus, $FileSizeKB, $distinationFolder, $run_by);
                   //LK08082026 end
                }
            } else {
                array_push($noDataPresents, array(
                    'clientcode' => $result['CURR_ATTY_CD']
                ));

                 //LK08082026 added for scheduler logs
                scheduler_logs(0, $reportName, $report_start_time, 'No Data Present', $result['CURR_ATTY_CD'], $sftpStatus, $FileSizeKB, $distinationFolder, $run_by);
            }
        }
    }else{
        //LK08082026 added for scheduler logs
        scheduler_logs(0, $reportName, $report_start_time, 'No Data Present', 'ALL', $sftpStatus, $FileSizeKB, 'N/A', $run_by);
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

function Sendmail10($newid)
{
    global $mail;
    $mail->clearAddresses();
    $mail->addAddress($newid);
    $mail->Subject = 'Complaint Log Late Notice Firms';
    // AH20260729 | Compliance domain updated to Gateway Portfolio
    // $mail->Body = "<p>We failed to receive a complaint log from your firm for the preceding month.  Complaint logs are due on the 1st business day of the following month.  AACANet requires that firms submit the log even if the firm did not have any consumer complaints to report.  If you are experiencing difficulty with submitting the log, please contact <a href='mailto:Compliance@aacanet.org'>Compliance@aacanet.org</a>. Otherwise please submit the log for last month.  Thank you</p>";
    // LK18082026 Updated AACANet to GPS
    //$mail->Body = "<p>We failed to receive a complaint log from your firm for the preceding month.  Complaint logs are due on the 1st business day of the following month.  AACANet requires that firms submit the log even if the firm did not have any consumer complaints to report.  If you are experiencing difficulty with submitting the log, please contact <a href='mailto:Compliance@gatewayportfolio.com'>Compliance@gatewayportfolio.com</a>. Otherwise please submit the log for last month.  Thank you</p>";
    $mail->Body = "<p>We failed to receive a complaint log from your firm for the preceding month.  Complaint logs are due on the 1st business day of the following month.  GPS requires that firms submit the log even if the firm did not have any consumer complaints to report.  If you are experiencing difficulty with submitting the log, please contact <a href='mailto:Compliance@gatewayportfolio.com'>Compliance@gatewayportfolio.com</a>. Otherwise please submit the log for last month.  Thank you</p>";

    if (!$mail->send()) {
        echo "Email not sent. ", $mail->ErrorInfo, PHP_EOL;
    }
}
