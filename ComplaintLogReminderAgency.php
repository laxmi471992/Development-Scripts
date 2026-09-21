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
  * 1.1     | 2026-18-08 | LK18082026          | Replaced AACANet with GPS and Email id wherever it appeared in the text
  *         |            |                     | 
  *         |            |                     | 
  * -----------------------------------------------------------------------------------------------------------
  */
function complaintLogReminderAgency($path, $id, $reportName, $code_name, $userType, $userReportName, $outputName, $reportDescription, $mailNotification, $sftpId, $mode, $run_by, $reportBasePath)
{
    $report_start_time = date("H:i:s");
    //LK08082026 added for scheduler logs
    $sftpStatus = 0;
    $FileSizeKB = 0;
    //LK08082026 end
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
    $query = "SELECT DISTINCT CURR_ATTY_NME, 'cmplog' AS MESSCODE,CURR_ATTY_CD
    FROM HSFLCLNTWF
    WHERE HACL = 0
    AND CURR_STS_CD NOT IN ('120','122','123','12E','12R','12C','12A','12B')
    group by  CURR_ATTY_NME";
    $results = getResult($query);
    if ($results['numRows'] > 0) {


        foreach ($results['results'] as $result) {
            if (in_array($result['CURR_ATTY_CD'], $codeNames)) {
                $emailid = array();
                $emailid = getEmailId($result['CURR_ATTY_CD'], $userType, $distinationFolder, $path = '');
                if ($emailid) {
                    foreach ($emailid as $email) {
                        Sendmail($email);
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
    }else{   //LK08082026 added for scheduler logs no data present
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

function Sendmail($newid)
{

    global $mail;
    $mail->clearAddresses();
    $mail->addAddress($newid);

    $mail->Subject = 'complaint Log Reminder Agency';

    //LK18082026 updated body msg AACA TO GPS
    // $mail->Body = "<p>As a reminder your monthly complaint log is due by the first business day of the
    // month. If there are no Customer Complaints a report showing no complaints must  
    // still be submitted.</p>                                                        
    // <p>AACA requires all Law Firms to keep a log of all customer complaints on accounts
    // placed with the firm and report each such customer complaint using AACA's      
    // Complaint Log Template monthly.  Law Firms are to report all complaints received
    // since the last reporting period regardless of whether they are resolved prior to
    // the reporting period.  Any complaint that remains unresolved at the time of     
    // reporting shall continue to be reported until it has been reported as resolved  
    // or the complaint has been reported as having been escalated to the client in    
    // according with the AACA escalation policy. <p>
    // <p>Please submit your report using My Uploads with the naming scheme of            
    // COMPLAINT LOG_DATE.  If you have questions, please contact                      
    // <a href='mailto:Compliance@gatewayportfolio.com' >Compliance@gatewayportfolio.com</a></p>";

    $mail->Body = "<p>As a reminder your monthly complaint log is due by the first business day of the
    month. If there are no Customer Complaints a report showing no complaints must  
    still be submitted.</p>                                                        
    <p>GPS requires all Law Firms to keep a log of all customer complaints on accounts
    placed with the firm and report each such customer complaint using GPS's      
    Complaint Log Template monthly.  Law Firms are to report all complaints received
    since the last reporting period regardless of whether they are resolved prior to
    the reporting period.  Any complaint that remains unresolved at the time of     
    reporting shall continue to be reported until it has been reported as resolved  
    or the complaint has been reported as having been escalated to the client in    
    according with the GPS escalation policy. <p>
    <p>Please submit your report using My Uploads with the naming scheme of            
    COMPLAINT LOG_DATE.  If you have questions, please contact                      
    <a href='mailto:Compliance@gatewayportfolio.com' >Compliance@gatewayportfolio.com</a></p>";
    
    $mail->send();
}

