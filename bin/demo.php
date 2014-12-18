<?php

require_once __DIR__."/../vendor/autoload.php";

function mkact( $actlet ) {
	return new TOGoS_TOGVM_NormalAction($actlet);
}

function mkcfaa( $name, array $args ) {
	$getFuncAction = mkact(new TOGoS_TOGVM_Actionlet_GetContextValue($name));
	return $getFuncAction->onResult(function($proc) use ($args) {
		return array(mkact(new TOGoS_TOGVM_Actionlet_InvokeProcedure($proc,$args)));
	});
}

$action = mkcfaa( 'standardOutputFunction', array("Hello") )->andThen( array(mkcfaa('standardOutputFunction', array(", world!"))) );

// $echolet = new TOGoS_TOGVM_Actionlet_Echo("Hello, world!\n");
// $echoAction = new TOGoS_TOGVM_NormalAction($echolet);

function le_echo( $x ) { echo $x; }

$ctx = array(
	'standardOutputFunction' => 'le_echo',
	'standardInputLineFunction' => function() { return fgets(STDIN); }
);

$interp = new TOGoS_TOGVM_Interpreter($ctx);
$interp->enqueueAction($action);
$interp->run();
