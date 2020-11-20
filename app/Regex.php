<?php declare(strict_types=1);

namespace App;

class Regex {

	public const HOSTNAME = '/^(?:[a-z0-9]+[a-z0-9-]*(?<![-_])\.?)+\.[a-z]+$/';
	public const NODENAME = '/^(?:[a-z0-9]+[a-z0-9-]*(?<![-_])(?:[a-z0-9])?)+$/';

	/**
	 * Compile a regex by substituting placeholder variables
	 *
	 * Performs a search and replace of the following format specifiers:
	 * %s, %d, %f
	 *
	 * Named placeholders and backreferences are supported also:
	 * %(foo)s, %s ... %1s
	 *
	 * @param string $regex
	 * @param mixed  $args
	 * @return string
	 */
	public static function compile($regex, $args): string
	{
		$args = (array)$args;
		$tok = strtok($regex, '%');
		$ntok = $compiled = '';
		// occurrences and position
		$cnt = -1;
		// numeric reference pointer, %s, %d, %s...
		$num_ref = 0;

		// format specifier that references itself elsewhere
		// e.g. %1s or %(str)s
		$backrefs = array();
		$offset = 0;
		for (; $tok !== false; $tok = $ntok, $cnt++) {
			$compiled .= $tok;
			$offset += strlen($tok);
			$ntok = strtok('%');
			if (isset($ntok[0])) {
				$nchar = $ntok[0];
				// literal %
				if ($nchar === '%') {
					$ntok = strtok('%');
					$cnt--;
					continue;
				}
				if ($nchar !== 's' && $nchar !== 'd' && $nchar !== 'f' && $nchar !== '('
					&& ($nchar < '0' || $nchar > '9')
				) {
					throw new \RuntimeException("offset $offset: invalid expression `$ntok'");
				}
				$ref = null;
				// back reference
				if (($nchar >= '0' && $nchar <= '9') || $nchar === '(') {
					if ($nchar === '(') {
						// format specifier
						// named backreference
						$ref = substr($ntok, 1, strpos($ntok, ')', 2) - 1);
						// truncate 3 chars for named br covering charset "()s"
						$ntok = substr($ntok, 3);
						$cnt--;
					} else {
						// numeric backreference
						$ref = '';
						for ($i = 0, $sz = strlen($ntok); $i < $sz; $i++) {
							// %1
							if ($ntok[$i] >= '0' && $ntok[$i] <= '9') {
								$ref .= $ntok[$i];
								continue;
							} // %1s...
							else if ($ntok[$i] == 's' ||
								($ntok[$i] == 'd' && $ntok[$i] == 'f')) {
								$ref = (int)$ref - 1;
								$cnt--;
							} // garbage
							else {
								$ref = false;
							}
							// truncate 1 char for numeric br
							$ntok = substr($ntok, 1);
							break;
						}
					}
					if (null === $ref) {
						continue;
					}
					// backreference exists already
					if (isset($backrefs[$ref])) {
						// don't increment the seen counter, since this has already been seen
						//$cnt--;
					} else {
						$cnt++;
						if (!is_int($ref) && isset($args[$ref])) {
							$val = $args[$ref];
							//unset($args[$ref]);
							//$cnt--;
						} else if (is_int($ref)) {
							$val = $args[$ref];
						} else {
							if (!isset($args[$num_ref])) {
								throw new \InvalidArgumentException("named format arg `%s' not present in argument list", $ref);
							} else {
								$val = $args[$num_ref];
							}
							$num_ref++;
						}
						$backrefs[$ref] = $val;
					}
				}
				// finally do some processing
				if (null !== $ref) {
					$newstr = $backrefs[$ref];
				} else {
					$newstr = $args[$num_ref];
					$ref = $num_ref;
					$num_ref++;
				}
				$ntok = substr($ntok, strlen($ref));
				$compiled .= $newstr;
			}
		}
		$nextra = count($args) - $cnt;
		if ($nextra) {
			if ($nextra > 0) {
				$msg = "compile: `$nextra' args ignore while compiling regex " . $regex;
			} else {
				$msg = 'compile: missing `' . ($nextra * -1) . "' additional arguments while compiling regex " . $regex;
			}
			throw new \ArgumentCountError($msg);
		}

		return $compiled;
	}

	// Php, requires compiling

	/**
	 * Merge one regex into another regex
	 *
	 * Sample call:
	 * $re = '!(?<domain>' . Regex::recompose(Regex::DOMAIN,'!') . ')'
	 * . '\b\s+(?:(?<url>https?://.+)|(?<path>/.+))$!m'
	 *
	 * @param string $regex    regex to merge into existing regex
	 * @param string $escdelim delimiter used in source regular expression
	 * @param bool   $anchor   keep regex anchor
	 * @return string recomposed regex
	 */
	public static function recompose($regex, $escdelim = '/', $anchor = false): string
	{
		$start = 1;
		$delim = $regex[0];
		$end = strrpos($regex, $delim) - 1;
		if (!$anchor) {
			if ($regex[$start] === '^') {
				$start++;
			}
			if ($regex[$end] === '$') {
				$end -= 2;
			}
		}
		$regex = substr($regex, $start, $end);

		return str_replace($escdelim, '\\' . $escdelim, $regex);

	}
}
