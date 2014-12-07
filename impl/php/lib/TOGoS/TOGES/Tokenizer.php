<?php

/*
 * Token format: {
 *   'sourceText': (may not actually fill this in),
 *   'value': (text value of token, after parsing quotes),
 *   'quoting': 'bare|single|double', (logical quote type),
 *   'sourceLocation': { 'filename': ..., 'lineNumber': ..., 'columnNumber': ..., 'endLineNumber': ..., 'endColumnNumber': ... },
 * }
 */

class TOGoS_TOGES_Tokenizer
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
	const STATE_SKIP_WHITESPACE = 6;
	
	protected $tokenCallback;
	protected $state = self::STATE_WHITESPACE;
	protected $tokenLocation;
	protected $tokenValue = '';
	protected $filename = '(unnamed source)';
	protected $lineNumber = 1;
	protected $columnNumber = 1;
	
	public function __construct($tokenCallback) {
		$this->tokenCallback = $tokenCallback;
	}
	
	public function setSourceLocation( $sourceLocation ) {
		$this->filename = $sourceLocation['filename'];
		$this->lineNumber = $sourceLocation['lineNumber'];
		$this->columnNumber = $sourceLocation['columnNumber'];
	}
	
	/* Returns source location of next character */
	protected function getSourceLocation() {
		return array(
			'filename' => $this->filename,
			'lineNumber' => $this->lineNumber,
			'columnNumber' => $this->columnNumber
		);
	}
	
	public static function isHorizontalWhitespace($c) {
		switch($c) {
		case " ": case "\r": case "\t": return true;
		default: return false;
		}
	}
	
	public static function isSelfDelimitingTokenChar($c) {
		// TODO: I suppose this could be configurable!
		switch($c) {
		case "\n":  case "\v": case "\f":
		case "(": case ")":
		case "[": case "]":
		case "{": case "}":
		case ",": case ";":
			return true;
		default:
			return false;
		}
	}
	
	protected function beginToken( $state, $c ) {
		$this->state = $state;
		$this->tokenLocation = $this->getSourceLocation();
		$this->tokenValue = $c;

	}
	protected function checkSelfDelimitingTokenChar($c) {
		if( self::isSelfDelimitingTokenChar($c) ) {
			$this->flush();
			$this->state = self::STATE_BAREWORD;
			$this->tokenLocation = $this->getSourceLocation();
			$this->tokenValue = $c;
			$this->flush(true);
			return true;
		}
		return false;
	}

	// Valid during char($c) calls
	protected $nextLineNumber;
	protected $nextColumnNumber;
	
	protected function getCharSourceLocation() {
		return array(
			'filename' => $this->filename,
			'lineNumber' => $this->lineNumber,
			'columnNumber' => $this->columnNumber,
			'endLineNumber' => $this->nextLineNumber,
			'endColumnNumber' => $this->nextColumnNumber
		);
	}

	protected function _char($c) {
		switch( $this->state ) {
		case self::STATE_WHITESPACE:
			if( $this->checkSelfDelimitingTokenChar($c) ) return;
			if( self::isHorizontalWhitespace($c) ) return;
			switch( $c ) {
			case '"':
				$this->state = self::STATE_DOUBLE_QUOTE;
				return;
			case "'":
				$this->state = self::STATE_SINGLE_QUOTE;
				return;
			case '#':
				$this->state = self::STATE_LINE_COMMENT;
				return;
			case '\\':
				$this->state = self::STATE_SKIP_WHITESPACE;
				return;
			default:
				// I guess everything else is a valid bareword character?
				$this->beginToken(self::STATE_BAREWORD, $c);
				return;
			}
		case self::STATE_BAREWORD:
			if( $this->checkSelfDelimitingTokenChar($c) ) return;
			if( self::isHorizontalWhitespace($c) ) {
				$this->flush();
				return;
			}
			
			switch($c) {
			case '"': case '"':
				throw new TOGoS_TOGCM_ParseError("Malplaced quote", array($this->getCharSourceLocation()));
			case '\\':
				throw new TOGoS_TOGCM_ParseError("Malplaced backslash", array($this->getCharSourceLocation()));
			}
			
			$this->tokenValue .= $c;
			return;
		default:
			throw new Exception("AXD");
		}
	}

	public function char($c) {
		if( $c == "\n" ) {
			$this->nextLineNumber = $this->lineNumber+1;
			$this->nextColumnNumber = 1;
		} else {
			$this->nextLineNumber = $this->lineNumber;
			$this->nextColumnNumber = $this->columnNumber + 1;
			if( $c == "\t" ) {
				while( ($this->nextColumnNumber - 1) % 8 != 0 ) ++$this->nextColumnNumber;
			}
		}
		
		$this->_char($c);
		
		$this->lineNumber = $this->nextLineNumber;
		$this->columnNumber = $this->nextColumnNumber;
	}
	
	public function string($string) {
		for( $i=0; $i<strlen($string); ++$i ) {
			$this->char($string[$i]);
		}
	}
	
	public function flush( $breakAfter=false ) {
		switch( $this->state ) {
		case self::STATE_WHITESPACE: return;
		case self::STATE_BAREWORD: $quoting = 'bare'; break;
		case self::DOUBLE_QUOTE: $quoting = 'double'; break;
		case self::SINGLE_QUOTE: $quoting = 'single'; break;
		default: return;
		}
		$this->tokenLocation['endLineNumber']   = $breakAfter ? $this->nextLineNumber   : $this->lineNumber;
		$this->tokenLocation['endColumnNumber'] = $breakAfter ? $this->nextColumnNumber : $this->columnNumber;
		$this->state = self::STATE_WHITESPACE;
		
		call_user_func( $this->tokenCallback, array(
			'value' => $this->tokenValue,
			'quoting' => $quoting,
			'sourceLocation' => $this->tokenLocation
		));
	}
	
	public static function tokenize($string, &$sourceLocation) {
		$C = new TOGoS_TOGVM_Thneed();
		$T = new TOGoS_TOGES_Tokenizer($C);
		$T->setSourceLocation($sourceLocation);
		$T->string($string);
		$T->flush();
		$sourceLocation = $T->getSourceLocation();
		return $C->getElements();
	}
}
