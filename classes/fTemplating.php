<?php
/**
 * Allows for quick and flexible HTML templating
 * 
 * @copyright  Copyright (c) 2007-2010 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fTemplating
 * 
 * @version    1.0.0b4
 * @changes    1.0.0b4  Added ::create() and ::retrieve() for named fTemplating instances [wb, 2010-05-11]
 * @changes    1.0.0b3  Fixed an issue with placing relative file path [wb, 2010-04-23]
 * @changes    1.0.0b2  Added the ::inject() method [wb, 2009-01-09]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class fTemplating
{
	const create   = 'fTemplating::create';
	const reset    = 'fTemplating::reset';
	const retrieve = 'fTemplating::retrieve';
	
	
	/**
	 * Named fTemplating instances
	 * 
	 * @var array
	 */
	static $instances = array();
	
	
	/**
	 * Creates a named template that can be accessed from any scope via ::retrieve()
	 * 
	 * @param  string $name  The name for this template instance
	 * @param  string $root  The filesystem path to use when accessing relative files, defaults to `$_SERVER['DOCUMENT_ROOT']`
	 * @return fTemplating  The new fTemplating instance
	 */
	static public function create($name, $root=NULL)
	{
		self::$instances[$name] = new self($root);
		return self::$instances[$name];
	}
	
	
	/**
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$instances = array();
	}
	
	
	/**
	 * Retrieves a named template
	 * 
	 * @param  string $name  The name of the template to retrieve
	 * @return fTemplating  The specified fTemplating instance
	 */
	static public function retrieve($name)
	{
		if (!isset(self::$instances[$name])) {
			throw new fProgrammerException(
				'The named template specified, %s, has not been created yet',
				$name
			);
		}
		return self::$instances[$name];
	}
	
	
	/**
	 * The buffered object id, used for differentiating different instances when doing replacements
	 * 
	 * @var integer
	 */
	private $buffered_id;
	
	/**
	 * A data store for templating
	 * 
	 * @var array
	 */
	private $elements;
	
	/**
	 * The directory to look for files
	 * 
	 * @var string
	 */
	protected $root;
	
	
	/**
	 * Initializes this templating engine
	 * 
	 * @param  string $root  The filesystem path to use when accessing relative files, defaults to `$_SERVER['DOCUMENT_ROOT']`
	 * @return fTemplating
	 */
	public function __construct($root=NULL)
	{
		if ($root === NULL) {
			$root = $_SERVER['DOCUMENT_ROOT'];
		}
		
		if (!file_exists($root)) {
			throw new fProgrammerException(
				'The root specified, %s, does not exist on the filesystem',
				$root
			);
		}
		
		if (!is_readable($root)) {
			throw new fEnvironmentException(
				'The root specified, %s, is not readable',
				$root
			);
		}
		
		if (substr($root, -1) != '/' && substr($root, -1) != '\\') {
			$root .= DIRECTORY_SEPARATOR;
		}
		
		$this->root        = $root;
		$this->buffered_id = NULL;
	}
	
	
	/**
	 * Finishing placing elements if buffering was used
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		// The __destruct method can't throw unhandled exceptions intelligently, so we will always catch here just in case
		try {
			$this->placeBuffered();
		} catch (Exception $e) {
			fCore::handleException($e);
		}
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @internal
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Adds a value to an array element
	 * 
	 * @param  string $element  The element to add to
	 * @param  mixed  $value    The value to add
	 * @return void
	 */
	public function add($element, $value)
	{
		if (!isset($this->elements[$element])) {
			$this->elements[$element] = array();
		}
		if (!is_array($this->elements[$element])) {
			throw new fProgrammerException(
				'%1$s was called for an element, %2$s, which is not an array',
				'add()',
				$element
			);
		}
		$this->elements[$element][] = $value;
	}
	
	
	/**
	 * Enables buffered output, allowing ::set() and ::add() to happen after a ::place() but act as if they were done before
	 * 
	 * Please note that using buffered output will affect the order in which
	 * code is executed since the elements are not actually ::place()'ed until
	 * the destructor is called.
	 * 
	 * If the non-template code depends on template code being executed
	 * sequentially before it, you may not want to use output buffering.
	 * 
	 * @return void
	 */
	public function buffer()
	{
		static $id_sequence = 1;
		
		if ($this->buffered_id) {
			throw new fProgrammerException('Buffering has already been started');
		}
		
		if (!fBuffer::isStarted()) {
			fBuffer::start();
		}
		
		$this->buffered_id = $id_sequence;
		
		$id_sequence++;
	}
	
	
	/**
	 * Erases all output since the invocation of the template - only works if buffering is on
	 * 
	 * @return void
	 */
	public function destroy()
	{
		if (!$this->buffered_id) {
			throw new fProgrammerException(
				'A template can only be destroyed if buffering has been enabled'
			);
		}
		
		$this->buffered_id = NULL;
		
		fBuffer::erase();
		fBuffer::stop();
		
		$this->__destruct();
	}
	
	
	/**
	 * Gets the value of an element
	 * 
	 * @param  string $element        The element to get
	 * @param  mixed  $default_value  The value to return if the element has not been set
	 * @return mixed  The value of the element specified, or the default value if it has not been set
	 */
	public function get($element, $default_value=NULL)
	{
		return (isset($this->elements[$element])) ? $this->elements[$element] : $default_value;
	}
	
	
	/**
	 * Includes the file specified - this is identical to ::place() except a filename is specified instead of an element
	 * 
	 * Please see the ::place() method for more details about functionality.
	 * 
	 * @param  string $file_path  The file to place
	 * @param  string $file_type  Will force the file to be placed as this type of file instead of auto-detecting the file type. Valid types include: `'css'`, `'js'`, `'php'` and `'rss'`.
	 * @return void
	 */
	public function inject($file_path, $file_type=NULL)
	{
		$prefix = '__injected_';
		$num    = 1;
		while (isset($this->elements[$prefix . $num])) {
			$num++;
		}
		$element = $prefix . $num;
		
		$this->set($element, $file_path);
		$this->place($element, $file_type);
	}
	
	
	/**
	 * Includes the element specified - element must be set through ::set() first
	 * 
	 * If the element is a file path ending in `.css`, `.js`, `.rss` or `.xml`
	 * an appropriate HTML tag will be printed (files ending in `.xml` will be
	 * treated as an RSS feed). If the element is a file path ending in `.inc`,
	 * `.php` or `.php5` it will be included.
	 * 
	 * Paths that start with `./` will be loaded relative to the current script.
	 * Paths that start with a file or directory name will be loaded relative
	 * to the `$root` passed in the constructor. Paths that start with `/` will
	 * be loaded from the root of the filesystem.
	 * 
	 * You can pass the `media` attribute of a CSS file or the `title` attribute
	 * of an RSS feed by adding an associative array with the following formats:
	 * 
	 * {{{
	 * array(
	 *     'path'  => (string) {css file path},
	 *     'media' => (string) {media type}
	 * );
	 * array(
	 *     'path'  => (string) {rss file path},
	 *     'title' => (string) {feed title}
	 * );
	 * }}}
	 * 
	 * @param  string $element    The element to place
	 * @param  string $file_type  Will force the element to be placed as this type of file instead of auto-detecting the file type. Valid types include: `'css'`, `'js'`, `'php'` and `'rss'`.
	 * @return void
	 */
	public function place($element, $file_type=NULL)
	{
		// Put in a buffered placeholder
		if ($this->buffered_id) {
			echo '%%fTemplating::' . $this->buffered_id . '::' . $element . '::' . $file_type . '%%';
			return;
		}
		
		if (!isset($this->elements[$element])) {
			return;
		}
		
		$this->placeElement($element, $file_type);
	}
	
	
	/**
	 * Prints a CSS `link` HTML tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the `'path'` to the CSS file. Array can also contain a key `'media'`.
	 * @return void
	 */
	protected function placeCSS($info)
	{
		if (!is_array($info)) {
			$info = array('path'  => $info);
		}
		
		if (!isset($info['media'])) {
			$info['media'] = 'all';
		}
		
		echo '<link rel="stylesheet" type="text/css" href="' . $info['path'] . '" media="' . $info['media'] . '" />' . "\n";
	}
	
	
	/**
	 * Performs the action of actually placing an element
	 * 
	 * @param  string $element    The element that is being placed
	 * @param  string $file_type  The file type to treat all values as
	 * @return void
	 */
	protected function placeElement($element, $file_type)
	{
		$values = $this->elements[$element];
		if (!is_object($values)) {
			settype($values, 'array');
		} else {
			$values = array($values);	
		}
		$values = array_values($values);
		
		foreach ($values as $value) {
			
			$type = $this->verifyValue($element, $value, $file_type);
			
			switch ($type) {
				case 'css':
					$this->placeCSS($value);
					break;
				
				case 'js':
					$this->placeJS($value);
					break;
					
				case 'php':
					$this->placePHP($element, $value);
					break;
					
				case 'rss':
					$this->placeRSS($value);
					break;
					
				default:
					throw new fProgrammerException(
						'The file type specified, %1$s, is invalid. Must be one of: %2$s.',
						$type,
						'css, js, php, rss'
					);
			}
		}
	}
	
	
	/**
	 * Prints a java`script` HTML tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the `'path'` to the javascript file
	 * @return void
	 */
	protected function placeJS($info)
	{
		if (!is_array($info)) {
			$info = array('path'  => $info);
		}
		
		echo '<script type="text/javascript" src="' . $info['path'] . '"></script>' . "\n";
	}
	
	
	/**
	 * Includes a PHP file
	 * 
	 * @param  string $element  The element being placed
	 * @param  string $path     The path to the PHP file
	 * @return void
	 */
	protected function placePHP($element, $path)
	{
		// Check to see if the element is a relative path
		if (!preg_match('#^(/|\\|[a-z]:(\\|/)|\\\\|//|\./|\.\\\\)#i', $path)) {
			$path = $this->root . $path;
		
		// Check to see if the element is relative to the current script
		} elseif (preg_match('#^(\./|\.\\\\)#', $path)) {
			$path = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME) . substr($path, 1);
		}
		
		if (!file_exists($path)) {
			throw new fProgrammerException(
				'The path specified for %1$s, %2$s, does not exist on the filesystem',
				$element,
				$path
			);
		}
		
		if (!is_readable($path)) {
			throw new fEnvironmentException(
				'The path specified for %1$s, %2$s, is not readable',
				$element,
				$path
			);
		}
				
		include($path);
	}
	
	
	/**
	 * Prints an RSS `link` HTML tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the `'path'` to the RSS xml file. May also contain a `'title'` key for the title of the RSS feed.
	 * @return void
	 */
	protected function placeRSS($info)
	{
		if (!is_array($info)) {
			$info = array(
				'path'  => $info,
				'title' => fGrammar::humanize(
					preg_replace('#.*?([^/]+).(rss|xml)$#iD', '\1', $info)
				)
			);
		}
		
		if (!isset($info['title'])) {
			throw new fProgrammerException(
				'The RSS value %s is missing the title key',
				$info
			);
		}
		
		echo '<link rel="alternate" type="application/rss+xml" href="' . $info['path'] . '" title="' . $info['title'] . '" />' . "\n";
	}
	
	
	/**
	 * Performs buffered replacements using a breadth-first technique
	 * 
	 * @return void
	 */
	private function placeBuffered()
	{
		if (!$this->buffered_id) {
			return;
		}
		
		$contents = fBuffer::get();
		fBuffer::erase();
		
		// We are gonna use a regex replacement that is eval()'ed as PHP code
		$regex       = '/%%fTemplating::' . $this->buffered_id . '::(.*?)::(.*?)%%/e';
		$replacement = 'fBuffer::startCapture() . $this->placeElement("$1", "$2") . fBuffer::stopCapture()';
		
		// Remove the buffered id, thus making any nested place() calls be executed immediately
		$this->buffered_id = NULL;
		
		echo preg_replace($regex, $replacement, $contents);
	}
	
	
	/**
	 * Gets the value of an element and runs it through fHTML::prepare()
	 * 
	 * @param  string $element        The element to get
	 * @param  mixed  $default_value  The value to return if the element has not been set
	 * @return mixed  The value of the element specified run through fHTML::prepare(), or the default value if it has not been set
	 */
	public function prepare($element, $default_value=NULL)
	{
		return fHTML::prepare($this->get($element, $default_value));
	}
	
	
	/**
	 * Sets the value for an element
	 * 
	 * @param  string $element  The element to set
	 * @param  mixed  $value    The value for the element
	 * @return void
	 */
	public function set($element, $value)
	{
		$this->elements[$element] = $value;
	}
	
	
	/**
	 * Ensures the value is valid
	 * 
	 * @param  string $element    The element that is being placed
	 * @param  mixed  $value      A value to be placed
	 * @param  string $file_type  The file type that this element will be displayed as - skips checking file extension
	 * @return string  The file type of the value being placed
	 */
	protected function verifyValue($element, $value, $file_type=NULL)
	{
		if (!$value && !is_numeric($value)) {
			throw new fProgrammerException(
				'The element specified, %s, has a value that is empty',
				$value
			);
		}
		
		if (is_array($value) && !isset($value['path'])) {
			throw new fProgrammerException(
				'The element specified, %1$s, has a value, %2$s, that is missing the path key',
				$element,
				$value
			);
		}
		
		if ($file_type) {
			return $file_type;
		}
		
		$path = (is_array($value)) ? $value['path'] : $value;
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		
		// Allow some common variations on file extensions
		$extension_map = array(
			'inc'  => 'php',
			'php5' => 'php',
			'xml'  => 'rss'
		);
		
		if (isset($extension_map[$extension])) {
			$extension = $extension_map[$extension];
		}
		
		if (!in_array($extension, array('css', 'js', 'php', 'rss'))) {
			throw new fProgrammerException(
				'The element specified, %1$s, has a value whose path, %2$s, does not end with a recognized file extension: %3$s.',
				$element,
				$path,
				'.css, .inc, .js, .php, .php5, .rss, .xml'
			);
		}
		
		return $extension;
	}
}



/**
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */