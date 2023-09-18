<?php
  namespace hbattat;
  use \DOMDocument;
  use \DOMXpath;
use Exception;

  /**
   *  Verifies email address by attempting to connect and check with the mail server of that account
   *
   *  Author: Sam Battat hbattat@msn.com
   *          http://github.com/hbattat
   *
   *  License: This code is released under the MIT Open Source License. (Feel free to do whatever)
   *
   *  Last update: Oct 11 2016
   *
   * @package VerifyEmail
   * @author  Husam (Sam) Battat <hbattat@msn.com>
   * This is a test message for packagist
   */

  class VerifyEmail {
    public $email;
    public $verifier_email;
    public $port;
    private $mx;
    private $connect;
    private $errors;
    private $debug;
    private $debug_raw;

    private $_yahoo_signup_page_url = 'https://login.yahoo.com/account/create?specId=yidReg&lang=en-US&src=&done=https%3A%2F%2Fwww.yahoo.com&display=login';
    private $_yahoo_signup_ajax_url = 'https://login.yahoo.com/account/module/create?validateField=yid';
    private $_yahoo_domains = array('yahoo.com');
    private $_hotmail_signin_page_url = 'https://login.live.com/';
    private $_hotmail_username_check_url = 'https://login.live.com/GetCredentialType.srf?wa=wsignin1.0';
    private $_hotmail_domains = array('hotmail.com', 'live.com', 'outlook.com', 'msn.com');
    private $page_content;
    private $page_headers;

    public function __construct($email = null, $verifier_email = null, $port = 25){
      $this->debug = array();
      $this->debug_raw = array();
      if(!is_null($email) && !is_null($verifier_email)) {
        $this->debug[] = 'Initialized with Email: '.$email.', Verifier Email: '.$verifier_email.', Port: '.$port;
        $this->set_email($email);
        $this->set_verifier_email($verifier_email);
      }
      else {
        $this->debug[] = 'Initialized with no email or verifier email values';
      }
      $this->set_port($port);
    }


    public function set_verifier_email($email) {
      $this->verifier_email = $email;
      $this->debug[] = 'Verifier Email was set to '.$email;
    }

    public function get_verifier_email() {
      return $this->verifier_email;
    }


    public function set_email($email) {
      $this->email = $email;
      $this->debug[] = 'Email was set to '.$email;
    }

    public function get_email() {
      return $this->email;
    }

    public function set_port($port) {
      $this->port = $port;
      $this->debug[] = 'Port was set to '.$port;
    }

    public function get_port() {
      return $this->port;
    }

    public function get_errors(){
      return array('errors' => $this->errors);
    }

    public function get_debug($raw = false) {
      if($raw) {
        return $this->debug_raw;
      }
      else {
        return $this->debug;
      }
    }

    public function verify() {
      $this->debug[] = 'Verify function was called.';

      $is_valid = false;

      //check if this is a yahoo email
      $domain = $this->get_domain($this->email);
      // if(in_array(strtolower($domain), $this->_yahoo_domains)) {
      //   $is_valid = $this->validate_yahoo();
      // }
      // else if(in_array(strtolower($domain), $this->_hotmail_domains)){
      //   $is_valid = $this->validate_hotmail();
      // }
      //otherwise check the normal way
      //else {
        //find mx
        $this->debug[] = 'Finding MX record...';
        $this->find_mx();

        if(!$this->mx) {
          $this->debug[] = 'No MX record was found.';
          $this->add_error('100', '', 'No suitable MX records found.');
          return $is_valid;
        }
        else {
          $this->debug[] = 'Found MX: '.$this->mx;
        }


        $this->debug[] = 'Connecting to the server...';
        $this->connect_mx();

        if(!$this->connect) {
          $this->debug[] = 'Connection to server failed.';
          $this->add_error('110', '', 'Could not connect to the server.');
          return $is_valid;
        }
        else {
          $this->debug[] = 'Connection to server was successful.';
        }


        $this->debug[] = 'Starting veriffication...';
        if(preg_match("/^220/i", $out = fgets($this->connect))){
          $this->debug[] = 'Got a 220 response. Sending HELO...';
          fputs ($this->connect , "HELO ".$this->get_domain($this->verifier_email)."\r\n");
          $out = fgets ($this->connect);
          $this->debug_raw['helo'] = $out;
          $this->debug[] = 'Response: '.$out;
          if(!preg_match("/^250/i", $out)){
            if(!preg_match("/^250/i", $out)){
              preg_match('!\d+!', $out, $matches);
              preg_match('/\d+\.\d+\.\d+/', $out, $sMatches);
              $reply_code = isset($sMatches[0]) ? $sMatches[0] : '';
              $this->add_error($matches[0], $reply_code, $out);
            }
            $this->debug[] = 'Not found! Email is invalid.';
            $is_valid = false;
            return false;
          }

          $this->debug[] = 'Sending MAIL FROM...';
          try {
            fputs ($this->connect , "MAIL FROM: <".$this->verifier_email.">\r\n");
          } catch(Exception $e) {
            $errorMessage = $e->getMessage();
            if ($errorMessage !== null && strpos($errorMessage, 'Broken pipe') !== false) {
                // Handle the broken pipe error here
                $this->debug_raw['mail_from'] = $errorMessage;
                $this->debug[] = 'Response: '. $errorMessage;
                $is_valid = false;
            } 
            return false;
          }
          $from = fgets ($this->connect);
          $this->debug_raw['mail_from'] = $from;
          $this->debug[] = 'Response: '.$from;

          $this->debug[] = 'Sending RCPT TO...';
          fputs ($this->connect , "RCPT TO: <".$this->email.">\r\n");
          $to = fgets ($this->connect);
          $this->debug_raw['rcpt_to'] = $to;
          $this->debug[] = 'Response: '.$to;

          $this->debug[] = 'Sending QUIT...';
          $quit = fputs ($this->connect , "QUIT");
          $this->debug_raw['quit'] = $quit;
          fclose($this->connect);

          $this->debug[] = 'Looking for 250 response...';
          if(!preg_match("/^250/i", $from) || !preg_match("/^250/i", $to)){
            if(!preg_match("/^250/i", $from)){
              preg_match('!\d+!', $from, $matches);
              preg_match('/\d+\.\d+\.\d+/', $from, $sMatches);
              $reply_code = isset($sMatches[0]) ? $sMatches[0] : '';
              $this->add_error($matches[0], $reply_code, $from);
            }
            if(!preg_match("/^250/i", $to)){
              preg_match('!\d+!', $to, $matches);
              preg_match('/\d+\.\d+\.\d+/', $to, $sMatches);
              $reply_code = isset($sMatches[0]) ? $sMatches[0] : '';
              $this->add_error($matches[0], $reply_code, $to);
            }
            $this->debug[] = 'Not found! Email is invalid.';
            $is_valid = false;
          }
          else{
            $this->debug[] = 'Found! Email is valid.';
            $is_valid = true;
          }
        }
        else {
          $this->debug[] = 'Encountered an unknown response code.';
        }
      //}

      return $is_valid;
    }

    private function get_domain($email) {
      $email_arr = explode('@', $email);
      $domain = array_slice($email_arr, -1);
      return $domain[0];
    }
    private function find_mx() {
      $domain = $this->get_domain($this->email);
      $mx_ip = false;
      // Trim [ and ] from beginning and end of domain string, respectively
      $domain = ltrim($domain, '[');
      $domain = rtrim($domain, ']');

      if( 'IPv6:' == substr($domain, 0, strlen('IPv6:')) ) {
        $domain = substr($domain, strlen('IPv6') + 1);
      }

      $mxhosts = array();
      if( filter_var($domain, FILTER_VALIDATE_IP) ) {
        $mx_ip = $domain;
      }
      else {
        getmxrr($domain, $mxhosts, $mxweight);
      }

      if(!empty($mxhosts) ) {
        $mx_ip = $mxhosts[array_search(min($mxweight), $mxweight)];
      }
      else {
        if( filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) {
          $record_a = dns_get_record($domain, DNS_A);
        }
        elseif( filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {
          $record_a = dns_get_record($domain, DNS_AAAA);
        }

        if( !empty($record_a) ) {
          $mx_ip = $record_a[0]['ip'];
        }

      }

      $this->mx = $mx_ip;
    }


    private function connect_mx() {
      //connect
      $this->connect = @fsockopen($this->mx, $this->port);
    }

    private function add_error($code, $reply_code, $msg) {
      $this->errors[] = array('code' => $code, 'reply_code' => $reply_code, 'message' => $msg);
    }

    private function clear_errors() {
      $this->errors = array();
    }

    private function validate_yahoo() {
      $this->debug[] = 'Validating a yahoo email address...';
      $this->debug[] = 'Getting the sign up page content...';
      $this->fetch_page('yahoo');

      $cookies = $this->get_cookies();
      $fields = $this->get_fields();

      $this->debug[] = 'Adding the email to fields...';
      $fields['yid'] = str_replace('@yahoo.com', '', strtolower($this->email));
      
      $this->debug[] = 'Ready to submit the POST request to validate the email.';

      $response = $this->request_validation('yahoo', $cookies, $fields);
      $response = json_decode($response, true);
      if (empty($response['errors'])) {
        return true;
      }
      
      $this->debug[] = 'Parsing the response...';
      $response_errors = is_array($response['errors']) ? $response['errors'] : [];

      $this->debug[] = 'Searching errors for exisiting username error...';
      foreach($response_errors as $err){
        if($err['name'] == 'yid' && $err['error'] == 'IDENTIFIER_EXISTS'){
          $this->debug[] = 'Found an error about exisiting email.';
          return true;
        }
      }
      return false;
    }

    private function validate_hotmail() {
      $this->debug[] = 'Validating a hotmail email address...';
      $this->debug[] = 'Getting the sign up page content...';
      $this->fetch_page('hotmail');

      $cookies = $this->get_cookies();

      $this->debug[] = 'Sending another request to get the needed cookies for validation...';
      $this->fetch_page('hotmail', implode(' ', $cookies));
      $cookies = $this->get_cookies();

      $this->debug[] = 'Preparing fields...';
      $fields = $this->prep_hotmail_fields($cookies);

      $this->debug[] = 'Ready to submit the POST request to validate the email.';
      $response = $this->request_validation('hotmail', $cookies, $fields);

      $this->debug[] = 'Searching username error...';
      $json_response = json_decode($response, true);
      $this->debug[] = print_r($json_response, true);

      if(!empty($json_response['IfExistsResult'])){
        return true;
      }
      return false;
    }

    private function fetch_page($service, $cookies = ''){
      if($cookies){
        $opts = array(
          'http'=>array(
            'method'=>"GET",
            'header'=>"Accept-language: en\r\n" .
                      "Cookie: ".$cookies."\r\n"
          )
        );
        $context = stream_context_create($opts);
      }
      if($service == 'yahoo'){
        if($cookies){
          $this->page_content = file_get_contents($this->_yahoo_signup_page_url, false, $context);
        }
        else{
          $this->page_content = file_get_contents($this->_yahoo_signup_page_url);
        }
      }
      else if($service == 'hotmail'){
        if($cookies){
          $this->page_content = file_get_contents($this->_hotmail_signin_page_url, false, $context);
        }
        else{
          $this->page_content = file_get_contents($this->_hotmail_signin_page_url);
        }
      }

      if($this->page_content === false){
        $this->debug[] = 'Could not read the sign up page.';
        $this->add_error('200', '', 'Cannot not load the sign up page.');
      }
      else{
        $this->debug[] = 'Sign up page content stored.';
        $this->debug[] = 'Getting headers...';
        $this->page_headers = $http_response_header;
        $this->debug[] = 'Sign up page headers stored.';
      }
    }

    private function get_cookies(){
      $this->debug[] = 'Attempting to get the cookies from the sign up page...';
      if($this->page_content !== false){
        $this->debug[] = 'Extracting cookies from headers...';
        $cookies = array();
        foreach ($this->page_headers as $hdr) {
          if (preg_match('/^Set-Cookie:\s*(.*?;).*?$/i', $hdr, $matches)) {
            $cookies[] = $matches[1];
          }
        }

        if(count($cookies) > 0){
          $this->debug[] = 'Cookies found: '.implode(' ', $cookies);
          return $cookies;
        }
        else{
          $this->debug[] = 'Could not find any cookies.';
        }
      }
      return false;
    }

    private function get_fields(){
      $dom = new DOMDocument();
      $fields = array();
      if(@$dom->loadHTML($this->page_content)){
        $this->debug[] = 'Parsing the page for input fields...';
        $xp = new DOMXpath($dom);
        $nodes = $xp->query('//input');
        foreach($nodes as $node){
          $fields[$node->getAttribute('name')] = $node->getAttribute('value');
        }

        $this->debug[] = 'Extracted fields.';
      }
      else{
        $this->debug[] = 'Something is worng with the page HTML.';
        $this->add_error('210', '', 'Could not load the dom HTML.');
      }
      return $fields;
    }

    private function request_validation($service, $cookies, $fields){
      if($service == 'yahoo'){
        $headers = array();
        $headers[] = 'Origin: https://login.yahoo.com';
        $headers[] = 'X-Requested-With: XMLHttpRequest';
        $headers[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36';
        $headers[] = 'content-type: application/x-www-form-urlencoded; charset=UTF-8';
        $headers[] = 'Accept: */*';
        $headers[] = 'Referer: https://login.yahoo.com/account/create?specId=yidReg&lang=en-US&src=&done=https%3A%2F%2Fwww.yahoo.com&display=login';
        $headers[] = 'Accept-Encoding: gzip, deflate, br';
        $headers[] = 'Accept-Language: en-US,en;q=0.8,ar;q=0.6';
      
        $cookies_str = implode(' ', $cookies);
        $headers[] = 'Cookie: '.$cookies_str;


        $postdata = http_build_query($fields);

        $opts = array('http' =>
          array(
            'method'  => 'POST',
            'header'  => $headers,
            'content' => $postdata
          )
        );

        $context  = stream_context_create($opts);
        $result = file_get_contents($this->_yahoo_signup_ajax_url, false, $context);
      }
      else if($service == 'hotmail'){
        $headers = array();
        $headers[] = 'Origin: https://login.live.com';
        $headers[] = 'hpgid: 33';
        $headers[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36';
        $headers[] = 'Content-type: application/json; charset=UTF-8';
        $headers[] = 'Accept: application/json';
        $headers[] = 'Referer: https://login.live.com';
        $headers[] = 'Accept-Encoding: gzip, deflate, br';
        $headers[] = 'Accept-Language: en-US,en;q=0.8,ar;q=0.6';

        $cookies_str = implode(' ', $cookies);
        $headers[] = 'Cookie: '.$cookies_str;

        $postdata = json_encode($fields);

        $opts = array('http' =>
          array(
            'method'  => 'POST',
            'header'  => $headers,
            'content' => $postdata
          )
        );
        $this->debug[] = print_r($opts, true);
        $context  = stream_context_create($opts);
        $result = file_get_contents($this->_hotmail_username_check_url, false, $context);
      }
      return $result;
    }

    private function prep_hotmail_fields($cookies){
      $fields = array();
      foreach($cookies as $cookie){
        list($key, $val) = explode('=', $cookie, 2);
        if($key == 'uaid'){
          $fields['uaid'] = $val;
          break;
        }
      }
      $fields['username'] = strtolower($this->email);
      return $fields;
    }

  }
