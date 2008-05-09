<?php
/**
 * Parses Flourish SQL statements in various ways
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fSQLParsing
 * 
 * @internal
 * 
 * @uses  fCore
 * @uses  fProgrammerException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-02-18]
 */
class fSQLParsing
{
	/**
	 * Checks to see if the second table is a join table for the first table
	 * 
	 * @internal
	 * 
	 * @param  string $first_table   This table will be used as a basis to see if the second table is a join table
	 * @param  string $second_table  This table will be checked against the first table to see if it is a join table
	 * @param  fISchema  $schema     The fISchema instance to get the relationship info from
	 * @return mixed  Will be FALSE if the second table is not a join table for the first table, or a many-to-many relationship array if it is 
	 */
	static private function checkForJoinTable($first_table, $second_table, fISchema $schema)
	{
		if ($schema === NULL) {
			return FALSE;	
		}
		
		$relationships = $schema->getRelationships($first_table, 'many-to-many');
		
		foreach ($relationships as $relationship) {
			if ($relationship['join_table'] == $second_table) {
				return $relationship;
			}	
		}
		
		return FALSE;
	}
	
	
	/**
	 * Creates a unique join table for the prefix specified
	 * 
	 * @internal
	 * 
	 * @param  string $prefix   The join name prefix to use
	 * @param  array $joins     The current joins
	 * @return string   A unique join name for the prefix specified
	 */
	static private function createJoinName($prefix, $joins)
	{
		$i = 1;
		while (isset($joins[$prefix . $i])) {
			$i++;	
		}	
		return $prefix . $i;
	}   
	
	
	/**
	 * Changes the route name if the route is for a one-to-many relationship
	 * 
	 * @internal
	 * 
	 * @param  string $route         The current route name we have determined
	 * @param  string $first_table   This table will be checked for one-to-many relationships
	 * @param  string $second_table  This table will be checked to see if it is on the many end of a one-to-many relationships with $first_table
	 * @param  fISchema  $schema     The fISchema instance to get the relationship info from
	 * @return string  Will return the current route if not in a one-to-many relationship, or the new route name if it is
	 */
	static private function fixRouteName($route, $first_table, $second_table, fISchema $schema)
	{
		if ($schema === NULL) {
			return $route;	
		}
		
		$relationships = $schema->getRelationships($first_table, 'one-to-many');
		
		foreach ($relationships as $relationship) {
			if ($relationship['related_table'] == $second_table) {
				return $relationship['related_column'];
			}	
		}
		
		return $route;
	}
	
	
	/**
	 * Takes the FROM clause from parseSelectSQL() and returns all of the tables and how they are joined
	 * 
	 * The output array will be an associative array having keys in the following formats. The keys
	 * with route names are standard joins for associating related tables. The keys with 'complex' in them
	 * have an ON clause that is non-standard and the keys with 'simple' in them have no ON clause.
	 *  - {first_table}_{second_table}[{route}]
	 *  - {first_table}_{second_table}[{route}]_join
	 *  - {first_table}_complex_{#}
	 *  - {first_table}_simple_{#}
	 * 
	 * The values of the associative array will be in the following format:
	 * <pre>
	 * array(
	 *     'join_type'        => (string) {the join type, such as 'INNER JOIN', 'LEFT JOIN', etc}
	 *     'table_name'       => (string) {the table to be joined},
	 *     'table_alias'      => (string) {the alias for the table},
	 *     'on_clause_type'   => (string) {this optional element will be 'simple_equation' or 'complex_expression'},
	 *     'on_clause_fields' => (array)  {only present when the on_clause_type is 'simple_equation', this will be a 2-element array with the two fields being equated},
	 *     'on_clause'        => (string) {only present when the on_clause_type is 'complex_expression', this will be the literal ON clause}
	 * );
	 * </pre>
	 * 
	 * @internal
	 * 
	 * @param  string $clause    The sql clause to parse
	 * @param  fISchema $schema  An instance of a class implementing the fISchema interface, used to find join tables for proper join naming
	 * @return array  The tables in the from clause (see method description for format)
	 */
	static public function parseJoins($sql, fISchema $schema=NULL)
	{
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
		$temp_sql = '';
		$strings = array();
		
		// Replace strings with a placeholder so they don't mess use the regex parsing
		foreach ($matches[0] as $match) {
			if ($match[0] == "'") {
				$strings[] = $match;
				$match = ':string_' . (sizeof($strings)-1);		
			}
			$temp_sql .= $match;
		}  
		
		// Turn comma joins into cross joins
		if (preg_match('#^(?:\w+(?:\s+(?:as\s+)?(?:\w+))?)(?:\s*,\s*(?:\w+(?:\s+(?:as\s+)?(?:\w+))?))*$#is', $temp_sql)) {
			$temp_sql = str_replace(',', ' CROSS JOIN ', $temp_sql);	
		}
		
		$table_aliases = array();
		$joined_table  = array();
		
		$joins = array();
		
		// Error out if we can't figure out the join structure
		if (!preg_match('#^(?:\w+(?:\s+(?:as\s+)?(?:\w+))?)(?:\s+(?:(?:CROSS|INNER|OUTER|LEFT|RIGHT)?\s+)*JOIN\s+(?:\w+(?:\s+(?:as\s+)?(?:\w+))?)(?:\s+ON\s+.*)?)*$#is', $temp_sql)) {
			fCore::toss('fProgrammerException', 'Unable to parse FROM clause, does not appears to be in comma style or join style');
		}
		
		$matches = preg_split('#\s+((?:(?:CROSS|INNER|OUTER|LEFT|RIGHT)?\s+)*?JOIN)\s+#i', $temp_sql, 0, PREG_SPLIT_DELIM_CAPTURE);
		
		$join = array('join_type' => 'none');
		foreach ($matches as $match) {
			
			// This isn't table info but rather just the match consisting of the join type
			if (substr(strtolower($match), -4) == 'join') {
				$join = array('join_type' => $match);	
				continue;
			}
			
			// This grabs the table name and alias (if there is one)
			preg_match('#\s*([\w.]+)(?:\s+(?:as\s+)?((?!ON)[\w.]+))?\s*(?:ON\s+(.*))?#im', $match, $parts);
			
			$table_name  = $parts[1];
			$table_alias = (isset($parts[2])) ? $parts[2] : $parts[1];
			$on_clause   = (isset($parts[3])) ? $parts[3] : NULL;
			
			$join['table_name']  = $table_name;
			$join['table_alias'] = $table_alias;
			
			$table_aliases[$table_alias] = $table_name;
			
			// When we don't have an ON clause we are just making a simple CROSS JOIN
			if (!$on_clause) {
				$join_name = self::createJoinName($table_name . '_simple_', $joins);
				$joins[$join_name] = $join;		
				continue;
			}
			
			
			// Check to see if we have a simple or complex ON clause
			$simple = preg_match('#^((?:\w+\.)?\w+)\s+=\s+((?:\w+\.)?\w+)$#i', $on_clause, $clause_elements);
			
			
			// Here we have a complex ON clause, so we will just store it	
			if (!$simple) {
				for ($i=0; $i < sizeof($strings); $i++) {
					$on_clause = str_replace(':string_' . $i, $strings[$i], $on_clause);	
				}
				
				$join['on_clause_type'] = 'complex_expression';
				$join['on_clause']      = $on_clause;	
				
				$join_name              = self::createJoinName($table_name . '_complex_', $joins);
				$joins[$join_name]      = $join;
				continue;	
			}
				
			
			// If we have an ON clause that is a simple equation, we can break it down further
			$join['on_clause_type'] = 'simple_equation';
			$join['on_clause_fields'] = array($clause_elements[1], $clause_elements[2]);
			
			// This is info we need to name the join
			if (preg_match('#^' . $join['table_alias'] . '\.#i', $clause_elements[1])) {
				$original_table = preg_replace('#\.\w+$#i', '', $clause_elements[2]);
				$route          = preg_replace('#^\w+\.#i', '', $clause_elements[2]);		
			} else {
				$original_table = preg_replace('#\.\w+$#i', '', $clause_elements[1]);
				$route          = preg_replace('#^\w+\.#i', '', $clause_elements[1]);	
			}
			
			$original_table = $table_aliases[$original_table];	
			
			// Route names for one-to-many routes are different
			$route = self::fixRouteName($route, $original_table, $table_name, $schema);
			
			// If this table is a join table it needs to be named differently
			if ($join_relationship = self::checkForJoinTable($original_table, $table_name, $schema)) {
				$joins[$original_table . '_' . $join_relationship['related_table'] . '{' . $table_name . '}_join'] = $join;
				$joined_table[$table_name] = $original_table;	
				continue;
			}
			
			// This indicates that this table is being joined to a join table, affecting the naming
			if (isset($joined_table[$original_table])) {
				$joins[$joined_table[$original_table] . '_' . $table_name . '{' . $original_table . '}'] = $join;
				continue;
			}
			
			// And here we have a plain old join
			$joins[$original_table . '_' . $table_name . '{' . $route . '}'] = $join;

		}  

		return $joins;
	}
	
	
	/**
	 * Takes a Flourish SQL SELECT query and parses it into clauses.
	 * 
	 * The select statement must be of the format:
	 * 
	 * SELECT [ table_name. | alias. ]*
	 * FROM table [ AS alias ] [ [ INNER | OUTER ] [ LEFT | RIGHT ] JOIN other_table ON condition | , ] ...
	 * [ WHERE condition [ , condition ]... ]
	 * [ GROUP BY conditions ]
	 * [ HAVING conditions ]
	 * [ ORDER BY [ column | expression ] [ ASC | DESC ] [ , [ column | expression ] [ ASC | DESC ] ] ... ]
	 * [ LIMIT integer [ OFFSET integer ] ]
	 * 
	 * The returned array will contain the following keys, which may have a NULL or non-empty string value:
	 *  - 'SELECT'
	 *  - 'FROM'
	 *  - 'WHERE'
	 *  - 'GROUP BY'
	 *  - 'HAVING'
	 *  - 'ORDER BY'
	 *  - 'LIMIT'
	 * 
	 * @internal
	 * 
	 * @param  string $sql   The sql to parse
	 * @return array  The various clauses of the SELECT statement (see method descript for details)
	 */
	public function parseSelectSQL($sql)
	{
		// Split the strings out of the sql so parsing doesn't get messed up by quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
		$possible_clauses = array('SELECT', 'FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT');
		$found_clauses    = array();
		foreach ($possible_clauses as $possible_clause) {
			$found_clauses[$possible_clause] = NULL;   
		}
		
		$current_clause = 0;
		
		foreach ($matches[0] as $match) {
			// This is a quoted string value, don't do anything to it
			if ($match[0] == "'") {
				$found_clauses[$possible_clauses[$current_clause]] .= $match;    
			
			// Non-quoted strings should be checked for clause markers
			} else {
				
				// Look to see if a new clause starts in this string
				$i = 1;
				while ($current_clause+$i < sizeof($possible_clauses)) {
					// If the next clause is found in this string
					if (stripos($match, $possible_clauses[$current_clause+$i]) !== FALSE) {
						list($before, $after) = preg_split('#\s*' . $possible_clauses[$current_clause+$i] . '\s*#i', $match);
						$found_clauses[$possible_clauses[$current_clause]] .= preg_replace('#\s*' . $possible_clauses[$current_clause] . '\s*#i', '', $before);
						$match = $after;
						$current_clause = $current_clause + $i;
						$i = 0;
					}  
					$i++;      
				}
				
				// Otherwise just add on to the current clause
				if (!empty($match)) {
					$found_clauses[$possible_clauses[$current_clause]] .= preg_replace('#\s*' . $possible_clauses[$current_clause] . '\s*#i', '', $match);    
				}  
			}
		}  
		
		return $found_clauses; 
	}
	
		
	/**
	 * Takes the FROM clause from parseSelectSQL() and returns all of the tables and each one's alias
	 * 
	 * @internal
	 * 
	 * @param  string $clause   The sql clause to parse
	 * @return array  The tables in the from clause, with the table alias being the key and value being the name
	 */
	static public function parseTableAliases($sql)
	{
		$joins = self::parseJoins($sql);
		
		$aliases = array();
		
		foreach ($joins as $join) {
			$aliases[$join['table_alias']] = $join['table_name'];	
		}
		
		return $aliases;
	}
} 



/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
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