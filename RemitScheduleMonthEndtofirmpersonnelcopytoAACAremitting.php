
/* CHANGELOG:
  * -----------------------------------------------------------------------------------------------------------
  * Version | Date       | Author              | Description
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-08-17 | LK17082026          | Added else condition for no data present and data present
  *         |            |                     | inserted for scheduler logs
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-08-18 | LK18082026          | Replaced AACANet with GPS wherever it appeared in the text
  *         |            |                     | 
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  */

<?php
function remitScheduleMonthEndtofirmpersonnelcopytoAACAremitting($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
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

    // LK17082026: added for schedular logs for AACA remitting.
    $sftpStatus = 0;
    $FileSizeKB = 0;
    //LK17082026end

    $query = "SELECT distinct CURR_ATTY_NME ,CURR_ATTY_CD, 'MERMT' AS MESSCODE
			FROM HSFLCLNTWF
			WHERE HACL = 0
			GROUP BY CURR_ATTY_NME";
    $results = getResult($query);
    if ($results['numRows'] > 0) {
        $emailid = array();
        $emailid = getEmailId('AACA', $userType, $distinationFolder, $path = '');
        if ($emailid) {
            foreach ($emailid as $email) {
                Sendmail12($email);
            }
            array_push($dataPresents, array(
                'code' => 'AACA'
            ));

            //LK17062026: added log  
           scheduler_logs(1, $reportName, $report_start_time, 'Notice sent successfully', 'AACA' , $sftpStatus, $FileSizeKB, $distinationFolder, $run_by);
        }
        else {  //LK17062026: added when no email recipient found for AACA, log added
                array_push($noDataPresents, array(
                    'code' => 'AACA'
                ));
 
             scheduler_logs(0, $reportName, $report_start_time,'No Data Present', 'AACA' , $sftpStatus, $FileSizeKB, $distinationFolder, $run_by);
            }
    } else {
        array_push($noDataPresents, array(
            'code' => 'AACA'
        ));

        //LK17062026: added for the case when no data is present for any client code. In this case, earlier there was no log generated.
        scheduler_logs(0, $reportName, $report_start_time,'No Data Present', 'AACA' , $sftpStatus, $FileSizeKB, $distinationFolder, $run_by);
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

function Sendmail12($newid)
{
    global $mail;
    $mail->Body = '';
    $mail->clearAddresses();
    $mail->addAddress($newid);
    //LK18082026 updated Subject msg - AACANet renamed to GPS 
    // $mail->Subject = 'Remit Schedule Month End to firm Personnel To Aaca Remitting';
    $mail->Subject = 'Remit Schedule Month End to firm Personnel To GPS Remitting';
    $query = "WITH CTE AS
    ( SELECT distinct CURR_ATTY_NME ,CURR_ATTY_CD, 'MERMT' AS MESSCODE
    FROM HSFLCLNTWF
    WHERE HACL = 0
    GROUP BY CURR_ATTY_NME
    )
    SELECT distinct WFEMBD AS 'notice'
    from CTE A
    left JOIN WFEMBDRM B ON A.MESSCODE = B.WFEMBDCD
        ORDER BY A.CURR_ATTY_NME, B.WFSEQNUM";
    $getemailBody = getResult($query);

    foreach ($getemailBody['results'] as $result) {
        $mail->Body .= $result['notice'] . "\n";
    }
    if (!$mail->send()) {

        echo "Email not sent. ", $mail->ErrorInfo, PHP_EOL;
    }
}
