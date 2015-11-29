<?php

/*
  05/12/2009

  By Steve Fatula of 5 Diamond IT Consulting

  This class handles database management

*/

class Database {

private $dbObject = NULL;
private $transactionActive = FALSE;
private $resultSet = NULL;
private $DatabaseName = NULL;
private $UserName = NULL;
private $Pwd = NULL;
private $HostName = NULL;

// Open a connection to a database when instantiated
public function __construct($dbName, $dbHost, $dbUser, $dbPassword, $Port, $Socket, $dbFlags, $NoUTF8) {
	$this->dbObject = mysqli_init();
	if ($this->dbObject === FALSE) $this->TriggerSQLError("Could not initialize MySQLi object for [{$dbName}]");
	$this->dbObject->options(MYSQLI_OPT_LOCAL_INFILE, true);

	if (is_null($dbFlags)) {
		$connect = $this->dbObject->real_connect($dbHost, $dbUser, $dbPassword, $dbName, $Port, $Socket, MYSQLI_CLIENT_FOUND_ROWS);
	} else {
		$connect = $this->dbObject->real_connect($dbHost, $dbUser, $dbPassword, $dbName, $Port, $Socket, $dbFlags);
	}
	if ($connect === FALSE) $this->TriggerSQLError("Error connecting to database $dbName, error number [{$this->dbObject->connect_errno}] message {$this->dbObject->connect_error}");
	$this->dbObject->real_query("set net_write_timeout=1200");
	$Version = phpversion();
	$VerInfo = explode(".", $Version);
	if ($NoUTF8 === FALSE && ($VerInfo[0] > 5 || ($VerInfo[0] == 5 && $VerInfo[1] >= 3))) $this->dbObject->set_charset("utf8");
	$this->DatabaseName = $dbName;
	$this->UserName = $dbUser;
	$this->Pwd = $dbPassword;
	$this->HostName = $dbHost;
}

// Close our database
public function __destruct() {
	if ($this->transactionActive) {
		$this->dbObject->rollback() === FALSE;
		$this->transactionActive = FALSE;
	}
	if (!is_null($this->dbObject)) $this->dbObject->close();
	$this->dbObject = NULL;
}

// Escape fields for use with MySQL
public function EscapeField($Field, $ExtraChars = FALSE, $DropQuotes = FALSE) {
	if (is_null($Field)) {
		return 'NULL';
	} else {
		$Field = $this->dbObject->real_escape_string($Field);
		if ($ExtraChars) $Field = preg_replace('/([%_])/u', '\\\\$1', $Field);
		if ($DropQuotes) return $Field;
			else return "'" . $Field . "'";
	}
}

// Executes MySQLi real_query function
public function Query($stmt, $ignoreError = FALSE) {
	$result = $this->dbObject->real_query($stmt);
	if ($result === FALSE) {
		if ($ignoreError) return FALSE;
		$this->TriggerSQLError($stmt);
	}
	return TRUE;
}

// Executes MySQLi real_query function
public function SetUpSerialRead($stmt, $ignoreError=FALSE) {
	if ($this->Query($stmt, $ignoreError)) {
		$this->resultSet = $this->dbObject->use_result();
		if ($this->resultSet === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError($stmt);
		}
	}
	return TRUE;
}

public function GetNextRow() {
	if (is_null($this->resultSet)) return FALSE;
	$result = $this->resultSet->fetch_assoc();
	if (is_null($result) || !$result) {
		$this->resultSet->free_result();
		$this->resultSet = NULL;
		return FALSE;
	}
	return $result;
}

public function Ping() {
	$result = $this->dbObject->ping();
	if ($result === False) $this->TriggerSQLError("Error on MySql ping");
	return True;
}

// Return values:
//	Default (not ignoring errors)
//		Syntax error triggers error which means value is not set
//		no result triggers error which means value is not set
//	If ignoring errors
//		Syntax error returns FALSE
//		No records returns NULL
public function SelectAndReturnRow($stmt, $ignoreError=FALSE) {
	$result = FALSE;
	if ($this->Query($stmt, $ignoreError)) {
		$resultSet = $this->dbObject->use_result();
		if ($resultSet === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError($stmt);
		}
		$result = $this->GetRow($resultSet, TRUE);
		$resultSet = NULL;
		if (!$result) {
			if ($ignoreError) {
				return NULL;
			} else {
				$this->TriggerSQLError("No results from MySQL statement {$stmt}");
			}
		}
	}
	return $result;
}

// Return values:
//	Default (not ignoring errors or no results)
//		Syntax error triggers error which means value is not set
//		no result triggers error which means value is not set
//	If ignoring errors
//		Syntax error returns FALSE
//		No records returns empty array
//  If ignoring no results
//		Syntax error triggers error which means value is not set
//		No records returns empty array
public function SelectAndReturnAllRows($stmt, $ignoreError=FALSE, $ignoreNoResults = FALSE) {
	$result = FALSE;
	if ($this->Query($stmt, $ignoreError)) {
		$resultSet = $this->dbObject->store_result();
		if ($resultSet === FALSE) {
			if ($ignoreError) return FALSE;
			$this->TriggerSQLError($stmt);
		}
		if ($resultSet->num_rows === 0) {
			if ($ignoreError) {
				return array();
			} else {
				if ($ignoreNoResults) {
					return array();
				} else {
					$this->TriggerSQLError("No results from MySQL statement {$stmt}");
				}
			}
		}
		while ($resultRow = $this->GetRow($resultSet)) {
			$result[] = $resultRow;
		}
		$resultSet = NULL;
	}
	return $result;
}

public function GetHostInfo() {
	return $this->dbObject->host_info;
}

// Insert a record and optionally return the insert id (could be 0)
public function InsertRow($stmt, $returnID=FALSE) {
	$rowID = NULL;
	$this->Query($stmt, FALSE);
	if ($returnID) $rowID = $this->dbObject->insert_id;
	return $rowID;
}

// Update a record
public function UpdateRow($stmt) {
	$this->Query($stmt, FALSE);
	return $this->dbObject->affected_rows;
}

// Delete a record
public function DeleteRow($stmt, $ignoreError=FALSE) {
	$this->Query($stmt, $ignoreError);
	return $this->dbObject->affected_rows;
}

public function TriggerSQLError($stmt) {
	$errNo = $this->dbObject->errno;
	$errDesc = $this->dbObject->error;
	$mesg = "MySQL error on statement {$stmt}\nErrNum={$errNo}, Message={$errDesc}";
	if ($this->Rollback() === FALSE) $mesg .= "\nRollback Failed";
	trigger_error($mesg, E_USER_ERROR);

}

private function GetRow($resultSet, $FreeResult = FALSE) {
	$result = $resultSet->fetch_assoc();
	if (is_null($result)) $result = FALSE;
	if ($FreeResult || $result === FALSE) {
		$resultSet->free_result();
		$resultSet = NULL;
	}
	return $result;
}

public function FreeResults() {
	if (!is_null($this->resultSet)) $this->resultSet->free_result();
}

public function Rollback() {
	if ($this->transactionActive) {
		if ($this->dbObject->rollback() === FALSE) {
			$this->EndTransaction();
			return FALSE;
		}
		$this->EndTransaction();
	}
	return TRUE;
}

public function StartTransaction() {
	// if ($this->dbObject->autocommit(FALSE) === FALSE) $this->TriggerSQLError("Autocommit False");
	$result = $this->dbObject->real_query("start transaction");
	if ($result === FALSE) $this->TriggerSQLError($stmt);
	$this->transactionActive = TRUE;
}

public function SetIsolationLevel($Level) {
	$sqlStmt = "set transaction isolation level {$Level}";
}

public function IgnoreTriggers() {
	return $this->Query("set @ignore_trigger = 1");
}

public function UseTriggers() {
	return $this->Query("set @ignore_trigger = NULL");
}

public function IgnoreConstraints() {
	return $this->Query("set foreign_key_checks = 0");
}

public function UseConstraints() {
	return $this->Query("set foreign_key_checks = 1");
}

public function ReplicateOff() {
	return $this->Query("set sql_log_bin = 0");
}

public function ReplicateOn() {
	return $this->Query("set sql_log_bin = 1");
}

public function MixedLoggingOn() {
	return $this->Query("set session binlog_format = 'MIXED'");
}

public function RowLoggingOn() {
	return $this->Query("set session binlog_format = 'ROW'");
}

public function EndTransaction() {
	// if ($this->dbObject->autocommit(TRUE) === FALSE) $this->TriggerSQLError("Autocommit True");
	$result = $this->dbObject->real_query("commit");
	if ($result === FALSE) $this->TriggerSQLError($stmt);
	$this->transactionActive = FALSE;
}

public function CheckConnection() {
	if (!$this->dbObject->ping()) $this->TriggerSQLError("Connection check failed");
	return TRUE;
}

public function Prepare($Statement) {
	$stmt = $this->dbObject->prepare($Statement);
	if ($stmt == FALSE) $this->TriggerSQLError($Statement);
	return $stmt;
}

public function CheckIfTableExists($TableName) {
	$stmt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$this->DatabaseName}' AND table_name = '{$TableName}'";
	$result = $this->SelectAndReturnRow($stmt, TRUE);
	if (is_null($result)) return false;
	return true;
}

public function GetDBName() {
	return $this->DatabaseName;
}

public function GetUserName() {
	return $this->UserName;
}

public function GetPassword() {
	return $this->Pwd;
}

public function GetHostName() {
	return $this->HostName;
}

public function EraseTmpFile($Filename) {
	$Command = "/usr/bin/sudo -u mysql /bin/rm -f {$Filename}";
	$Output = array();
	exec($Command, $Output, $Retvar);
	if ($Retvar != 0) NeobitsLogError("Error in command {$Command}");
}

public function GetSlaveLag() {
	$sqlStmt = "show slave status";
	$SlaveInfo = $this->SelectAndReturnRow($sqlStmt, TRUE);
	if (is_null($SlaveInfo)) return 0;
	if ($SlaveInfo["Slave_IO_Running"] == "No") trigger_error("Slave IO is not running for {$this->HostName}", E_USER_ERROR);
	$Lag = $SlaveInfo["Seconds_Behind_Master"];
	if (is_null($Lag)) $this->TriggerSQLError("Slave is not running");
	return $Lag;
}

public function GetFieldList($Table, $ExcludeList) {
	$sqlStmt = "show columns from `{$Table}`";
	$Fields = "";
	$FieldList = $this->SelectAndReturnAllRows($sqlStmt);
	foreach ($FieldList as $FieldInfo) {
		if (in_array($FieldInfo["Field"], $ExcludeList) === FALSE) {
			if (!empty($Fields)) $Fields .= ",";
			$Fields .= $FieldInfo["Field"];
		}
	}
	return $Fields;
}

/*
	TableName - Name of the table we are managing partitions for
	PeriodsToKeep - The number of periods we want to always have at least
	PeriodType - "Month" or "Week"
*/
public function RotatePartitions($TableName, $FieldName, $PeriodsToKeep, $PeriodType) {
	if ($PeriodType !== "Month" && $PeriodType !== "Week") 	trigger_error("Bad period type passed {$PeriodType}", E_USER_ERROR);
	if ($PeriodsToKeep > 60) trigger_error("Maximum partitions is 60, passed {$PeriodsToKeep}", E_USER_ERROR);

	// Based on PeriodType, compute our dates, we want the first day of the next periods
	$Year = date("Y");
	$Month = date("m");
	$Day = date('d');
	$DayNumber = date('N');
	$DateList = array();
	if ($PeriodType == "Month") {
		for ($i=0; $i<=$PeriodsToKeep; $i++) {
			$Date = date("Y-m", mktime(0, 0, 0, $Month - $i, 32, $Year)) . "-01"; // First day of the following month
			$DateList[] = $Date;
		}
	}
	if ($PeriodType == "Week") {
		// We want dates in the range Sun through Saturday, including today, Sunday dates
		// Get the number of days until next Sunday
		$DaysToSunday = 7 - $DayNumber;
		if ($DaysToSunday == 0) $DaysToSunday = 7;
		$DaysToSunday += $Day;
		for ($i=0; $i<=$PeriodsToKeep; $i++) {
			$Date = date("Y-m-d", mktime(0, 0, 0, $Month, 0 - ($i * 7) + $DaysToSunday, $Year)); // Sunday
			$DateList[] = $Date;
		}
	}
	$sqlStmt = "select PARTITION_NAME from INFORMATION_SCHEMA.PARTITIONS WHERE TABLE_NAME = '{$TableName}' and TABLE_SCHEMA = '{$this->DatabaseName}'";
	$Results = $this->SelectAndReturnAllRows($sqlStmt, False, True);

	// Make a list of partitions to remove, and, partitions to add
	$PartitionsToRemove = $PartitionsExisting = array();
	$LatestOldPartition = '';
	$DefinePartitions = False;
	foreach($Results as $Result) {
		$PartitionName = $Result["PARTITION_NAME"];
		if (empty($PartitionName)) $DefinePartitions = True;
		if ($PartitionName > $LatestOldPartition) $LatestOldPartition = $PartitionName;
		$PartitionDate = str_replace("_", "-", $PartitionName);
		if (in_array($PartitionDate, $DateList)) {
			// Partition already exists, keep track of it
			$PartitionsExisting[] = $PartitionDate;
		} else {
			// Extra partition, needs to be removed
			if (!empty($PartitionName)) $PartitionsToRemove[] = $PartitionName;
		}
	}
	// Let's see which partitions are new and need to be added
	$PartitionsToAdd = array_diff($DateList, $PartitionsExisting);
	sort($PartitionsToAdd);
	$RemovePart = $AddPart = '';
	foreach ($PartitionsToRemove as $PartitionName) {
		if (empty($PartitionName)) continue;
		if (!empty($RemovePart)) $RemovePart .= ",";
		$RemovePart .= $PartitionName;
	}
	foreach ($PartitionsToAdd as $DateToAdd) {
		$PartitionName = str_replace("-", "_", $DateToAdd);
		if ($PartitionName <= $LatestOldPartition) {
			echo "Partition {$PartitionName} is older than {$LatestOldPartition}, not adding\n";
			continue;
		}
		if (!empty($AddPart)) $AddPart .= ", ";
		$AddPart .= "partition {$PartitionName} values less than (to_days('$DateToAdd'))";
	}
	if (!empty($RemovePart)) {
		$sqlStmt = "alter table $TableName drop partition {$RemovePart}";
		echo "Drop is {$sqlStmt}\n\n";
		$this->Query($sqlStmt);
	}
	if (!empty($AddPart)) {
		if ($DefinePartitions) $sqlStmt = "alter table $TableName partition by range (to_days($FieldName)) ({$AddPart})";
		else $sqlStmt = "alter table $TableName add partition ({$AddPart})";
		echo "Add is {$sqlStmt}\n\n";
		$this->Query($sqlStmt);
	}
}

/**
 * Returns last errno if any
 * @return int last error number
 */
public function GetDbErrno() {
	return $this->dbObject->errno;
}

/**
 * Returns last error if any
 * @return string last error description
 */
public function GetDbError() {
	return $this->dbObject->error;
}

/**
 * Get mysqli dbObject
 * @return mysqli
 */
public function GetDbObject() {
	return $this->dbObject;
}


}