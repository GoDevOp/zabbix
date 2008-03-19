<?php
/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function	event_source2str($sourceid){
		switch($sourceid){
			case EVENT_SOURCE_TRIGGERS:	return S_TRIGGERS;
			case EVENT_SOURCE_DISCOVERY:	return S_DISCOVERY;
			default:			return S_UNKNOWN;
		}
	}

	
/* function:
 *     event_initial_time
 *
 * description:
 *     returs 'true' if event is initial, otherwise false; 
 *
 * author: Aly
 */
function event_initial_time($row,$show_unknown=0){
	$events = get_latest_events($row,$show_unknown);
	
	if(!empty($events) && 
		($events[0]['value'] == $row['value']) && 
		($row['type'] == TRIGGER_MULT_EVENT_ENABLED) &&
		($row['value'] == TRIGGER_VALUE_TRUE))
	{
		return true;
	}
	if(!empty($events) && ($events[0]['value'] == $row['value'])){
		return false;
	}

	return true;
}

/* function:
 *     first_initial_eventid
 *
 * description:
 *     return first initial eventid
 *
 * author: Aly
 */

function first_initial_eventid($row,$show_unknown=0){
	
	$events = get_latest_events($row,$show_unknown);

	$sql_cond=($show_unknown == 0)?' AND e.value<>2 ':'';
	
	if(empty($events)){
		$sql = 'SELECT e.eventid '.
				' FROM events e '.
				' WHERE e.objectid='.$row['triggerid'].$sql_cond.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);
		
		if($rows = DBfetch($res)) return $rows['eventid'];
	}
	else if(!empty($events) && ($events[0]['value'] != $row['value'])){
		$eventid = $events[0]['eventid'];
		$sql = 'SELECT e.eventid '.
				' FROM events e '.
				' WHERE e.eventid > '.$eventid.
					' AND e.objectid='.$row['triggerid'].$sql_cond.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);
		
		if($rows = DBfetch($res)){
			return $rows['eventid'];
		}
		
		$row['eventid'] = $eventid;
		$row['value'] = $events[0]['value'];
		return first_initial_eventid($row,$show_unknown=0);			// recursion!!!
	}
	else if(!empty($events) && ($events[0]['value'] == $row['value'])){
		$eventid = (count($events) > 1)?($events[1]['eventid']):(0);

		$sql = 'SELECT e.eventid,e.clock '.
				' FROM events e '.
				' WHERE e.eventid > '.$eventid.
					' AND e.objectid='.$row['triggerid'].
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value='.$row['value'].
				' ORDER BY e.object, e.objectid, e.eventid';
		$res = DBselect($sql,1);
		$rows = DBfetch($res);

		return $rows['eventid'];
	}
return false;
}

/* function:
 *     get_latest_events
 *
 * description:
 *     return latest events by VALUE
 *
 * author: Aly
 */
function get_latest_events($row,$show_unknown=0){

	$eventz = array();
	$events = array();

// SQL's are optimized that's why it's splited that way	

/*******************************************/
// Check for optimization after changing!  */
/*******************************************/

	$sql = 'SELECT e.eventid, e.value '.
			' FROM events e '.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid < '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.TRIGGER_VALUE_FALSE.
			' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';
	if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];
	
	$sql = 'SELECT e.eventid, e.value '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid < '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.TRIGGER_VALUE_TRUE.
			' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';

	if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];

	if($show_unknown != 0){
		$sql = 'SELECT e.eventid, e.value '.
				' FROM events e'.
				' WHERE e.objectid='.$row['triggerid'].
					' AND e.eventid < '.$row['eventid'].
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value='.TRIGGER_VALUE_UNKNOWN.
				' ORDER BY e.object DESC, e.objectid DESC, e.eventid DESC';
		if($rez = DBfetch(DBselect($sql,1))) $eventz[$rez['value']] = $rez['eventid'];
	}

/*******************************************/

	arsort($eventz);
	foreach($eventz as $key => $value){
		$events[] = array('eventid'=>$value,'value'=>$key);
	}
return $events;
}

/* function:
 *     get_next_event
 *
 * description:
 *     return next event by value
 *
 * author: Aly
 */
function get_next_event($row,$show_unknown=0){
	$sql_cond=($show_unknown == 0)?' AND e.value<>'.TRIGGER_VALUE_UNKNOWN:'';
	
	if((TRIGGER_MULT_EVENT_ENABLED == $row['type']) && (TRIGGER_VALUE_TRUE == $row['value'])){
		$sql = 'SELECT e.eventid, e.value '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid > '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.$row['value'].
			' ORDER BY e.object, e.objectid, e.eventid';
	}
	else{
		$sql = 'SELECT e.eventid, e.value, e.clock '.
			' FROM events e'.
			' WHERE e.objectid='.$row['triggerid'].
				' AND e.eventid > '.$row['eventid'].
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value<>'.$row['value'].
				$sql_cond.
			' ORDER BY e.object, e.objectid, e.eventid';
	}
	$rez = DBfetch(DBselect($sql,1));
return $rez;
}


function get_history_of_discovery_events($start,$num){
	$db_events = DBselect('select distinct e.source,e.object,e.objectid,e.clock,e.value from events e'.
		' where e.source='.EVENT_SOURCE_DISCOVERY.' order by e.clock desc',
		10*($start+$num)
		);
   
	$table = new CTableInfo(S_NO_EVENTS_FOUND); 
	$table->SetHeader(array(S_TIME, S_IP, S_DESCRIPTION, S_STATUS));
	$col=0;
	
	$skip = $start;
	while(($event_data = DBfetch($db_events))&&($col<$num)){
		if($skip > 0){
			$skip--;
			continue;
		}

		if($event_data["value"] == 0){
			$value=new CCol(S_UP,"off");
		}
		elseif($event_data["value"] == 1){
			$value=new CCol(S_DOWN,"on");
		}
		else{
			$value=new CCol(S_UNKNOWN_BIG,"unknown");
		}


		switch($event_data['object']){
			case EVENT_OBJECT_DHOST:
				$object_data = DBfetch(DBselect('select ip from dhosts where dhostid='.$event_data['objectid']));
				$description = SPACE;
				break;
			case EVENT_OBJECT_DSERVICE:
				$object_data = DBfetch(DBselect('select h.ip,s.type,s.port from dhosts h,dservices s '.
					' where h.dhostid=s.dhostid and s.dserviceid='.$event_data['objectid']));
				$description = S_SERVICE.': '.discovery_check_type2str($object_data['type']).'; '.
					S_PORT.': '.$object_data['port'];
				break;
			default:
				continue;
		}

		if(!$object_data) continue;


		$table->AddRow(array(
			date("Y.M.d H:i:s",$event_data["clock"]),
			$object_data['ip'],
			$description,
			$value));

		$col++;
	}
return $table;
}

function make_event_details($triggerid,&$trigger_data){
	$table = new CTableInfo();
	
	if(is_show_subnodes()){
		$table->AddRow(array(S_NODE, get_node_name_by_elid($triggerid)));
	}
	
	$table->AddRow(array(S_HOST, $trigger_data['host']));
	$table->AddRow(array(S_TRIGGER, $trigger_data['exp_desc']));
	$table->AddRow(array(S_SEVERITY, new CSpan(get_severity_description($trigger_data["priority"]), get_severity_style($trigger_data["priority"]))));
	$table->AddRow(array(S_EXPRESSION, $trigger_data['exp_expr']));
	
return $table;
}

function make_small_eventlist($triggerid,&$trigger_data,$show_unknown=0){
	$sql_cond = '';
	if(0 == $show_unknown){
		$sql_cond = ' AND value<>2 ';
	}
	
	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_STATUS,S_ACK,S_DURATION));

	$sql = 'SELECT * '.
			' FROM events '.
			' WHERE objectid='.$triggerid.
				' AND object='.EVENT_OBJECT_TRIGGER.
				$sql_cond.
			' ORDER BY clock DESC';

	$result = DBselect($sql,10*($_REQUEST['start']+PAGE_SIZE));

	$rows = array();
	$count = 0;
	
	$col=0;
	$skip = $_REQUEST['start'];
	while(($col<PAGE_SIZE) && ($row=DBfetch($result))){	
		if($skip > 0){
			if((0 == $show_unknown) && ($row['value'] == TRIGGER_VALUE_UNKNOWN)) continue;
			$skip--;
			continue;
		}
		
		if(!empty($rows) && ($rows[$count]['value'] != $row['value'])){
			$count++;
		}
		else if(!empty($rows) && 
				($rows[$count]['value'] == $row['value']) && 
				($trigger_data['type'] == TRIGGER_MULT_EVENT_ENABLED) && 
				($row['value'] == TRIGGER_VALUE_TRUE)
				){
			$count++;
		}
		$rows[$count] = $row;
		$col++;
	}

	$clock=time();
	
	foreach($rows as $id => $row){
		$lclock=$clock;
		$clock=$row["clock"];
		
		$duration = zbx_date2age($lclock,$clock);

		if($row["value"] == TRIGGER_VALUE_FALSE){
			$value=new CCol(S_FALSE_BIG,"off");
		}
		elseif($row["value"] == TRIGGER_VALUE_TRUE){
			$value=new CCol(S_TRUE_BIG,"on");
		}
		else{
			$value=new CCol(S_UNKNOWN_BIG,"unknown");
		}
	
		$ack = "-";
		if($row["value"] == 1 && $row["acknowledged"] == 1){
			$db_acks = get_acknowledges_by_eventid($row["eventid"]);
			$rows=0;
			while($a=DBfetch($db_acks))	$rows++;
			
			$ack=array(
				new CLink(new CSpan(S_YES,'off'),'acknow.php?eventid='.$row['eventid'],'action'),
				SPACE.'('.$rows.')'
				);
		}

		$table->AddRow(array(
			date('Y.M.d H:i:s',$row['clock']),
			$value,
			$ack,
			$duration
			));
	}
return $table;
}
?>
