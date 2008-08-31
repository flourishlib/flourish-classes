<?php
/**
 * Returns all available shipping options and prices for the origin and destination locations specified
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fShippingRates
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-20]
 */
class fShippingRates
{
	/**
	 * The API key
	 * 
	 * @var string
	 */
	private $api_key = NULL;
	
	/**
	 * The API login
	 * 
	 * @var string
	 */
	private $api_login = NULL;
	
	/**
	 * The API password
	 * 
	 * @var string
	 */
	private $api_password = NULL;
	
	/**
	 * If debuging is enabled
	 * 
	 * @var boolean
	 */
	private $debug = NULL;
	
	/**
	 * If info for the current request
	 * 
	 * @var array
	 */
	private $request_info = array();
	
	/**
	 * The shipping company to get rates for
	 * 
	 * @var string
	 */
	private $shipping_company = NULL;
	
	/**
	 * If test mode is enabled
	 * 
	 * @var boolean
	 */
	private $test_mode = NULL;
	
	/**
	 * The field info for UPS
	 * 
	 * @var array
	 */
	private $ups_field_info = array(
		'ship_to_address_line_1'      => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_to_address_line_2'      => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_to_address_line_3'      => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_to_city'                => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 30),
		'ship_to_state'               => array(
											 'required'     => array('ship_to_country' => array('US')),
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   'AL', 'AK', 'AS', 'AZ', 'AR', 'CA', 'CO', 'CT',
																   'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID',
																   'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH',
																   'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE',
																   'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'MP',
																   'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 'SC',
																   'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA',
																   'WV', 'WI', 'WY', 'AE', 'AA', 'AE', 'AE', 'AE',
																   'AP')),
		'ship_to_province'            => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string'),
		'ship_to_zip_code'            => array(
											 'required'     => array('ship_to_country' => array(
																   'AX', 'DZ', 'AD', 'AR', 'AM', 'AU', 'AT', 'AZ',
																   'BH', 'BD', 'BB', 'BY', 'BE', 'BM', 'BO', 'BA',
																   'BR', 'BN', 'BG', 'KH', 'CA', 'CV', 'KY', 'TD',
																   'CL', 'CN', 'CX', 'CC', 'CO', 'CR', 'HR', 'CU',
																   'CY', 'CZ', 'DK', 'DO', 'EG', 'SV', 'EE', 'ET',
																   'FO', 'FI', 'FR', 'GA', 'GE', 'DE', 'GR', 'GT',
																   'GW', 'HT', 'HN', 'HK', 'HU', 'IS', 'IN', 'ID',
																   'IR', 'IL', 'IT', 'JM', 'JP', 'JO', 'KZ', 'KE',
																   'KG', 'KR', 'LV', 'LB', 'LA', 'LS', 'LR', 'LY',
																   'LT', 'LU', 'MK', 'MG', 'MV', 'MY', 'MT', 'MX',
																   'MD', 'MC', 'MN', 'MA', 'MZ', 'MM', 'NP', 'NL',
																   'NZ', 'NI', 'NE', 'NG', 'NO', 'OM', 'PK', 'PG',
																   'PY', 'PE', 'PH', 'PL', 'PT', 'PR', 'RO', 'RU',
																   'SA', 'CS', 'SG', 'SK', 'ZA', 'SI', 'ES', 'LK',
																   'SD', 'SZ', 'SE', 'CH', 'TW', 'TJ', 'TH', 'TN',
																   'TR', 'TM', 'UA', 'GB', 'US', 'UY', 'UZ', 'VE',
																   'VN', 'ZM')),
											 'default'      => NULL,
											 'type'         => 'money'),
		'ship_to_country'             => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   'AX', 'AF', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI',
																   'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ',
																   'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ',
																   'BM', 'BT', 'BO', 'BA', 'BW', 'BV', 'BR', 'IO',
																   'BN', 'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV',
																   'KY', 'CF', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO',
																   'KM', 'CD', 'CG', 'CK', 'CR', 'CI', 'HR', 'CU',
																   'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG',
																   'SV', 'GQ', 'ER', 'EE', 'ET', 'FK', 'FO', 'FJ',
																   'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE',
																   'DE', 'GH', 'GI', 'GR', 'GL', 'GD', 'GP', 'GU',
																   'GT', 'GN', 'GW', 'GY', 'HT', 'HM', 'HN', 'HK',
																   'HU', 'IS', 'IN', 'ID', 'IR', 'IQ', 'IE', 'IL',
																   'IT', 'JM', 'JP', 'JO', 'KZ', 'KE', 'KI', 'KP',
																   'KR', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR',
																   'LY', 'LI', 'LT', 'LU', 'MO', 'MK', 'MG', 'MW',
																   'MY', 'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU',
																   'YT', 'MX', 'FM', 'MD', 'MC', 'MN', 'MS', 'MA',
																   'MZ', 'MM', 'NA', 'NR', 'NP', 'NL', 'AN', 'NC',
																   'NZ', 'NI', 'NE', 'NG', 'NU', 'NF', 'MP', 'NO',
																   'OM', 'PK', 'PW', 'PS', 'PA', 'PG', 'PY', 'PE',
																   'PH', 'PN', 'PL', 'PT', 'PR', 'QA', 'RE', 'RO',
																   'RU', 'RW', 'SH', 'KN', 'LC', 'PM', 'VC', 'WS',
																   'SM', 'ST', 'SA', 'SN', 'CS', 'SC', 'SL', 'SG',
																   'SK', 'SI', 'SB', 'SO', 'ZA', 'GS', 'ES', 'LK',
																   'SD', 'SR', 'SJ', 'SZ', 'SE', 'CH', 'SY', 'TW',
																   'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK', 'TO', 'TT',
																   'TN', 'TR', 'TM', 'TC', 'TV', 'UG', 'UA', 'AE',
																   'GB', 'US', 'UM', 'UY', 'UZ', 'VU', 'VA', 'VE',
																   'VN', 'VG', 'VI', 'WF', 'EH', 'YE', 'ZM', 'ZW')),
		'ship_from_address_line_1'    => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_from_address_line_2'    => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_from_address_line_3'    => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 35),
		'ship_from_city'              => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 30),
		'ship_from_state'             => array(
											 'required'     => array('ship_to_country' => array('US')),
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   'AL', 'AK', 'AS', 'AZ', 'AR', 'CA', 'CO', 'CT',
																   'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID',
																   'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH',
																   'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE',
																   'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'MP',
																   'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 'SC',
																   'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA',
																   'WV', 'WI', 'WY', 'AE', 'AA', 'AE', 'AE', 'AE',
																   'AP')),
		'ship_from_province'          => array(
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string'),
		'ship_from_zip_code'          => array(
											 'required'     => array('ship_to_country' => array(
																   'AX', 'DZ', 'AD', 'AR', 'AM', 'AU', 'AT', 'AZ',
																   'BH', 'BD', 'BB', 'BY', 'BE', 'BM', 'BO', 'BA',
																   'BR', 'BN', 'BG', 'KH', 'CA', 'CV', 'KY', 'TD',
																   'CL', 'CN', 'CX', 'CC', 'CO', 'CR', 'HR', 'CU',
																   'CY', 'CZ', 'DK', 'DO', 'EG', 'SV', 'EE', 'ET',
																   'FO', 'FI', 'FR', 'GA', 'GE', 'DE', 'GR', 'GT',
																   'GW', 'HT', 'HN', 'HK', 'HU', 'IS', 'IN', 'ID',
																   'IR', 'IL', 'IT', 'JM', 'JP', 'JO', 'KZ', 'KE',
																   'KG', 'KR', 'LV', 'LB', 'LA', 'LS', 'LR', 'LY',
																   'LT', 'LU', 'MK', 'MG', 'MV', 'MY', 'MT', 'MX',
																   'MD', 'MC', 'MN', 'MA', 'MZ', 'MM', 'NP', 'NL',
																   'NZ', 'NI', 'NE', 'NG', 'NO', 'OM', 'PK', 'PG',
																   'PY', 'PE', 'PH', 'PL', 'PT', 'PR', 'RO', 'RU',
																   'SA', 'CS', 'SG', 'SK', 'ZA', 'SI', 'ES', 'LK',
																   'SD', 'SZ', 'SE', 'CH', 'TW', 'TJ', 'TH', 'TN',
																   'TR', 'TM', 'UA', 'GB', 'US', 'UY', 'UZ', 'VE',
																   'VN', 'ZM')),
											 'default'      => NULL,
											 'type'         => 'money'),
		'ship_from_country'           => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   'AX', 'AF', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI',
																   'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ',
																   'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ',
																   'BM', 'BT', 'BO', 'BA', 'BW', 'BV', 'BR', 'IO',
																   'BN', 'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV',
																   'KY', 'CF', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO',
																   'KM', 'CD', 'CG', 'CK', 'CR', 'CI', 'HR', 'CU',
																   'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG',
																   'SV', 'GQ', 'ER', 'EE', 'ET', 'FK', 'FO', 'FJ',
																   'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE',
																   'DE', 'GH', 'GI', 'GR', 'GL', 'GD', 'GP', 'GU',
																   'GT', 'GN', 'GW', 'GY', 'HT', 'HM', 'HN', 'HK',
																   'HU', 'IS', 'IN', 'ID', 'IR', 'IQ', 'IE', 'IL',
																   'IT', 'JM', 'JP', 'JO', 'KZ', 'KE', 'KI', 'KP',
																   'KR', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR',
																   'LY', 'LI', 'LT', 'LU', 'MO', 'MK', 'MG', 'MW',
																   'MY', 'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU',
																   'YT', 'MX', 'FM', 'MD', 'MC', 'MN', 'MS', 'MA',
																   'MZ', 'MM', 'NA', 'NR', 'NP', 'NL', 'AN', 'NC',
																   'NZ', 'NI', 'NE', 'NG', 'NU', 'NF', 'MP', 'NO',
																   'OM', 'PK', 'PW', 'PS', 'PA', 'PG', 'PY', 'PE',
																   'PH', 'PN', 'PL', 'PT', 'PR', 'QA', 'RE', 'RO',
																   'RU', 'RW', 'SH', 'KN', 'LC', 'PM', 'VC', 'WS',
																   'SM', 'ST', 'SA', 'SN', 'CS', 'SC', 'SL', 'SG',
																   'SK', 'SI', 'SB', 'SO', 'ZA', 'GS', 'ES', 'LK',
																   'SD', 'SR', 'SJ', 'SZ', 'SE', 'CH', 'SY', 'TW',
																   'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK', 'TO', 'TT',
																   'TN', 'TR', 'TM', 'TC', 'TV', 'UG', 'UA', 'AE',
																   'GB', 'US', 'UM', 'UY', 'UZ', 'VU', 'VA', 'VE',
																   'VN', 'VG', 'VI', 'WF', 'EH', 'YE', 'ZM', 'ZW')),
		'package_weight'              => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'float'),
		'weight_units'                => array(
											 'required'     => TRUE,
											 'default'      => 'LBS',
											 'type'         => 'float'),
		'package_type'                => array(
											 'required'     => TRUE,
											 'default'      => '00',
											 'type'         => 'string',
											 'valid_values' => array(
																   '00', //Unknown
																   '01', //UPS Letter
																   '02', //Customer Supplied Package
																   '03', //Tube
																   '04', //PAK
																   '21', //UPS Express Box
																   '2a', //UPS Small Express Box
																   '2b', //UPS Medium Express Box
																   '2c', //UPS Large Express Box
																   '24', //UPS 25KG Box
																   '25', //UPS 10KG Box
																   '30', //Pallet
																   )),
		'dimensions_units'            => array(
											 'required'     => TRUE,
											 'default'      => 'IN',
											 'type'         => 'string',
											 'valid_values' => array('IN', 'CM')),
		'package_width'               => array(
											 'required'     => array('package_type' => array('00', '02', '30')),
											 'default'      => NULL,
											 'type'         => 'float'),
		'package_height'              => array(
											 'required'     => array('package_type' => array('00', '02', '30')),
											 'default'      => NULL,
											 'type'         => 'float'),
		'package_length'              => array(
											 'required'     => array('package_type' => array('00', '02', '30')),
											 'default'      => NULL,
											 'type'         => 'float'),
		'pickup_type'                 => array(
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   '01', // Daily pickup
																   '03', // Customer counter (Include UPS Store locations?)
																   '06', // One-time pickup
																   '07', // On call air (Pickups that only deal with air?)
																   '11', // Suggested retail rates (suggested what Staples, etc may charge?)
																   '19', // Letter center (aka drop box?)
																   '20'  // Air service center (Staffed locations that only deal with air?)
																   )),
		'customer_classification'     => array(
											 'required'     => array('pickup_type' => array('11')),
											 'default'      => NULL,
											 'type'         => 'string',
											 'valid_values' => array(
																   '01', // Wholesale
																   '03', // Occassional
																   '04'  // Retail
																   ))
		);
	
	
	/**
	 * Sets up to process rate requests
	 * 
	 * @param  string $shipping_company  The company to get shipping rates for
	 * @param  string $api_login         The login to the rate api
	 * @param  string $api_password      The password for the api account
	 * @param  string $api_key           The key to log into the api
	 * @return fShippingRates
	 */
	public function __construct($shipping_company, $api_login, $api_password, $api_key)
	{
		$valid_shipping_companies = array('ups');
		if (!in_array($shipping_company, $valid_shipping_companies)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The shipping company specified, %1$s, is invalid. Must be one of: %2$s.',
					fCore::dump($shipping_company),
					join(', ', $valid_shipping_companies)
				)
			);
		}
		
		$this->shipping_company = $shipping_company;
		$this->api_login        = $api_login;
		$this->api_password     = $api_password;
		$this->api_key          = $api_key;
	}
	
	
	/**
	 * Sets if debug messages should be shown
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function enableDebugging($flag)
	{
		$this->debug = (boolean) $flag;
	}
	
	
	/**
	 * Fetches the rates from the shipping company
	 * 
	 * @throws fValidationException
	 * 
	 * @return array  An array of (string) {service name} => (string) {price with currency symbol}
	 */
	public function fetch()
	{
		$this->validate();
		
		if ($this->shipping_company == 'ups') {
			$result = $this->postUpsRequest();
		}
		
		return $result;
	}
	
	
	/**
	 * Builds and sends the XML request to the UPS servers, returning an array of service names and prices
	 * 
	 * @throws fValidationException
	 * 
	 * @return array  An associative array of service names and prices
	 */
	private function postUpsRequest()
	{
		fCore::debug(
			fGrammar::compose(
				'Data being sent to API:%s',
				"\n" . fCore::dump($this->request_info)
			),
			$this->debug
		);
		
		$post_data = <<<XMLDATA
<?xml version="1.0" ?>
<AccessRequest xml:lang="en-US">
	<AccessLicenseNumber>{$this->api_key}</AccessLicenseNumber>
	<UserId>{$this->api_login}</UserId>
	<Password>{$this->api_password}</Password>
</AccessRequest>
<?xml version="1.0" ?>
<RatingServiceSelectionRequest>
	<Request>
		<RequestAction>Rate</RequestAction>
		<RequestOption>Shop</RequestOption>
	</Request>
	<PickupType>
		<Code>{$this->request_info['pickup_type']}</Code>
	</PickupType>
	<CustomerClassification>
		<Code>{$this->request_info['customer_classification']}</Code>
	</CustomerClassification>
	<Shipment>
		 <Shipper>
			  <Address>
				  <AddressLine1>{$this->request_info['ship_from_address_line_1']}</AddressLine1>
				  <AddressLine2>{$this->request_info['ship_from_address_line_2']}</AddressLine2>
				  <AddressLine3>{$this->request_info['ship_from_address_line_3']}</AddressLine3>
				  <City>{$this->request_info['ship_from_city']}</City>
				  <StateProvinceCode>{$this->request_info['ship_from_state']}{$this->request_info['ship_from_province']}</StateProvinceCode>
				  <PostalCode>{$this->request_info['ship_from_zip_code']}</PostalCode>
				  <CountryCode>{$this->request_info['ship_from_country']}</CountryCode>
			  </Address>
		 </Shipper>
		 <ShipTo>
			  <Address>
				  <AddressLine1>{$this->request_info['ship_to_address_line_1']}</AddressLine1>
				  <AddressLine2>{$this->request_info['ship_to_address_line_2']}</AddressLine2>
				  <AddressLine3>{$this->request_info['ship_to_address_line_3']}</AddressLine3>
				  <City>{$this->request_info['ship_to_city']}</City>
				  <StateProvinceCode>{$this->request_info['ship_to_state']}{$this->request_info['ship_to_province']}</StateProvinceCode>
				  <PostalCode>{$this->request_info['ship_to_zip_code']}</PostalCode>
				  <CountryCode>{$this->request_info['ship_to_country']}</CountryCode>
			  </Address>
		 </ShipTo>
		 <ShipFrom>
			  <Address>
				  <AddressLine1>{$this->request_info['ship_from_address_line_1']}</AddressLine1>
				  <AddressLine2>{$this->request_info['ship_from_address_line_2']}</AddressLine2>
				  <AddressLine3>{$this->request_info['ship_from_address_line_3']}</AddressLine3>
				  <City>{$this->request_info['ship_from_city']}</City>
				  <StateProvinceCode>{$this->request_info['ship_from_state']}{$this->request_info['ship_from_province']}</StateProvinceCode>
				  <PostalCode>{$this->request_info['ship_from_zip_code']}</PostalCode>
				  <CountryCode>{$this->request_info['ship_from_country']}</CountryCode>
			  </Address>
		 </ShipFrom>
		 <Package>
			  <PackagingType>
				  <Code>{$this->request_info['package_type']}</Code>
			  </PackagingType>
			  <PackageWeight>
				  <UnitOfMeasurement>
					   <Code>{$this->request_info['weight_units']}</Code>
				  </UnitOfMeasurement>
				  <Weight>{$this->request_info['package_weight']}</Weight>
			  </PackageWeight>
			  <Dimensions>
				  <UnitOfMeasurement>
					   <Code>{$this->request_info['dimensions_units']}</Code>
				  </UnitOfMeasurement>
				  <Length>{$this->request_info['package_length']}</Length>
				  <Width>{$this->request_info['package_width']}</Width>
				  <Height>{$this->request_info['package_height']}</Height>
			  </Dimensions>
		 </Package>
	</Shipment>
</RatingServiceSelectionRequest>
XMLDATA;
			
		if (!$this->test_mode) {
			$server = 'https://www.ups.com/ups.app/xml/Rate';
		} else {
			$server = 'https://wwwcie.ups.com/ups.app/xml/Rate';
		}
		
		$context_options = array (
			'http' => array (
				'method' => 'POST',
				'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
						 . "Content-Length: " . strlen($post_data) . "\r\n",
				'content' => $post_data
			)
		);
		$context = stream_context_create($context_options);
		
		// Suppress errors to handle the nasty message from php about IIS not properly terminating an SSL connection
		$result  = @trim(urldecode(file_get_contents($server, FALSE, $context)));
		
		fCore::debug("Data received from gateway:\n" . $result, $this->debug);
		
		$xml = new SimpleXMLElement($result);
		
		if ($xml->Response->ResponseStatusCode != '1') {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'There was an error retrieving the rates from the API'
				)
			);
		}
		
		$service_names = array(
			'01' => 'UPS Next Day Air',
			'02' => 'UPS Second Day Air',
			'03' => 'UPS Ground',
			'07' => 'UPS Worldwide Express',
			'08' => 'UPS Worldwide Expedited',
			'11' => 'UPS Standard',
			'12' => 'UPS Three-Day Select',
			'13' => 'UPS Next Day Air Saver',
			'14' => 'UPS Next Day Air Early A.M.',
			'54' => 'UPS Worldwide Express Plus',
			'59' => 'UPS Second Day Air A.M.',
			'65' => 'UPS Saver'
		);
		
		$output = array();
		
		$currency_symbol = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£'
		);
		
		foreach ($xml->RatedShipment as $option) {
			$output[$service_names[(string) $option->Service->Code]] = $currency_symbol[(string) $option->TotalCharges->CurrencyCode] . number_format((string) $option->TotalCharges->MonetaryValue, 2, '.', ',');
		}
		
		natcasesort($output);
		
		return $output;
	}
	
	
	/**
	 * Sets the default values for any fields that have not been manually assigned
	 * 
	 * @return void
	 */
	private function setDefaultValues()
	{
		if ($this->shipping_company == 'ups') {
			$field_info =& $this->ups_field_info;
		}
		
		foreach ($field_info as $field => $info) {
			if ($info['default'] !== NULL && !isset($this->request_info[$field])) {
				$this->request_info[$field] = $info['default'];
			}
		}
	}
	
	
	/**
	 * Set the customer classification
	 * 
	 * @param  string $customer_classification  The units that the dimensions are measured in
	 * @return void
	 */
	public function setCustomerClassification($customer_classification)
	{
		$this->request_info['customer_classification'] = $customer_classification;
	}
	
	
	/**
	 * Set the dimensions units (IN or CM)
	 * 
	 * @param  string $dimensions_units  The units that the dimensions are measured in
	 * @return void
	 */
	public function setDimensionsUnits($dimensions_units)
	{
		$this->request_info['dimensions_units'] = $dimensions_units;
	}
	
	
	/**
	 * Set the package height
	 * 
	 * @param  float $package_height  The height of the package being shipped
	 * @return void
	 */
	public function setPackageHeight($package_height)
	{
		$this->request_info['package_height'] = $package_height;
	}
	
	
	/**
	 * Set the package length
	 * 
	 * @param  float $package_length  The length of the package being shipped
	 * @return void
	 */
	public function setPackageLength($package_length)
	{
		$this->request_info['package_length'] = $package_length;
	}
	
	
	/**
	 * Set the package type
	 * 
	 * @param  string $package_type  The package type
	 * @return void
	 */
	public function setPackageType($package_type)
	{
		$this->request_info['package_type'] = $package_type;
	}
	
	
	/**
	 * Set the package weight
	 * 
	 * @param  float $package_weight  The weight of the package being shipped
	 * @return void
	 */
	public function setPackageWeight($package_weight)
	{
		$this->request_info['package_weight'] = $package_weight;
	}
	
	
	/**
	 * Set the package width
	 * 
	 * @param  float $package_width  The width of the package being shipped
	 * @return void
	 */
	public function setPackageWidth($package_width)
	{
		$this->request_info['package_width'] = $package_width;
	}
	
	
	/**
	 * Set the pickup type
	 * 
	 * @param  string $pickup_type  The pickup type
	 * @return void
	 */
	public function setPickupType($pickup_type)
	{
		$this->request_info['pickup_type'] = $pickup_type;
	}
	
	
	/**
	 * Set the ship from address line 1
	 * 
	 * @param  string $ship_from_address_line_1  The address being shipped from
	 * @return void
	 */
	public function setShipFromAddressLine1($ship_from_address_line_1)
	{
		$this->request_info['ship_from_address_line_1'] = $ship_from_address_line_1;
	}
	
	
	/**
	 * Set the ship from address line 2
	 * 
	 * @param  string $ship_from_address_line_2  The address being shipped from
	 * @return void
	 */
	public function setShipFromAddressLine2($ship_from_address_line_2)
	{
		$this->request_info['ship_from_address_line_2'] = $ship_from_address_line_2;
	}
	
	
	/**
	 * Set the ship from address line 3
	 * 
	 * @param  string $ship_from_address_line_3  The address being shipped from
	 * @return void
	 */
	public function setShipFromAddressLine3($ship_from_address_line_3)
	{
		$this->request_info['ship_from_address_line_3'] = $ship_from_address_line_3;
	}
	
	
	/**
	 * Set the ship from city
	 * 
	 * @param  string $ship_from_city  The city being shipped from
	 * @return void
	 */
	public function setShipFromCity($ship_from_city)
	{
		$this->request_info['ship_from_city'] = $ship_from_city;
	}
	
	
	/**
	 * Set the ship from country
	 * 
	 * @param  string $ship_from_country  The country being shipped from
	 * @return void
	 */
	public function setShipFromCountry($ship_from_country)
	{
		$this->request_info['ship_from_country'] = $ship_from_country;
	}
	
	
	/**
	 * Set the ship from province
	 * 
	 * @param  string $ship_from_province  The province being shipped from
	 * @return void
	 */
	public function setShipFromProvince($ship_from_province)
	{
		$this->request_info['ship_from_province'] = $ship_from_province;
	}
	
	
	/**
	 * Set the ship from state
	 * 
	 * @param  string $ship_from_state  The state being shipped from
	 * @return void
	 */
	public function setShipFromState($ship_from_state)
	{
		$this->request_info['ship_from_state'] = $ship_from_state;
	}
	
	
	/**
	 * Set the ship from zip code
	 * 
	 * @param  string $ship_from_zip_code  The zip code being shipped from
	 * @return void
	 */
	public function setShipFromZipCode($ship_from_zip_code)
	{
		$this->request_info['ship_from_zip_code'] = $ship_from_zip_code;
	}
	
	
	/**
	 * Set the ship to address line 1
	 * 
	 * @param  string $ship_to_address_line_1  The address being shipped from
	 * @return void
	 */
	public function setShipToAddressLine1($ship_to_address_line_1)
	{
		$this->request_info['ship_to_address_line_1'] = $ship_to_address_line_1;
	}
	
	
	/**
	 * Set the ship to address line 2
	 * 
	 * @param  string $ship_to_address_line_2  The address being shipped from
	 * @return void
	 */
	public function setShipToAddressLine2($ship_to_address_line_2)
	{
		$this->request_info['ship_to_address_line_2'] = $ship_to_address_line_2;
	}
	
	
	/**
	 * Set the ship to address line 3
	 * 
	 * @param  string $ship_to_address_line_3  The address being shipped from
	 * @return void
	 */
	public function setShipToAddressLine3($ship_to_address_line_3)
	{
		$this->request_info['ship_to_address_line_3'] = $ship_to_address_line_3;
	}
	
	
	/**
	 * Set the ship to city
	 * 
	 * @param  string $ship_to_city  The city being shipped from
	 * @return void
	 */
	public function setShipToCity($ship_to_city)
	{
		$this->request_info['ship_to_city'] = $ship_to_city;
	}
	
	
	/**
	 * Set the ship to country
	 * 
	 * @param  string $ship_to_country  The country being shipped from
	 * @return void
	 */
	public function setShipToCountry($ship_to_country)
	{
		$this->request_info['ship_to_country'] = $ship_to_country;
	}
	
	
	/**
	 * Set the ship to province
	 * 
	 * @param  string $ship_to_province  The province being shipped from
	 * @return void
	 */
	public function setShipToProvince($ship_to_province)
	{
		$this->request_info['ship_to_province'] = $ship_to_province;
	}
	
	
	/**
	 * Set the ship to state
	 * 
	 * @param  string $ship_to_state  The state being shipped from
	 * @return void
	 */
	public function setShipToState($ship_to_state)
	{
		$this->request_info['ship_to_state'] = $ship_to_state;
	}
	
	
	/**
	 * Set the ship to zip code
	 * 
	 * @param  string $ship_to_zip_code  The zip code being shipped from
	 * @return void
	 */
	public function setShipToZipCode($ship_to_zip_code)
	{
		$this->request_info['ship_to_zip_code'] = $ship_to_zip_code;
	}
	
	
	/**
	 * Enters test mode, where transactions are sent to the test api url
	 * 
	 * @param  boolean $enable  If test mode should be enabled
	 * @return void
	 */
	public function setTestMode($enable)
	{
		$this->test_mode = (boolean) $enable;
	}
	
	
	/**
	 * Set the weight units (LBS or KGS)
	 * 
	 * @param  string $weight_units  The units that the weight is measured in
	 * @return void
	 */
	public function setWeightUnits($weight_units)
	{
		$this->request_info['weight_units'] = $weight_units;
	}
	
	
	/**
	 * Makes sure all of the required fields are entered, and that data types are correct
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	public function validate()
	{
		if ($this->shipping_company == 'ups') {
			$field_info =& $this->ups_field_info;
		}
		
		$messages = array();
		
		$this->setDefaultValues();
		
		foreach ($field_info as $field => $info) {
			// Handle simple required fields
			if ($info['required'] === TRUE && !isset($this->request_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a value',
					fGrammar::humanize($field)
				);
			
			// Handle conditional required fields
			} elseif (is_array($info['required'])) {
				$keys = array_keys($info['required']);
				$conditional_field  = $keys[0];
				$conditional_values = $info['required'][$conditional_field];
				if (isset($this->request_info[$conditional_field]) && in_array($this->request_info[$conditional_field], $conditional_values) && !isset($this->request_info[$field])) {
					$messages[] = fGrammar::compose(
						'%s: Please enter a value',
						fGrammar::humanize($field)
					);
				}
			}
		}
		
		foreach ($this->request_info as $field => $value) {
			$info =& $field_info[$field];
			
			if ($this->request_info[$field] === NULL) {
				continue;
			}
			
			if (isset($info['valid_values']) && !in_array($this->request_info[$field], $info['valid_values'])) {
				$messages[] = fGrammar::compose(
					'%1$s: Please choose from one of the following: %2$s',
					fGrammar::humanize($field),
					join(', ', $info['valid_values'])
				);
				continue;
			}
			if ($info['type'] == 'string' && !is_string($this->request_info[$field]) && !is_numeric($this->request_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a string',
					fGrammar::humanize($field)
				);
				continue;
			}
			if (isset($info['max_length']) && strlen($this->request_info[$field]) > $info['max_length']) {
				$messages[] = fGrammar::compose(
					'%1$s: Please enter a value no longer than %2$s characters',
					fGrammar::humanize($field),
					$info['max_length']
				);
				continue;
			}
		}
		
		if ($messages) {
			fCore::toss('fValidationException', join("\n", $messages));
		}
		
		
		// Make sure empty elements are set to NULL so we don't get php warnings
		foreach ($field_info as $field => $info) {
			if (!isset($this->request_info[$field])) {
				$this->request_info[$field] = NULL;
			}
		}
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