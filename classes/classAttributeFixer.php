<?php

/**
 * Inspects and fixes objects if they're missing attributes from class modifications
 * Happens for large sites sometimes, the script runs and ... breaks down somewhere half-way
 * This should always be used with the checkFixObjectAttributes script in the php/bin of this extension (see notes there)
 */
class classAttributeFixer {
	private $classId = null;
	private $db = null;
	private $bDisplay = true;

	function __construct() {
		// grab db connection from ez (context)
		$this->db = eZDb :: instance();
	}

	function inspectAllClasses() {
		$aAllClassDiagnosticHash = array();
		$allClasses = eZContentClass::fetchAllClasses( true );
		foreach ($allClasses as $oClass) {
			$classResultHash = $this->inspectOneClass($oClass);
			array_push($aAllClassDiagnosticHash, $classResultHash);
		}

		return($aAllClassDiagnosticHash);
	}

	function inspectOneClass($mClass) {
		if (is_int($mClass)) {
			$oClass = eZContentClass::fetch($mClass, true);
		} else if ($mClass instanceof eZContentClass) {
			$oClass = $mClass;
		} else {
			die("$mClass is not of eZContentClass class");
		}

		// get all attributes out of the class
		$aClassAttributes = $oClass->fetchAttributes();
		$totalObjectCount = $oClass->attribute("object_count");
		$contentClassId = $oClass->attribute("id");
		$contentClassIdentifier = $oClass->attribute("identifier");

if ($this->bDisplay) {
	print "Looking at class $contentClassIdentifier (classid = $contentClassId)... ";
}

		$aMissingAttributeIdentifiers = array();
		$aMissingAttributeIds = array();
		$aAttributeIdentifierToBadCount = array();
		$aClassAttributeIds = array();
		// first see if all your objects have all the needed attributes
		$bClassOk = true;
		foreach ($aClassAttributes as $oClassAttribute) {
			$classAttributeId = $oClassAttribute->attribute("id"); 
			array_push($aClassAttributeIds, $classAttributeId);
			$attributeIdentifier = $oClassAttribute->attribute("identifier");

			$findObjectsWithMissingAttributesQuery = <<<EOQ
SELECT count(id) as brokenClassCount 
FROM ezcontentobject
WHERE contentclass_id = $contentClassId
AND id NOT IN (
	SELECT ea.contentobject_id
	FROM ezcontentobject_attribute ea
	INNER JOIN ezcontentobject ezco on ezco.id = ea.contentobject_id
	WHERE contentclassattribute_id = $classAttributeId
)
EOQ;

			$rows = $this->db->arrayQuery($findObjectsWithMissingAttributesQuery);
			$badCount = $rows[0]["brokenClassCount"];
			if ($badCount > 0) {
				$bClassOk = false;
				array_push($aMissingAttributeIdentifiers, $attributeIdentifier);
				array_push($aMissingAttributeIds, $classAttributeId);
			}

			$aAttributeIdentifierToBadCount[$attributeIdentifier] = $badCount;
		}

if ($this->bDisplay) {
	if (sizeof($aMissingAttributeIdentifiers) > 0) {
		print "Missing attributes... ";
	}
}

		// TODO: then check how many objects have attributes they shouldn't have, and which ones
		$sAttributeIds = implode(",", $aClassAttributeIds);
		$findExtraParams = <<<EOQ
SELECT DISTINCT(ea.contentclassattribute_id)
FROM ezcontentobject_attribute ea
INNER JOIN ezcontentobject ezco on ezco.id = ea.contentobject_id
WHERE ezco.contentclass_id = $contentClassId
AND ea.contentclassattribute_id NOT IN ($sAttributeIds)
EOQ;
		$aExtraAttributeIds = $this->db->arrayQuery($findExtraParams);

		$findExtraParamObjectCount = <<<EOQ
SELECT COUNT(DISTINCT(ezco.id))) as objectCount
FROM ezcontentobject_attribute ea
INNER JOIN ezcontentobject ezco on ezco.id = ea.contentobject_id
WHERE ezco.contentclass_id = $contentClassId
AND ea.contentclassattribute_id NOT IN ($sAttributeIds)
EOQ;
		$row = $this->db->arrayQuery($findExtraParamObjectCount);
		$iObjectsWithExtraAttributes = $row[0]["objectCount"];
		if ($iObjectsWithExtraAttributes > 0) {
			$bClassOk = false;
if ($this->bDisplay) {
	print "object with extra attributes...";
}
		}
if ($this->bDisplay) {
	print "\n";
}
		return(array(
			"class_id" => $contentClassId,
			"class_identifier" => $contentClassIdentifier,
			"totalObjectCount" => $totalObjectCount,
			"is_class_ok" => $bClassOk,
			"missingAttributes" => $aMissingAttributeIdentifiers,
			"missingAttributeIds" => $aMissingAttributeIds,
			"missingObjectCountPerAttribute" => $aAttributeIdentifierToBadCount,
			"countObjectsWithExtraParams" => $iObjectsWithExtraAttributes,
			"extraAttributes-contentclassattribute_id" => $aExtraAttributeIds
		));
	}

	function fixAllClasses() {
		$aAllClassDiagnosticHash = array();
		$allClasses = eZContentClass::fetchAllClasses( true );

		foreach ($allClasses as $oClass) {
			$classResultHash = $this->fixBadClass($oClass);
			array_push($aAllClassDiagnosticHash, $classResultHash);
		}

		return($aAllClassDiagnosticHash);
	}

	function fixBadClass($mClass) {
		if (is_int($mClass)) {
			$oClass = eZContentClass::fetch($mClass, true);
		} else if ($mClass instanceof eZContentClass) {
			$oClass = $mClass;
		} else {
			die("$mClass is not of eZContentClass class");
		}

		$this->bDisplay = false; // don't need diagnostic display during this phase

		$contentClassIdentifier = $oClass->attribute("identifier");
		$contentClassId = $oClass->attribute("id");
		print "Looking at class $contentClassIdentifier (classid = $contentClassId)... ";

		$diagnosticHash = $this->inspectOneClass($mClass);
		if ($diagnosticHash["is_class_ok"]) {
			print "OK\n";
			return(true);
		}

		// then act accordingly - do attributes need to be added, or removed?
		// TODO: let's do remove first, that seems easier
		// $objectAttribute->removeThis( $objectAttribute->attribute( 'id' ) );
		// we need to inspect "extraAttributes-contentclassattribute_id" attribute, if we ever see it

		print "Full Diagnostic Information:\n";
		print_r($diagnosticHash);
		print "Fixing class... ";

		// try to use calls from syncobjectattributes.php, that seems to work on pure class attribute classes, and our inspection should have enough data to run these again
		$missingAttributeIds = $diagnosticHash["missingAttributeIds"];
		foreach ($missingAttributeIds as $classAttributeId) {
			$oClassAttribute = eZContentClassAttribute::fetch($classAttributeId);
			$objects = null;
			$oClassAttribute->initializeObjectAttributes( $objects );
		}

		print "Fixed.\n";
	}
}

?>