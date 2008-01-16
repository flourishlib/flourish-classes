<?php
/**
 * Provides fSchema class related functions for ORM code
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMSchema
 * 
 * @uses  fCore
 * @uses  fISchema
 * @uses  fORMDatabase
 * @uses  fSchema
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fORMSchema
{
	/**
	 * An object that implements the fISchema interface
	 * 
	 * @var fISchema
	 */
	static private $schema_object;

	
	/**
	 * Private class constructor to prevent instantiating the class
	 * 
	 * @return fORMSchema
	 */
	private function __construct() { }
	
	
	/**
	 * Allows attaching an fSchema-compatible object as the schema singleton for ORM code
	 * 
	 * @param  fISchema $schema  An object that implements the fISchema interface
	 * @return void
	 */
	static public function attach(fISchema $schema)
	{
		self::$schema_object = $schema;
	}
	
	
	/**
	 * Return the instance of the fSchema class
	 * 
	 * @return fSchema  The schema instance
	 */
	static public function getInstance()
	{
		if (!self::$schema_object) {
			self::$schema_object = new fSchema(fORMDatabase::getInstance());
		}
		return self::$schema_object;
	}
	
	
	/**
	 * Turns on schema caching, using fUnexpectedException flushing
	 * 
	 * @param  string  $cache_file  The file to use for caching
	 * @return void
	 */
	static public function enableSmartCaching($cache_file)
	{
		if (!self::getInstance() instanceof fSchema) {
            fCore::toss('fProgrammerException', 'Smart caching is only available (and most likely only applicable) if you are using the fSchema object');        
        }
        self::getInstance()->setCacheFile($cache_file);
		fCore::addTossCallback('fUnexpectedException', array(self::getInstance(), 'flushInfo')); 
	}
}


// Handle loading the fISchema interface
if (!class_exists('fSchema')) { }


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