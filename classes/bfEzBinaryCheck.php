<?php
/**
 * Problem: sometimes (when the moon is right) the binary file handler writes files without extensions. Normally, this would not be an issue - the ezbinary file handler works fine at reading the mimetype and setting the headings.
 * However, there is a setting in file.ini that allows binaries to popup in the browser (aka in-line) and under that condition the file's mimetype actually does matter
 *
 * What this util does:
 * 1) loops over ezbinary table\
 * 2) finds file on disk
 * 3) updates file on disk with mimetype file extension
 * 4) updates ezbinary file with matching file from disk (check with / without file extension)
 * 
 */
class bfEzBinaryCheck {

	public $sVarPath = './var/ezdemo_site';
	public $sStorageDir = '/storage/original/application/';

	function __construct() {
		$this->sVarPath = eZINI::instance()->variable( 'FileSettings', 'VarDir' );
	}

	public function getEzBinaryRows() {

		$db = eZDb::instance(); 
		$aRows = array();

		$query = "select * from ezbinaryfile";
		$aRows = $db->arrayQuery($query);
		return $aRows;
	}

	public function report() {
		return $this->processBinaryFiles();
	}


	public function fix() {
		return $this->processBinaryFiles(true);
	}

	private function processBinaryFiles($confirm = false) {

		$db = eZDb :: instance();
		$aBinaryRows = $this->getEzBinaryRows();
		foreach($aBinaryRows as $aRow) {

			$bMatch = false;
			
			$sRowFile = $aRow['filename'];

			$aPathInfo = pathinfo($aRow['filename']);
			$sRowFileName = $aPathInfo['filename'];	        
			$sRowFileExt = $aPathInfo['extension'];
	
			
			echo $aRow['mime_type'] . " ";
			echo $aRow['filename']. " ";

			/* 
				Permutations
				0. db: something.ext = file: something.ext [match] (no change)
				1. db: something.ext = file: something -> rename file
				2. db: something = file: something.(auto)ext -> update DB					
				3. db: something = file: something [match] -> update DB and update file	
			*/
			/* 
				0ba74e5312f3829772e98747ee7ab8b1.pdf 
				d2a56fb18aeb813f082d54191eba555b
				1369fec9ea7aef1711db1264f10d249e
			*/

			if(strlen($sRowFileExt)){ //db file extension			

				//try local file no extension
				if(file_exists($this->sVarPath.$this->sStorageDir.$sRowFileName)) {
					echo '[db +ext / file -ext -> Rename local] ';

					if($confirm == true) { //rename file
						rename($this->sVarPath.$this->sStorageDir.$sRowFileName , $this->sVarPath.$this->sStorageDir.$sRowFileName.'.'.$sRowFileExt );
					} else { echo '[test] '; }
				} elseif (file_exists($this->sVarPath.$this->sStorageDir.$sRowFileName.'.')) { //busted dot only version
					echo '[db +ext / file (.)+ext -> Rename Local] ';

					if($confirm == true) { //rename file
						rename($this->sVarPath.$this->sStorageDir.$sRowFileName.'.' , $this->sVarPath.$this->sStorageDir.$sRowFileName.'.'.$sRowFileExt );
					} else { echo '[test] '; }

				} elseif (file_exists($this->sVarPath.$this->sStorageDir.$sRowFileName.'.'.$sRowFileExt)) {
					echo '[db +ext / file +ext -> OK] ';			

				} else {
					echo '[db +ext / no file] ';
				}

			} else { //db no extension

				//auto extension from mimetype
				$sMimeExt = $this->mimeToExt($aRow['mime_type']);
				
				if(strlen( $sMimeExt ) ) { // auto extension
					
					if (file_exists($this->sVarPath.$this->sStorageDir.$sRowFileName)) {
						echo '[db -ext / file -ext -> Rename local -> Update db] ';	

						$query_role_add_ext = "update ezbinaryfile set filename = '" .$sRowFileName.'.'.$sMimeExt. "' where filename = '" .$sRowFileName. "'";
						echo $query_role_add_ext . " ";

						if($confirm == true) { //rename file, update db
							rename($this->sVarPath.$this->sStorageDir.$sRowFileName , $this->sVarPath.$this->sStorageDir.$sRowFileName.'.'.$sMimeExt );
							$db->query($query_role_add_ext);
						} else { echo '[test] '; }

					} elseif (file_exists($this->sVarPath.$this->sStorageDir.$sRowFileName.'.'.$sMimeExt)) {														
						echo '[db -ext / file +ext -> Update db] ';
						$query_role_add_ext = "update ezbinaryfile set filename = '" .$sRowFileName.'.'.$sMimeExt. "' where filename = '" .$sRowFileName. "'";
						echo $query . " ";

						if($confirm == true) { //update db
							$db->query($query_role_add_ext);
						} else { echo '[test] '; }

					} else {
						echo '[db -ext / no file] ';
					}
				} else {
					echo '[db -ext / no autoext] ';
				}
			}			
			echo "\n\r";
		}
		return $confirm;		
	}

	private function mimeToExt($mimetype = '') {

		switch($mimetype) {
			case 'application/pdf': {
				return 'pdf';
				break;
			}
		}
		return '';
	}

}

?>
