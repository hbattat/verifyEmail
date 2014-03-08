emailVerify
==========
Is a PHP function that can be easily used to verify an email address and make sure it is valid and does exist on the mail server.

This function connects to the mail server and checks whether the mailbox exists or not.


How to use:
===========
Simply call the function:

```PHP
verifyEmail('some.email.address@example.com', 'my.email.address@my-domain.com');
```
The first email address 'some.email.address@example.com' is the one to be checked, and the second 'my.email.address@my-domain.com' is an email address to be provided to the server (just for testing, but would be better if it is a valid email)

This will restun a string "valid" if the email some.email.address@example.com is valid, and "invalid" if the email is invalid


If you want to get the the actual details of that connection, add another parameter to the function:

```PHP
print_r(verifyEmail('some.email.address@example.com', 'my.email.address@my-domain.com', true));
```

This will resturn an array that looks like this:

```HTML
Array ( [0] => invalid [1] => 250 mx.google.com at your service 250 2.1.0 OK u4si6155213qat.124 - gsmtp 550-5.1.1 The email account that you tried to reach does not exist. Please try )
```


Notes:
======
- Some mail servers will silentlty reject the test message, to prevent spammers from checking against their users' emails and filter the valid emails, so this function might not work properly with all mail servers.
