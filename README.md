# SmallPOP3

A PHP class for connecting to a POP3 server and manipulating with the mailbox.

## Usage
* Include the class file to your script
```
require_once("/path/to/your/SmallPOP3.php");
```
* Instantiate the class
```
$pop3 = new SmallPOP3("mail.example.com", "foo", "barbazqux123");
```
The class will automatically end the connection to the server gracefully using destructor.

## The timeout issue
The first thing you should do, is to adjust the timeout to the POP3 server, because the lower the timeout is, the faster the script will be. You can change the timeout in two ways:

* The 6th parameter in constructor called ```$timeout```, that you need to pass everytime when you're instantiating the class
* The ```DEFAULT_TIMEOUT``` constant in the class, which will be used for all instances, whose constructor hasn't got the 6th parameter set

You can pass both integers and floating point numbers, which will be parsed correctly. The default value of the ```DEFAULT_TIMEOUT``` constant is __1.5 seconds__.

The problem is, that when recieving the server reply, PHP waits until the connection is closed (which cannot be done, because that isn't how POP3 works) or for the timeout period and it seems there is no way to get around it. This however slows the script down a lot, especially if you are using methods like ```retrieveAll()```. This can lead to errors like HTTP ```504 Gateway Timeout```, but it is also very unpleasant to wait for the webpage to load for even minutes.


To make this period as short as possible, you should adjust the timeout to needs of your server and its connection. If you deploy this script on some webhosting or VPS, you will mostly don't need to have timeout greater than 1 second, but if you are going to try or deploy it on your home wireless connection, you may need to increase it to about 3 seconds.

## Adjustable constants
* __FORMATTED_SIZE_PRECISION__  
Specifies the decimal precision used when converting the size units to human-readable format. Defaults to ```2```. Example with a message with the size of 136852 bytes:
  * -1 => 140 kB
  * 0 => 137 kB
  * 1 => 136,9 kB
  * 2 => 136,85 kB
  * etc...


* __DEFAULT_TIMEOUT__  
Specifies the default timeout used when a different value isn't specified in constructor. Defaults to ```1.5``` seconds. Please see the text above to adjust it as good as possible for your needs, because it slows the script down a lot.

* __CREDENTIALS_ALLOW_SPECIAL_CHARS__  
Specify, whether you want to allow the script to authenticate to the server with an username or password, that contains non-printable characters. Defaults to ```FALSE``` to prevent unwanted command injection to the POP3 server by a user, who could enter rogue credentials to your web app. If you set this to ```TRUE``` and allow passing user data to the constructor's ```$user``` and ```$passwd``` parameters, it is recommended to sanitize the input in your own code.


## Public methods
### __construct()
The constructor of the class, which makes the connection to the specified POP3 server and authenticates the user.
```
__construct(string $host, string $user, string $passwd, bool $secure = TRUE, bool $ignorecert = FALSE, int/float $timeout = self::DEFAULT_TIMEOUT, int $port = NULL)
```

##### Parameters
* __$host__  
Specifies the hostname, IPv4 or IPv6 address of the POP3 server, that you want to connect to.

* __$user__  
Specifies the username.

* __$passwd__  
Specifies the password.

* __$secure__  
Specify, whether you want to use secure POP3S connection via SSL/TLS to the server. Defaults to ```TRUE``` to prevent unwanted credentials leakage.

* __$ignorecert__  
Specify, whether you want to check, if the certificate, that the server provided while using secure connection, was signed by a valid certificate authority, didn't expire or the hostname of the server matches. Has no effect and is ignored when ```$secure``` is ```FALSE```.

* __$timeout__  
Specified the timeout of the connection. Please see the text above to adjust it as good as possible for your needs, because it slows the script down a lot. If not specified, it will use the value of the ```DEFAULT_TIMEOUT``` constant.

* __$port__  
Specifies the TCP port, where the POP3 server accepts incoming connections. If not specified, it will use ```995``` for secure connections and ```110``` for plaintext connections.




---
### messageCount()
This method can be used to fetch the number of messages in the mailbox, total size of the mailbox or both.
```
messageCount(int $returnmode = 0, bool $formattedsizes = FALSE): int/string/array
```

##### Parameters
* __$returnmode__  
Specify whether the method will return total number of messages, total size of all messages or both via the class's in-build public constants: __MSGCOUNT_COUNT__, __MSGCOUNT_SIZE__, __MSGCOUNT_BOTH__.

* __$formattedsizes__  
Specify the output format of size: plain bytes (e.g. 136852) on ```FALSE``` or human readable size with units (e.g. 133.85 kB) on ```TRUE```.

##### Return values  
The data return format depends on the ```$returnmode``` parameter.
* __MSGCOUNT_COUNT__: *(int)* message count
* __MSGCOUNT_SIZE__: *(string)* total size of the mailbox, either formatted to be human-readable or not.
* __MSGCOUNT_BOTH__: *(array)* combination of the above; 2 items inside the array: ```["msgcount"]``` and ```["totalsize"]```



---
### messageSizes()
Fetches sizes of every message in the mailbox and returns them.
```
messageSizes(bool $formattedsizes = FALSE): array
```

##### Parameters
* __$formattedsizes__  
See the ```messageCount()``` method.

##### Return values
Returns an array of strings with the sizes of every message in the mailbox.  
:exclamation: The returned array starts at 1, so it complies with POP3 mail numbering.



---
### command()
Issue a custom POP3 command and return the server reply as string.
```
command(string $command, bool $stripcontrollines = FALSE): string
```

##### Parameters
* __$command__  
The command to send to the server.

* __$stripcontrollines__  
If set to ```TRUE```, it will strip the first (e.g. ```+OK Message follows```) and the last line (containing the dot ```.``` character) from the server reply, if it is multiline.

##### Return values
Returns a string with the server reply, either with stripped control lines or not.



---
### retrieve()
Retrieve a single message from the server identified by number.
```
retrieve(int $number, bool $raw = FALSE): string/array
```

##### Parameters
* __$number__  
The number of the message in the mailbox to retrieve.

* __$raw__  
Return raw message instead of parsing it into an array.

##### Return values
The return type depends on the setting of the ```$raw``` parameter.  
```FALSE```: *(string)* raw message as the POP3 server returned it  
```TRUE```: *(array)* with the following items:
* __From__ => name and e-mail address of the sender
* __To__ => mostly your e-mail address
* __Subject__ => subject of the e-mail
* __Date__ => date and time, when the e-mail was sent
* __Content-Type__ => Content-Type header of the e-mail used to easily identify the formatting of the mail
* __Content-Transfer-Encoding__ => Content-Transfer-Encoding header of the e-mail used to easily identify the encoding of the e-mail
* __HEADERS__ => all headers of the e-mail unparsed
* __BODY__ => the body of the e-mail itself, would likely be encoded
If the e-mail doesn't contain any of the parts above, the array item will contain boolean ```FALSE```.



---
### retrieveAll()
Retrieve all messages from the mailbox.
```
retrieveAll(bool $raw = FALSE): array
```

##### Parameters
* __$raw__  
See the ```retrieve()``` method.

##### Return values
Array of all the messages from the mailbox in the desired format (see the *Return values* section of ```retrieve()``` method).

:exclamation: The returned array starts at 1, so it complies with POP3 mail numbering.  
:exclamation: Since the whole mailbox will be inserted into an array, it can present memory issues. Before you use this function, it is recommened to check the size of your mailbox to prevent them.



---
### delete()
Delete a single message from the mailbox identified by number. This function doesn't have any return value.
```
delete(int $number)
```

##### Parameters
* __$number__  
The number of the message in the mailbox.



---
### deleteAll()
Delete all messages from the mailbox. This function doesn't have any parameters and return value.
```
deleteAll()
```



---
### headers()
Return headers from a single message in the mailbox. Not all POP3 servers may support this function.
```
headers(int $number, bool $raw = FALSE): string/array
```
The parameters and return value are same as in the ```retrieve()``` method, except the returned array don't have the ```BODY``` element.



---
### headersAll()
Return headers from all messages in the mailbox. Not all POP3 servers may support this function.
```
headersAll(bool $raw = FALSE): array
```
The parameters and return value are same as in the ```retrieveAll()``` method, except the returned second-dimension arrays don't have the ```BODY``` element.



---
### revertDeletes()
Revert the deleted messages from the mailbox in the current session. It doesn't work when you try to revert deleted messages from another connection than the messages were deleted. This function doesn't have any parameters and return value.
```
revertDeletes()
```



---
### keepAlive()
Issues an NOOP command to the server too keep the connection alive. Useful for example in long-polling sessions. This function doesn't have any parameters and return value.
```
keepAlive()
```


## Licensing
This project is licensed under __MIT License__.


## Want to contribute?
If you want to remind me of any bug or fix it right away, or even add some new functionality or just make anything better, feel free to create a pull request or an issue.
