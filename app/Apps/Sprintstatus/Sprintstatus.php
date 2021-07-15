<?php
namespace App\Apps\Sprintstatus;
use App\Apps\App;
use App\Libs\Jira\Fields;
use App\Libs\Jira\Jira;
use Carbon\Carbon;
use App\Email;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Sprintstatus extends App{
	public $timezone='Asia/Karachi';
	public $query=null;
	public $jira_fields = ['key','status','statuscategory','summary','resolutiondate','created','transitions']; 
    public $jira_customfields = [];  	
	public $jira_server = 'EPS';
	public $scriptname = 'sprintstatus';
	public $datafolder = "data/sprintstatus";
	public $options = 0;
	public function __construct($options=null)
    {
		$this->namespace = __NAMESPACE__;
		//$this->mongo_server = env("MONGO_DB_SERVER", "mongodb://127.0.0.1");
		$this->template = "data/sprintstatus/template.xlsx";
		$this->report = "data/sprintstatus/report.xlsx";
		$this->sprint_header_row_in_template = 2;
		$this->state_row_in_template = 8;
		$this->task_row_in_template = $this->state_row_in_template+2;
		$this->options = $options;
		parent::__construct($this);

    }
	public function TimeToRun($update_every_xmin=10)
	{
		return parent::TimeToRun($update_every_xmin);
	}
	public function InConsole($yes)
	{
		
	}
	function IssueParser($code,$issue,$fieldname)
	{
		switch($fieldname)
		{
			default:
				dd('"'.$fieldname.'" not handled in IssueParser');
		}
	}
	public function Rebuild()
	{
		//$this->db->cards->drop();
		$states = $this->ReadStates(1);
	}
	public function GetStatesToTrack()
	{
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->template);
		$sheet = $spreadsheet->setActiveSheetIndex(0);
		$data = $sheet->toArray();
		$states_to_track = [];
		$col = 1;
		foreach($data[$this->state_row_in_template-1] as $state)
		{
			if($state != null)
				$states_to_track[$state]=['state'=>$state,'col'=>$col]; 
			$col++;
		}
		return $states_to_track;
	}
	function SecondsToString($ss,$hours_day) 
	{
		$s = $ss%60;
		$m = floor(($ss%3600)/60);
		$h = floor(($ss)/3600);
		
		$d = floor($h/$hours_day);
		$h = $h%$hours_day;
		//return "$d days, $h hours, $m minutes, $s seconds";
		$del = '';
		$str = '';
		if($d != 0)
		{
			$str = $d."d";
			$del = ",";
		}
		if($h != 0)
		{
			$str .= $del.$h."h";
			$del = ",";
		}
		if($m != 0)
		{
			$str .= $del.$m."m";
			$del = ",";
		}
		if($str == '')
			return '0';
		return $str;
	}
	public function  colLetter($c, $offset=null)
	{
	   // https://icesquare.com/wordpress/example-code-to-convert-a-number-to-excel- 
	   //column-letter/
	   //0 -> A
	   //25 -> Z
	   //26 -> AA
	   //27 -> AB
	   //799 -> ADT

		if ( is_null($offset) ) { //converts position 0 to 2 etc etc.
			$offset = 0;
		}
		$offset += 1;  //the solution converts 1 -> A, hence we need to add 1 if we want to start with zero

		$c = (int)$c;
		if ($c < 0) {
			return '';
		} else {
			$c += $offset;
		}
		$letter = '';             
		while($c != 0){
		   $p = ($c - 1) % 26;
		   $c = (int)(($c - $p) / 26);
		   $letter = chr(65 + $p) . $letter;
		}    
		return $letter;        
	}
	public function GenerateReport($tickets,$sprint_data,$states_to_track)
	{
		
		//dd($sprint_data);
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->template);
		$sheet = $spreadsheet->setActiveSheetIndex(0);
		$r = $this->sprint_header_row_in_template;
		$sheet->setCellValue('B'.$r++, $sprint_data->id);
		$sheet->setCellValue('B'.$r++, $sprint_data->name);
		$sheet->setCellValue('B'.$r++, $this->DateStringToObj($sprint_data->startDate)->format(DATE_RFC1036));
		$sheet->setCellValue('B'.$r++, $this->DateStringToObj($sprint_data->endDate)->format(DATE_RFC1036));
		
		$closed_on = '';
		if($sprint_data->state == 'closed')
			$closed_on = ' on '.$this->DateStringToObj($sprint_data->completeDate)->format(DATE_RFC1036);
		
		$sheet->setCellValue('B'.$r, strtoupper($sprint_data->state).$closed_on);
		
		if($sprint_data->state == 'active')
		{
			$sheet->getStyle('B'.$r)->getFill()
			->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()
			->setARGB('00FF00');
		}
		else if($sprint_data->state == 'closed')
		{
			$sheet->getStyle('B'.$r)->getFill()
			->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
			->getStartColor()
			->setARGB('A9A9A9');
		}
		$r = $r+1;
		$sheet->setCellValue('B'.$r++, $this->TimestampToObj($this->CurrentDateTime())->format(DATE_RFC1036));
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$fp = @fopen($this->report,"w");
		if($fp == false)
		{
			echo("\033[31m Unable to overwrite ".$this->report."\n");
			dd("Please close and retry");
		}
		$r = $this->task_row_in_template;
		foreach($tickets as $ticket)
		{
			
			$sheet->setCellValue('A'.$r, $ticket->key);
			$sheet->setCellValue('B'.$r, $this->TimestampToObj($ticket->created)->format('Y-m-d'));
			$sheet->setCellValue('C'.$r, $ticket->status);
			
			
			if($ticket->statuscategory == 'inprogress')
			{
				$sheet->getStyle('C'.$r)->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('00FF00');
			}
			else if($ticket->statuscategory == 'resolved')
			{
				$sheet->getStyle('C'.$r)->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('A9A9A9');
			}
			$sheet->setCellValue('D'.$r, $ticket->statuscategory);
			
			//$sheet->setCellValueByColumnAndRow(10, 3,'hhh');
			foreach($ticket->statetime as $st)
			{
				$state = $st->state;
				
				if(!isset($states_to_track[$state]))
				{
					if(!isset($warned[$state]))
						echo("\033[31m ".$state." is not configured in ".$this->template."\n");
					$warned[$state]=1;
				}
				else
				{
					//dd($st);
					$col = $states_to_track[$state]['col'];
					$sheet->setCellValueByColumnAndRow($col, $r,$this->SecondsToString($st->minutes*60,24));
					$sheet->setCellValueByColumnAndRow($col+1, $r,$this->SecondsToString($st->business_minutes*60,9));
					
					if($state == $ticket->status)
					{
						if($ticket->statuscategory == 'resolved')
							$color = 'A9A9A9';
						else
							$color = '8FBC8F';
						$cols = $this->colLetter($col-1);
						$sheet->getStyle(($cols).$r)->getFill()
						->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
						->getStartColor()
						->setARGB($color);
						$cols = $this->colLetter($col);
						$sheet->getStyle($cols.$r)->getFill()
						->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
						->getStartColor()
						->setARGB($color);
					}
				}
			}
			$r++;
		}
		$writer->save($this->report);
	}
	public function ReadStates($rebuild=0)
	{
		$states = $this->Read('states')->data;
		if(($states==null)||($rebuild==1))
		{
			$states = $this->FetchJiraTicketStates();
			$o =  new \StdClass();
			$o->id = 'states';
			$o->data = $states;
			$this->save($o);
			$states = $this->Read('states')->data;
		}
		return $states;
	}
	public function ProcessTickets($tickets,$sprintstart,$sprintend,$states,$sprint_state)
	{
		$current_time = $this->CurrentDateTime();
		if($sprint_state == 'closed')
			$baseend = $sprintend;
		else
			$baseend=$this->CurrentDateTime();
		foreach($tickets as $ticket)
		{
			$basestart = $sprintstart;
			$basestart = $ticket->created;
		
			$statetime = [];	
			$statetime[$ticket->status]=new \StdClass();
			$statetime[$ticket->status]->start = $basestart;
			$statetime[$ticket->status]->end = null;
			foreach($ticket->transitions as $transition)
			{
				$fromstate = $transition->from;
				$tostate = $transition->to;
				$time = $transition->created;
				if(!isset($statetime[$fromstate]))
				{
					$statetime[$fromstate] =  new \StdClass();
					$statetime[$fromstate]->start = null;
					$statetime[$fromstate]->end = null;
				}
				if(!isset($statetime[$tostate]))
				{
					$statetime[$tostate] =  new \StdClass();
					$statetime[$tostate]->start = null;
					$statetime[$tostate]->end = null;
				}
				$statetime[$fromstate]->end = $time;
				if($statetime[$fromstate]->start == null)
					$statetime[$fromstate]->start = $basestart;
				$min = round(($statetime[$fromstate]->end - $statetime[$fromstate]->start)/60);
				if($min > 0)
					$statetime[$fromstate]->min[] = $min;
				$statetime[$fromstate]->business_min[] = $this->GetBusinessMinutes($statetime[$fromstate]->start,$statetime[$fromstate]->end,$starthour=9,$endhour=18,[]);
				$statetime[$tostate]->start = $time;
				$statetime[$tostate]->end = null;
			}
			
			foreach($statetime as $state=>$obj)
			{
				if($obj->end == null)
				{
					$statetime[$state]->end = $baseend;
					$min = round(($statetime[$state]->end - $statetime[$state]->start)/60);
					if($min > 0)
						$statetime[$state]->min[] = $min;
					$statetime[$state]->business_min[] = $this->GetBusinessMinutes($statetime[$state]->start,$statetime[$state]->end,$starthour=9,$endhour=18,[]);
				}
				$obj->state = $state;
				$obj->statuscategory = $states->$state->category;
				$obj->statusid = $states->$state->id;
				$obj->minutes = 0;
				$obj->business_minutes = 0;
				if(!isset($obj->min))
					continue;
				foreach($obj->min as $min)
				{
					$obj->minutes += $min;
				}
				foreach($obj->business_min as $min)
				{
					$obj->business_minutes += $min;
				}
				//echo $ticket->key."  ".$obj->state."(".$obj->statuscategory.":".$obj->statusid.") ".SecondsToString($obj->minutes*60,24)."  ".SecondsToString($obj->business_minutes*60,9)."\n";
			}
			$ticket->statetime =  array_values($statetime);
		}
	}
	public function ProcessTickets2($tickets,$sprintstart,$sprintend,$states,$sprint_state)
	{
		$current_time = $this->CurrentDateTime();
		if($sprint_state == 'closed')
			$boundary_end_time = $sprintend;
		else
			$boundary_end_time=$this->CurrentDateTime();
		foreach($tickets as $ticket)
		{
			$ticket->statetime = [];
			$basetime = $sprintstart;
			$basetime = $ticket->created;
			
			//dump($ticket->key);
			//dump('created ='.$ticket->created);
			//dump('sprint start ='.$sprintstart);
			//dump('basetime ='.$basetime);
			foreach($ticket->transitions as $transition)
			{
				if(!isset($ticket->statetime[$transition->from]))
					$ticket->statetime[$transition->from] =  new \StdClass();
				if(!isset($ticket->statetime[$transition->to]))
					$ticket->statetime[$transition->to] =  new \StdClass();
				$ticket->statetime[$transition->to]->start = $transition->created;
				if(!isset($ticket->statetime[$transition->from]->start))
					$ticket->statetime[$transition->from]->start = $basetime;
				
				$ticket->statetime[$transition->from]->finish = $transition->created;
				$min = round(($ticket->statetime[$transition->from]->finish - $ticket->statetime[$transition->from]->start)/60);
				$ticket->statetime[$transition->from]->min[] = $min;
				$ticket->statetime[$transition->from]->business_min[] =  $this->GetBusinessMinutes($ticket->statetime[$transition->from]->start,$ticket->statetime[$transition->from]->finish,$starthour=9,$endhour=18,[]);
				
				
				$to = $transition->to;
				if($states->$to->category == 'Done')
				{
					$ticket->statetime[$transition->to]->finish = $boundary_end_time;
					
					$min = round(($ticket->statetime[$transition->to]->finish - $ticket->statetime[$transition->to]->start)/60);
					$ticket->statetime[$transition->to]->min[] = $min;
					$ticket->statetime[$transition->to]->business_min[] =  $this->GetBusinessMinutes($ticket->statetime[$transition->to]->start,$ticket->statetime[$transition->to]->finish,$starthour=9,$endhour=18,[]);
					
				}
				
				if($min < 0)
				{
					dump($ticket);
					dd('error');
				}
			}
			foreach($ticket->statetime as $state=>$obj)
			{
				$obj->state = $state;
				$obj->statuscategory = $states->$state->category;
				$obj->statusid = $states->$state->id;
				$obj->minutes = 0;
				$obj->business_minutes = 0;
				if(!isset($obj->min))
					continue;
				foreach($obj->min as $min)
				{
					$obj->minutes += $min;
				}
				foreach($obj->business_min as $min)
				{
					$obj->business_minutes += $min;
				}
				//echo $ticket->key."  ".$obj->state."(".$obj->statuscategory.":".$obj->statusid.") ".SecondsToString($obj->minutes*60,24)."  ".SecondsToString($obj->business_minutes*60,9)."\n";
			}
			$ticket->statetime =  array_values($ticket->statetime);
		}
	}
	public function Script()
	{
		dump("Running script");
		
		if($this->options['sprint'] ==  "null")
			dd('Sprint id missing');
		
		$states_to_track = $this->GetStatesToTrack();
		$states = $this->ReadStates();
		$warned = [];
		foreach($states_to_track as $s=>$statedata)
		{
			if(!isset($states->$s))
			{
				echo("\033[31m ".$s." in template  is not valid"."\n");
				dd("Please update ".$this->template);
			}
		}
		$sprint_data = $this->FetchSprintData($this->options['sprint']);
		
		$sprintstart = $this->DateStringToObj($sprint_data->startDate)->timestamp;
		$sprintend = $this->DateStringToObj($sprint_data->endDate)->timestamp;
		
		$keys = $this->FetchSprintTasks($this->options['sprint']);
		//$this->query = 'key in (HMIP-2267)';
		$this->query = 'key in ('.implode(",",$keys).")";
		
		$tickets =  $this->FetchJiraTickets();
		
		$this->ProcessTickets($tickets,$sprintstart,$sprintend,$states,$sprint_data->state);
		
		$this->GenerateReport($tickets,$sprint_data,$states_to_track);
		
		return;
		
		
		$ts = strtotime($sdata->startDate);
		$start = $this->TimestampToObj($ts);
		dd($start );
		dd(date('Y-m-d H:i:s',$ymd ));
		dd($ymd);
		$keys = $this->FetchSprintTasks(2244 );
		$this->query = 'key in ('.implode(",",$keys).")";
		
		
		//$email =  new Email();
		//$email->Send(2,'dd','ff');
		$tickets =  $this->FetchJiraTickets();
		$status = $this->FetchJiraStatus();
		foreach($tickets as $ticket)
		{
			
			dump($ticket->statuscategory);
			
		}
		dd($tickets);
	}
}