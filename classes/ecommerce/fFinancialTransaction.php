<?php
/**
 * Interface to various payment gateway systems for credit card/echeck transactions
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fFinancialTransaction
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-20]
 */
class fFinancialTransaction
{
	/**
	 * The field names for authorize.net
	 * 
	 * @var array
	 */
	private $authorize_net_field_info = array(
		'account_number'              => array(
											 'field'        => 'x_login',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'transaction_key'             => array(
											 'field'        => 'x_tran_key',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 16),
		'invoice_number'              => array(
											 'field'        => 'x_invoice_num',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'invoice_description'         => array(
											 'field'        => 'x_description',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 255),
		'amount'                      => array(
											 'field'        => 'x_amount',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'tax_amount'                  => array(
											 'field'        => 'x_tax',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'tax_exempt'                  => array(
											 'field'        => 'x_tax_exempt',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'boolean'),
		'shipping_amount'             => array(
											 'field'        => 'x_freight',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'duty_amount'                 => array(
											 'field'        => 'x_duty',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'currency_code'               => array(
											 'field'        => 'x_currency_code',
											 'required'     => TRUE,
											 'default'      => 'USD',
											 'type'         => 'string',
											 'valid_values' => array('USD','EUR','GBP','AUD')),
		'payment_type '               => array(
											 'field'        => 'x_method',
											 'required'     => TRUE,
											 'default'      => 'CC',
											 'type'         => 'string',
											 'valid_values' => array('CC', 'ECHECK')),
		'transaction_type'            => array(
											 'field'        => 'x_type',
											 'required'     => TRUE,
											 'default'      => 'AUTH_CAPTURE',
											 'type'         => 'string',
											 'valid_values' => array('AUTH_CAPTURE','AUTH_ONLY','CAPTURE_ONLY','CREDIT','VOID','PRIOR_AUTH_CAPTURE')),
		'transaction_id'              => array(
											 'field'        => 'x_trans_id',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 10),
		'send_customer_email'         => array(
											 'field'        => 'x_email_customer',
											 'required'     => FALSE,
											 'default'      => FALSE,
											 'type'         => 'boolean'),
		'customer_id'                 => array(
											 'field'        => 'x_cust_id',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'customer_ip_address'         => array(
											 'field'        => 'x_customer_ip',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 15),
		'customer_email'              => array(
											 'field'        => 'x_email',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 255),
		'credit_card_number'          => array(
											 'field'        => 'x_card_num',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 22),
		'credit_card_expiration_date' => array(
											 'field'        => 'x_exp_date',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'date'),
		'credit_card_cvv_code'        => array(
											 'field'        => 'x_card_code',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 4),
		'billing_first_name'          => array(
											 'field'        => 'x_first_name',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_last_name'           => array(
											 'field'        => 'x_last_name',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_company'             => array(
											 'field'        => 'x_company',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_address'             => array(
											 'field'        => 'x_address',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'billing_city'                => array(
											 'field'        => 'x_city',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'billing_state'               => array(
											 'field'        => 'x_state',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'billing_country'             => array(
											 'field'        => 'x_country',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'billing_zip_code'            => array(
											 'field'        => 'x_zip',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'billing_phone_number'        => array(
											 'field'        => 'x_phone',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 25),
		'billing_fax_number'          => array(
											 'field'        => 'x_fax',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 25),
		'shipping_first_name'         => array(
											 'field'        => 'x_ship_to_first_name',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_last_name'          => array(
											 'field'        => 'x_ship_to_last_name',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_company'            => array(
											 'field'        => 'x_ship_to_company',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_address'            => array(
											 'field'        => 'x_ship_to_address',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'shipping_city'               => array(
											 'field'        => 'x_ship_to_city',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'shipping_state'              => array(
											 'field'        => 'x_ship_to_state',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'shipping_country'            => array(
											 'field'        => 'x_ship_to_country',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'shipping_zip_code'           => array(
											 'field'        => 'x_ship_to_zip',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20));
	
	/**
	 * If debuging is enabled
	 * 
	 * @var boolean
	 */
	private $debug = NULL;
	
	/**
	 * The payment gateway to use
	 * 
	 * @var string
	 */
	private $gateway = NULL;
	
	/**
	 * The field names for authorize.net
	 * 
	 * @var array
	 */
	private $secure_pay_field_info = array(
		'account_number'              => array(
											 'field'        => 'x_login',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'transaction_key'             => array(
											 'field'        => 'x_tran_key',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 16),
		'invoice_number'              => array(
											 'field'        => 'x_invoice_num',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'invoice_description'         => array(
											 'field'        => 'x_description',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 255),
		'amount'                      => array(
											 'field'        => 'x_amount',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'tax_amount'                  => array(
											 'field'        => 'x_tax',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'tax_exempt'                  => array(
											 'field'        => 'x_tax_exempt',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'boolean'),
		'shipping_amount'             => array(
											 'field'        => 'x_freight',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'duty_amount'                 => array(
											 'field'        => 'x_duty',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'money'),
		'currency_code'               => array(
											 'field'        => 'x_currency_code',
											 'required'     => TRUE,
											 'default'      => 'USD',
											 'type'         => 'string',
											 'valid_values' => array('USD','EUR','GBP','AUD')),
		'payment_type '               => array(
											 'field'        => 'x_method',
											 'required'     => TRUE,
											 'default'      => 'CC',
											 'type'         => 'string',
											 'valid_values' => array('CC', 'ECHECK')),
		'transaction_type'            => array(
											 'field'        => 'x_type',
											 'required'     => TRUE,
											 'default'      => 'AUTH_CAPTURE',
											 'type'         => 'string',
											 'valid_values' => array('AUTH_CAPTURE','AUTH_ONLY','CAPTURE_ONLY','CREDIT','VOID','PRIOR_AUTH_CAPTURE')),
		'transaction_id'              => array(
											 'field'        => 'x_trans_id',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 10),
		'send_customer_email'         => array(
											 'field'        => 'x_email_customer',
											 'required'     => FALSE,
											 'default'      => FALSE,
											 'type'         => 'boolean'),
		'customer_id'                 => array(
											 'field'        => 'x_cust_id',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'customer_ip_address'         => array(
											 'field'        => 'x_customer_ip',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 15),
		'customer_email'              => array(
											 'field'        => 'x_email',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 255),
		'credit_card_number'          => array(
											 'field'        => 'x_card_num',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 22),
		'credit_card_expiration_date' => array(
											 'field'        => 'x_exp_date',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'date'),
		'credit_card_cvv_code'        => array(
											 'field'        => 'x_card_code',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 4),
		'billing_first_name'          => array(
											 'field'        => 'x_first_name',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_last_name'           => array(
											 'field'        => 'x_last_name',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_company'             => array(
											 'field'        => 'x_company',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'billing_address'             => array(
											 'field'        => 'x_address',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'billing_city'                => array(
											 'field'        => 'x_city',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'billing_state'               => array(
											 'field'        => 'x_state',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'billing_country'             => array(
											 'field'        => 'x_country',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'billing_zip_code'            => array(
											 'field'        => 'x_zip',
											 'required'     => TRUE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20),
		'billing_phone_number'        => array(
											 'field'        => 'x_phone',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 25),
		'billing_fax_number'          => array(
											 'field'        => 'x_fax',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 25),
		'shipping_first_name'         => array(
											 'field'        => 'x_ship_to_first_name',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_last_name'          => array(
											 'field'        => 'x_ship_to_last_name',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_company'            => array(
											 'field'        => 'x_ship_to_company',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 50),
		'shipping_address'            => array(
											 'field'        => 'x_ship_to_address',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'shipping_city'               => array(
											 'field'        => 'x_ship_to_city',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'shipping_state'              => array(
											 'field'        => 'x_ship_to_state',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 40),
		'shipping country'            => array(
											 'field'        => 'x_ship_to_country',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 60),
		'shipping_zip_code'           => array(
											 'field'        => 'x_ship_to_zip',
											 'required'     => FALSE,
											 'default'      => NULL,
											 'type'         => 'string',
											 'max_length'   => 20));
	
	/**
	 * Transaction information
	 * 
	 * @var array
	 */
	private $transaction_info = array();
	
	/**
	 * If we are in test mode
	 * 
	 * @var boolean
	 */
	private $test_mode = FALSE;
	
	
	/**
	 * Sets up to process a transaction
	 * 
	 * @param  string $gateway          The payment gateway to use
	 * @param  string $account_number   The account number/id
	 * @param  string $transaction_key  The account (transaction) key
	 * @return fFinancialTransaction
	 */
	public function __construct($gateway, $account_number, $transaction_key)
	{
		$valid_gateways = array('authorize_net', 'secure_pay');
		if (!in_array($gateway, $valid_gateways)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The payment gateway specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($gateway),
					join(', ', $valid_gateways)
				)
			);
		}
		
		$this->gateway = $gateway;
		$this->setAccountNumber($account_number);
		$this->setTransactionKey($transaction_key);
	}
	
	
	/**
	 * Sets up some default post fields for the gateway specified
	 * 
	 * @param  array $post_data  The array of field => value combinations that is actually going to be posted
	 * @return array  The $post_data with the gateway specific fields added
	 */
	private function addGatewaySpecificFields($post_data)
	{
		$post_data['x_version']        = '3.1';
		$post_data['x_delim_data']     = 'TRUE';
		$post_data['x_relay_response'] = 'FALSE';
		return $post_data;
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
	 * If test mode is enabled, modifies the transaction info accordingly
	 * 
	 * @return void
	 */
	private function handleTestMode()
	{
		if ($this->test_mode && $this->gateway == 'authorize_net') {
			$this->setGatewaySpecificField('x_test_request', 'TRUE');
			$this->setCreditCardNumber('4007000000027');
			$this->setCreditCardExpirationDate(date('m/Y', strtotime('+1 year')));
		}
	}
	
	
	/**
	 * Takes the transaction info, posts to the gateway and returns the result
	 * 
	 * @return string  The unparsed result from the gateway
	 */
	private function post()
	{
		$this->handleTestMode();
		$translated_transaction_info = $this->translateTransactionInfo();
		$translated_transaction_info = $this->addGatewaySpecificFields($translated_transaction_info);
		
		fCore::debug("Data being sent to gateway:\n" . print_r($translated_transaction_info, TRUE), $this->debug);
		
		$post_data = http_build_query($translated_transaction_info);
			
		if ($this->gateway == 'authorize_net') {
			$server = 'https://secure.authorize.net//gateway/transact.dll';
		} elseif ($this->gateway == 'secure_pay') {
			$server = 'https://www.securepay.com/AuthSpayAdapter/process.aspx';
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
		
		if ($this->gateway == 'secure_pay' && $this->test_mode) {
			$result  = '1,,1,,,,12345678';
		} else {
			// Suppress errors to handle the nasty message from php about IIS not properly terminating an SSL connection
			$result  = @trim(urldecode(file_get_contents($server, FALSE, $context)));
		}
		
		fCore::debug(
			fGrammar::compose(
				'Data received from gateway:%s',
				"\n" . $result
			),
			$this->debug
		);
		
		return $result;
	}
	
	
	/**
	 * Runs the transaction, will thrown an exception if something goes wrong
	 * 
	 * @throws  fValidationException
	 * 
	 * @return string  The transaction id returned by the gateway
	 */
	public function process()
	{
		$this->validate();
		
		$result = $this->post();
		$result_array = explode(',', $result);
		
		if ($result_array[0] == '2') {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The transaction was declined by the financial institution'
				)
			);
		}
		
		if ($result_array[0] == '3') {
			switch ($result_array[2]) {
				case '6':
				case '7':
				case '8':
					$message = fGrammar::compose('The credit card information entered is invalid');
					break;
				case '9':
				case '10':
					$message = fGrammar::compose('The bank account information entered is invalid');
					break;
				case '17':
					$message = fGrammar::compose('The type of credit card entered is not currently accepted');
					break;
				case '18':
					$message = fGrammar::compose('Electronic checks are not currently accepted');
					break;
				default:
					fCore::toss(
						'fConnectivityException',
						fGrammar::compose('There was an error processing the transaction')
					);
			}
			fCore::toss('fValidationException', $message);
		}
		
		return $result_array[6];
	}
	
	
	/**
	 * Sets the account number for the gateway
	 * 
	 * @param  string $account_number  The account number for the gateway
	 * @return void
	 */
	public function setAccountNumber($account_number)
	{
		$this->transaction_info['account_number'] = $account_number;
	}
	
	
	/**
	 * Sets the amount (including tax and shipping)
	 * 
	 * @param  float $amount  The total price
	 * @return void
	 */
	public function setAmount($amount)
	{
		$this->transaction_info['amount'] = $amount;
	}
	
	
	/**
	 * Sets the billing address
	 * 
	 * @param  string $billing_address  The address for billing
	 * @return void
	 */
	public function setBillingAddress($billing_address)
	{
		$this->transaction_info['billing_address'] = $billing_address;
	}
	
	
	/**
	 * Sets the billing city
	 * 
	 * @param  string $billing_city  The city for billing
	 * @return void
	 */
	public function setBillingCity($billing_city)
	{
		$this->transaction_info['billing_city'] = $billing_city;
	}
	
	
	/**
	 * Sets the billing company
	 * 
	 * @param  string $billing_company  The company for billing
	 * @return void
	 */
	public function setBillingCompany($billing_company)
	{
		$this->transaction_info['billing_company'] = $billing_company;
	}
	
	
	/**
	 * Sets the billing country
	 * 
	 * @param  string $billing_country  The country for billing
	 * @return void
	 */
	public function setBillingCountry($billing_country)
	{
		$this->transaction_info['billing_country'] = $billing_country;
	}
	
	
	/**
	 * Sets the billing fax number
	 * 
	 * @param  string $billing_fax_number  The fax number for billing
	 * @return void
	 */
	public function setBillingFaxNumber($billing_fax_number)
	{
		$this->transaction_info['billing_fax_number'] = $billing_fax_number;
	}
	
	
	/**
	 * Sets the billing first name
	 * 
	 * @param  string $billing_first_name  The first name for billing
	 * @return void
	 */
	public function setBillingFirstName($billing_first_name)
	{
		$this->transaction_info['billing_first_name'] = $billing_first_name;
	}
	
	
	/**
	 * Sets the billing last name
	 * 
	 * @param  string $billing_last_name  The last name for billing
	 * @return void
	 */
	public function setBillingLastName($billing_last_name)
	{
		$this->transaction_info['billing_last_name'] = $billing_last_name;
	}
	
	
	/**
	 * Sets the billing phone number
	 * 
	 * @param  string $billing_phone_number  The phone number for billing
	 * @return void
	 */
	public function setBillingPhoneNumber($billing_phone_number)
	{
		$this->transaction_info['billing_phone_number'] = $billing_phone_number;
	}
	
	
	/**
	 * Sets the billing state
	 * 
	 * @param  string $billing_state  The state for billing
	 * @return void
	 */
	public function setBillingState($billing_state)
	{
		$this->transaction_info['billing_state'] = $billing_state;
	}
	
	
	/**
	 * Sets the billing zip code
	 * 
	 * @param  string $billing_zip_code  The zip code for billing
	 * @return void
	 */
	public function setBillingZipCode($billing_zip_code)
	{
		$this->transaction_info['billing_zip_code'] = $billing_zip_code;
	}
	
	
	/**
	 * Sets the credit card cvv code
	 * 
	 * @param  string $credit_card_cvv_coode  The cvv code on the customer's credit card
	 * @return void
	 */
	public function setCreditCardCvvCode($credit_card_cvv_code)
	{
		$this->transaction_info['credit_card_cvv_code'] = $credit_card_cvv_code;
	}
	
	
	/**
	 * Sets the credit card expiration date
	 * 
	 * @param  string $credit_card_expiration_date  The customer's credit card number
	 * @return void
	 */
	public function setCreditCardExpirationDate($credit_card_expiration_date)
	{
		$this->transaction_info['credit_card_expiration_date'] = $credit_card_expiration_date;
	}
	
	
	/**
	 * Sets the credit card number
	 * 
	 * @param  string $credit_card_number  The customer's credit card number
	 * @return void
	 */
	public function setCreditCardNumber($credit_card_number)
	{
		$this->transaction_info['credit_card_number'] = str_replace(array(' ', '-'), '', $credit_card_number);
	}
	
	
	/**
	 * Sets the currency code
	 * 
	 * @param  string $currency_code  The currency code for the transaction
	 * @return void
	 */
	public function setCurrencyCode($currency_code)
	{
		$this->transaction_info['currency_code'] = $currency_code;
	}
	
	
	/**
	 * Sets the customer's email address
	 * 
	 * @param  string $customer_email  The customer's email address
	 * @return void
	 */
	public function setCustomerEmail($customer_email)
	{
		$this->transaction_info['customer_email'] = $customer_email;
	}
	
	
	/**
	 * Sets the customer id
	 * 
	 * @param  string $customer_id  If customer's identifier
	 * @return void
	 */
	public function setCustomerId($customer_id)
	{
		$this->transaction_info['customer_id'] = $customer_id;
	}
	
	
	/**
	 * Sets the customer ip address
	 * 
	 * @param  string $customer_ip_address  The customer's IP address
	 * @return void
	 */
	public function setCustomerIpAddress($customer_ip_address)
	{
		$this->transaction_info['customer_ip_address'] = $customer_ip_address;
	}
	
	
	/**
	 * Sets the default values for any fields that have not been manually assigned
	 * 
	 * @return void
	 */
	private function setDefaultValues()
	{
		if ($this->gateway == 'authorize_net') {
			$field_info =& $this->authorize_net_field_info;
		} elseif ($this->gateway == 'secure_pay') {
			$field_info =& $this->secure_pay_field_info;
		}
		
		foreach ($field_info as $field => $info) {
			if ($info['default'] !== NULL && !isset($this->transaction_info[$field])) {
				$this->transaction_info[$field] = $info['default'];
			}
		}
	}
	
	
	/**
	 * Sets the duty amount
	 * 
	 * @param  float $duty_amount  The total for duty
	 * @return void
	 */
	public function setDutyAmount($duty_amount)
	{
		$this->transaction_info['duty_amount'] = $duty_amount;
	}
	
	
	/**
	 * Adds a field => value pair to the transaction. Used for gateway-specific fields.
	 * 
	 * @param  string $field  The field name
	 * @param  mixed  $value  The gateway-formatted value
	 * @return void
	 */
	public function setGatewaySpecificField($field, $value)
	{
		$this->transaction_info[$field] = $value;
	}
	
	
	/**
	 * Sets the invoice description
	 * 
	 * @param  string $invoice_description  The invoice description
	 * @return void
	 */
	public function setInvoiceDescription($invoice_description)
	{
		$this->transaction_info['invoice_description'] = $invoice_description;
	}
	
	
	/**
	 * Sets the invoice number
	 * 
	 * @param  string $invoice_number  The invoice number
	 * @return void
	 */
	public function setInvoiceNumber($invoice_number)
	{
		$this->transaction_info['invoice_number'] = $invoice_number;
	}
					
	
	/**
	 * Sets the send customer email flag
	 * 
	 * @param  boolean $send_customer_email  If the customer should be emailed a confirmation of the transaction
	 * @return void
	 */
	public function setSendCustomerEmail($send_customer_email)
	{
		$this->transaction_info['send_customer_email'] = $send_customer_email;
	}
	
	
	/**
	 * Sets the shipping address
	 * 
	 * @param  string $shipping_address  The address for shipping
	 * @return void
	 */
	public function setShippingAddress($shipping_address)
	{
		$this->transaction_info['shipping_address'] = $shipping_address;
	}
	
	
	/**
	 * Sets the shipping amount
	 * 
	 * @param  float $shipping_amount  The total for shipping
	 * @return void
	 */
	public function setShippingAmount($shipping_amount)
	{
		$this->transaction_info['shipping_amount'] = $shipping_amount;
	}
	
	
	/**
	 * Sets the shipping city
	 * 
	 * @param  string $shipping_city  The city for shipping
	 * @return void
	 */
	public function setShippingCity($shipping_city)
	{
		$this->transaction_info['shipping_city'] = $shipping_city;
	}
	
	
	/**
	 * Sets the shipping company
	 * 
	 * @param  string $shipping_company  The company for shipping
	 * @return void
	 */
	public function setShippingCompany($shipping_company)
	{
		$this->transaction_info['shipping_company'] = $shipping_company;
	}
	
	
	/**
	 * Sets the shipping country
	 * 
	 * @param  string $shipping_country  The country for shipping
	 * @return void
	 */
	public function setShippingCountry($shipping_country)
	{
		$this->transaction_info['shipping_country'] = $shipping_country;
	}
	
	
	/**
	 * Sets the shipping first name
	 * 
	 * @param  string $shipping_first_name  The first name for shipping
	 * @return void
	 */
	public function setShippingFirstName($shipping_first_name)
	{
		$this->transaction_info['shipping_first_name'] = $shipping_first_name;
	}
	
	
	/**
	 * Sets the shipping last name
	 * 
	 * @param  string $shipping_last_name  The last name for shipping
	 * @return void
	 */
	public function setShippingLastName($shipping_last_name)
	{
		$this->transaction_info['shipping_last_name'] = $shipping_last_name;
	}
	
	
	/**
	 * Sets the shipping state
	 * 
	 * @param  string $shipping_state  The state for shipping
	 * @return void
	 */
	public function setShippingState($shipping_state)
	{
		$this->transaction_info['shipping_state'] = $shipping_state;
	}
	
	
	/**
	 * Sets the shipping zip code
	 * 
	 * @param  string $shipping_zip_code  The zip code for shipping
	 * @return void
	 */
	public function setShippingZipCode($shipping_zip_code)
	{
		$this->transaction_info['shipping_zip_code'] = $shipping_zip_code;
	}
	
	
	/**
	 * Sets the tax amount
	 * 
	 * @param  float $tax_amount  The total for taxes
	 * @return void
	 */
	public function setTaxAmount($tax_amount)
	{
		$this->transaction_info['tax_amount'] = $tax_amount;
	}
	
	
	/**
	 * Sets if the transaction is tax exempt
	 * 
	 * @param  boolean $tax_exempt  If the transaction is tax exempt
	 * @return void
	 */
	public function setTaxExempt($tax_exempt)
	{
		$this->transaction_info['tax_exempt'] = $tax_exempt;
	}
	
	
	/**
	 * Enters test mode, where transactions don't transfer money
	 * 
	 * @param  boolean $enable  If test mode should be enabled
	 * @return void
	 */
	public function setTestMode($enable)
	{
		$this->test_mode = (boolean) $enable;
	}
	
	
	/**
	 * Sets the transaction id
	 * 
	 * @param  string $transaction_id  The transaction indentifier
	 * @return void
	 */
	public function setTransactionId($transaction_id)
	{
		$this->transaction_info['transaction_id'] = $transaction_id;
	}
	
	
	/**
	 * Sets the transaction key for the gateway
	 * 
	 * @param  string $transaction_key  The transaction key for the gateway
	 * @return void
	 */
	public function setTransactionKey($transaction_key)
	{
		$this->transaction_info['transaction_key'] = $transaction_key;
	}
	
	
	/**
	 * Sets the transaction type
	 * 
	 * @param  string $transaction_type  The type of transaction being performed
	 * @return void
	 */
	public function setTransactionType($transaction_type)
	{
		$this->transaction_info['transaction_type'] = $transaction_type;
	}
	
	
	/**
	 * Returns the date specified as mm/yyyy
	 * 
	 * @param  string $date  The date to be standardized
	 * @return string  The standardized date or FALSE if there was an error
	 */
	private function standardizeDate($date)
	{
		if (!preg_match('#^(\d{1,2})(-|/)?(\d{2}|\d{4})$#', $date, $match)) {
			return FALSE;
		}
		
		if ($match[1] > 12 || $match[1] < 1) {
			return FALSE;
		}
		
		$month = (strlen($match[1]) == 1) ? '0' . $match[1] : $match[1];
		$year  = (strlen($match[3]) == 2) ? '20' . $match[3] : $match[3];
		
		if ($year < date('Y')) {
			return FALSE;
		}
		
		return $month . '/' . substr($year, 2);
	}
	
	
	/**
	 * Returns the number as
	 * 
	 * @param  string $value  The value to convert into a money format
	 * @return string  The standardized monetary value or FALSE if there was an error
	 */
	private function standardizeMoney($value)
	{
		if (is_string($value)) {
			$value = trim($value);
			if ($value[0] == '$') {
				$value = trim(substr($value, 1));
			}
		}
		if (!is_numeric($value)) {
			return FALSE;
		}
		
		return '$' . number_format($value, 2, '.', ',');
	}
	
	
	/**
	 * Translates the friendly field names into the gateway specific field names
	 * 
	 * @return array  The translated transaction info
	 */
	private function translateTransactionInfo()
	{
		$field_info =& $this->authorize_net_field_info;
		
		$translated_transaction_info = array();
		foreach ($this->transaction_info as $field => $value) {
			// Standardize values for know fields
			if (isset($field_info[$field])) {
				if ($field_info[$field]['type'] == 'date') {
					$value = $this->standardizeDate($value);
				} elseif ($field_info[$field]['type'] == 'money') {
					$value = $this->standardizeMoney($value);
				} elseif ($field_info[$field]['type'] == 'boolean') {
					$value = ($value) ? 'TRUE' : 'FALSE';
				}
				$translated_transaction_info[$field_info[$field]['field']] = $value;
					
			// Just pass on unknown fields
			} else {
				$translated_transaction_info[$field] = $value;
			}
		}
		
		return $translated_transaction_info;
	}
	
	
	/**
	 * Makes sure all of the required fields are entered, and that data types are correct
	 * 
	 * @throws  fValidationException
	 * 
	 * @return void
	 */
	public function validate()
	{
		if ($this->gateway == 'authorize_net') {
			$field_info =& $this->authorize_net_field_info;
		} elseif ($this->gateway == 'secure_pay') {
			$field_info =& $this->secure_pay_field_info;
		}
		
		$messages = array();
		
		$this->setDefaultValues();
		
		foreach ($field_info as $field => $info) {
			if ($info['required'] && !isset($this->transaction_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a value',
					fGrammar::humanize($field)
				);
			}
		}
		
		foreach ($this->transaction_info as $field => $value) {
			$info =& $field_info[$field];
			if (isset($info['valid_values']) && !in_array($this->transaction_info[$field], $info['valid_values'])) {
				$messages[] = fGrammar::compose(
					'%s: Please choose from one of the following: %s',
					fGrammar::humanize($field),
					join(', ', $info['valid_values'])
				);
				continue;
			}
			if ($info['type'] == 'string' && !is_string($this->transaction_info[$field]) && !is_numeric($this->transaction_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a string',
					fGrammar::humanize($field)
				);
				continue;
			}
			if (isset($info['max_length']) && strlen($this->transaction_info[$field]) > $info['max_length']) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a value no longer than %s characters',
					fGrammar::humanize($field),
					$info['max_length']
				);
				continue;
			}
			if ($info['type'] == 'date' && !$this->standardizeDate($this->transaction_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a month/year',
					fGrammar::humanize($field)
				);
				continue;
			}
			if ($info['type'] == 'money' && !$this->standardizeMoney($this->transaction_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a monetary value',
					fGrammar::humanize($field)
				);
				continue;
			}
			if ($info['type'] == 'boolean' && !is_bool($this->transaction_info[$field])) {
				$messages[] = fGrammar::compose(
					'%s: Please enter a boolean value',
					fGrammar::humanize($field)
				);
				continue;
			}
		}
		
		if ($messages) {
			fCore::toss('fValidationException', join("\n", $messages));
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