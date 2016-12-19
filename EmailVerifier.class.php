<?php
	/**
	 *	EmailVerifier
	 *	E-Mail existance verifier.
	 *
	 *	@author OOP by RZEROSTERN, from a hbattat's fork. Original source: https://github.com/hbattat/verifyEmail
	 *	@version Determined by original author.
	 *	@license Same as original
	 */
	class EmailVerifier
	{
		private $email_array;
		private $domain;
		private $mx_ip;
		private $mxhosts;
		private $result;
		private $from;
		private $to;

		/**
		 *	Constructor
		 *	Assigns variables for working through the 
		 */
		public function __construct($to, $from){
			$this->from = $from;
			$this->to = $to;

			$this->email_array = explode("@", $this->to);
			$domain = array_slice($this->email_array, -1);
			$this->domain = $domain[0];

			$this->domain = ltrim($this->domain, "[");
			$this->domain = rtrim($this->domain, "]");

			if( "IPv6:" == substr($this->domain, 0, strlen("IPv6:")) ){
				$this->domain = substr($this->domain, strlen("IPv6") + 1);
			}
		}

		/**
		 *	validate
		 *	Main function, it checks if the given email exists and is enabled.
		 *	@return Array with the details of the result.
		 */
		public function validate(){
			$this->mxhosts = array();

			if(filter_var($this->domain, FILTER_VALIDATE_IP)){
				$this->mx_ip = $this->domain;
			} else {
				getmxrr($this->domain, $this->mxhosts, $mxweight);
			}

			if(!empty($this->mxhosts)){
				$this->mx_ip = $this->mxhosts[array_search(min($mxweight), $this->mxhosts)];
			} else {
				if(filter_var($this->domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
					$record_a = dns_get_record($this->domain, DNS_A);
				} elseif(filter_var($this->domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
					$record_a = dns_get_record($this->domain, DNS_AAAA);
				}

				if(!empty($record_a)){
					$this->mx_ip = $record_a[0]['ip'];
				} else {
					$this->result['stat'] = "INVALID";
					$this->result['details'] = "No suitable MX records found";

					return $this->result;
				}
			}

			$this->connect();
			return $this->result;
		}

		/**
		 *	connect
		 *	The connector. It connects to SMTP for test the given email.
		 *	@return Result of the test into an array.
		 */
		private function connect(){
			$connect = @fsockopen($this->mx_ip, 25);

			if($connect){ 
				if(preg_match("/^220/i", $out = fgets($connect, 1024))){
					fputs ($connect , "HELO $mx_ip\r\n"); 
					$out = fgets ($connect, 1024);
					$details .= $out."\n";
			
					fputs ($connect , "MAIL FROM: <$this->from>\r\n"); 
					$from = fgets ($connect, 1024); 
					$details .= $from."\n";

					fputs ($connect , "RCPT TO: <$this->to>\r\n"); 
					$to = fgets ($connect, 1024);
					$details .= $to."\n";

					fputs ($connect , "QUIT"); 
					fclose($connect);

					if(!preg_match("/^250/i", $from) || !preg_match("/^250/i", $to)){
						$this->result['stat'] = "INVALID";
						$this->result['details'] = $details;
					}
					else{
						$this->result['stat'] = "VALID";
						$this->result['details'] = $details;
					}
				} 
			}
			else{
				$this->result['stat'] = "invalid";
				$this->result['details'] = "Could not connect to server";
			}
		}
	}
?>