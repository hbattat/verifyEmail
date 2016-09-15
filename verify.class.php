<?php
  namespace VE;
  /**
   *  Verifies email address by attempting to connect and check with the mail server of that account
   *
   *  Author: Sam Battat hbattat@msn.com
   *          http://github.com/hbattat
   *
   *  License: This code is released under the MIT Open Source License. (Feel free to do whatever)
   *
   *  Last update: Jul 09 2016
   *
   * @package VerifyEmail
   * @author  Husam (Sam) Battat <hbattat@msn.com>
   */

  class VerifyEmail {
    public $email;
    public $verifier_email;
    public $port;
    private $mx;
    private $connect;
    private $errors;
    private $debug;


    public function __construct($email, $verifier_email, $port = 25){
      $this->email = $email;
      $this->verifier_email = $verifier_email;
      $this->port = $port;

      $this->debug = array();
      $this->debug[] = 'initialized with Email: '.$email.', Verifier Email: '.$verifier_email.', Port: '.$port;
    }


    public function set_verifier_email($email) {
      $this->verifier_email = $email;
    }

    public function get_verifier_email() {
      return $this->verifier_email;
    }


    public function set_email($email) {
      $this->email = $email;
    }

    public function get_email() {
      return $this->email;
    }

    public function set_port($port) {
      $this->port = $port;
    }

    public function get_port() {
      return $this->port;
    }

    public function get_errors(){
      return array('errors' => $this->errors);
    }

    public function get_debug() {
      return $this->debug;
    }

    public function verify() {
      $this->debug[] = 'Verify function was called.';

      $is_valid = false;

      //check if this is a yahoo email
      $domain = $this->get_domain();
      if($domain == 'yahoo.com') {
        $is_valid = $this->validate_yahoo();
      }
      //otherwise check the normal way
      else {
        //find mx
        $this->debug[] = 'Finding MX record...';
        $this->find_mx();

        if(!$this->mx) {
          $this->debug[] = 'No MX record was found.';
          $this->add_error('100', 'No suitable MX records found.');
          return $is_valid;
        }
        else {
          $this->debug[] = 'Found MX: '.$this->mx;
        }


        $this->debug[] = 'Connecting to the server...';
        $this->connect_mx();

        if(!$this->connect) {
          $this->debug[] = 'Connection to server failed.';
          $this->add_error('110', 'Could not connect to the server.');
          return $is_valid;
        }
        else {
          $this->debug[] = 'Connection to server was successful.';
        }


        $this->debug[] = 'Starting veriffication...';
        if(preg_match("/^220/i", $out = fgets($this->connect, 1024))){
          $this->debug[] = 'Got a 220 response. Sending HELO...';
          fputs ($this->connect , "HELO ".$this->mx."\r\n"); 
          $out = fgets ($this->connect, 1024);
          $this->debug[] = 'Response: '.$out;
     
          $this->debug[] = 'Sending MAIL FROM...';
          fputs ($this->connect , "MAIL FROM: <".$this->verifier_email.">\r\n"); 
          $from = fgets ($this->connect, 1024); 
          $this->debug[] = 'Response: '.$from;

          $this->debug[] = 'Sending RCPT TO...';
          fputs ($this->connect , "RCPT TO: <".$this->email.">\r\n"); 
          $to = fgets ($this->connect, 1024);
          $this->debug[] = 'Response: '.$to;

          $this->debug[] = 'Sending QUIT...';
          fputs ($this->connect , "QUIT"); 
          fclose($this->connect);

          $this->debug[] = 'Looking for 250 response...';
          if(!preg_match("/^250/i", $from) || !preg_match("/^250/i", $to)){
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
      }

      return $is_valid;
    }

    private function get_domain() {
      $email_arr = explode("@", $this->email);
      $domain = array_slice($email_arr, -1);
      return $domain[0];
    }
    private function find_mx() {
      // $email_arr = explode("@", $this->email);
      // $domain = array_slice($email_arr, -1);
      // $domain = $domain[0];
      $domain = $this->get_domain();
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
      $yahoo_url = 'https://edit.yahoo.com/reg_json?AccountID='.$this->email.'&PartnerName=yahoo_default&ApiName=ValidateFields';
      $result = json_decode(file_get_contents($yahoo_url), true);
      if( $result['ResultCode'] == 'SUCCESS' || 
          ($result['ResultCode'] == 'PERMANENT_FAILURE' && @empty($result['SuggestedIDList']) )
        ) {
        return false;
      }
      else {
        return true;
      }
    }


  }
