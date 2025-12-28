<?php
declare(strict_types=1);

/**
 * LUAParser - Parse Lua SavedVariables files into PHP arrays
 * 
 * Handles:
 * - Tables (indexed and associative)
 * - Nested structures
 * - Numbers (int and float)
 * - Strings (quoted and unquoted keys)
 * - Booleans (true/false, converted to PHP booleans)
 * - nil (converted to PHP null)
 * - Comments (-- style)
 * 
 * Does NOT handle:
 * - Lua functions
 * - Metatables
 * - Complex expressions
 */
class LUAParser
{
	public array $data = [];
	private string $content = '';
	private int $pos = 0;
	private int $len = 0;
	private int $line = 1;

	/**
	 * Parse a Lua file
	 * 
	 * @param string $filepath Path to .lua file
	 * @throws RuntimeException on parse errors
	 */
	public function parseFile(string $filepath): void
	{
		if (!file_exists($filepath)) {
			throw new RuntimeException("File not found: {$filepath}");
		}

		$this->content = file_get_contents($filepath);
		if ($this->content === false) {
			throw new RuntimeException("Failed to read file: {$filepath}");
		}

		$this->parse();
	}

	/**
	 * Parse Lua string content
	 * 
	 * @param string $content Lua code as string
	 * @throws RuntimeException on parse errors
	 */
	public function parseString(string $content): void
	{
		$this->content = $content;
		$this->parse();
	}

	/**
	 * Main parser logic
	 */
	private function parse(): void
	{
		$this->pos = 0;
		$this->len = strlen($this->content);
		$this->line = 1;
		$this->data = [];

		while ($this->pos < $this->len) {
			$this->skipWhitespaceAndComments();

			if ($this->pos >= $this->len) {
				break;
			}

			// Look for top-level assignments: VarName = value
			if (
				preg_match(
					'/^([A-Za-z_][A-Za-z0-9_]*)\s*=/',
					substr($this->content, $this->pos),
					$matches
				)
			) {

				$varName = $matches[1];
				$this->pos += strlen($matches[0]);
				$this->skipWhitespaceAndComments();

				$value = $this->parseValue();
				$this->data[$varName] = $value;
			} else {
				// Skip any unexpected character
				$this->pos++;
			}
		}
	}

	/**
	 * Skip whitespace and comments
	 */
	private function skipWhitespaceAndComments(): void
	{
		while ($this->pos < $this->len) {
			$ch = $this->content[$this->pos];

			// Whitespace
			if (ctype_space($ch)) {
				if ($ch === "\n") {
					$this->line++;
				}
				$this->pos++;
				continue;
			}

			// Single-line comment: --
			if (
				$this->pos + 1 < $this->len &&
				$this->content[$this->pos] === '-' &&
				$this->content[$this->pos + 1] === '-'
			) {

				// Skip to end of line
				while ($this->pos < $this->len && $this->content[$this->pos] !== "\n") {
					$this->pos++;
				}
				continue;
			}

			break;
		}
	}

	/**
	 * Parse a value (table, string, number, boolean, nil)
	 * 
	 * @return mixed
	 */
	private function parseValue()
	{
		$this->skipWhitespaceAndComments();

		if ($this->pos >= $this->len) {
			return null;
		}

		$ch = $this->content[$this->pos];

		// Table
		if ($ch === '{') {
			return $this->parseTable();
		}

		// String
		if ($ch === '"' || $ch === "'") {
			return $this->parseQuotedString();
		}

		// Number or keyword
		if (
			preg_match(
				'/^(\-?[0-9]+\.?[0-9]*([eE][\+\-]?[0-9]+)?|true|false|nil)\b/',
				substr($this->content, $this->pos),
				$matches
			)
		) {

			$token = $matches[1];
			$this->pos += strlen($token);

			if ($token === 'true') {
				return true;
			} elseif ($token === 'false') {
				return false;
			} elseif ($token === 'nil') {
				return null;
			} else {
				// Number
				return strpos($token, '.') !== false || stripos($token, 'e') !== false
					? (float) $token
					: (int) $token;
			}
		}

		// Unquoted string (fallback for simple identifiers)
		if (
			preg_match(
				'/^([A-Za-z_][A-Za-z0-9_]*)/',
				substr($this->content, $this->pos),
				$matches
			)
		) {
			$this->pos += strlen($matches[1]);
			return $matches[1];
		}

		throw new RuntimeException("Unexpected character at line {$this->line}: '{$ch}'");
	}

	/**
	 * Parse a Lua table into PHP array
	 * 
	 * @return array
	 */
	private function parseTable(): array
	{
		$result = [];
		$this->pos++; // skip '{'
		$this->skipWhitespaceAndComments();

		$autoIndex = 1;

		while ($this->pos < $this->len) {
			$this->skipWhitespaceAndComments();

			if ($this->pos >= $this->len) {
				throw new RuntimeException("Unexpected end of input in table at line {$this->line}");
			}

			// End of table
			if ($this->content[$this->pos] === '}') {
				$this->pos++;
				break;
			}

			// Parse key-value pair or indexed value
			$key = null;
			$value = null;

			// Check for [key] = value or key = value
			if ($this->content[$this->pos] === '[') {
				// [key] = value
				$this->pos++; // skip '['
				$this->skipWhitespaceAndComments();
				$key = $this->parseValue();
				$this->skipWhitespaceAndComments();

				if ($this->pos >= $this->len || $this->content[$this->pos] !== ']') {
					throw new RuntimeException("Expected ']' at line {$this->line}");
				}
				$this->pos++; // skip ']'
				$this->skipWhitespaceAndComments();

				if ($this->pos >= $this->len || $this->content[$this->pos] !== '=') {
					throw new RuntimeException("Expected '=' at line {$this->line}");
				}
				$this->pos++; // skip '='
				$this->skipWhitespaceAndComments();
				$value = $this->parseValue();

			} elseif (
				preg_match(
					'/^([A-Za-z_][A-Za-z0-9_]*)\s*=/',
					substr($this->content, $this->pos),
					$matches
				)
			) {

				// key = value
				$key = $matches[1];
				$this->pos += strlen($matches[0]);
				$this->skipWhitespaceAndComments();
				$value = $this->parseValue();

			} else {
				// Indexed value (no key)
				$value = $this->parseValue();
				$key = $autoIndex++;
			}

			// Store key-value pair
			if ($key !== null) {
				$result[$key] = $value;
			}

			$this->skipWhitespaceAndComments();

			// Check for comma or end of table
			if ($this->pos < $this->len && $this->content[$this->pos] === ',') {
				$this->pos++; // skip ','
			}
		}

		return $result;
	}

	/**
	 * Parse a quoted string
	 * 
	 * @return string
	 */
	private function parseQuotedString(): string
	{
		$quote = $this->content[$this->pos];
		$this->pos++; // skip opening quote

		$str = '';
		$escaped = false;

		while ($this->pos < $this->len) {
			$ch = $this->content[$this->pos];
			$this->pos++;

			if ($escaped) {
				// Handle escape sequences
				switch ($ch) {
					case 'n':
						$str .= "\n";
						break;
					case 't':
						$str .= "\t";
						break;
					case 'r':
						$str .= "\r";
						break;
					case '\\':
						$str .= '\\';
						break;
					case $quote:
						$str .= $quote;
						break;
					default:
						$str .= $ch;
				}
				$escaped = false;
			} elseif ($ch === '\\') {
				$escaped = true;
			} elseif ($ch === $quote) {
				// End of string
				return $str;
			} else {
				$str .= $ch;
				if ($ch === "\n") {
					$this->line++;
				}
			}
		}

		throw new RuntimeException("Unterminated string at line {$this->line}");
	}

	/**
	 * Get nested value using dot notation
	 * 
	 * @param string $path Dot-separated path (e.g., "WhoDatDB.characters")
	 * @return mixed
	 */
	public function get(string $path)
	{
		$keys = explode('.', $path);
		$value = $this->data;

		foreach ($keys as $key) {
			if (!is_array($value) || !isset($value[$key])) {
				return null;
			}
			$value = $value[$key];
		}

		return $value;
	}

	/**
	 * Check if path exists
	 * 
	 * @param string $path Dot-separated path
	 * @return bool
	 */
	public function has(string $path): bool
	{
		return $this->get($path) !== null;
	}
}