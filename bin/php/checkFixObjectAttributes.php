<?php
set_time_limit(0);
error_reporting(E_ALL);

/**
 * When there are a ton of objects, sometimes attribute addition to classes leaves some actual objects without those attributes. Run this file:
 * 1) With no arguments to see if you have a problem
 * 2) With -f fix to fix all classes
 * 3) With -f fix -c {class_id} to fix one class
 * 
 * @copyright Copyright (C) Beaconfire 2012. All rights reserved.
 * @version 1.0.0
 */

#################
#  Setting up env
#################
require 'autoload.php';
if ( file_exists( "config.php" ) ) {
    require "config.php";
}
$cli = eZCLI::instance();
$params = new ezcConsoleInput();

$siteaccessOption = new ezcConsoleOption( 's', 'siteaccess', ezcConsoleInput::TYPE_STRING );
$siteaccessOption->mandatory = false;
$siteaccessOption->shorthelp = "The siteaccess name.";
$params->registerOption( $siteaccessOption );

$helpOption = new ezcConsoleOption( 'h', 'help' );
$helpOption->mandatory = false;
$helpOption->shorthelp = "Show help information";
$params->registerOption( $helpOption );

$classIdOpt = new ezcConsoleOption( 'c', 'classid', ezcConsoleInput::TYPE_STRING );
$classIdOpt->mandatory = false;
$classIdOpt->shorthelp = "Class ID";
$params->registerOption( $classIdOpt );

$functionOpt = new ezcConsoleOption( 'f', 'function', ezcConsoleInput::TYPE_STRING );
$functionOpt->mandatory = false;
$functionOpt->shorthelp = "Function (report|fix)";
$params->registerOption( $functionOpt );

// Process console parameters
try {
	$params->process();
} catch ( ezcConsoleOptionException $e ) {
	print $e->getMessage(). "\n\n" . $params->getHelpText( 'run script.' ) . "\n\n";
	exit();
}

if ($helpOption->value == 1) {
	print $params->getHelpText( 'run script.' ) . "\n";
	exit();
}

// Init an eZ Publish script - needed for some API function calls
// and a siteaccess switcher
$ezp_script_env = eZScript::instance( array( 'debug-message' => '',
                                              'use-session' => true,
                                              'use-modules' => true,
                                              'use-extensions' => true ) );
$ezp_script_env->startup();
if( $siteaccessOption->value ) {
    $ezp_script_env->setUseSiteAccess( $siteaccessOption->value );
}
$ezp_script_env->initialize();

# Start script body
$classID = intval($classIdOpt->value);
$function = $functionOpt->value;
if ($function != "fix") {
	$function = "report";
}

$oClassFixer = new classAttributeFixer();
if ($function == "report") {
	// pull all classes, unless class specified
	if ($classID != 0) {
		$aResultHash = $oClassFixer->inspectOneClass($classID);
	} else {
		$aResultHash = $oClassFixer->inspectAllClasses();
	}
	// then display the executive summary
	print "===================\n BAD OBJECT SUMMARY\n===================\n\n";
	$bHasBadClasses = false;
	foreach ($aResultHash as $classDetailHash) {
		if (!($classDetailHash["is_class_ok"])) {
			print $classDetailHash["class_identifier"]." (total ".$classDetailHash["totalObjectCount"]." objects): ";
			// find out what's wrong
			if ($classDetailHash["countObjectsWithExtraParams"] > 0) {
				print ($classDetailHash["countObjectsWithExtraParams"]." objects with extra params");
			}
			if (sizeof($classDetailHash["missingAttributes"]) > 0) {
				print ("objects missing attributes [".implode (", ", $classDetailHash["missingAttributes"])."]");
			}
			print "\n";
			$bHasBadClasses = true;
		}
	}
	if (!$bHasBadClasses) {
		print "All checks out fine\n";
	}
} else {
	// obviously, call class fixer to do this as well
	if ($classID != 0) {
		$aResultHash = $oClassFixer->fixBadClass($classID);
	} else {
		$aResultHash = $oClassFixer->fixAllClasses();
	}
}

// Avoid fatal error at the end
$ezp_script_env->shutdown();
die();

?>