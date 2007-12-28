<?php
/**
 * Represents a directory on the filesystem, also provides static directory-related methods
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fDirectory
 * 
 * @uses  fCore
 * @uses  fEnvironmentException
 * @uses  fFile
 * @uses  fProgrammerException
 * 
 * @todo  Test this class
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-12-21]
 */
class fDirectory
{
	/**
     * An exception to be thrown after a deletion has happened
     * 
     * @var object 
     */
    protected $exception;
    
    /**
	 * The full path to the directory
	 * 
	 * @var string 
	 */
	protected $directory;
	
	/**
	 * The temporary directory to use for various tasks
	 * 
	 * @var string 
	 */
	const TEMP_DIRECTORY = '__temp/';
	
	
	/**
	 * Creates an object to represent a directory on the filesystem
	 * 
	 * @param  string $directory  The full path to the directory
	 * @return fDirectory
	 */
	public function __construct($directory)
	{
		if (!file_exists($directory)) {
			fCore::toss('fEnvironmentException', 'The directory specified does not exist');   
		}
        if (!is_dir($directory)) {
            fCore::toss('fEnvironmentException', 'The path specified is not a directory');   
        }
		self::makeCanonical($directory);
        $this->directory = $directory;
	}
	
    
    /**
     * Makes sure a directory has a / or \ at the end
     * 
     * @param  string $directory  The directory to check
     * @return string  The directory name in canonical form
     */
    static public function makeCanonical($directory)
    {
        if (substr($directory, -1) != '/' && substr($directory, -1) != '\\') {
            $directory .= '/';   
        }
        return $directory;
    }
    
	
	/**
	 * When used in a string context, represents the file as the filename
	 * 
	 * @return string  The filename of the file
	 */
	public function __toString()
	{
		return $this->getPath();
	}
	
	
    /**
     * Gets the directory's current path
     * 
     * @param  boolean $from_doc_root  If the path should be returned relative to the document root
     * @return string  The path for the directory
     */
    public function getPath($from_doc_root=FALSE)
    {
        if ($this->exception) { throw $this->exception; }
        if ($from_doc_root) {
            return str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->directory);    
        }
        return $this->directory;    
    }
	
	
	/**
	 * Check to see if the current directory is a temporary directory
	 * 
	 * @return boolean  If the directory is a temp directory
	 */
	public function checkIfTemp()
	{
		if ($this->exception) { throw $this->exception; }
        return preg_match('#' . self::TEMP_DIRECTORY . '$#', $this->directory);    
	}
	
    
    /**
     * Check to see if the current directory is writable
     * 
     * @return boolean  If the directory is writable
     */
    public function checkIfWritable()
    {
        if ($this->exception) { throw $this->exception; }
        return is_writable($this->directory);   
    }
    
	
	/**
	 * Gets (and creates if necessary) a temp dir for the current directory
	 * 
	 * @return fDirectory  The object representing the temp dir
	 */
	public function getTemp()
	{
		if ($this->checkIfTemp()) {
            fCore::toss('fProgrammerException', 'The current directory is a temporary directory');   
        }
        $temp_dir = $this->directory . self::TEMP_DIRECTORY;
        if (!file_exists($temp_dir)) {
			$old_umask = umask(0000);
			mkdir($temp_dir);
			umask($old_umask);
		} 
        return new fDirectory($temp_dir);    
	}
    
    
    /**
     * Gets the parent directory
     * 
     * @return fDirectory  The object representing the parent dir
     */
    public function getParent()
    {
        if ($this->exception) { throw $this->exception; }
        return new fDirectory(preg_replace('#[^/\\\\]+(/|\\\\)$#', '', $this->directory));    
    }
    
    
    /**
     * Will clean out a temp directory of all files/directories. Removes all files over 6 hours old.
     * 
     * @return void
     */
    public function clean() 
    {
        if (!$this->checkIfTemp()) {
            fCore::toss('fProgrammerException', 'Only temporary directories can be cleaned');   
        }
        
        // Delete the files
        $files = $this->recursiveScan();
        foreach ($files as $file) {
            if ($file instanceof fFile) {
                if (filemtime($file->getPath()) < strtotime('-6 hours')) {
                    $file->delete();
                }
            }
        }
        
        // Delete the directories
        $dirs = $this->recursiveScan();
        foreach ($dirs as $dir) {
            if ($dir instanceof fDirectory) {    
                if (filemtime($dir->getPath()) < strtotime('-6 hours') && $dir->scan() == array()) {
                    $dir->delete();
                }
            }
        }    
    }
    
    
    /**
     * Will delete a directory and all files and folders inside of it
     * 
     * @return void
     */
    public function delete() 
    {
        $files = $this->scan();
        
        foreach ($files as $file) {
            $file->delete();
        }  
        
        rmdir($this->directory);  
        $this->directory = NULL;
        
        try {
            fCore::toss('fProgrammerException', 'The action requested can not be performed because the directory has been deleted');   
        } catch (fPrintableException $e) {
            $this->exception = $e;
        }
    }
    
    
    /**
     * Performs a scandir on a directory, removing the . and .. folder references
     * 
     * @return array  The fFile and fDirectory objects for the files/folders in this directory
     */
    public function scan()
    {
        if ($this->exception) { throw $this->exception; }
        
        $files = array_diff(scandir($this->directory), array('.', '..')); 
        $objects = array();
        
        foreach ($files as $file) {
            if (is_dir($this->directory . $file)) {
                $objects[] = new fDirectory($this->directory . $file);   
            } else {
                $objects[] = new fFile($this->directory . $file); 
            }
        }  
        
        return $objects;
    }
    
    
    /**
     * Performs a recursive scandir on a directory, removing the . and .. folder references
     * 
     * @return array  The fFile and fDirectory objects for the files/folders (listed recursively) in this directory
     */
    public function recursiveScan()
    {
        $files  = $this->scan();
        $objects = $files;
        
        $total_files = sizeof($files);
        for ($i=0; $i < $total_files; $i++) {
            if ($files[$i] instanceof fDirectory) {
                $objects = array_splice($objects, $i, 0, $files[$i]->recursiveScan());   
            }
        }  
        
        return $objects;
    }    
}  


/**
 * Copyright (c) 2007 William Bond <will@flourishlib.com>
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
?>