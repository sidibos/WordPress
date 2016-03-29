<?php
/**
 * Assanka CSV parser
 *
 * Provides methods to parse a CSV file or string, returning either a
 * two-dimensional array of results or row-by-row parsing.
 *
 * Differs from fgetcsv/str_getcsv in the following ways:
 * - Allows setting a custom line delimiter
 * - By default automatically supports both \n and \r\n line delimiters
 * - Supports both backslash and double-quote escaping methods automatically
 * - Supports multi-character delimiters
 * - Improved compatibility and less prone to errors
 * - Much slower on some strings (eg lots and lots of tiny cells); as fast or faster (!!) on others
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\CSV;

class CSVParserV1 {
	private $_fielddelimiter = ',';
	private $_fielddelimiterlength = 1;
	private $_rowdelimiter = "\n";
	private $_rowdelimiterlength = 1;
	private $_automaticallyhandleCRLF = true;
	private $_fieldquotestring = '"';
	private $_fieldquotestringlength = 1;
	private $_whitespacechars = array(' '=>true, '\t'=>true);
	private $_parseoffset = 0;
	private $_csvstring = '';
	private $_csvstringlength = 0;
	private $_convertLatin1ToUTF8 = 'auto';


	/**
	 * Set the field delimiter - in "CSV"s, this is a comma, while in "TSV"s this is a tab.
	 * Supports multi-character strings.
	 *
	 * @param string $fielddelimiter The field delimiter to use when parsing
	 *
	 * @return void
	 */
	public function setFieldDelimiter($fielddelimiter) {
		$this->_fielddelimiter = $fielddelimiter;
		$this->_fielddelimiterlength = strlen($fielddelimiter);
		$this->_updateWhitespaceCharacters();
	}

	/**
	 * Set the row delimiter.  If '\n' is set, '\r\n' is also automatically handled.
	 * Supports multi-character strings.
	 *
	 * @param string $rowdelimiter The row delimiter to use when parsing
	 *
	 * @return void
	 */
	public function setRowDelimiter($rowdelimiter) {
		if ($rowdelimiter == "\r\n") $rowdelimiter = "\n";
		$this->_rowdelimiter = $rowdelimiter;
		$this->_rowdelimiterlength = strlen($rowdelimiter);
		$this->_automaticallyhandleCRLF = ($rowdelimiter == "\n");
		$this->_updateWhitespaceCharacters();
	}

	/**
	 * Set the field quote string - for example, " for standard CSVs.  The field quote
	 * character is treated as escaped within the string if it is preceded by either a
	 * backslash or another occurrence of the field quote string (eg "foo""bar" > foo"bar).
	 * Supports multi-character strings.
	 *
	 * @param string $fieldquotestring The field quote string to look for when parsing
	 *
	 * @return void
	 */
	public function setFieldQuoteString($fieldquotestring) {
		$this->_fieldquotestring = $fieldquotestring;
		$this->_fieldquotestringlength = strlen($fieldquotestring);
		$this->_updateWhitespaceCharacters();
	}

	/**
	 * Set whether the parsed string/file should automatically be converted to UTF8
	 * from Latin-1.  By default, Excel exports as Latin-1 so many CSVs may be Latin-1.
	 *
	 * @param mixed $convertsourcefromlatin1 Whether the string should be converted.  Can be either true (always), false (never), or 'auto' (detect for files, assume strings are already utf8)
	 *
	 * @return void
	 */
	public function setConvertLatin1ToUTF8($convertsourcefromlatin1) {
		$this->_convertLatin1ToUTF8 = $convertsourcefromlatin1;
	}

	/**
	 * Set the CSV string to parse.
	 *
	 * @param string $csvstring The CSV string to be parsed.
	 *
	 * @return void
	 */
	public function setCSVString($csvstring) {
		if ($this->_convertLatin1ToUTF8 and $this->_convertLatin1ToUTF8 !== 'auto') {
			$this->_csvstring = utf8_encode($csvstring);
		} else {
			$this->_csvstring = $csvstring;
		}

		// Remove any UTF-8 BOM from the string
		$this->_csvstring = $this->_stripBom($this->_csvstring);

		$this->_csvstringlength = strlen($this->_csvstring);
		$this->_parseoffset = 0;
	}

	/**
	 * Set the file to be parsed.  This is read in and stored at once.
	 * Throws an exception if the file could not be read.
	 *
	 * @param string $filepath              The path to the file to be read and parsed.
	 * @param bool   $autodetectlineendings (optional) Whether to attempt to autodetect the file's line endings
	 *
	 * @return void
	 */
	public function setCSVFile($filepath, $autodetectlineendings = true) {

		// Perform basic file accessibility checks
		if (!is_readable($filepath) or !is_file($filepath)) {
			throw new CSVParserException('Supplied file path does not exist, is not readable, or not a file', get_defined_vars());
		}

		// Try to work out the line endings if appropriate
		if ($autodetectlineendings) {
			$filecheckoutput = shell_exec('file -L -b '.escapeshellarg($filepath));
			if ($filecheckoutput and preg_match('/with ([A-Z]{2,4})(, .*)? line terminators/i', $filecheckoutput, $matches)) {
				if (strtolower($matches[1]) == 'cr') $this->setRowDelimiter("\r");
				else $this->setRowDelimiter("\n");
			}
		}

		$this->_csvstring = file_get_contents($filepath);

		if ($this->_convertLatin1ToUTF8 === 'auto') {
			$this->_csvstring = mb_convert_encoding($this->_csvstring, 'UTF-8', mb_detect_encoding($this->_csvstring, "UTF-8,ISO-8859-1", true));
		} elseif ($this->_convertLatin1ToUTF8) {
			$this->_csvstring = utf8_encode($this->_csvstring);
		}

		// Remove any UTF-8 BOM from the string
		$this->_csvstring = $this->_stripBom($this->_csvstring);

		$this->_csvstringlength = strlen($this->_csvstring);
		$this->_parseoffset = 0;
	}

	/**
	 * Retrieve the entire CSV as a two-dimensional array.
	 *
	 * @return array A two-dimensional array of rows, each containing cells.
	 */
	public function getRows() {
		$this->_parseoffset = 0;
		$rows = array();
		while (($row = $this->getRow()) !== false) {
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Retrieve a single row from the CSV.
	 *
	 * @return array Returns an array of cells for the row, or false if no more rows exist.
	 */
	public function getRow() {

		// Locally cache variables for fast loop access (nb arrays & strings are automatically pointers)
		$i = $this->_parseoffset;
		$csvstring = $this->_csvstring;
		$csvstringlength = $this->_csvstringlength;
		$whitespacechars = $this->_whitespacechars;
		$fieldquotestring = $this->_fieldquotestring;
		$fieldquotestringlength = $this->_fieldquotestringlength;
		$fielddelimiter = $this->_fielddelimiter;
		$fielddelimiterlength = $this->_fielddelimiterlength;
		$rowdelimiter = $this->_rowdelimiter;
		$rowdelimiterlength = $this->_rowdelimiterlength;
		$automaticallyhandleCRLF = $this->_automaticallyhandleCRLF;

		// If the parse offset is equal to the overall string length, parsing is complete - return false.
		if ($i == $csvstringlength) return false;

		$row = array();
		$rowendencountered = false;
		while ($i < $csvstringlength) {
			$csvcell = '';
			$nextcellendchar = 0;

			// Skip unescaped, unquoted whitespace at the very start of the cell
			while (!empty($whitespacechars[$csvstring[$i]])) ++$i;

			// Check the start of the cell for the enclosing character, and if so loop along the string
			// to capture the entire cell
			if ($csvstring[$i] == $fieldquotestring) {
				++$i;
				while ($i < $csvstringlength) {

					// Look for the cell end string
					$nextcellendchar = strpos($csvstring, $fieldquotestring, $i);

					// Check to see if the end string is escaped, or escaping (eg \' or '')
					if ($nextcellendchar !== false) {
						$nextcellendchar -= $i;
						$isescaped = false;
						$j = 1;

						// Process backslash escapes
						while ($j <= $nextcellendchar and $csvstring[$i + $nextcellendchar - $j] == "\\") {
							$isescaped = !$isescaped;
							++$j;
						}
						if ($isescaped) {
							if ($nextcellendchar - $j + 1) $csvcell .= substr($csvstring, $i, $nextcellendchar - $j + 1);
							$numescapes = ($j - 2) / 2;
							while ($numescapes--) $csvcell .= "\\";
							$csvcell .= $fieldquotestring;
							$i += $nextcellendchar + $fieldquotestringlength;
							continue;
						}

						// Process double-encloser escapes
						if ($nextcellendchar + $i + $fieldquotestringlength < $csvstringlength and substr($csvstring, $i + $nextcellendchar + $fieldquotestringlength, $fieldquotestringlength) == $fieldquotestring) {
							$csvcell .= substr($csvstring, $i, $nextcellendchar + $fieldquotestringlength);
							$i += $nextcellendchar + $fieldquotestringlength + $fieldquotestringlength;
							continue;
						}
					}

					// Not escaped - add on the cell string up to the terminating character
					if ($nextcellendchar === false) {
						$csvcell .= substr($csvstring, $i);
						$i = $csvstringlength;
					} else {
						$csvcell .= substr($csvstring, $i, $nextcellendchar);
						$i += $nextcellendchar + $fieldquotestringlength;
					}

					// At the end of the cell - remove whitespace
					while ($i < $csvstringlength and !empty($whitespacechars[$csvstring[$i]])) ++$i;

					// Break out of the quoted field processing loop
					break;
				}
			}

			// Quoted strings have been processed - now scan until the next field end or line end.
			// This section also processes unquoted strings and numbers.
			while ($i < $csvstringlength) {

				// Check whether a line end or field end occur first
				$fieldendpos = strpos($csvstring, $fielddelimiter, $i);
				if ($fieldendpos === 0) $lineendpos = false;
				else $lineendpos = strpos($csvstring, $rowdelimiter, $i);
				if ($fieldendpos !== false and ($fieldendpos < $lineendpos or $lineendpos === false)) {
					$nextfieldendpos = $fieldendpos - $i;
					$skiplength = $fielddelimiterlength;
				} elseif ($lineendpos !== false) {
					$nextfieldendpos = $lineendpos - $i;
					$skiplength = $rowdelimiterlength;
					if ($automaticallyhandleCRLF and $csvstring[$i + $nextfieldendpos - 1] == "\r") {
						$nextfieldendpos--;
						++$skiplength;
					}
					$rowendencountered = true;
				} else {
					$csvcell .= substr($csvstring, $i);
					$i = $csvstringlength;
					break;
				}

				// Check whether the cell termination string was escaped
				$isescaped = false;
				$j = 1;
				if ($nextfieldendpos) while ($j <= $nextfieldendpos and $csvstring[$i + $nextfieldendpos - $j] == "\\") {
					$isescaped = !$isescaped;
					++$j;
				}

				// If it was, continue processing the cell
				if ($isescaped) {
					if ($nextfieldendpos - $j + 1) $csvcell .= substr($csvstring, $i, $nextfieldendpos - $j + 1);
					$numescapes = ($j - 2) / 2;
					while ($numescapes--) $csvcell .= "\\";
					$csvcell .= substr($csvstring, $i + $nextfieldendpos, $skiplength);
					$i += $nextfieldendpos + $skiplength;
					continue;
				}

				// Add the cell contents up to the terminating character, if any
				$csvcell .= substr($csvstring, $i, $nextfieldendpos);
				$i += $nextfieldendpos + $skiplength;

				break;
			}

			// The cell has been fully captured.  Add it to the row
			$row[] = $csvcell;

			// If a line ending was encountered, return the row
			if ($rowendencountered) {
				$this->_parseoffset = $i;
				return $row;
			}
		}

		$this->_parseoffset = $i;

		// Check if any row exists to return
		if ($row) return $row;

		return false;
	}

	/**
	 * Retrieve the progress through the file so far - only useful with getRow().
	 *
	 * @return float The percentage through the CSV string parsing has reached, as a float to 4 decimal places.
	 */
	public function getParseProgress() {
		if ($this->_csvstringlength == 0) return 0;
		return round($this->_parseoffset / $this->_csvstringlength, 4);
	}

	/**
	 * Strip a UTF-8 BOM from the beginning of the string
	 *
	 * @param string $string The original string
	 * @return string The original string with no BOM
	 */
	private function _stripBom($string) {

		static $bom;
		if (!$bom) $bom = pack("CCC", 0xef, 0xbb, 0xbf);

		return ltrim($string, $bom);
	}

	private function _updateWhitespaceCharacters() {
		$whitespacechars = array();
		if ($this->_fielddelimiter != " " and $this->_rowdelimiter != " " and $this->_fieldquotestring != " ") $whitespacechars[" "] = true;
		if ($this->_fielddelimiter != "\t" and $this->_rowdelimiter != "\t" and $this->_fieldquotestring != "\t") $whitespacechars["\t"] = true;
		$this->_whitespacechars = $whitespacechars;
	}
}
