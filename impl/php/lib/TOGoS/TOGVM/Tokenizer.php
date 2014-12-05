<?php

/*
 * Token format: {
 *   'sourceText': (may not actually fill this in),
 *   'value': (text value of token, after parsing quotes),
 *   'quoting': 'bare|single|double', (logical quote type),
 *   'span': { 'filename': ..., 'beginLineNumber': ..., 'columnNumber': ... },
 *   'endLocation
 * }
 */

class TOGoS_TOGVM_Tokenizer
{
	/*
	public static function barewordRegex() {
		return '[A-Za-z0-9_\-\+!@#$%^&*]+';
	}
	public static function flatQuoteRegex($quoteChar) {
		return $quoteChar."((?:[^$quoteChar]|\\[\\'\"abfrntv]|\\u[A-Fa-f0-9]{4})*)".$quoteChar;
	}
	public static function tokenize($str) {
		preg_match(
	}
	*/
	
	const STATE_WHITESPACE = 0;
	const STATE_BAREWORD = 1;
	const STATE_SINGLE_QUOTE = 2;
	const STATE_DOUBLE_QUOTE = 3;
	const STATE_QUOTE_ENDED = 4;
	const STATE_LINE_COMMENT = 5;
	
	protected $state = self::STATE_WHITESPACE;
	protected $tokenText;
	
	public static function isHorizontalWhitespace($c) {
		switch($c) {
		case " ": case "\r": case "\t": return true;
		}
		return false;
	}
	
	public function char($c) {
		switch( $this->state ) {
		case self::STATE_WHITESPACE:
			if( self::isWhitespace($c) ) return;
			// TODO
		}
	}
	
	public static function tokenize($string, $filename, $lineNumber, $columnNumber) {
		//new TOGoS_TOGVM_Tokenizer();
		return array();
	}
}
