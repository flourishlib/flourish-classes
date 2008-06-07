<?php
/**
 * Allows for quick and flexible HTML templating
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fTemplating
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fTemplating
{
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
	private $root;
	
	
	/**
	 * Initializes this templating engine
	 * 
	 * @param  string $root  The filesystem path to use when accessing relative files, defaults to $_SERVER['DOCUMENT_ROOT']
	 * @return fTemplating
	 */
	public function __construct($root=NULL)
	{
		if ($root === NULL) {
			$root = $_SERVER['DOCUMENT_ROOT'];
		}
		
		if (!file_exists($root)) {
			fCore::toss('fProgrammerException', 'The root specified does not exist on the filesystem');
		}
		
		if (!is_readable($root)) {
			fCore::toss('fProgrammerException', 'The root specified can not be read from');
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
			fCore::toss('fProgrammerException', 'add() was called for an element, ' . $element . ', which is not an array');
		}
		$this->elements[$element][] = $value;
	}
	
	
	/**
	 * Enables buffered output, allowing set() and add() to happen after a place() but act as if they were done before.
	 * 
	 * Please note that using buffered output will affect the order in which code is executed
	 * since the elements are not actually place()'ed until the destructor is called. If the
	 * non-template code depends on template code being executed sequentially before it, you
	 * may not want to use output buffering.
	 * 
	 * @return void
	 */
	public function buffer()
	{
		static $id_sequence = 1;
		
		if ($this->buffered_id) {
			fCore::toss('fProgrammerException', 'Buffering has already been started');
		}
		
		if (!fBuffer::isStarted()) {
			fBuffer::start();
		}
		
		$this->buffered_id = $id_sequence;
		
		$id_sequence++;
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
	 * Includes the element specified (element must be set through setElement() first). If the
	 * element is a file path ending in .css, .js or .rss an html tag will be printed. If the
	 * element is a file path ending in .php it will be included.
	 * 
	 * Paths that start with './' will be loaded relative to the current script. Paths that
	 * start with a file or directory name will be loaded relative to the root passed in the
	 * constructor. Paths that start with '/' will be loaded from the root of the filesystem.
	 * 
	 * You can pass the media attribute of a CSS file or the title attribute of an RSS feed by
	 * adding an associative array with the following formats:
	 * 
	 * <pre>
	 * array(
	 *     'path'  => (string) {css file path},
	 *     'media' => (string) {media type}
	 * );
	 * </pre>
	 * <pre>
	 * array(
	 *     'path'  => (string) {rss file path},
	 *     'title' => (string) {feed title}
	 * );
	 * </pre>
	 * 
	 * @param  string $element  The element to place
	 * @return void
	 */
	public function place($element)
	{
		// Put in a buffered placeholder
		if ($this->buffered_id) {
			echo '%%fTemplating::' . $this->buffered_id . '::' . $element . '%%';
			return;
		}
		
		if (!isset($this->elements[$element])) {
			return;
		}
		
		$this->placeElement($element);
	}
	
	
	/**
	 * Prints a CSS link html tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the 'path' to the css file. Array can also contain a key 'media'.
	 * @return void
	 */
	private function placeCSS($info)
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
	 * @param  string $element  The element that is being placed
	 * @return void
	 */
	private function placeElement($element)
	{
		$values = $this->elements[$element];
		settype($values, 'array');
		$values = array_values($values);
		
		foreach ($values as $value) {
			
			$file_extension = $this->verifyValue($element, $value);
			
			switch ($file_extension) {
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
			}
		}
	}
	
	
	/**
	 * Prints a javascript html tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the 'path' to the javascript file
	 * @return void
	 */
	private function placeJS($info)
	{
		if (!is_array($info)) {
			$info = array('path'  => $info);
		}
		
		echo '<script type="text/javascript" src="' . $info['path'] . '"></script>' . "\n";
	}
	
	
	/**
	 * Includes a PHP file, exporting all of the elements to variables in the scope of the include
	 * 
	 * @param  string $element  The element being placed
	 * @param  string $path     The path to the PHP file
	 * @return void
	 */
	private function placePHP($element, $path)
	{
		// Check to see if the element is a relative path
		if (!preg_match('#^(/|\\|[a-z]:(\\|/)|\\\\|//|\./|\.\\\\)#i', $path)) {
			$path = $this->root . $path;
		
		// Check to see if the element is relative to the current script
		} elseif (preg_match('#^(\./|\.\\\\)#', $path)) {
			$path = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME) . substr($path, 2);
		}
		
		if (!file_exists($path)) {
			fCore::toss('fProgrammerException', 'The path specified for ' . $element . ', ' . $path . ', does not exist on the filesystem');
		}
		
		if (!is_readable($path)) {
			fCore::toss('fProgrammerException', 'The path specified for ' . $element . ', ' . $path . ', can not be read from');
		}
				
		include($path);
	}
	
	
	/**
	 * Prints an RSS link html tag to the output
	 * 
	 * @param  mixed $info  The path or array containing the 'path' to the RSS xml file. May also contain a 'title' key for the title of the RSS feed.
	 * @return void
	 */
	private function placeRSS($info)
	{
		if (!is_array($info)) {
			$info = array('path'  => $info,
				'title' => fInflection::humanize(
					preg_replace('#.*?([^/]+).rss$#i', '\1', $info)
				)
			);
		}
		
		if (!isset($info['title'])) {
			fCore::toss('fProgrammerException', 'The value ' . fCore::dump($info) . ' is missing the title key');
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
		$regex       = '/%%fTemplating::' . $this->buffered_id . '::(.*?)%%/e';
		$replacement = 'fBuffer::startCapture() . $this->placeElement("$1") . fBuffer::stopCapture()';
		
		// Remove the buffered id, thus making any nested place() calls be executed immediately
		$this->buffered_id = NULL;
		
		echo preg_replace($regex, $replacement, $contents);
	}
	
	
	/**
	 * Gets the value of an element and runs it through {@link fHTML::prepare()}
	 * 
	 * @param  string $element        The element to get
	 * @param  mixed  $default_value  The value to return if the element has not been set
	 * @return mixed  The value of the element specified run through {@link fHTML::prepare()}, or the default value if it has not been set
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
	 * @param  string $element  The element that is being placed
	 * @param  mixed  $value    A value to be placed
	 * @return string  The file extension of the value being placed
	 */
	private function verifyValue($element, $value)
	{
		if (empty($value)) {
			fCore::toss('fProgrammerException', 'The element specified, ' . $element . ', has a value that is empty');
		}
		
		if (is_array($value) && !isset($value['path'])) {
			fCore::toss('fProgrammerException', 'The element specified, ' . $element . ', has a value, ' . fCore::dump($value) . ', that is missing the path key');
		}
		
		$path = (is_array($value)) ? $value['path'] : $value;
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		
		if (!in_array($extension, array('css', 'js', 'php', 'rss'))) {
			fCore::toss('fProgrammerException', 'The element specified, ' . $element . ', has a value whose path, ' . $path . ', does not appear to be a .css, .js, .php or .rss file');
		}
		
		return $extension;
	}
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
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