<?php

if (!defined('INCLUDED_AMEMBER_CONFIG'))
    die("Direct access to this location is not allowed");

global $config;
require_once($config['root_dir']."/plugins/payment/cc_core/cc_core.inc.php");

function safecharge3d_get_dump($var){
//dump of array
    $s = "";
    foreach ($var as $k=>$v)
        $s .= "$k => $v<br />\n";
    return $s;
}

class payment_safecharge3d extends payment {
    function do_payment($payment_id, $member_id, $product_id,
            $price, $begin_date, $expire_date, &$vars){
        return cc_core_do_payment('safecharge3d', $payment_id, $member_id, $product_id,
            $price, $begin_date, $expire_date, $vars);
    }
    function get_cancel_link($payment_id){
        global $db;
        return cc_core_get_cancel_link('safecharge3d', $payment_id);
    }
    function get_plugin_features(){
        return array(
            'title' => $config['payment']['safecharge3d']['title'] ? $config['payment']['safecharge3d']['title'] : _PLUG_PAY_SAFECHARGE_TITLE,
            'description' => $config['payment']['safecharge3d']['description'] ? $config['payment']['safecharge3d']['description'] : _PLUG_PAY_SAFECHARGE_DESC,
            'code' => 2,
            'type_options' => array(
		'Visa' => 'Visa', 'Master Card' => 'Master Card',
		'Switch / UK Maestro' => 'Switch / UK Maestro', 'Maestro' => 'Maestro', 'Solo' => 'Solo', 'Diners'=>'Diners'
		),
            'name_f' => 2,
            'phone' => 1,
	    'maestro_solo_switch' => 1,
            'currency' => array(
                'GBP' => 'Great Britain Pounds',
                'USD' => 'United States Dollar',
                'EUR' => 'Euro',
                'NIS' => 'New Israeli Shekel',
                'HKD' => 'Honk-Kong Dollar',
                'YEN' => 'Japanese Yen',
                'CAD' => 'Canadian Dollar',
                'AUD' => 'Australian Dollar',
                'NOK' => 'Norwegian Krone'
            )
        );
    }

    function parse_response($ret = ''){
	$res = array();
	$fields = array(
	    'ClientID',
	    'ClientLoginID',
	    'ClientUniqueID',
	    'TransactionID',
	    'Status',
	    'AuthCode',
	    'AVSCode',
	    'CVV2Reply',
	    'Reason',
	    'ErrCode',
	    'ExErrCode',
	    'CustomData',
        'ECI',
        'PaReq',
        'MerchantID',
        'ACSurl',
        'ThreeDReason'
	    );

	foreach ($fields as $field){
	    if (preg_match("/<".$field.">([^<]*)<\/".$field.">/i", $ret, $matches)){
		$res[$field] = $matches[1];
	    }
	}

	return $res;
    }

    function run_transaction($vars){
        global $db, $config;
        $payment_id = intval($vars['sg_CustomData']);

        foreach ($vars as $kk=>$vv){
            $v = urlencode($vv);
            $k = urlencode($kk);
            $vars1[] = "$k=$v";
        }
        $vars1 = join('&', $vars1);

        if ($this->config['testing'])
            $url = "https://test.safecharge.com/processtrans.asp";
        else
            $url = "https://process.safecharge.com/processtrans.asp";

	    $ret = cc_core_get_url($url, $vars1);

        $res = $this->parse_response($ret);

        $db->log_error("SafeCharge 3D RESPONSE:<br />".safecharge3d_get_dump($res));

        $return['RESULT']    = $res['Status']; // Approved, Success, Declined, Error, Pending
        $return['RESPMSG']   = preg_match("/Error/i", $res['Status']) ? "[" . $res['ErrCode'] . "][" . $res['ExErrCode'] . "] " . $res['Reason'] : '';
        $return['AVS']       = $res['AVSCode'];
        $return['PNREF']     = $res['AuthCode'];
        $return['CVV_VALID'] = $res['CVV2Reply'];
        $return['TRANSID']   = $res['TransactionID']; // A 64-bit unique integer generated by the Gateway in order to identify each transaction.

        if (!$res['ECI'] && $res['PaReq'] && $res['ACSurl']){        	/*
        	PaReq - As returned in SafeCharge transaction response
            TermUrl - The Issuer send the authentication response to the merchant page
            MD - MerchantID as returned in SafeCharge transaction response.
        	*/
            $payment = $db->get_payment($payment_id);
            $payment['data']['transaction_id'] = $res['TransactionID'];
            $db->update_payment($payment['payment_id'], $payment);

            $input_vars = & get_input_vars();
            $input_vars1 = array();
            foreach ($input_vars as $kk=>$vv){
                $v = urlencode($vv);
                $k = urlencode($kk);
                $input_vars1[] = "$k=$v";
            }
            $input_vars1 = join('&', $input_vars1);
            $url = $config['root_surl'] . "/plugins/payment/cc_core/cc.php?" . $input_vars1;

        	$vars = array(
        	    'PaReq'    => $res['PaReq'],
        	    'TermUrl'  => $url,
        	    'MD'       => $res['MerchantID']
        	    );

            $vars1 = array();            foreach ($vars as $kk=>$vv){
                $v = urlencode($vv);
                $k = urlencode($kk);
                $vars1[] = "$k=$v";
            }
            $vars1 = join('&', $vars1);
	    
	    $db->log_error("SafeCharge 3D REDIRECT:<br />".$res['ACSurl'] . "?" . $vars1);

            header("Location: " . $res['ACSurl'] . "?" . $vars1);
            exit;

        }
/*
ECI
This field contains the ECI (ECommerce Indicator) response. The values can be:
6,1 = The cardholder is not participating, but the attempt to authenticate was recorded.
7 = Usual payment process without liability shift.
If the ECI value is returned at this point it means that no PaReq/MerchantID,ACSurl will be returned.

PaReq - Payer Authentication Request. Must be included in the redirect form.
MerchantID - Will be returned in the redirection from the ACS back to the merchant. Must be up to 255 characters long.
ACSurl - URL of the ACS, to which the browser should be redirected.
ThreeDReason - A text description for the ECI values.
*/

        return $return;
    }
    function void_transaction($pnref, &$log, $transid, $vars, $cc){

	    srand(time());

	    $vars['sg_AuthCode'] = $pnref;
        $vars['sg_TransactionID'] = $transid;
        $vars['sg_TransType'] = 'Void';
	    $vars['sg_ClientUniqueID'] = $vars['sg_CustomData'] . '-' . rand(100, 999);

        $vars_l = $vars;

        $vars_l['sg_CardNumber'] = $cc;
        if ($vars['sg_CVV2'])
            $vars_l['sg_CVV2'] = preg_replace('/./', '*', $vars['sg_CVV2']);

        if ($vars['sg_ClientPassword'])
            $vars_l['sg_ClientPassword'] = preg_replace('/./', '*', $vars['sg_ClientPassword']);
        $log[] = $vars_l;

        $db->log_error("SafeCharge 3D DEBUG:<br />".safecharge3d_get_dump($vars_l));
	    $res = $this->run_transaction($vars);
        $log[] = $res;
        return $res;
    }
    /*************************************************************
      cc_bill - do real cc bill
    ***************************************************************/
    function cc_bill($cc_info, $member, $amount, $currency, $product_description, $charge_type, $invoice, $payment){

        global $db, $config, $plugin_config;

        $input_vars = & get_input_vars();

        $this_config   = $plugin_config['payment']['safecharge3d'];
        $product = $db->get_product($payment['product_id']);

        $log = array();
        //////////////////////// cc_bill /////////////////////////

        srand(time());
        $auth_type = 'Sale';
        if ($charge_type == CC_CHARGE_TYPE_TEST){
            $amount = "1.00";
            $auth_type = 'Auth';
        }

        if ($cc_info['cc_name_f'] == ''){
            $cc_info['cc_name_f'] = $member['name_f'];
            $cc_info['cc_name_l'] = $member['name_l'];
        }

        $vars = array(
            'sg_TransType'      => $auth_type,
            'sg_ClientLoginID'  => $this_config['login'],
            'sg_ClientPassword' => $this_config['password'],
            'sg_ClientUniqueID' => $payment['payment_id'] . '-' . rand(100, 999),
            'sg_CustomData'     => $payment['payment_id'],
            'sg_Amount'         => $amount,
            'sg_Currency'       => $currency ? $currency : 'GBP',
	        'sg_NameOnCard'	    => $cc_info['cc_name_f'] . " "  . $cc_info['cc_name_l'],
            'sg_CardNumber'     => $cc_info['cc_number'],
            'sg_CVV2'           => $cc_info['cc_code'],
            'sg_ExpMonth'       => substr($cc_info['cc-expire'], 0, 2),
            'sg_ExpYear'        => substr($cc_info['cc-expire'], 2, 2),
            'sg_ResponseFormat' => 4,
            'sg_ProductID'      => $product['title'],

            'sg_FirstName'      => $cc_info['cc_name_f'],
            'sg_LastName'       => $cc_info['cc_name_l'],
            'sg_Address'        => $cc_info['cc_street'],
            'sg_City'           => $cc_info['cc_city'],
            'sg_Zip'            => $cc_info['cc_zip'],
            'sg_Country'        => $cc_info['cc_country'],
            'sg_State'          => $cc_info['cc_state'],
            'sg_Phone'          => $cc_info['cc_phone'],
            'sg_IPAddress'      => $member['remote_addr']  ? $member['remote_addr'] : $_SERVER['REMOTE_ADDR'],
            'sg_Email'          => $member['email']
        );

        if ($charge_type != CC_CHARGE_TYPE_RECURRING && !$input_vars['PARes']){
            $vars['sg_TransType'] = 'Auth3D';
            $vars['sg_Version'] = '1.8.2';
        }

        if ($input_vars['PARes']){            $vars['sg_TransactionID'] = $payment['data']['transaction_id'];
            $vars['sg_PARes']         = $input_vars['PARes'];        }


    	if ($cc_info['cc_issuenum'])
    	    $vars['sg_DC_Issue'] = intval(substr($cc_info['cc_issuenum'], 0, 2));
    	if ($cc_info['cc_startdate']){
    	    $vars['sg_DC_StartMon'] = substr($cc_info['cc_startdate'], 0, 2);
    	    $vars['sg_DC_StartYear'] = substr($cc_info['cc_startdate'], 2, 2);
    	}


//        if ($cc_info['cc_type'])
//            $vars['sg_NameOnCard'] = $cc_info['cc_type'];

        // prepare log record
        $vars_l = $vars;

        $vars_l['sg_CardNumber'] = $cc_info['cc'];
        if ($vars['sg_CVV2'])
            $vars_l['sg_CVV2'] = preg_replace('/./', '*', $vars['sg_CVV2']);

        if ($vars['sg_ClientPassword'])
            $vars_l['sg_ClientPassword'] = preg_replace('/./', '*', $vars['sg_ClientPassword']);
        $log[] = $vars_l;
        /////
        $db->log_error("SafeCharge 3D DEBUG:<br />".safecharge3d_get_dump($vars_l));

        $res = $this->run_transaction($vars);
        $log[] = $res;

        if (preg_match("/Approved/i", $res['RESULT'])){
            if ($charge_type == CC_CHARGE_TYPE_TEST){
                //$this->void_transaction($res['PNREF'], $log, $res['TRANSID'], $vars, $cc_info['cc']);
	    }
            return array(CC_RESULT_SUCCESS, "", $res['PNREF'], $log);
        } elseif (preg_match("/Declined/i", $res['RESULT'])) {
            return array(CC_RESULT_DECLINE_PERM, ($res['RESPMSG'] ? $res['RESPMSG'] : $res['RESULT']), "", $log);
        } else {
            return array(CC_RESULT_INTERNAL_ERROR, ($res['RESPMSG'] ? $res['RESPMSG'] : $res['RESULT']), "", $log);
        }
    }
}

function safecharge3d_get_member_links($user){
    return cc_core_get_member_links('safecharge3d', $user);
}

function safecharge3d_rebill(){
    return cc_core_rebill('safecharge3d');
}

cc_core_init('safecharge3d');
?>