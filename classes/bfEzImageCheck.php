<?php
/**
 * Problem: sometimes the ezimage table and the filesystem get out of sync
 *
 * What this util does:
 * 1) loops over ezimage table\
 * 2) finds file on disk
 * 3) reports on file match
 * 4) TODO: suggest nearby file
 * 
 */
class bfEzImageCheck {

	//filepath is already provided by ezimage table
	
	function __construct() {
		return true;
	}

	static function getEzImageRows() {

		$db = eZDb::instance(); 
		$aRows = array();

		$query = "select * from ezimagefile order by filepath";
		$aRows = $db->arrayQuery($query);
		return $aRows;
	}

	static function report($nLogLevel) {
		return self::processImageFiles($nLogLevel);
	}

	static function isImageAlias($sFilename) {
		// look for "_"
		if(stripos($sFilename, '_') > 1){
			return true;
		}
		return false; //default assume not alias		
	}

	private static function processImageFiles($nLogLevel = 1) {

		echo 'LogLevel: '.$nLogLevel."\n\r";

		$db = eZDb :: instance();
		$aImageRows = self::getEzImageRows();
		$nTotal = 0;
		foreach($aImageRows as $aRow) {
			$nTotal++;

			$bMatch = false;
			
			$sRowFile = $aRow['filepath'];

			$aPathInfo = pathinfo($aRow['filepath']);
			$sRowFileName = $aPathInfo['filename'];	        
			$sRowFileExt = $aPathInfo['extension'];	

			if(self::isImageAlias($sRowFileName)){
				continue; //skip
			}

			//try local file no extension
			if(file_exists($sRowFile)) {
				if($nLogLevel > 1) {
					echo $sRowFile. ' [file-exists] '."\n\r";
				}
			} else {
				echo $sRowFile. ' [no file] '."\n\r";
			}
		}
		echo $nTotal.' records found'."\n\r";
		return $confirm;		
	}

}

?>