<?php
/**
 * A simple php interface to send xml packets to the Plesk API
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-09-23]
 */
class fPleskAPI
{
	/**
	 * The server URL
	 * 
	 * @var string 
	 */
	private $server_url;
	
	/**
	 * The API version
	 * 
	 * @var string 
	 */
	private $api_version;

	/**
	 * The login username
	 * 
	 * @var string 
	 */
	private $username;
	
	/**
	 * The login password
	 * 
	 * @var string 
	 */
	private $password;
	
	
	/**
	 * Sets all of the parameters needed to send requests
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $server_url   The server to connect to
	 * @param  string $username     The username to log in as
	 * @param  string $password     The password for the spcified username
	 * @param  string $api_version  The api version
	 * @return fPleskApi
	 */
	public function __construct($server_url, $username, $password, $api_version='1.5.1.0')
	{
		$this->server_url  = $server_url;
		$this->username    = $username;
		$this->password    = $password;
		$this->api_version = $api_version;	
	}
	
	
	/**
	 * Sends an xml packet to the server and returns a simple xml element
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $xml_packet   The xml packet to send (don't include the xml tag, or the packet tag)
	 * @return SimpleXMLElement	 The returned xml as an object	
	 */
	public function send($xml_packet)
	{
		$xml_packet = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><packet version="' . $this->api_version . '">' . $xml_packet . '</packet>';
		
		$context_options = array (
			'http' => array (
				'method' => 'POST',
				'header' => "Content-type: text/xml\r\n"
						  . "Content-Length: " . strlen($xml_packet) . "\r\n"
						  . "Accept: */*\r\n"
						  . "HTTP_AUTH_LOGIN: " . $this->username . "\r\n"
						  . "HTTP_AUTH_PASSWD: " . $this->password . "\r\n"
						  . "Pragma: no-cache\r\n",
				'content' => $xml_packet
			)
		);
		$context = stream_context_create($context_options);
		$response = file_get_contents('https://' . $this->server_url . ':8443/enterprise/control/agent.php', FALSE, $context);	
		return new SimpleXMLElement($response);
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
