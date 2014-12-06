<?php

/*
 * Token format: {
 *   'sourceText': (may not actually fill this in),
 *   'value': (text value of token, after parsing quotes),
 *   'quoting': 'bare|single|double', (logical quote type),
 *   'sourceLocation': { 'filename': ..., 'lineNumber': ..., 'columnNumber': ..., 'endLineNumber': ..., 'endColumnNumber': ... },
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
	
	protected function sl($l, $c) {
		return array('filename'=>$this->filename, 'lineNumber'=>$l, 'columnNumber'=>$c);
	}
	
	protected function beginToken( $state, $c, $lineNumber, $columnNumber ) {
		$this->state = $state;
		$this->tokenLocation = $this->sl($lineNumber, $columnNumber);
		$this->tokenValue = $c;

	}
	protected function checkSelfDelimitingTokenChar($c, $lineNumber, $columnNumber) {
		if( self::isSelfDelimitingTokenChar($c) ) {
			$this->flush();
			$this->state = self::STATE_BAREWORD;
			$this->tokenLocation = $this->sl($lineNumber, $columnNumber);
			$this->tokenValue = $c;
			$this->flush();
			return true;
		}
		return false;
	}
	
	public function char($c) {
		$lineNumber = $this->lineNumber;
		$columnNumber = $this->columnNumber;
		if( $c == "\n" ) {
			++$this->lineNumber;
			$this->columnNumber = 1;
		} else if( $c == "\t" ) {
			do {
				++$this->columnNumber;
			} while( ($this->columnNumber - 1) % 8 != 0 );
		} else {
			++$this->columnNumber;
		}
		
		switch( $this->state ) {
		case self::STATE_WHITESPACE:
			if( $this->checkSelfDelimitingTokenChar($c, $lineNumber, $columnNumber) ) return;
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
				$this->beginToken(self::STATE_BAREWORD, $c, $lineNumber, $columnNumber);
				return;
			}
		case self::STATE_BAREWORD:
			if( $this->checkSelfDelimitingTokenChar($c, $lineNumber, $columnNumber) ) return;
			if( self::isHorizontalWhitespace($c) ) {
				$this->flush();
				return;
			}
			
			switch($c) {
			case '"': case '"':
				throw new TOGoS_TOGCM_ParsError("Malplaced quote", $this->sel($lineNumber,$columnNumber));
			case '\\':
				throw new TOGoS_TOGCM_ParsError("Malplaced backslash", $this->sel($lineNumber,$columnNumber));
			}
			
			$this->tokenValue .= $c;
			return;
		default:
			throw new Exception("AXD");
		}
	}
	
	public function string($string) {
		for( $i=0; $i<strlen($string); ++$i ) {
			$this->char($string[$i]);
		}
	}
	
	public function flush() {
		switch( $this->state ) {
		case self::STATE_WHITESPACE: return;
		case self::STATE_BAREWORD: $quoting = 'bare'; break;
		case self::DOUBLE_QUOTE: $quoting = 'double'; break;
		case self::SINGLE_QUOTE: $quoting = 'single'; break;
		default: return;
		}
		$this->tokenLocation['endLineNumber'] = $this->lineNumber;
		$this->tokenLocation['endColumnNumber'] = $this->columnNumber;
		$this->state = self::STATE_WHITESPACE;
		
		call_user_func( $this->tokenCallback, array(
			'value' => $this->tokenValue,
			'quoting' => $quoting,
			'sourceLocation' => $this->tokenLocation
		));
	}
	
	public static function tokenize($string, $sourceLocation) {
		$C = new TOGoS_TOGVM_Thneed();
		$T = new TOGoS_TOGVM_Tokenizer($C);
		$T->setSourceLocation($sourceLocation);
		$T->string($string);
		$T->flush();
		$sourceLocation = $T->getSourceLocation();
		return $C->getElements();
	}
}
