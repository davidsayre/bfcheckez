<?php
////////////////////
//  Setting up env
///////////////////
require 'autoload.php'; // because this is not a cron script

if (file_exists( "config.php" )) {
    require "config.php";
}

// set up command line params here
$params = new ezcConsoleInput();

$helpOption = new ezcConsoleOption( 'h', 'help' );
$helpOption->mandatory = false;
$helpOption->shorthelp = "Show help information";
$params->registerOption( $helpOption );

$siteaccessOption = new ezcConsoleOption( 's', 'siteaccess', ezcConsoleInput::TYPE_STRING );
$siteaccessOption->mandatory = false;
$siteaccessOption->shorthelp = "The siteaccess name.";
$params->registerOption( $siteaccessOption );

$runrealOpt = new ezcConsoleOption( 'r', 'run-fix', ezcConsoleInput::TYPE_STRING );
$runrealOpt->mandatory = false;
$runrealOpt->shorthelp = "Run Fix (Y)?";
$params->registerOption( $runrealOpt );

// Process console parameters
try {
  $params->process();
} catch ( ezcConsoleOptionException $e ) {
	echo $e->getMessage(). "\n";
	echo "\n";
	echo $params->getHelpText( 'Some quick explanation' ) . "\n";
	echo "\n";
	exit();
}
// Init an eZ Publish script - needed for some API function calls
// and a siteaccess switcher

$ezp_script_env = eZScript::instance(array(
	'debug-message' => '',
	'use-session' => true,
	'use-modules' => true,
	'use-extensions' => true
));
$ezp_script_env->startup();

if( $siteaccessOption->value ) {
	$ezp_script_env->setUseSiteAccess( $siteaccessOption->value );
}
$ezp_script_env->initialize();

//////////////////////////
// Script process
//////////////////////////

$ezBinaryFixManager = new bfEzBinaryFix();

if ($runrealOpt->value == "Y") {
	echo 'Fixing real data!\n';
	$ezBinaryFixManager->fix();
} else {
	$ezBinaryFixManager->report();
	echo 'Run with "--run-for-real=Y" to really fix the issue!\n';	
}

// Avoid fatal error at the end
$ezp_script_env->shutdown();

?>