<?php

/**
 * Provides the actual substance of actions
 */
interface TOGoS_TOGVM_Actionlet
{
	public function __invoke( array $c );
}
