<?php
 /* CHANGELOG:
  * -----------------------------------------------------------------------------------------------------------
  * Version | Date       | Author              | Description
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-08-08 | LK08082026          | Added scheduler logs for notice sent successfully and no data present
  *         |            |                     | 
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  * 
  * 1.1     | 2026-18-08 | LK18082026          | Replaced AACANet with GPS and Email id wherever it appeared in the text
  *         |            |                     | 
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  */

function fileinquirylatenoticeTofirms($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
    $report_start_time = date("H:i:s");
    $date = new DateTime();
    $fileName = filterReportName($date, $reportName);
    $paths = explode(",", $path);
    $dataPresents = array();
    $noDataPresents = array();
    $new_status = 3;
    $msg = 'No Data Present';
    $status_msg = 'Failed';
    $sftpStatus = 0;
    $status = 0;
    $FileSizeKB = 0;
    $folder = explode(",", $path);
    $distFolder = getDisFolder($folder[0], $reportBasePath);
    $distinationFolder = getFolderName($distFolder);

    foreach ($paths as $companyPath) {
        $companyPath = str_replace(["'", " "], "", $companyPath);
        $companyName = createDirgetCompanyName($companyPath, $reportBasePath);
        $companyStatus = getCompanyStatus($companyName);
        $query = "WITH CTE AS
            (
            SELECT B.RMSASNDESC, C.ORGORGDESC, A.ATKUSERS,A.ATKFRCD AS 'Firm',D.CURR_STS_CD,
            (CASE WHEN A.ATKUSERE <> '  ' THEN A.ATKUSERE
            WHEN A.ATKUSERL <> '     ' THEN A.ATKUSERL ELSE A.ATKUSERS END) AS USER_LAST,
            (CASE WHEN A.ATKTIMEE <> '  ' THEN A.ATKTIMEE
            WHEN A.ATKTIMEL <> '     ' THEN A.ATKTIMEL ELSE A.ATKTIMES END) AS TIME_LAST,
            (CASE WHEN A.ATKDATEE <> '        ' THEN CAST(A.ATKDATEE AS DATE)
            WHEN A.ATKDATEL <> '        ' THEN CAST(A.ATKDATEL AS DATE) ELSE CAST(A.ATKDATES AS DATE) END) AS DATE_LAST,
            (CASE WHEN A.ATKDATEE = '        ' THEN 'O' ELSE 'C' END) AS OPEN_CLOSE,
            (CASE WHEN A.ATKDATEE <> '        ' THEN A.ATKNTCODEE
            WHEN A.ATKDATEL <> '        ' THEN A.ATKNTCODEL ELSE A.ATKNTCODES END) AS CODE_LAST,
            (CASE WHEN A.ATKDATEE <> '        ' THEN A.ATKNTDESCE
            WHEN A.ATKDATEL <> '        ' THEN A.ATKNTDESCL ELSE A.ATKNTDESCS END) AS CODEDESC_LAST,
            (CASE WHEN A.ATKDATEE <> '        ' THEN A.ATKTKTSKTE
            WHEN A.ATKDATEL <> '        ' THEN A.ATKTKTSKTL ELSE A.ATKTKTSKTS END) AS TASK_TO_LAST,
            DATEDIFF(CURRENT_DATE(),CAST(A.ATKDATES AS DATE)) AS DAYS_FROM_OPEN,
            DATEDIFF(CURRENT_DATE(), (CASE WHEN A.ATKDATEE <> '        ' THEN CAST(A.ATKDATEE AS DATE)
            WHEN A.ATKDATEL <> '        ' THEN CAST(A.ATKDATEL AS DATE) ELSE CAST(A.ATKDATES AS DATE) END)) AS DAYS_FROM_LAST,
            DATEDIFF(CURRENT_DATE(),CAST(A.ATKNXCDDT AS DATE)) AS DAYS_LATE,
            (CASE WHEN A.ATKNXCDDT = '        ' THEN 'None' ELSE CAST(A.ATKNXCDDT AS DATE) END) AS NEXT_UPDATE_BY_DATE_1,
            (CASE WHEN A.ATKDATEE <> '        ' THEN 'Closed'
            WHEN (CASE WHEN A.ATKNXCDDT = '        ' THEN 'None' ELSE CAST(A.ATKNXCDDT AS DATE) END) <> 'None' AND
            (CASE WHEN A.ATKNXCDDT = '        ' THEN 'None' ELSE CAST(A.ATKNXCDDT AS DATE) END) < CURRENT_DATE() THEN 'Late'
            WHEN A.ATKDATEL = '        ' THEN 'New' ELSE 'Open' END) AS TICKET_STATUS,
            (CASE WHEN A.ATKTKTRCDE <> '  ' THEN A.ATKTKTRCDE
            WHEN A.ATKTKTRCDL <> '     ' THEN A.ATKTKTRCDL ELSE A.ATKTKTRCDS END) AS TRACK_LAST
            FROM ATKFATKMS A
            LEFT JOIN RMSPSYSASN B ON A.ATKFRCD = B.RMSOFFCRCD
            LEFT JOIN RMSPSYSORG C ON A.ATKORGCD = C.ORGLEVEL2 AND C.ORGTYPECDE =1 AND C.ORGRECTYPE = 2
            LEFT JOIN HSFLCLNTWF D ON A.ATKFILENUM = D.FILNUM
            WHERE 
            D.CURR_STS_CD NOT LIKE '9%'
            AND D.CURR_STS_CD NOT IN ('120','122','123','12E','12R','12A','12B','12C')
            )
            SELECT DISTINCT RMSASNDESC,  COUNT(RMSASNDESC) AS '# Tickets', ROUND(AVG(DAYS_LATE),2) AS 'Average Days Late'
            FROM CTE
            WHERE DATE_LAST <= CURRENT_DATE()
            AND TICKET_STATUS = 'Late'
            AND TASK_TO_LAST = 'F'
            and CURR_STS_CD <'900' AND CURR_STS_CD NOT IN ('120','122','123','12E','12R','12A','12B','12C')
            and Firm ='" . $companyName . "'
            GROUP BY RMSASNDESC, ORGORGDESC";

        $results = getResult($query);
        if ($results['numRows'] > 0) {
            $emailid = array();
            $emailid = getEmailId($companyName, $userType, $distinationFolder, $path = '');
            if ($emailid) {
                foreach ($emailid as $email) {
                    Sendmail6($email);
                }
                array_push($dataPresents, array(
                    'clientcode' => $companyName
                )); 
                
                //LK08082026 added for scheduler logs notice sent successfully
                scheduler_logs(1, $reportName, $report_start_time, 'Notice sent successfully', $companyName , $sftpStatus, $FileSizeKB, $companyPath, $run_by);
            }
        } else {
            array_push($noDataPresents, array(
                'clientcode' => $companyName
            ));

             //LK08082026 added for scheduler logs no data present
            scheduler_logs( 0, $reportName, $report_start_time,'No Data Present', $companyName, $sftpStatus, $FileSizeKB, $companyPath,$run_by );
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
    }

    return array('status' => $status, 'status_msg' => $status_msg, 'new_status' => $new_status);
}
function Sendmail6($newid)
{
    global $mail;
    $mail->clearAddresses();
    $mail->addAddress($newid);
    $mail->Subject = 'File Inquiry Late Notice To Firm';
    //LK18082026: updated body msg - AACANet renamed to GPS (email address was already correct, no change needed there)
    // $mail->Body = "
    // AACANet has previously sent a request for information about one or more accounts.
    // Our records show that you have failed to respond to requests in a timely manner. 
    // A report showing all LATE responses has been placed in your company My Downloads folder. 
    // Because these responses are LATE and the client is in need of a response.  Therefore your immediate action is required. 
    // Responses must be returned by the end of business Thursday to remove the request from the list.

    // To respond, firms must provide information in the column provided on the spreadsheet and return it using My Uploads. 
    // Make sure you select File Inquiry Response in the drop-down prior to sending.  
    // If you need help responding to the request or sending your responses, please contact Compliance@gatewayportfolio.com. 
    // Thank you for your immediate attention to this matter.<br>This is System Generated Email Please ignore you have already Responded.";
    $mail->Body = "
    GPS has previously sent a request for information about one or more accounts.
    Our records show that you have failed to respond to requests in a timely manner. 
    A report showing all LATE responses has been placed in your company My Downloads folder. 
    Because these responses are LATE and the client is in need of a response.  Therefore your immediate action is required. 
    Responses must be returned by the end of business Thursday to remove the request from the list.

    To respond, firms must provide information in the column provided on the spreadsheet and return it using My Uploads. 
    Make sure you select File Inquiry Response in the drop-down prior to sending.  
    If you need help responding to the request or sending your responses, please contact Compliance@gatewayportfolio.com. 
    Thank you for your immediate attention to this matter.<br>This is System Generated Email Please ignore you have already Responded.";
    if (!$mail->send()) {
		echo "Email not sent. ", $mail->ErrorInfo, PHP_EOL;
	}
}
