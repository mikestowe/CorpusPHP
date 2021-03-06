<?
//cache arrays, need to be here
$___Urls = false;

/**
* cu = Clean Up, reloads the page without any current GETs
*
* @todo Not very corpusy- although very useful
* @param string|bool $params
*/
function _cu($params = false) {
	global $_meta; //clean up, mostly for the admin
	redirect(href() . ($params ? '?' . $params : '') );
}

/**
* Redirects the users browser and prevents further code execution
*
* @param int|string $url if an id integer will redirrect to the seo url of the category. if a string redirrects to url
* @param int $code the http status code to return, eg: 301
* @param mixed $strict if true, will not attempt to full path the url
*/
function redirect($url, $code = false, $strict = false) {
	$url = href($url, false, $strict);
	if($code) {
		header('Location: ' . $url, TRUE, (int)$code);
	}else{
		header('Location: ' . $url);
	}
	
	die('A death occurred - Error in redirection... look over there!');
}

/**
* Sends a 204 header preventing the browser from loading the page, and optionally ends execution
* 
* @param bool $fatal
*/
function stopbrowser( $fatal = true ){
	header('HTTP/1.0 204 No Response', 204);
	if($fatal) { die('Browser was Stopped'); }
}

/**
* Creates a url, either from id or str
*
* @param int|string $url if an id integer will return the seo url of the category. if a string, the full path of the address
* @param bool $encode whether to encode the url
* @param bool $strict if true, will not attempt to full path the url
* @param bool|null $ssl enable/disable ssl, if null - auto
* @return string generated URL
*/
function href($url = false, $encode = true, $strict = false, $ssl = null) {
	return _::href($url, $encode, $strict, $ssl);
}

/**
* Damned simple Authorize.net Processor
*
* <code>
* $submit_data = array(
*	x_amount => (double)$pricing_data['price'],
*	x_card_num => $_POST['cc_number'],
*	x_exp_date => (int)$_POST['cc_expir_month'] . substr( (int)$_POST['cc_expir_year'] , -2),
*	x_card_code => $_POST['ccv'],
*	x_cust_id => (int)$login->user_id,
*	x_invoice_num => $new_order_id,  //we need to calculate this
*	x_first_name => $_POST['fname'],
*	x_last_name => $_POST['lname'],
*	x_company => '',
*	x_address => $_POST['address'] . ', ' . $_POST['address2'],
*	x_city => $_POST['city'],
*	x_state => $_POST['state'],
*	x_zip => $_POST['zip'],
*	x_country => 'USA', //may want to change if we do more
*	x_phone => '',
*	x_email => $login->email,
*	x_ship_to_first_name => $_POST['fname'],
*	x_ship_to_last_name => $_POST['lname'],
*	x_ship_to_address => $_POST['address'] . ', ' . $_POST['address2'],
*	x_ship_to_city => $_POST['city'],
*	x_ship_to_state => $_POST['state'],
*	x_ship_to_zip => $_POST['zip'],
*	x_ship_to_country => (!is_array($order->delivery['country'])) ? $order->delivery['country'] : $order->delivery['country']['title'],
*	x_description => $description,
* );
* AuthNetProcess( $submit_data );
* </code>
* @todo merge into Creditcard class gracefully
* @param array $ProcessData array of data to send to
* @param array $rr response array return by reference
* @param MessageStack $ms An optional message stack to use for errors
* @return array|bool array with success code on success, false on failure
*/
function AuthNetProcess( $ProcessData, &$rr = false, &$ms = false ) {
	
	$StdData = array(
		x_login => AUTHORIZENET_AIM_LOGIN, // The login name as assigned to you by authorize.net
		x_tran_key => AUTHORIZENET_AIM_TXNKEY,  // The Transaction Key (16 digits) is generated through the merchant interface
		x_relay_response => 'FALSE', // AIM uses direct response, not relay response
		x_delim_data => 'TRUE', // The default delimiter is a comma
		x_version => '3.1',  // 3.1 is required to use CVV codes
		x_type => AUTHORIZENET_AIM_AUTHORIZATION_TYPE,
		x_method => 'CC', //MODULE_PAYMENT_AUTHORIZENET_AIM_METHOD == 'Credit Card' ? 'CC' : 'ECHECK',
		x_trans_id => (isset($authnet_trans_id)) ? $authnet_trans_id : '',
		x_email_customer => 'FALSE',
		x_email_merchant => 'FALSE',
		// Merchant defined variables go here
		Date => date('r'),
		IP => $_SERVER['REMOTE_ADDR'],
		Session => session_id(),
		x_test_request => 'FALSE',
	);

	$submit_data = array_merge( $StdData, $ProcessData );

	$data = http_build_query($submit_data);

	$ch=curl_init();

	curl_setopt($ch, CURLOPT_URL, AUTHORIZENET_AIM_GATEWAY);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //set to work on windows/iis/osx
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$authorize = curl_exec($ch);
	curl_close ($ch);

	$response=split('\,', $authorize);

	// Parse the response code and text for custom error display
	$response_code=explode(',', $response[0]);
	$response_reason_code=$response[2];
	$response_text=explode(',', $response[3]);
	$x_response_code=$response_code[0];
	$x_response_text=$response_text[0];
	$response_approval_code=$response[4];

	db::perform( 'logging', array(
		'logtime' => array( true, 'now()' ),
		'type' => 'AuthNetTransaction',
		'ip' => $_SERVER['REMOTE_ADDR'],
		'request' => json_encode($_POST),
		'data' => json_encode($submit_data),
		'data2' => $data,
		'data3' => $authorize,
	) );
	
	$rr = $response;

	if ($x_response_code != '1') {
		//error
		if( $ms instanceof MessageStack ) { 
			$ms->add( $response_reason_code . ' - ' . $x_response_text, true );
		}

		return false;
	}else{
		//we're golden
		return array(
			'authnet_trans_id' => $response[6], //other data too at some point?
		);
	}

}

/**
* Serialize the current $_GET back to a query string, excluding anything in the excluded array
* 
* @todo needs a better name, eg: getvars() or something
*
* @param array $exclude_array array of get variables to be excluded
* @return string http query string
*/
function getvarsSerializer($exclude_array = false, $strict = false) {
	if( !is_array($exclude_array) ) { $exclude_array = func_get_args(); }
	$exclude_array[] = 'url';
	$gets = array();
	foreach($_GET as $key => $value) {
		if( !in_array($key, $exclude_array)	) {
			$gets[$key] = $value;
		}
	}
	return http_build_query($gets);
}

/**
* Specialized "Not Empty" function
* 
* @todo merge firstNotEmpty into this
* @param mixed $s
*/
function nempty($s) { return (strlen(trim( (string)$s) ) > 0); }

/**
* @todo Determine if there is need for these
*/
function valOrFalseIfEmpty( $val ) { return !nempty($val) ? false : $val; }

function valOrFalseIfNumeric( $val ) { return is_numeric($val) ? false : $val; }

/**
* From a list of paramaters returns the first which is not empty
* 
* @todo merge into nempty as anything that isn't empty returns truthy
* @param mixed ... a variable list of paramaters
* @return mixed
*/
function firstNotEmpty() { //takes any number of paramters
	for ($i = 0;$i < func_num_args();$i++) {
		$x = func_get_arg($i);
		if( nempty($x) ) {
			return $x;
		}
	}
	return null;
}

function generateRandomValue($length, $type = 'mixed') {
	if ( ($type != 'mixed') && ($type != 'chars') && ($type != 'digits')) return false;

	$rand_value = '';
	while (strlen($rand_value) < $length) {
		if ($type == 'digits') {
			$char = rand(0,9);
		} else {
			$char = chr(rand(0,255));
		}
		if ($type == 'mixed') {
			if (eregi('^[a-z0-9]$', $char)) $rand_value .= $char;
		} elseif ($type == 'chars') {
			if (eregi('^[a-z]$', $char)) $rand_value .= $char;
		} elseif ($type == 'digits') {
			if (ereg('^[0-9]$', $char)) $rand_value .= $char;
		}
	}

	return strtolower($rand_value);
}

/**
* UTF-8 Wrapper for htmlspecialchars
*
* @param string $string
* @param constant $quote_style
* @param string $charset
* @return string entitalized string
*/
function htmlS($string, $quote_style = ENT_COMPAT, $charset = "UTF-8" ) {
	return htmlspecialchars($string, $quote_style, $charset );
}

/**
* UTF-8 Wrapper for htmlentities supporting arrays as well as strings
*
* @param string|array $content
* @param constant $quote_style
* @param string $charset
* @return string|array entitalized string
*/
function htmlE( $content, $quote_style = ENT_COMPAT, $charset = "UTF-8" ) {
	if( is_array($content) ) {
		foreach($content as &$v) {
			$v = htmlE($v, $quote_style, $charset);
		}
		return $content;
	}else{
		return htmlentities($content, $quote_style, $charset);
	}
}

/**
* Implode with a glue, sep+glue, peice, sep+glue, peice, sep+glue, post pattern, useful for SQL
*
* @param string $glue
* @param array $pieces
* @param string $sep
* @param string $post
* @return string
*/
function implodePre( $glue, $pieces, $sep = '', $post = '' ) {
	return $glue . implode($sep . $glue, $pieces) . $post;
}

/**
* Replaces html / xhtml br tags with actual line breaks
*
* @param string $str
* @return string
*/
function br2nl($str){
	$str = preg_replace('/\<br\s*\/?\>/i', " \n", $str);
	return $str;
}

function dateSplit( $date, &$day = false, &$month = false, &$year = false, &$hour = false, &$minute = false, &$second = false ) {
	if( is_string( $date ) ) { $date = strtotime( $date ); }
	if( $date != 0 ) { //very little chance
		$day = date( 'j', $date );
		$month = date( 'F', $date );
		$year = date( 'Y', $date );
	}
	/**
	*@todo add remaining logic
	*/
}