VerifyEmail
==========
Is a PHP class that can be easily used to verify an email address and make sure it is valid and does exist on the mail server.

This class connects to the mail server and checks whether the mailbox exists or not.


How to use:
===========
Initialize the class:

```PHP
$ve = new VE\VerifyEmail('some.email.address@example.com', 'my.email.address@my-domain.com');
```
OR (you can specify other port number than 25)
```PHP
$ve = new VE\VerifyEmail('some.email.address@example.com', 'my.email.address@my-domain.com', 26);
```

The first email address 'some.email.address@example.com' is the one to be checked, and the second 'my.email.address@my-domain.com' is an email address to be provided to the server. This email needs to be valid and from the same server that the script is running from. To make sure your server is not treated as a spam or gets blacklisted check the score of your server here https://mail-tester.com


Then you call the verify function:

```PHP
var_dump($ve->verify());
```

This will restun a boolean. True if the email is valid, false otherwise.

```HTML
bool(true)
```


If you want to get any errors, call this function after the verify function:

```PHP
print_r($ve->get_errors());
```

This will resturn an array of all errors (if any):


```HTML
Array
(
    [0] => No suitable MX records found.
)
```



If you want to get all debug messages of the connection, call this function:

```PHP
print_r($ve->get_debug());
```

This will return an array of all messages and values that used during the process.



```HTML
Array
(
    [0] => initialized with Email: h*****@gmail.com, Verifier Email: sam@verifye.ml, Port: 25
    [1] => Verify function was called.
    [2] => Finding MX record...
    [3] => Found MX: alt4.gmail-smtp-in.l.google.com
    [4] => Connecting to the server...
    [5] => Connection to server was successful.
    [6] => Starting veriffication...
    [7] => Got a 220 response. Sending HELO...
    [8] => Response: 250 mx.google.com at your service

    [9] => Sending MAIL FROM...
    [10] => Response: 250 2.1.0 OK gw8si3985770wjb.84 - gsmtp

    [11] => Sending RCPT TO...
    [12] => Response: 250 2.1.5 OK gw8si3985770wjb.84 - gsmtp

    [13] => Sending QUIT...
    [14] => Looking for 250 response...
    [15] => Found! Email is valid.
)
```


Notes:
======
- Some mail servers will silentlty reject the test message, to prevent spammers from checking against their users' emails and filter the valid emails, so this function might not work properly with all mail servers.

- You server must be configured properly as a mail server to avaiod being blocked or blacklisted. This includes things like SSL, SPF records, Domain Keys, DMARC records, etc. To check your server use this tool https://mail-tester.com

