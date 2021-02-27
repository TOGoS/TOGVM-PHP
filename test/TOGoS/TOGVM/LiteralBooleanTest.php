<?php

class TOGoS_TOGVM_LiteralBooleanTest extends TOGoS_TOGVM_TestCase
{
	protected function tp( $z, $s ) {
		$expression = [
			'classRef' => 'http://ns.nuke24.net/TOGVM/Expression/LiteralBoolean',
			'literalValue' => $s
		];
		$interp = new TOGoS_TOGVM_Interpreter([]);
		$r = $interp->evaluate($expression, []);
		$this->assertSame($z, $r);
	}
	
	public function testThem() {
		$this->tp( true, true );
		$this->tp( true, 'true' );
		$this->tp( true, 1 );
		$this->tp( true, '1' );
		$this->tp( false, false );
		$this->tp( false, 'false' );
		$this->tp( false, 0 );
		$this->tp( false, '0' );
	}
}
