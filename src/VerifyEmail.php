<?php
  namespace hbattat;
  use \DOMDocument;
  use \DOMXpath;
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
    private $debug = [];
    private $debug_raw = [];

    private $_yahoo_signup_page_url = 'https://login.yahoo.com/account/create?specId=yidReg&lang=en-US&src=&done=https%3A%2F%2Fwww.yahoo.com&display=login';
    private $_yahoo_signup_ajax_url = 'https://login.yahoo.com/account/module/create?validateField=yid';
    private $yahoo_signup_page_content;
    private $yahoo_signup_page_headers;

    public function __construct($email = null, $verifier_email = null, $port = 25){
      if(!is_null($email) && !is_null($verifier_email)) {
        $this->add_debug_message( sprintf("Initialized with Email: %s, Verifier Email: %s, Port: %s", $email , $verifier_email , $port ) );
        $this->set_email($email);
        $this->set_verifier_email($verifier_email);
      }
      else {
        $this->add_debug_message('Initialized with no email or verifier email values');
      }
      $this->set_port($port);
    }

	protected function add_debug_message( $message ){
		$this->debug[] = $message ;
  	}

    public function set_verifier_email($email) {
      $this->verifier_email = $email;
      $this->add_debug_message('Verifier Email was set to '.$email);
    }

    public function get_verifier_email() {
      return $this->verifier_email;
    }


    public function set_email($email) {
      $this->email = $email;
		$this->add_debug_message('Email was set to '.$email);
    }

    public function get_email() {
      return $this->email;
    }

    public function set_port($port) {
      $this->port = $port;
		$this->add_debug_message('Port was set to '.$port);
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
      if(strtolower($domain) == 'yahoo.com') {
        $is_valid = $this->validate_yahoo();
      }
      //otherwise check the normal way
      else {
        //find mx
        $this->add_debug_message('Finding MX record...');
        $this->find_mx();

        if(!$this->mx) {
          $this->add_debug_message( 'No MX record was found.');
          $this->add_error('100', 'No suitable MX records found.');
          return $is_valid;
        }
        else {
          $this->add_debug_message('Found MX: '.$this->mx);
        }


        $this->add_debug_message( 'Connecting to the server...' );
        $this->connect_mx();

        if(!$this->connect) {
          $this->add_debug_message( 'Connection to server failed.' );
          $this->add_error('110', 'Could not connect to the server.');
          return $is_valid;
        }
        else {
          $this->add_debug_message( 'Connection to server was successful.');
        }


        $this->add_debug_message('Starting veriffication...');
        if(preg_match("/^220/i", $out = fgets($this->connect))){
          $this->add_debug_message('Got a 220 response. Sending HELO...');
          fputs ($this->connect , "HELO ".$this->get_domain($this->verifier_email)."\r\n");
          $out = fgets ($this->connect);
          $this->debug_raw['helo'] = $out;
          $this->add_debug_message( 'Response: '.$out);

          $this->add_debug_message('Sending MAIL FROM...');
          fputs ($this->connect , "MAIL FROM: <".$this->verifier_email.">\r\n");
          $from = fgets ($this->connect);
          $this->debug_raw['mail_from'] = $from;
          $this->add_debug_message('Response: '.$from);

          $this->add_debug_message( 'Sending RCPT TO...' );
          fputs ($this->connect , "RCPT TO: <".$this->email.">\r\n");
          $to = fgets ($this->connect);
          $this->debug_raw['rcpt_to'] = $to;
          $this->add_debug_message('Response: '.$to);

          $this->add_debug_message('Sending QUIT...');
          $quit = fputs ($this->connect , "QUIT");
          $this->debug_raw['quit'] = $quit;
          fclose($this->connect);

          $this->add_debug_message('Looking for 250 response...');
          if(!preg_match("/^250/i", $from) || !preg_match("/^250/i", $to)){
            $this->add_debug_message('Not found! Email is invalid.');
            $is_valid = false;
          }
          else{
            $this->add_debug_message('Found! Email is valid.');
            $is_valid = true;
          }
        }
        else {
          $this->add_debug_message('Encountered an unknown response code.');
        }
      }

      return $is_valid;
    }

    private function get_domain($email) {
      $email_arr = explode("@", $email);
      $domain = array_slice($email_arr, -1);
      return $domain[0];
    }
    private function find_mx() {
      $domain = $this->get_domain($this->email);
      $mx_ip = false;
      // Trim [ and ] from beginning and end of domain string, respectively
      $domain = ltrim($domain, "[");
      $domain = rtrim($domain, "]");

      if( "IPv6:" == substr($domain, 0, strlen("IPv6:")) ) {
        $domain = substr($domain, strlen("IPv6") + 1);
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

    private function add_error($code, $msg) {
      $this->errors[] = array('code' => $code, 'message' => $msg);
    }

    private function clear_errors() {
      $this->errors = array();
    }

    private function validate_yahoo() {
      $this->add_debug_message('Validating a yahoo email address...');
      $this->add_debug_message('Getting the sign up page content...');
      $this->fetch_yahoo_signup_page();

      $cookies = $this->get_yahoo_cookies();
      $fields = $this->get_yahoo_fields();

      $this->add_debug_message('Adding the email to fields...');
      $fields['yid'] = str_replace('@yahoo.com', '', strtolower($this->email));
      
      $this->add_debug_message('Ready to submit the POST request to validate the email.');

      $response = $this->request_yahoo_ajax($cookies, $fields);
      
      $this->add_debug_message('Parsing the response...');
      $response_errors = json_decode($response, true)['errors'];

      $this->add_debug_message('Searching errors for exisiting username error...');
      foreach($response_errors as $err){
        if($err['name'] == 'yid' && $err['error'] == 'IDENTIFIER_EXISTS'){
          $this->add_debug_message('Found an error about exisiting email.');
          return true;
        }
      }
      return false;
    }

    private function fetch_yahoo_signup_page(){
      $this->yahoo_signup_page_content = file_get_contents($this->_yahoo_signup_page_url);
      if($this->yahoo_signup_page_content === false){
        $this->add_debug_message('Could not read the sign up page.');
        $this->add_error('200', 'Cannot not load the sign up page.');
      }
      else{
        $this->add_debug_message('Sign up page content stored.');
        $this->add_debug_message('Getting headers...');
        $this->yahoo_signup_page_headers = $http_response_header;
        $this->add_debug_message('Sign up page headers stored.');
      }
    }

    private function get_yahoo_cookies(){
      $this->add_debug_message('Attempting to get the cookies from the sign up page...');
      if($this->yahoo_signup_page_content !== false){
        $this->add_debug_message('Extracting cookies from headers...');
        $cookies = array();
        foreach ($this->yahoo_signup_page_headers as $hdr) {
          if (preg_match('/^Set-Cookie:\s*(.*?;).*?$/', $hdr, $matches)) {
            $cookies[] = $matches[1];
          }
        }

        if(count($cookies) > 0){
          $this->add_debug_message('Cookies found: '.implode(' ', $cookies));
          return $cookies;
        }
        else{
          $this->add_debug_message('Could not find any cookies.');
        }
      }

      return false;
    }

    private function get_yahoo_fields(){
      $dom = new DOMDocument();
      $fields = array();
      if(@$dom->loadHTML($this->yahoo_signup_page_content)){
        $this->add_debug_message('Parsing the page for input fields...');
        $xp = new DOMXpath($dom);
        $nodes = $xp->query('//input');
        foreach($nodes as $node){
          $fields[$node->getAttribute('name')] = $node->getAttribute('value');
        }

        $this->add_debug_message('Extracted fields.');
      }
      else{
        $this->add_debug_message('Something is worng with the page HTML.');
        $this->add_error('210', 'Could not load the dom HTML.');
      }
      return $fields;
    }

    private function request_yahoo_ajax($cookies, $fields){
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

      return $result;
    }

  }
