<?php

/**
 * phpSandbox class for static analyze
 * @author Sergey Kalita <ns.jeer@gmail.com>
 * @license https://opensource.org/licenses/GPL-3.0 GPL-3.0
 **/

 // @TODO disallow links, if needed
class phpSandbox{
	public static $standart = ['if', 'elseif', 'switch', 'for', 'foreach', 'do', 'while'];
	
	/**
	* Delete all comments & encapsed strings
	**/
	public static function clean($file){
		$string = '';
		$tokens = token_get_all($file);
		foreach ($tokens as $token) {
			if (is_array($token)) {
				if ($token[0] == T_CONSTANT_ENCAPSED_STRING){
					$string .= "''";
				} elseif ($token[0] != T_DOC_COMMENT && $token[0] != T_COMMENT){
					$string .= $token[1];
				}
			} else {
				$string .= $token;
			}
		}
		return $string;
	}
	
	public static function check($file, $allowed){
		if (!$f = @file_get_contents($file)){
			throw new Exception('file ' . $file . ' not found');
		}
		
		$f = static::clean($f);
		
		$name = '\b[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\b';
		
		// class
		if (preg_match('% [\s]+ class [\s]+ %x', $f)){
			throw new Exception('Class declaration not allowed');
		}
		
		// function
		if (preg_match('% [\s]+ function [\s]+ %x', $f)){
			throw new Exception('Function declaration not allowed');
		}
		
		// dynamic calls
		if (preg_match('% \$('.$name.') [\s]* \( %x', $f) || preg_match('% \] [\s]* \( %x', $f)){
			throw new Exception('Variable function calls are not allowed');
		}
		
		// multiply dynamic calls
		if (preg_match('% ->[\s]* ('.$name.' [\s]*->[\s]* '.$name.') [\s]* \( %x', $f)){
			throw new Exception('Multiply dynamic calls are not allowed');
		}
		
		$allowed = array_merge($allowed, static::$standart);
		foreach ($allowed AS &$item){
			if (is_array($item))
				$item = implode('::', $item);
			$item = mb_strtolower($item);
		}
		unset($item);

		// object dynamic calls
		$length = mb_strlen($f);
		preg_match_all('% \$('.$name.') [\s]*=[\s]* new [\s]* ('.$name.') %x', $f, $match, PREG_SET_ORDER+PREG_OFFSET_CAPTURE);
		foreach ($match AS $item){
			$var = $item[1][0];
			$class = $item[2][0];
			$offset = $item[2][1];
			
			// find end of the object use
			if (preg_match('% \$'.$var.' [\s]*=[\s]* %x', $f, $match, PREG_OFFSET_CAPTURE, $offset)){
				$end = $match[0][1];
			} else $end = $length;
			
			$object = mb_substr($f, $offset, $end - $offset);
			
			// find all dynamic use
			preg_match_all('% \$'.$var.' [\s]*->[\s]* ('.$name.') (?=[\s]*\() %x', $object, $match);
			foreach ($match[1] AS $function){
				if (!in_array(mb_strtolower($class . '::' . $function), $allowed)){
					throw new Exception($class . '->' . $function . ' is not allowed');
				}
			}
		}
		// delete object dynamic calls for better search
		$f = preg_replace('% \$('.$name.') [\s]*=[\s]* new [\s]* ('.$name.') %x', '', $f);
		$f = preg_replace('% \$'.$name.' [\s]*->[\s]* ('.$name.') (?=[\s]*\() %x', '', $f);
		
		/*----------------------------------------------------------------------------------------*/
		
		// other functions
		preg_match_all("% ($name [\s]*::[\s]* $name|$name) (?= [\s]* \( ) %x", $f, $match);
		foreach ($match[1] AS $function){
			$function = str_replace(' ', '', $function);
			if (!in_array(mb_strtolower($function), $allowed)){
				throw new Exception($function . ' is not allowed');
			}
		}
		
		return true;
	}
}

/*

use example

$allowed = array(['Yii', 't'], 'get_meta_tags', 'echo', 'eval', 'gfh', 'bad', 'hello', 'substr', 'array_merge', 'json_decode', 'file_get_contents', array('Foo', 'bar'), ['Foo', 'bad']);
phpSandbox::check('Blogger.php', $allowed);
*/