<?php
/**
 * A simple interface to cache data using different backends
 * 
 * @copyright  Copyright (c) 2009-2011 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fCache
 * 
 * @version    1.0.0b2
 * @changes    1.0.0b2  Fixed API calls to the memcache extension to pass the TTL as the correct parameter [wb, 2011-02-01]
 * @changes    1.0.0b   The initial implementation [wb, 2009-04-28]
 */
class fCache
{
	/**
	 * The data cache, only used for file caches
	 * 
	 * The array structure is:
	 * {{{
	 * array(
	 *     (string) {key} => array(
	 *         'value'  => (mixed) {the key's value},
	 *         'expire' => (integer) {the timestamp to expire at, 0 for none}
	 *     )
	 * )
	 * }}}
	 * 
	 * @var array
	 */
	protected $cache;
	
	/**
	 * The data store to use - the file path for a file cache, Memcache object for memcache
	 * 
	 * @var mixed
	 */
	protected $data_store;
	
	/**
	 * The data state, only used for file caches
	 * 
	 * The valid values are:
	 *  - `'clean'`
	 *  - `'dirty'`
	 * 
	 * @var string
	 */
	protected $state;
	
	/**
	 * The type of cache
	 * 
	 * The valid values are:
	 *  - `'apc'`
	 *  - `'file'`
	 *  - `'memcache'`
	 *  - `'xcache'`
	 * 
	 * @var string
	 */
	protected $type;
	
	/**
	 * Set the type and master key for the cache
	 * 
	 * A `file` cache uses a single file to store values in an associative
	 * array and is probably not suitable for a large number of keys.
	 * 
	 * Using an `apc` or `xcache` cache will have far better performance
	 * than a file or directory, however please remember that keys are shared
	 * server-wide.
	 * 
	 * @param  string $type        The type of caching to use: `'apc'`, `'file'`, `'memcache'`, `'xcache'`
	 * @param  mixed  $data_store  The path for a `file` cache, or an `Memcache` object for a `memcache` cache - not used for `apc` or `xcache`
	 * @return fCache
	 */
	public function __construct($type, $data_store=NULL)
	{
		switch ($type) {
			case 'file': 
				$exists = file_exists($data_store);
				if (!$exists && !is_writable(dirname($data_store))) {
					throw new fEnvironmentException(
						'The file specified, %s, does not exist and the directory it in inside of is not writable',
						$data_store
					);		
				}
				if ($exists && !is_writable($data_store)) {
					throw new fEnvironmentException(
						'The file specified, %s, is not writable',
						$data_store
					);
				}
				$this->data_store = $data_store;
				if ($exists) {
					$this->cache = unserialize(file_get_contents($data_store));
				} else {
					$this->cache = array();	
				}
				$this->state = 'clean';
				break;
			
			case 'apc':
			case 'xcache':
			case 'memcache':
				if (!extension_loaded($type)) {
					throw new fEnvironmentException(
						'The %s extension does not appear to be installed',
						$type
					);	
				}
				if ($type == 'memcache') {
					if (!$data_store instanceof Memcache) {
						throw new fProgrammerException(
							'The data store provided is not a valid %s object',
							'Memcache'
						);
					}
					$this->data_store = $data_store;	
				}
				break;
				
			default:
				throw new fProgrammerException(
					'The type specified, %s, is not a valid cache type. Must be one of: %s.',
					$type,
					join(', ', array('apc', 'directory', 'file', 'memcache', 'xcache'))
				);	
		}
		
		$this->type = $type;				
	}
	
	
	/**
	 * Cleans up after the cache object
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		$this->save();
		if ($this->type == 'memcache') {
			$this->data_store->close();	
		}
	}
	
	
	/**
	 * Tries to set a value to the cache, but stops if a value already exists
	 * 
	 * @param  string  $key    The key to store as, this should not exceed 250 characters
	 * @param  mixed   $value  The value to store, this will be serialized
	 * @param  integer $ttl    The number of seconds to keep the cache valid for, 0 for no limit
	 * @return boolean  If the key/value pair were added successfully
	 */
	public function add($key, $value, $ttl=0)
	{
		switch ($this->type) {
			case 'apc':
				return apc_add($key, serialize($value), $ttl);
				
			case 'file':
				if (isset($this->cache[$key]) && $this->cache[$key]['expire'] && $this->cache[$key]['expire'] >= time()) {
					return FALSE;	
				}
				$this->cache[$key] = array(
					'value'  => $value,
					'expire' => (!$ttl) ? 0 : time() + $ttl
				);
				$this->state = 'dirty';
				return TRUE;
			
			case 'memcache':
				if ($ttl > 2592000) {
					$ttl = time() + 2592000;		
				}
				return $this->data_store->add($key, serialize($value), 0, $ttl);
			
			case 'xcache':
				if (xcache_isset($key)) {
					return FALSE;	
				}
				xcache_set($key, serialize($value), $ttl);
				return TRUE;
		}		
	}
	
	
	/**
	 * Clears the WHOLE cache of every key, use with caution!
	 * 
	 * xcache may require a login or password depending on your ini settings.
	 * 
	 * @return void
	 */
	public function clear()
	{
		switch ($this->type) {
			case 'apc':
				apc_clear_cache('user');
				return;
				
			case 'file':
				$this->cache = array();
				$this->state = 'dirty';
				return;
			
			case 'memcache':
				$this->data_store->flush();
				return;
			
			case 'xcache':
				xcache_clear_cache(XC_TYPE_VAR, 0);
				return;
		}			
	}
	
	
	/**
	 * Deletes a value from the cache
	 * 
	 * @param  string $key  The key to delete
	 * @return void
	 */
	public function delete($key)
	{
		switch ($this->type) {
			case 'apc':
				apc_delete($key);
				return;
				
			case 'file':
				if (isset($this->cache[$key])) {
					unset($this->cache[$key]);
					$this->state = 'dirty';	
				}
				return;
			
			case 'memcache':
				$this->data_store->delete($key);
				return;
			
			case 'xcache':
				xcache_unset($key);
				return;
		}		
	}
	
	
	/**
	 * Returns a value from the cache
	 * 
	 * @param  string $key      The key to return the value for
	 * @param  mixed  $default  The value to return if the key did not exist
	 * @return mixed  The cached value or the default value if no cached value was found
	 */
	public function get($key, $default=NULL)
	{
		switch ($this->type) {
			case 'apc':
				$value = apc_fetch($key);
				if ($value === FALSE) { return $default; }
				return unserialize($value);
				
			case 'file':
				if (isset($this->cache[$key])) {
					$expire = $this->cache[$key]['expire'];
					if (!$expire || $expire >= time()) {
						return $this->cache[$key]['value'];	
					} elseif ($expire) {
						unset($this->cache[$key]);
						$this->state = 'dirty';	
					}
				} 
				return $default;
			
			case 'memcache':
				$value = $this->data_store->get($key);
				if ($value === FALSE) { return $default; }
				return unserialize($value);
			
			case 'xcache':
				$value = xcache_get($key);
				if ($value === FALSE) { return $default; }
				return unserialize($value);
		}		
	}
	
	
	/**
	 * Only valid for `file` caches, saves the file to disk and will randomly clean up expired values
	 * 
	 * @return void
	 */
	public function save()
	{
		if ($this->type != 'file') {
			return;
		}			
		
		// Randomly clean the cache out
		if (rand(0, 99) == 50) {
			$clear_before = time();
			
			foreach ($this->cache as $key => $value) {
				if ($value['expire'] && $value['expire'] < $clear_before) {
					unset($this->cache[$key]);	
					$this->state = 'dirty';
				}	
			}
		}
		
		if ($this->state == 'clean') { return; }
		
		file_put_contents($this->data_store, serialize($this->cache));
		$this->state = 'clean';	
	}
	
	
	/**
	 * Sets a value to the cache, overriding any previous value
	 * 
	 * @param  string  $key    The key to store as, this should not exceed 250 characters
	 * @param  mixed   $value  The value to store, this will be serialized
	 * @param  integer $ttl    The number of seconds to keep the cache valid for, 0 for no limit
	 * @return void
	 */
	public function set($key, $value, $ttl=0)
	{
		switch ($this->type) {
			case 'apc':
				apc_store($key, serialize($value), $ttl);
				return;
				
			case 'file':
				$this->cache[$key] = array(
					'value'  => $value,
					'expire' => (!$ttl) ? 0 : time() + $ttl
				);
				$this->state = 'dirty';
				return;
			
			case 'memcache':
				if ($ttl > 2592000) {
					$ttl = time() + 2592000;
				}
				$value = serialize($value);
				if (!$this->data_store->replace($key, $value, 0, $ttl)) {
					$this->data_store->set($key, $value, 0, $ttl);
				}
				return;
			
			case 'xcache':
				xcache_set($key, serialize($value), $ttl);
				return;
		}				
	}
}



/**
 * Copyright (c) 2009-2011 Will Bond <will@flourishlib.com>
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
