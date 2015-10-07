<?php

/*
    Author: David Sayre @ Beaconfire
    Purpose: verify the file system iamges against the database ezimage table
    Use: Extend this class with your own and re-define the variables as needed
*/

class verifyEzImageFiles{

	var $cli;
        
    var $mysqli;

    var $log_level = 2;
    var $limit = 999999;

	var $error_log = 'ezimagefile_error.log';
	var $debug_log = 'ezimagefile.log';
    var $debug_write = false;

    var $query_ezimagefiles = "select * from ezimagefile";
    var $data_file = "ezimagefiles-missing.csv";
    var $csv_delim = ',';

    function __construct()
    {

            $this->mysqlConnect();
    }

    function mysqlConnect() {

        $this->mysqli = new mysqli( EZ_CONN_HOST, EZ_CONN_USERNAME, EZ_CONN_PASSWORD, EZ_CONN_DBNAME, EZ_CONN_PORT);
        if ( mysqli_connect_errno() )
        {
            printf( "Connect mysql failed: %s\n", mysqli_connect_error() );
            exit();
        } else {
           // print_r($this->mysqli);
        }
    }

    function setFile($sFileName){
    	$this->data_file = $sFileName;
    	return true;
    }

    function getFilePaths() {
    	$query = $this->query_ezimagefiles;

    	$aFilePaths = array();

    	$result = $this->mysqli->query($query);
        while( $row = $result->fetch_array() ) {
            $aFilePaths[] = $row['filepath'];
		}
        return $aFilePaths;
    }	

    function exportData() {

    	$this->clearFile();

    	$query = $this->query_ezimagefiles;

    	$result = $this->mysqli->query($query);
        while( $row = $result->fetch_array() ) {

        	$sFilePath = $row['filepath'];
	       	$nId = $row['id'];

            $this->writeFile($nId. ','.$sFilePath);
		}
        return true;

    }

    /* override */
    function getIgnorePatterns() {
         $aIgnorePatterns = array(
                        '_small'
                        ,'_medium'
                        ,'_large'
                        ,'_videosmall'
                        ,'_line_email'
                        ,'_videolarge'
                        ,'_reference'
                        ,'_articlethumbnail'
                        ,'_full_image'
        );

        return $aIgnorePatterns;
    }

    function verifyFromCSV($bToggleShow = true) {
		$this->writeDebug('white',1,'read CSV');     

        $aIgnorePatterns = $this->getIgnorePatterns();

		if(file_exists($this->data_file)) {
			$this->writeDebug('white',1,'Start Processing');
			$row = 1;
			if (($handle = fopen($this->data_file, "r")) !== FALSE) {
				$continue = true;
			    while (($data = fgetcsv($handle, 1000, $this->csv_delim)) !== FALSE && $continue) {
			    	$this->writeDebug('white',3,'parse row '.$row);
			    	$row++;
			    	$num = count($data);
			    	$this->writeDebug('white',4,$num." fields in line ". $row);
			    	if($this->log_level > 3) { 
			    			$this->writeDebug('blue',5,implode($data,'|'));
			    	}
					$sFilePath = $data[1];
                    
                    $bIgnoreFound = $this->array_in_string($sFilePath,$aIgnorePatterns);
                    if( $bIgnoreFound ) { 

                        $this->writeDebug('white',4,'Ignore imagealias');

                    } else {

                    	if(file_exists($sFilePath)) {
                            if(!$bToggleShow){
    						  $this->writeDebug('white',2,'File found : '.$sFilePath);
                            }
    					} else {
    						if($bToggleShow){
                                $this->writeDebug('red',2,'File NOT found : '.$sFilePath);
                            }
    					}

                    }
                    

					//limit optional
					if($this->limit) { 
						if($this->limit > 0 && $row > $this->limit) {
							$this->writeDebug('white',1,'Limit '.$this->limit);
							break;
						}
					}
			    }
			    fclose($handle);
			}
			$this->writeDebug('white',1,'Finished Processing');

		} else {
			$this->writeDebug('red',1,'Data file '.$this->data_file.' not found');
		}

		return true;
	}	

    function array_in_string($needle,$haystack)
    {
       foreach ($haystack as $item)
       {
          if (stripos($needle, $item) > 0) {
             return true;
             break;
          }
       }
       return false;
    }

    function string_in_array($needle, $haystack)
    {
       foreach ($haystack as $item)
       {
          if (stripos($item, $needle) > 0)
          {
             return true;
             break;
          }
       }
       return false;
    }

	function writeFile($sLine){
        file_put_contents($this->data_file,$sLine."\n", FILE_APPEND | LOCK_EX);
    }

    function clearFile(){
        file_put_contents($this->data_file,'', LOCK_EX);
    }

    /********/ 
    /* LOGS */
    function writeDebug($color = 'white', $level = 3, $debug, $newline = true){     
        if($level <= $this->log_level) {
            if(is_object($this->cli)) { 
                $this->cli->output( $this->cli->stylize( $color, $debug ),$newline);
            } else {
                echo($debug. "\r\n");               
            }
            if($this->debug_write) {
                file_put_contents($this->debug_log,$debug. "\r\n", FILE_APPEND | LOCK_EX );            
            } 
        }
    }

    function writeErrors($error) {
        file_put_contents($this->error_log,$error. "\r\n", FILE_APPEND | LOCK_EX );    
    }

    function initLogs() {
    	file_put_contents($this->error_log,'');
		file_put_contents($this->debug_log,'');
	}
}
?>