<?php
/**
 * Provides {@link fSchema} class related functions for ORM code
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMSchema
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
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
	 * Allows attaching an {@link fSchema}-compatible object as the schema singleton for ORM code
	 * 
	 * @param  fISchema $schema  An object that implements the {@link fISchema} interface
	 * @return void
	 */
	static public function attach(fISchema $schema)
	{
		self::$schema_object = $schema;
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
			fCore::toss(
				'fProgrammerException',
				fCore::compose('Smart caching is only available (and most likely only applicable) if you are using the fSchema object')
			);       
		}
		self::getInstance()->setCacheFile($cache_file);
		fCore::registerTossCallback('fUnexpectedException', array(self::getInstance(), 'flushInfo'));
	}
	
	
	/**
	 * Return the instance of the {@link fSchema} class
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
	 * @param  string $table              The main table we are searching on behalf of
	 * @param  string $related_table      The related table we are searching under
	 * @param  string $route              The route to get info about
	 * @param  string $relationship_type  The relationship type: NULL, '*-to-many', '*-to-one', 'one-to-one', 'one-to-meny', 'many-to-one', 'many-to-many'
	 * @return void
	 */
	static public function getRoute($table, $related_table, $route, $relationship_type=NULL)
	{
		$valid_relationship_types = array(
			NULL,
			'*-to-many',
			'*-to-one',
			'many-to-many',
			'many-to-one',
			'one-to-many',
			'one-to-one'
		);
		if (!in_array($relationship_type, $valid_relationship_types)) {
			$valid_relationship_types[0] = '{null}';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The relationship type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($relationship_type),
					join(', ', $valid_relationship_types)
				)
			);	
		}
		
		if ($route === NULL) {
			$route = self::getRouteName($table, $related_table, $route, $relationship_type);
		}
		
		$routes = self::getRoutes($table, $related_table, $relationship_type);
		
		if (!isset($routes[$route])) {                                                                             
			$relationship_type .= ($relationship_type) ? ' ' : '';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The route specified, %s, for the %srelationship between %s and %s does not exist',
					fCore::dump($route),
					$relationship_type,
					fCore::dump($table),
					fCore::dump($related_table)
				)
			);
		}
		
		return $routes[$route];
	}
	
	
	/**
	 * Returns the name of the only route from the specified table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table              The main table we are searching on behalf of
	 * @param  string $related_table      The related table we are trying to find the routes for
	 * @param  string $route              The route that was preselected, will be verified if present
	 * @param  string $relationship_type  The relationship type: NULL, '*-to-many', '*-to-one', 'one-to-one', 'one-to-meny', 'many-to-one', 'many-to-many'
	 * @return string  The only route from the main table to the related table
	 */
	static public function getRouteName($table, $related_table, $route=NULL, $relationship_type=NULL)
	{
		$valid_relationship_types = array(
			NULL,
			'*-to-many',
			'*-to-one',
			'many-to-many',
			'many-to-one',
			'one-to-many',
			'one-to-one'
		);
		if (!in_array($relationship_type, $valid_relationship_types)) {
			$valid_relationship_types[0] = '{null}';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The relationship type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($relationship_type),
					join(', ', $valid_relationship_types)
				)
			);	
		}
		
		$routes = self::getRoutes($table, $related_table, $relationship_type);
		
		if (!empty($route) && isset($routes[$route])) {
			return $route;
		}
		
		$keys = array_keys($routes);
		
		if (sizeof($keys) > 1) {
			$relationship_type .= ($relationship_type) ? ' ' : '';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'There is more than one route for the %srelationship between %s and %s',
					$relationship_type,
					fCore::dump($table),
					fCore::dump($related_table)
				)
			);
		}
		if (sizeof($keys) == 0) {
			$relationship_type .= ($relationship_type) ? ' ' : '';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The table %s is not in a %srelationship with the table %s',
					fCore::dump($table),
					$relationship_type,
					fCore::dump($related_table)
				)
			);
		}
		
		return $keys[0];
	}
	
	
	/**
	 * Returns the name of the route specified by the relationship
	 * 
	 * @internal
	 * 
	 * @param  string $type          The type of relationship: 'one-to-one', 'one-to-many', 'many-to-one', 'many-to-many'
	 * @param  array  $relationship  The relationship array from {@link fISchema::getKeys()}
	 * @return string  The name of the route
	 */
	static public function getRouteNameFromRelationship($type, $relationship)
	{
		$valid_types = array('one-to-one', 'one-to-many', 'many-to-one', 'many-to-many');
		if (!in_array($type, $valid_types)) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The relationship type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($type),
					join(', ', $valid_types)
				)
			);
		}
		
		if (isset($relationship['join_table']) || $type == 'many-to-many') {
			return $relationship['join_table'];
		}
		
		if ($type == 'one-to-many') {
			return $relationship['related_column'];
		}
		
		return $relationship['column'];
	}
	
	
	/**
	 * Returns an array of all routes from a table to one of its related tables
	 * 
	 * @internal
	 * 
	 * @param  string $table              The main table we are searching on behalf of
	 * @param  string $related_table      The related table we are trying to find the routes for
	 * @param  string $relationship_type  The relationship type: NULL, '*-to-many', '*-to-one', 'one-to-one', 'one-to-meny', 'many-to-one', 'many-to-many'
	 * @return void
	 */
	static public function getRoutes($table, $related_table, $relationship_type=NULL)
	{
		$valid_relationship_types = array(
			NULL,
			'*-to-many',
			'*-to-one',
			'many-to-many',
			'many-to-one',
			'one-to-many',
			'one-to-one'
		);
		if (!in_array($relationship_type, $valid_relationship_types)) {
			$valid_relationship_types[0] = '{null}';
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The relationship type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($relationship_type),
					join(', ', $valid_relationship_types)
				)
			);	
		}
		
		$all_relationships = self::getInstance()->getRelationships($table);
		
		$routes = array();
		
		foreach ($all_relationships as $type => $relationships) {
			
			// Filter the relationships by the relationship type
			if ($relationship_type !== NULL) {
				$match = strpos($type, str_replace('*', '', $relationship_type)) !== FALSE;
				if (!$match) {
					continue;	
				}
			}
			
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
	 * Forces use as a static class
	 * 
	 * @return fORMSchema
	 */
	private function __construct() { }
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