<?php

/*
  05/06/2011	
  
  By Steve Fatula of 5 Diamond IT Consulting
  
  This class handles management of product suppliers
  
*/

class OfbizSuppliers{

// Constants
const CREATEDBY = "admin";
const PARTYTYPEID = "PARTY_GROUP";
const PARTYSTATUSID = "PARTY_ENABLED";
const SHIPPINGPURPOSE = "SHIPPING_LOCATION";
const ORIGINPURPOSE = "SHIP_ORIG_LOCATION";
const BILLROLE = "BILL_FROM_VENDOR";
const SHIPROLE = "SHIP_FROM_VENDOR";
const SUPPLIERROLE = "SUPPLIER";
const AGENTROLE = "SUPPLIER_AGENT";

// Class variables
private $OfbizDbObj;

// Supplier Attributes
private $SupplierID;
private $SupplierName;

public function __construct() {
	$this->OfbizDbObj = Config::GetDBName(Config::DATABASE_OFBIZ);
}

public function CreateNewSupplier($PartyID, $PartyName) {
	if (empty($PartyID) && empty($PartyName)) trigger_error("Either the party id or the party name must be specified", E_USER_ERROR);
	if (empty($PartyID)) $this->SupplierID = NULL;
		else $this->SupplierID = $PartyID;
	if (empty($PartyName)) $this->SupplierName = $PartyID;
		else $this->SupplierName = $PartyName;
	return $this->AddNew();
}

public function GetSupplierName($PartyID) {
	$sqlStmt = $this->GetSupplierNameStmt();
	$sqlStmt->SetParameters(array("partyid" => $PartyID));
	$Results = $sqlStmt->SelectAndReturnRow(TRUE);
	if (is_null($Results)) $Name = NULL;
	    else $Name = $Results["GROUP_NAME"];
	return $Name;
}

private function GetSupplierNameStmt() {
	static $getSupplierNameStmt = NULL;
	
	if (is_null($getSupplierNameStmt)) $getSupplierNameStmt = new MysqlStatement($this->OfbizDbObj, "select GROUP_NAME from PARTY_GROUP where PARTY_ID = ?", array("partyid" => MysqlStatement::TYPE_STRING));
	return $getSupplierNameStmt;
}

private function GetInsertPartyStmt() {
	static $insertPartyStmt = NULL;
	
	if (is_null($insertPartyStmt)) $insertPartyStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY (PARTY_ID, PARTY_TYPE_ID, DESCRIPTION, STATUS_ID, CREATED_BY_USER_LOGIN, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values (?, '" . self::PARTYTYPEID . "', ?, '" . self::PARTYSTATUSID . "', '" . self::CREATEDBY . "', now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING, "partyname" => MysqlStatement::TYPE_STRING));
	return $insertPartyStmt;
}

private function GetInsertMechPurposeStmt() {
	static $insertMechPurposeStmt = NULL;

	if (is_null($insertMechPurposeStmt)) $insertMechPurposeStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY_CONTACT_MECH_PURPOSE (PARTY_ID, CONTACT_MECH_ID, CONTACT_MECH_PURPOSE, FROM_DATE, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values (?, ?, ?, now(), now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING, "mechid" => MysqlStatement::TYPE_STRING, "mechpurpose" => MysqlStatement::TYPE_STRING));
	return $insertMechPurposeStmt;	
}	

private function GetInsertMechStmt() {
	static $insertMechStmt = NULL;

	if (is_null($insertMechStmt)) $insertMechStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY_CONTACT_MECH (PARTY_ID, CONTACT_MECH_ID, FROM_DATE, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values (?, ?, now(), now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING, "mechid" => MysqlStatement::TYPE_STRING));
	return $insertMechStmt;	
}	

private function GetInsertPartyGroupStmt() {
	static $insertPartyGroupStmt = NULL;
	
	if (is_null($insertPartyGroupStmt)) $insertPartyGroupStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY_GROUP (PARTY_ID, GROUP_NAME, GROUP_NAME_LOCAL, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values (?, ?, ?, now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING, "groupname" => MysqlStatement::TYPE_STRING, "namelocal" => MysqlStatement::TYPE_STRING));
	return $insertPartyGroupStmt;
}

private function GetInsertPartyRoleStmt() {
	static $insertPartyRoleStmt = NULL;
	
	if (is_null($insertPartyRoleStmt)) $insertPartyRoleStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY_ROLE (PARTY_ID, ROLE_TYPE_ID, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values (?, ?, now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING, "role" => MysqlStatement::TYPE_STRING));
	return $insertPartyRoleStmt;
}

private function GetInsertPartyStatusStmt() {
	static $insertPartyStatusStmt = NULL;
	
	if (is_null($insertPartyStatusStmt)) $insertPartyStatusStmt = new MysqlStatement($this->OfbizDbObj, "insert into PARTY_STATUS (STATUS_ID, PARTY_ID, STATUS_DATE, LAST_UPDATED_STAMP, LAST_UPDATED_TX_STAMP, CREATED_STAMP, CREATED_TX_STAMP) values ('" . self::PARTYSTATUSID . "', ?, now(), now(), now(), now(), now())", array("partyid" => MysqlStatement::TYPE_STRING));
	return $insertPartyStatusStmt;
}

private function AddNew() {
	
	if (is_null($this->SupplierID)) $this->SupplierID = OfbizServiceManager::GetNextSequence("Party");
	// $MechID = OfbizServiceManager::GetNextSequence("ContactMech", 1);
	
	$this->OfbizDbObj->StartTransaction();
	$sqlStmt = $this->GetInsertPartyStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "partyname" => $this->SupplierName));
	$sqlStmt->InsertRow();
/*
	$sqlStmt = $this->GetInsertMechStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "mechid" => $MechID));
	$sqlStmt->InsertRow();
	
	$sqlStmt = $this->GetInsertMechPurposeStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "mechid" => $MechID, "mechpurpose" => self::SHIPPINGPURPOSE));
	$sqlStmt->InsertRow();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "mechid" => $MechID, "mechpurpose" => self::ORIGINPURPOSE));
	$sqlStmt->InsertRow();
*/	
	$sqlStmt = $this->GetInsertPartyGroupStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "groupname" => $this->SupplierName, "namelocal" => $this->SupplierName));
	$sqlStmt->InsertRow();
	
	$sqlStmt = $this->GetInsertPartyRoleStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "role" => self::BILLROLE));
	$sqlStmt->InsertRow();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "role" => self::SHIPROLE));
	$sqlStmt->InsertRow();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "role" => self::SUPPLIERROLE));
	$sqlStmt->InsertRow();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID, "role" => self::AGENTROLE));
	$sqlStmt->InsertRow();

	$sqlStmt = $this->GetInsertPartyStatusStmt();
	$sqlStmt->SetParameters(array("partyid" => $this->SupplierID));
	$sqlStmt->InsertRow();	
	
	$this->OfbizDbObj->EndTransaction();
}

}
?>