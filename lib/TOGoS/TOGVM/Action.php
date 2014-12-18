<?php

interface TOGoS_TOGVM_Action
{
	/**
	 * Return a list of actions to be run next.  These will be appended
	 * to a queue, so for a simple single-threaded app, we'd want to
	 * only return one at a time.
	 */
	public function step( array $c );
}
