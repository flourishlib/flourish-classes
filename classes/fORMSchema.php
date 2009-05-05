<?php
/**
 * Provides fSchema class related functions for ORM code
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMSchema
 * 
 * @version    1.0.0b2
 * @changes    1.0.0b2  Backwards Compatiblity Break - removed ::enableSmartCaching(), fORM::enableSchemaCaching() now provides equivalent functionality [wb, 2009-05-04]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class fORMSchema
{
	// The following constants allow for nice looking callbacks to static methods
	const attach                       = 'fORMSchema::attach';
	const getRoute                     = 'fORMSchema::getRoute';
	const getRouteName                 = 'fORMSchema::getRouteName';
	const getRouteNameFromRelationship = 'fORMSchema::getRouteNameFromRelationship';
	const getRoutes                    = 'fORMSchema::getRoutes';
	const reset                        = 'fORMSchema::reset';
	const retrieve                     = 'fORMSchema::retrieve';
	
	
	/**
	 * The schema object to use for all ORM functionality
	 * 
	 * @var fSchema
	 */
	static private $schema_object = NULL;
	
	
	/**
	 * Allows attaching an fSchema-compatible object as the schema singleton for ORM code
	 * 
	 * @param  fSchema $schema  An object that is compatible with fSchema
	 * @return void
	 */
	static public function attach($schema)
	{
		self::$schema_object = $schema;
	}
	
	
	/**
	 * Returns information about the specified route
	 * 
	 * @internal
	 * 
	 * @param  string $table              The main table we are searching on behalf of
	 * @param  string $related_table      The related table we are searching under
	 * @param  string $route              The route to get info about
	 * @param  string $relationship_type  The relationship type: `NULL`, `'*-to-many'`, `'*-to-one'`, `'one-to-one'`, `'one-to-meny'`, `'many-to-one'`, `'many-to-many'`
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
			throw new fProgrammerException(
				'The relationship type specified, %1$s, is invalid. Must be one of: %2$s.',
				$relationship_type,
				join(', ', $valid_relationship_types)
			);
		}
		
		if ($route === NULL) {
			$route = self::getRouteName($table, $related_table, $route, $relationship_type);
		}
		
		$routes = self::getRoutes($table, $related_table, $relationship_type);
		
		if (!isset($routes[$route])) {
			$relationship_type .= ($relationship_type) ? ' ' : '';
			throw new fProgrammerException(
				'The route specified, %1$s, for the %2$srelationship between %3$s and %4$s does not exist',
				$route,
				$relationship_type,
				$table,
				$related_table
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
	 * @param  string $relationship_type  The relationship type: `NULL`, `'*-to-many'`, `'*-to-one'`, `'one-to-one'`, `'one-to-meny'`, `'many-to-one'`, `'many-to-many'`
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
			throw new fProgrammerException(
				'The relationship type specified, %1$s, is invalid. Must be one of: %2$s.',
				$relationship_type,
				join(', ', $valid_relationship_types)
			);
		}
		
		$routes = self::getRoutes($table, $related_table, $relationship_type);
		
		if (!empty($route) && isset($routes[$route])) {
			return $route;
		}
		
		$keys = array_keys($routes);
		
		if (sizeof($keys) > 1) {
			$relationship_type .= ($relationship_type) ? ' ' : '';
			throw new fProgrammerException(
				'There is more than one route for the %1$srelationship between %2$s and %3$s',
				$relationship_type,
				$table,
				$related_table
			);
		}
		if (sizeof($keys) == 0) {
			$relationship_type .= ($relationship_type) ? ' ' : '';
			throw new fProgrammerException(
				'The table %1$s is not in a %2$srelationship with the table %3$s',
				$table,
				$relationship_type,
				$related_table
			);
		}
		
		return $keys[0];
	}
	
	
	/**
	 * Returns the name of the route specified by the relationship
	 * 
	 * @internal
	 * 
	 * @param  string $type          The type of relationship: `'one-to-one'`, `'one-to-many'`, `'many-to-one'`, `'many-to-many'`
	 * @param  array  $relationship  The relationship array from fSchema::getKeys()
	 * @return string  The name of the route
	 */
	static public function getRouteNameFromRelationship($type, $relationship)
	{
		$valid_types = array('one-to-one', 'one-to-many', 'many-to-one', 'many-to-many');
		if (!in_array($type, $valid_types)) {
			throw new fProgrammerException(
				'The relationship type specified, %1$s, is invalid. Must be one of: %2$s.',
				$type,
				join(', ', $valid_types)
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
	 * @param  string $relationship_type  The relationship type: `NULL`, `'*-to-many'`, `'*-to-one'`, `'one-to-one'`, `'one-to-meny'`, `'many-to-one'`, `'many-to-many'`
	 * @return array  All of the routes from the main table to the related table
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
			throw new fProgrammerException(
				'The relationship type specified, %1$s, is invalid. Must be one of: %2$s.',
				$relationship_type,
				join(', ', $valid_relationship_types)
			);
		}
		
		$all_relationships = self::retrieve()->getRelationships($table);
		
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
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$schema_object = NULL;
	}
	
	
	/**
	 * Return the instance of the fSchema class
	 * 
	 * @return fSchema  The schema instance
	 */
	static public function retrieve()
	{
		if (!self::$schema_object) {
			self::$schema_object = new fSchema(fORMDatabase::retrieve());
		}
		return self::$schema_object;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMSchema
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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