<?php

  /* CHANGELOG:
  * -----------------------------------------------------------------------------------------------------------
  * Version | Date       | Author              | Description
  * -----------------------------------------------------------------------------------------------------------
  * 1.0     | 2026-08-01 | LK01082026          | Added Generation Summary in Scheduled Batch Report Details.
  *         |            |                     | Displayed Start Date & End Date in one row.
  *         |            |                     | Updated Job Status to show Hold for reports on hold.
  * -----------------------------------------------------------------------------------------------------------
  */
include_once '../config.php';

if ($_POST['reportId'] !='') {

 $query = "SELECT * FROM Batch_Report WHERE BatchId = '".$_POST['reportId']."'";

 $result = mysqli_query($conn,$query);
 $response = "<table border='0' width='100%' class='TFtable'>";
 while( $row = mysqli_fetch_array($result) ){
    $StartDate =$row['StartDate'];
	
	$Time      =$row['Time'];
	$Day       =$row['Day'];
	$expday    =explode(',',$Day);
	$ReportName=$row['ReportName'];
	$UserType  =$row['UserType'];
	$UserName  =$row['UserName'];
	$Subject   =$row['Subject'];
	$recurrence_pattern = $row['recurrence_pattern'];
	$months = $row['months'];
	$monthly_days = $row['monthly_days'];
	$Type      =$row['Type'];
	$no_of_days = $row['no_of_days'];
  $last_run_date = $row['last_run_date'];
  
  //LK01082026 FOR GENERATE SUMMARY
  $totalRunClients = array();
  $successRunClients = array();
  $skippedRunClients = array();

  if ($last_run_date != '') {
      $lastRunDateOnly = date('Y-m-d', strtotime($last_run_date));
      $logQuery = "SELECT code, status FROM SCHEDULER_LOGS WHERE report_name = '".mysqli_real_escape_string($conn, $ReportName)."' AND createdAt = '".$lastRunDateOnly."'";
      $logResult = mysqli_query($conn, $logQuery);
      if ($logResult) {
          while ($logRow = mysqli_fetch_assoc($logResult)) {
              $code = trim($logRow['code']);
              if ($code == '') continue;
              $totalRunClients[$code] = $code;
              if ($logRow['status'] == 1) {
                  $successRunClients[$code] = $code;
              } else {
                  $skippedRunClients[$code] = $code;
              }
          }
      }
  }
  natcasesort($totalRunClients);
  natcasesort($successRunClients);
  natcasesort($skippedRunClients);

  $totalRunCount    = count($totalRunClients);
  $successRunCount  = count($successRunClients);
  $successRunText   = !empty($successRunClients) ? implode(', ', $successRunClients) : '-';
  $skippedRunText   = !empty($skippedRunClients) ? implode(', ', $skippedRunClients) : '-';

  $successRunShort = (strlen($successRunText) > 50) ? substr($successRunText, 0, 50).'...' : $successRunText;
  $skippedRunShort = (strlen($skippedRunText) > 50) ? substr($skippedRunText, 0, 50).'...' : $skippedRunText;
  //LK01082026 end


  $fullDirectoryPath = $row['directoryPath'];

  //LK01082026 start for total directory client count
 $directoryClients = array();
  $directoryPathsArr = explode(',', (string)$fullDirectoryPath);
  foreach ($directoryPathsArr as $singlePath) {
      $singlePath = trim($singlePath, " \t\n\r\0\x0B'\"");
      $singlePath = str_replace('\\', '/', $singlePath);
      $singlePath = preg_replace('#/+#', '/', $singlePath);
      $singlePath = trim($singlePath, '/ ');
      if ($singlePath == '') continue;
      $parts = explode('/', $singlePath);
      foreach ($parts as $idx => $part) {
          if (strcasecmp($part, 'downloadfile') == 0 && isset($parts[$idx+1])) {
              $code = trim($parts[$idx+1]);
              if ($code != '') {
                  $directoryClients[$code] = $code;
              }
          }
      }
  }
  $totalDirectoryClientCount = count($directoryClients);
  //end LK01082026

  $sftp = $row['sftp_id'];
  if(strlen($row['directoryPath']) > 50) {
  $directoryPath = substr($row['directoryPath'], 0, 50). '...';
  }
  else
  {
    $directoryPath = $row['directoryPath'];
  }
	$CreatedBy =$row['CreatedBy'];
	$id = $row['BatchId'];
  if($UserType==1){
    $usertypename='AACA';
  }else if($UserType==2){
	  $usertypename='Firm';
	}else if($UserType==3){
	   $usertypename='Client';
	}else if($UserType==4){
	   $usertypename='Agency';
	}

  if($row['status'] == 0)
  {
    $status = 'Scheduled';
  }else if($row['status'] == 1){
    $status = 'In Execution';
  }else if($row['status'] == 2){
    $status = 'Completed';
  }

   //LK01082026 start for hold status
   if($row['schedule_status'] == 1)
{
    $status = 'Hold';
}
  //LK01082026 end

	if($row['EndDate'] != '')
    {
      $EndDate = $row['EndDate'];
      $endDate = date('m-d-Y', strtotime($row['EndDate']));
    }
    else
    {
      $EndDate = 'No end date';
      $endDate = 'No end date';
    }

    // if($row['Subject'] != '')
    // {
    //   $Subject = $row['Subject'];
    // }
    // else
    // {
    //   $Subject = 'No Subject available';
    // }

    // if($row['Type'] != '')
    // {
    //   $Type = $row['Type'];
    // }
    // else
    // {
    //   $Type = 'No Type available';
    // }

    if($recurrence_pattern != '')
    {
      $recurrence_pattern = $recurrence_pattern;
    }
    else
    {
       $recurrence_pattern = 'No pattern available';
    }


    if($months != '')
    {
       $months = $months;
    }
    else
    {
       $months = 'Months not available';
    }


    if($monthly_days != '')
    {
       $monthly_days = $monthly_days;
    }
    


    if($Day != '')
     {

	$weekDays = array();

	foreach($expday as $newday){
	    if($newday==1){
	      $weekDays[] = "Monday ";
	    }else if($newday==2){
	      $weekDays[] = "Tuesday ";
	    }else if($newday==3){
	      $weekDays[] = "Wednesday ";
	    }else if($newday==4){
	      $weekDays[] = "Thursday ";
	    }else if($newday==5){
	      $weekDays[] = "Friday ";
	    }else if($newday==6){
	      $weekDays[] = "Saturday ";
	    }else if($newday==7){
	      $weekDays[] = "Sunday ";
	    }
	    } 

	    $imp_day = implode(', ',$weekDays);

   	}
   	else
   	{
   		$imp_day = "No day available";
   	}


//LK01082026 start for changes start date and end date in one row

//  $response .= "<tr>";
//  $response .= "<td>Start Date: </td><td>".date('m-d-Y', strtotime($StartDate))."</td>";
//  $response .= "</tr>";

//  $response .= "<tr>";
//  $response .= "<td>End Date: </td><td>".$endDate."</td>";
//  $response .= "</tr>";

 $response .= "<tr>";
 $response .= "<td>Start Date: </td>";
 $response .= "<td style='padding:0;'>
   <table width='100%' style='border-collapse:collapse;'>
     <tr>
       <td style='padding:8px 5px; width:50%;'>".date('m-d-Y', strtotime($StartDate))."</td>
       <td style='padding:8px 5px; border-left:1px solid #b6c9e2;'><b>End Date:</b> ".$endDate."</td>
     </tr>
   </table>
 </td>";
 $response .= "</tr>";

 //LK01082026 change end


 if($recurrence_pattern == 'Customization'){
 $response .= "<tr>";
 $response .= "<td>Time: </td><td>".$Time."</td>";
 $response .= "</tr>";
 }

 // $response .= "<tr>";
 // $response .= "<td>Day: </td><td>".$imp_day."</td>";
 // $response .= "</tr>";

 if($recurrence_pattern == 'Daily' && $row['EndDate'] == '')
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for everyday Starting from ".$newStartDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'Daily' && $row['EndDate'] != '')
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for everyday Starting from ".$newStartDate." till ".$newEndDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'Weekly' && $row['EndDate'] == '')
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$imp_day." of every week Starting ".$newStartDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'Weekly' && $row['EndDate'] != '')
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$imp_day." of every week Starting from ".$newStartDate." and Ending at ".$newEndDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'Monthly' && !empty($row['EndDate']))
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));

  $array_dates = explode(",",$monthly_days);

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }


  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of every months Starting from ".$newStartDate." and Ending at ".$newEndDate."</td>";
 $response .= "</tr>";
 }

 if($recurrence_pattern == 'Monthly' && empty($row['EndDate']))
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  
  $newTime = date('g:i a', strtotime($Time));

  $array_dates = explode(",",$monthly_days);

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }

  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);
	

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of every months Starting from ".$newStartDate."</td>";
 $response .= "</tr>";
 }


 if($recurrence_pattern == 'Quarterly' && $row['EndDate'] == '')
 {
 	
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));
  $monthly_days = $row['quarterly_days'];
  $array_dates = explode(",",$monthly_days);
  $quarterly_months = $row['months'];

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }


  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of following months ".$quarterly_months." Starting from ".$newStartDate."</td>";
 $response .= "</tr>";

 }

  if($recurrence_pattern == 'Quarterly' && $row['EndDate'] != '')
 {
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));
  $monthly_days = $row['quarterly_days'];

  $quarterly_months = $row['months'];

  $array_dates = explode(",",$monthly_days);

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }

  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);
	

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of following months ".$quarterly_months." Starting from ".$newStartDate." till ".$newEndDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'semi_monthly' && $row['EndDate'] == '')
 {
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));
 
  $semi_months = $row['semi_month_days'];

  if($semi_months == '1,15')
  {
  	$final_dates = '1<sup>st</sup> and 15<sup>th</sup>';
  }
  else if ($semi_months == '15,32')
  {
  	$final_dates = '15<sup>th</sup> and month end';
  }

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of every months Starting from ".$newStartDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'semi_monthly' && $row['EndDate'] != '')
 {
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));
  $semi_months = $row['semi_month_days'];

  if($semi_months == '1,15')
  {
  	$final_dates = '1<sup>st</sup> and 15<sup>th</sup>';
  }
  else if ($semi_months == '15,32')
  {
  	$final_dates = '15<sup>th</sup> and month end';
  }
	

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of every months Starting from ".$newStartDate." till ".$newEndDate."</td>";
 $response .= "</tr>";

 }

 if($recurrence_pattern == 'Annually' && $row['EndDate'] == '')
 {
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));
  
  $array_dates = explode(",",$monthly_days);
  $anually_months = $row['months'];

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }


  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of following months ".$anually_months." Starting from ".$newStartDate."</td>";
 $response .= "</tr>";
 }

 if($recurrence_pattern == 'Annually' && $row['EndDate'] != '')
 {
  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));
 
  $anually_months = $row['months'];

  $array_dates = explode(",",$monthly_days);

  foreach($array_dates as $row)
  {
  if($row == 32)
   {
	$array_new = array(" month end");
	end($array_dates);        
	$key = key($array_dates); 
	unset($array_dates[$key]);

	$array_dates = array_merge($array_dates, $array_new);
   }
  }

  $last  = array_slice($array_dates, -1);
  $first = join(', ', array_slice($array_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);
	

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for every ".$final_dates." of following months ".$anually_months." Starting from ".$newStartDate." till ".$newEndDate."</td>";
 $response .= "</tr>";
 }

 if($recurrence_pattern == 'Customization' && empty($row['EndDate']))
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newTime = date('g:i a', strtotime($Time));

  $array_dates = explode(",",$no_of_days);

  foreach($array_dates as $row)
  {
  if($row == 1)
   {
	$array_new_dates[] = '1<sup>st</sup>'; 
   }
   if($row == 2)
   {
	$array_new_dates[] = '2<sup>nd</sup>'; 
   }
   if($row == 3)
   {
	$array_new_dates[] = '3<sup>rd</sup>'; 
   }
   if($row == 4)
   {
	$array_new_dates[] = '4<sup>th</sup>'; 
   }
   if($row == 5)
   {
	$array_new_dates[] = '5<sup>th</sup>'; 
   }
   if($row == 6)
   {
	$array_new_dates[] = '6<sup>th</sup>'; 
   }
   if($row == 7)
   {
	$array_new_dates[] = '7<sup>th</sup>'; 
   }
   if($row == 8)
   {
	$array_new_dates[] = '8<sup>th</sup>'; 
   }
   if($row == 9)
   {
	$array_new_dates[] = '9<sup>th</sup>'; 
   }
   if($row == 10)
   {
	$array_new_dates[] = '10<sup>th</sup>'; 
   }
   if($row == 11)
   {
	$array_new_dates[] = '11<sup>th</sup>'; 
   }
   if($row == 12)
   {
	$array_new_dates[] = '12<sup>st</sup>'; 
   }
   if($row == 13)
   {
	$array_new_dates[] = '13<sup>th</sup>'; 
   }
   if($row == 14)
   {
	$array_new_dates[] = '14<sup>th</sup>'; 
   }
   if($row == 15)
   {
	$array_new_dates[] = '15<sup>th</sup>'; 
   }
   if($row == 16)
   {
	$array_new_dates[] = '16<sup>th</sup>'; 
   }
   if($row == 17)
   {
	$array_new_dates[] = '17<sup>th</sup>'; 
   }
   if($row == 18)
   {
	$array_new_dates[] = '18<sup>th</sup>'; 
   }
   if($row == 19)
   {
	$array_new_dates[] = '19<sup>th</sup>'; 
   }
   if($row == 20)
   {
	$array_new_dates[] = '20<sup>th</sup>'; 
   }
   if($row == 21)
   {
	$array_new_dates[] = '21<sup>st</sup>'; 
   }
   if($row == 22)
   {
	$array_new_dates[] = '22<sup>nd</sup>'; 
   }
   if($row == 23)
   {
	$array_new_dates[] = '23<sup>rd</sup>'; 
   }
   if($row == 32)
   {
	$array_new_dates[] = 'last'; 
   }
  }


  $last  = array_slice($array_new_dates, -1);
  $first = join(', ', array_slice($array_new_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for  ".$final_dates." buisiness day of every months Starting from ".$newStartDate." at ".$newTime."</td>";
 $response .= "</tr>";
 }


 if($recurrence_pattern == 'Customization' && !empty($row['EndDate']))
 {

  $newStartDate = date("M d,Y", strtotime($StartDate));
  $newEndDate = date("M d,Y", strtotime($EndDate));
  $newTime = date('g:i a', strtotime($Time));

  $array_dates = explode(",",$no_of_days);

  foreach($array_dates as $row)
  {
  if($row == 1)
   {
	$array_new_dates[] = '1<sup>st</sup>'; 
   }
   if($row == 2)
   {
	$array_new_dates[] = '2<sup>nd</sup>'; 
   }
   if($row == 3)
   {
	$array_new_dates[] = '3<sup>rd</sup>'; 
   }
   if($row == 4)
   {
	$array_new_dates[] = '4<sup>th</sup>'; 
   }
   if($row == 5)
   {
	$array_new_dates[] = '5<sup>th</sup>'; 
   }
   if($row == 6)
   {
	$array_new_dates[] = '6<sup>th</sup>'; 
   }
   if($row == 7)
   {
	$array_new_dates[] = '7<sup>th</sup>'; 
   }
   if($row == 8)
   {
	$array_new_dates[] = '8<sup>th</sup>'; 
   }
   if($row == 9)
   {
	$array_new_dates[] = '9<sup>th</sup>'; 
   }
   if($row == 10)
   {
	$array_new_dates[] = '10<sup>th</sup>'; 
   }
   if($row == 11)
   {
	$array_new_dates[] = '11<sup>th</sup>'; 
   }
   if($row == 12)
   {
	$array_new_dates[] = '12<sup>st</sup>'; 
   }
   if($row == 13)
   {
	$array_new_dates[] = '13<sup>th</sup>'; 
   }
   if($row == 14)
   {
	$array_new_dates[] = '14<sup>th</sup>'; 
   }
   if($row == 15)
   {
	$array_new_dates[] = '15<sup>th</sup>'; 
   }
   if($row == 16)
   {
	$array_new_dates[] = '16<sup>th</sup>'; 
   }
   if($row == 17)
   {
	$array_new_dates[] = '17<sup>th</sup>'; 
   }
   if($row == 18)
   {
	$array_new_dates[] = '18<sup>th</sup>'; 
   }
   if($row == 19)
   {
	$array_new_dates[] = '19<sup>th</sup>'; 
   }
   if($row == 20)
   {
	$array_new_dates[] = '20<sup>th</sup>'; 
   }
   if($row == 21)
   {
	$array_new_dates[] = '21<sup>st</sup>'; 
   }
   if($row == 22)
   {
	$array_new_dates[] = '22<sup>nd</sup>'; 
   }
   if($row == 23)
   {
	$array_new_dates[] = '23<sup>rd</sup>'; 
   }
   if($row == 32)
   {
	$array_new_dates[] = 'last'; 
   }
  }


  $last  = array_slice($array_new_dates, -1);
  $first = join(', ', array_slice($array_new_dates, 0, -1));
  $both  = array_filter(array_merge(array($first), $last), 'strlen');

  $final_dates = join(' and ', $both);

 $response .= "<tr>";
 $response .= "<td>Frequency: </td><td>Report will be scheduled for ".$final_dates." buisiness day of every months Starting from ".$newStartDate." at ".$newTime." till ".$newEndDate."</td>";
 $response .= "</tr>";
 }



 $response .= "<tr>";
 $response .= "<td>Report Name: </td><td>".$ReportName."</td>";
 $response .= "</tr>";

 $response .= "<tr>";
 $response .= "<td>User Type: </td><td>".$usertypename."</td>";
 $response .= "</tr>";

 // $response .= "<tr>";
 // $response .= "<td>User Name: </td><td>".$UserName."</td>";
 // $response .= "</tr>";

 // $response .= "<tr>";
 // $response .= "<td>Subject: </td><td>".$Subject."</td>";
 // $response .= "</tr>";

 // $response .= "<tr>";
 // $response .= "<td>Type: </td><td>".$Type."</td>";
 // $response .= "</tr>";

 $response .= "<tr>";
 $response .= "<td>Created By: </td><td>".$CreatedBy."</td>";
 $response .= "</tr>";

 $response .= "<tr>";
 $response .= "<td>Directory Path: </td><td><span data-title='".$fullDirectoryPath."'>".$directoryPath."</span></td>";
 $response .= "</tr>";

 //LK01082026 start for generation summary
 $response .= "<tr>";
 $response .= "<td>Generation Summary: </td>";
 $response .= "<td>
    <div>Total (".$totalDirectoryClientCount."):</div>
    <div>Generated (".$successRunCount."): <span data-title='".htmlspecialchars($successRunText, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($successRunShort, ENT_QUOTES, 'UTF-8')."</span></div>
    <div>No Data Found (".count($skippedRunClients)."): <span data-title='".htmlspecialchars($skippedRunText, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($skippedRunShort, ENT_QUOTES, 'UTF-8')."</span></div>
 </td>";
 $response .= "</tr>";

 //LK01082026 end for generation summary

 if($sftp != 0)
 {

$sftp_query = "SELECT * FROM SCHEDULER_SFTP WHERE id IN (".$sftp.") ";
$result2 = mysqli_query($conn,$sftp_query);

while( $data = mysqli_fetch_array($result2) ){
  $arr[] = $data['path'];
  $sftpPath = $data['path'];
}
$sftpDirectory = implode(" ,",$arr);

 $response .= "<tr>";
 $response .= "<td>SFTP Directory Path: </td><td><span data-title='".$sftpDirectory."'>".$sftpPath."</span></td>";
 $response .= "</tr>"; 
 }

if($last_run_date != '')
{
 $response .= "<tr>";
 $response .= "<td>Last Run Date & Time: </td><td>".date('m-d-Y H:i:s', strtotime($last_run_date))."</td>";
 $response .= "</tr>";
}

 $response .= "<tr>";
 $response .= "<td><b style='font-weight: 900;'>Job Status: </b></td><td>".$status."</td>";
 $response .= "</tr>";

}
$response .= "</table>";

echo $response;
exit;
}

?>