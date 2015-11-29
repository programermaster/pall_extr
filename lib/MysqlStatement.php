<?php

/*
  08/11/2009
  
  By Steve Fatula of 5 Diamond IT Consulting
  
  This class handles MySQL Prepared statements
  
*/

class MysqlStatement {

const TYPE_INTEGER = "i";
const TYPE_DOUBLE = "d";
const TYPE_STRING = "s";
const TYPE_BLOB = "b";
const VALIDTYPES = "idsb";

private $dbObject = NULL;

private $StatementObj = NULL;
private $Statement = NULL;
private $Parms = array();
private $Row;

// $FIelds is an array with key as the field name, value as the field type
public function __construct($DatabaseObj, $Statement, $Fields) {
	if (empty($Statement)) trigger_error("Empty MySQL statement passed", E_USER_ERROR);
	if (!is_object($DatabaseObj)) trigger_error("Database object must be passed", E_USER_ERROR);
	if (is_null($Fields) || !is_array($Fields)) trigger_error("Statement requires an array of fields", E_USER_ERROR);
	$this->dbObject = $DatabaseObj; // Obtain the database object
	$this->StatementObj = $this->dbObject->Prepare($Statement);
	if ($this->StatementObj === FALSE) {
		$this->StatementObj = NULL;
		$this->dbObject->TriggerSQLError($Statement);
	}
	$this->Statement = $Statement;
	foreach($Fields as $k => $v) {
		$this->AddParameter($k, $v);
	}
}

private function AddParameter($Name, $Type, $Value = NULL) {
	if (empty($Name)) trigger_error("Field name is empty, but is required", E_USER_ERROR);
	if (array_key_exists($Name, $this->Parms)) trigger_error("Duplicate parameter {$Name}", E_USER_ERROR);
	if (stripos(self::VALIDTYPES, $Type) === FALSE) trigger_error("Invalid field type passed [{$Type}]", E_USER_ERROR);
	$this->Parms[$Name] = array("type" => $Type, "value" => $Value);
}

private function GetTypeString() {
	$types = "";
	foreach($this->Parms as $v) {
		$types .= $v["type"];
	}
	return $types;
}

// Values is an array with a key of the field name, and, value of the value
public function SetParameters($Values) {
	foreach($Values as $k => $v) {
		if ($this->SetParameter($k, $v) === FALSE) return false;
	}
	return TRUE;
}

private function SetParameter($Name, $Value) {
	if (!array_key_exists($Name, $this->Parms)) trigger_error("Field name was not added, cannot set [{$Name}]", E_USER_ERROR);
	$this->Parms[$Name]["value"] = $Value;
	return TRUE;
}

private function BindParams() {
	if (count($this->Parms) == 0) return;
	if ($this->StatementObj->reset() === FALSE) $this->TriggerSQLError();
	$ar = array();
	$ar[] = $this->GetTypeString();
	foreach($this->Parms as $k => $v) {
		$ar[] = &$this->Parms[$k]["value"];
	}
	if (call_user_func_array(array($this->StatementObj, 'bind_param'), $ar) === FALSE) $this->TriggerSQLError();
}

private function TriggerSQLError() {
	$errNo = $this->StatementObj->errno;
	$errDesc = $this->StatementObj->error;
	$mesg = "MySQL error on statement {$this->Statement}\nErrNum={$errNo}, Message={$errDesc}";
	if ($this->dbObject->Rollback() === FALSE) $mesg .= "\nRollback Failed";
	trigger_error($mesg, E_USER_ERROR);
}

public function Execute($ignoreError) {
	$this->BindParams();
	$result = $this->StatementObj->execute();
	if ($result === FALSE) {
		if ($ignoreError) return FALSE;
		$this->TriggerSQLError();
	}
	return TRUE;
}

public function InsertRow($returnID=FALSE, $ignoreError = FALSE) {
	$rowID = NULL;
	$Worked = $this->Execute($ignoreError);
	if ($returnID && $Worked) $rowID = $this->StatementObj->insert_id;
	return $rowID;
}

public function UpdateRows() {
	$this->Execute(FALSE);
	return $this->StatementObj->affected_rows;
}

public function DeleteRows() {
	$this->Execute(FALSE);
	return $this->StatementObj->affected_rows;
}

// If ignoring error, NULL is returned for no records
public function SelectAndReturnRow($ignoreError=FALSE) {
	$result = FALSE;
	if ($this->Execute($ignoreError)) {
		if ($this->StatementObj->store_result() === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError();
		}
		$meta = $this->StatementObj->result_metadata();
		if ($meta === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError();
		}
		$row = array();
		$params = array();
		while($column = $meta->fetch_field()) {
			$params[] = &$row[$column->name];
		}
		if (call_user_func_array(array($this->StatementObj, 'bind_result'), $params) === FALSE) $this->TriggerSQLError();
		$return = $this->StatementObj->fetch();
		if ($return === NULL) {
			if ($ignoreError) {
				return NULL;
			} else {
				trigger_error("No results from MySQL statement {$this->Statement}", E_USER_ERROR);
			}
		}
		
		if ($return === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError();
		}
		foreach($row as $k => $v) {
			$result[$k] = $v;
		}
	}
	return $result;
}

public function SelectAndReturnAllRows($ignoreError=FALSE) {
	$resultArray = array();
	if ($this->Execute($ignoreError)) {
		if ($this->StatementObj->store_result() === FALSE) {
			if ($ignoreError) return $resultArray;
			$this->TriggerSQLError();
		}
		$meta = $this->StatementObj->result_metadata();
		if ($meta === FALSE) {
			if ($ignoreError) return $resultArray;
			$this->TriggerSQLError();
		}
		$row = array();
		$params = array();
		while($column = $meta->fetch_field()) {
			$params[] = &$row[$column->name];
		}
		if (call_user_func_array(array($this->StatementObj, 'bind_result'), $params) === FALSE) $this->TriggerSQLError();
		while ($return = $this->StatementObj->fetch()) {
			$result = array();
			foreach($row as $k => $v) {
				$result[$k] = $v;
			}
			$resultArray[] = $result;
		}		
		if ($return === FALSE) {
			if ($ignoreError) return $resultArray;
			$this->TriggerSQLError();
		}
		if ($ignoreError) {
			return $resultArray;
		} else {
			if (count($resultArray) == 0) trigger_error("No results from MySQL statement {$this->Statement}", E_USER_ERROR);
		}
	}
	return $resultArray;
}

public function SelectRows($ignoreError=FALSE) {
	if ($this->Execute($ignoreError)) {
		if ($this->StatementObj->store_result() === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError();
		}
		$meta = $this->StatementObj->result_metadata();
		if ($meta === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError();
		}
		$this->Row = array();
		$params = array();
		while($column = $meta->fetch_field()) {
			$params[] = &$this->Row[$column->name];
		}
		if (call_user_func_array(array($this->StatementObj, 'bind_result'), $params) === FALSE) $this->TriggerSQLError();
	}
	return TRUE;
}

public function GetRow($ignoreError=FALSE) {
	$result = FALSE;
	if ($return = $this->StatementObj->fetch()) {
		$result = array();
		foreach($this->Row as $k => $v) {
			$result[$k] = $v;
		}
	}		
	if ($return === FALSE) {
		if ($ignoreError) return $result;
		$this->TriggerSQLError();
	}
	if ($return == NULL) return false;
	return $result;	
}



}
?>