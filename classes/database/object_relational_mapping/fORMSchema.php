<?php
/**
 * Provides fSchema class related functions for ORM code
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMSchema
 * 
 * @uses  fCore
 * @uses  fISchema
 * @uses  fORMDatabase
 * @uses  fSchema
 * @uses  fValidationException
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
	 * Returns information about the specified route
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are searching under
	 * @param  string $route          The route to get info about
	 * @return void
	 */
	static public function getRoute($table, $related_table, $route)
	{
		if ($route === NULL) {
			$route = self::getOnlyRouteName($table, $related_table);	
		}
		
		$routes = self::getRoutes($table, $related_table);
		
		if (!isset($routes[$route])) {
			fCore::toss('fProgrammerException', 'The route specified, ' . $route . ', for the relationship between ' . $table . ' and ' . $related_table . ' does not exist');	 		
		}
		
		return $routes[$route];
	}
	
	
	/**
	 * Returns an array of all routes from a table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @return void
	 */
	static public function getRoutes($table, $related_table)
	{
		$relationship_types = self::getInstance()->getRelationships($table);
		
		$routes = array();
		
		foreach ($relationship_types as $type => $relationships) {
			foreach ($relationships as $relationship) {
				if ($relationship['related_table'] == $related_table) {
					if ($type == 'many-to-many') {
						$routes[$relationship['join_table']] = $relationship;		
					} elseif ($type == 'one-to-many') {
						$routes[$relationship['related_column']] = $relationship;		
					} else {
						$routes[$relationship['column']] = $relationship;		
					}
				}
			}	
		}
		
		return $routes; 
	}
	
	
	/**
	 * Returns the name of the only route from the specified table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @param  string $route          The route that was preselected, will be verified if present
	 * @return void
	 */
	static public function getRouteName($table, $related_table, $route=NULL)
	{
		$routes = self::getRoutes($table, $related_table);
		
		if (!empty($route) && isset($routes[$route])) {
			return $route;	
		}
		
		$keys = array_keys($routes);
		
		if (sizeof($keys) > 1) {
			fCore::toss('fProgrammerException', 'There is more than one route for ' . $table . ' to ' . $related_table);	
		}
		if (sizeof($keys) == 0) {
			fCore::toss('fProgrammerException', 'The table ' . $table . ' is not related to the table ' . $related_table);
		}
		
		return $keys[0];
	}
	
	
	/**
	 * Returns information about the specified to-many route
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are searching under
	 * @param  string $route          The route to get info about
	 * @return void
	 */
	static public function getToManyRoute($table, $related_table, $route)
	{
		$route = self::getToManyRouteName($table, $related_table, $route);	
		
		$routes = self::getToManyRoutes($table, $related_table);
		
		if (!isset($routes[$route])) {
			fCore::toss('fProgrammerException', 'The to-many route specified, ' . $route . ', for the relationship between ' . $table . ' and ' . $related_table . ' does not exist');	 		
		}
		
		return $routes[$route];
	}
	
	
	/**
	 * Returns an array of all routes from a table to one of its one-to-many or many-to-many related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @return void
	 */
	static public function getToManyRoutes($table, $related_table)
	{
		$relationship_types = self::getInstance()->getRelationships($table);
		unset($relationship_types['one-to-one']);
		unset($relationship_types['many-to-one']);
		
		$routes = array();
		
		foreach ($relationship_types as $type => $relationships) {
			foreach ($relationships as $relationship) {
				if ($relationship['related_table'] == $related_table) {
					if ($type == 'many-to-many') {
						$routes[$relationship['join_table']] = $relationship;		
					} else {
						$routes[$relationship['related_column']] = $relationship;		
					}
				}
			}	
		}
		
		return $routes; 
	}
	
	
	/**
	 * Returns the name of the only to-many route from the specified table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @param  string $route          The route that was preselected, will be verified if present
	 * @return void
	 */
	static public function getToManyRouteName($table, $related_table, $route=NULL)
	{
		$routes = self::getToManyRoutes($table, $related_table);
		
		if (!empty($route) && isset($routes[$route])) {
			return $route;	
		}
		
		$keys = array_keys($routes);
		
		if (sizeof($keys) > 1) {
			fCore::toss('fProgrammerException', 'There is more than one to-many route for ' . $table . ' to ' . $related_table);	
		}
		if (sizeof($keys) == 0) {
			fCore::toss('fProgrammerException', 'The table ' . $table . ' is not related to the table ' . $related_table . ' by a to-many relationship');
		}
		
		return $keys[0];
	}
	
	
	/**
	 * Returns information about the specified to-one route
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are searching under
	 * @param  string $route          The route to get info about
	 * @return void
	 */
	static public function getToOneRoute($table, $related_table, $route)
	{
		$route = self::getToOneRouteName($table, $related_table, $route);	
		
		$routes = self::getToOneRoutes($table, $related_table);
		
		if (!isset($routes[$route])) {
			fCore::toss('fProgrammerException', 'The to-one route specified, ' . $route . ', for the relationship between ' . $table . ' and ' . $related_table . ' does not exist');	 		
		}
		
		return $routes[$route];
	}
	
	
	/**
	 * Returns an array of all routes from a table to one of its one-to-one or many-to-one related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @return void
	 */
	static public function getToOneRoutes($table, $related_table)
	{
		$relationship_types = self::getInstance()->getRelationships($table);
		unset($relationship_types['one-to-many']);
		unset($relationship_types['many-to-many']);
		
		$routes = array();
		
		foreach ($relationship_types as $type => $relationships) {
			foreach ($relationships as $relationship) {
				if ($relationship['related_table'] == $related_table) {
					$routes[$relationship['column']] = $relationship;		
				}
			}	
		}
		
		return $routes; 
	}
	
	
	/**
	 * Returns the name of the only to-one route from the specified table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table          The main table we are searching on behalf of
	 * @param  string $related_table  The related table we are trying to find the routes for
	 * @param  string $route          The route that was preselected, will be verified if present
	 * @return void
	 */
	static public function getToOneRouteName($table, $related_table, $route=NULL)
	{
		$routes = self::getToOneRoutes($table, $related_table);
		
		if (!empty($route) && isset($routes[$route])) {
			return $route;	
		}
		
		$keys = array_keys($routes);
		
		if (sizeof($keys) > 1) {
			fCore::toss('fProgrammerException', 'There is more than one to-one route for ' . $table . ' to ' . $related_table);	
		}
		if (sizeof($keys) == 0) {
			fCore::toss('fProgrammerException', 'The table ' . $table . ' is not related to the table ' . $related_table . ' by a to-one relationship');
		}
		
		return $keys[0];
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
?>