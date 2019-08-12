<?php
// Include file with the interface
require_once("SmallPOP3Interface.php");

class SmallPOP3 implements SmallPOP3Interface {
  // User-changeable constants
  private const FORMATTED_SIZE_PRECISION = 2; // int
  private const DEFAULT_TIMEOUT = 1.5; // int or float
  private const CREDENTIALS_ALLOW_SPECIAL_CHARS = FALSE; // bool

  // Public constants changing the output of the messgaeCount() function
  public const MSGCOUNT_COUNT = 0;
  public const MSGCOUNT_SIZE = 1;
  public const MSGCOUNT_BOTH = 2;



  // Resource containing the connection to the POP3 server
  private $conn;



  // Constructor, that makes the connection to the POP3 server and
  // authenticates the user
  public function __construct($host, $user, $passwd, $secure = TRUE, $ignorecert = FALSE, $timeout = self::DEFAULT_TIMEOUT, $port = NULL) {
    // Validate the user input
    if(filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE && filter_var($host, FILTER_VALIDATE_IP) === FALSE) {
      throw new SmallPOP3Exception("The host parameter has to be a hostname or IP address!", 10);
    }

    if(self::CREDENTIALS_ALLOW_SPECIAL_CHARS && (!ctype_print($user) || !ctype_print($passwd))) {
      throw new SmallPOP3Exception("The user and password parameters have to be both strings without any control characters!", 20);
    }

    $timeout = floatval($timeout);
    if($timeout <= 0 || $timeout >= 2147483647) {
      throw new SmallPOP3Exception("The timeout value has to be a float bigger than 0!", 30);
    }

    // Set the connection's options
    $context = stream_context_create(array(
      'ssl' => array(
        'verify_peer' => !$ignorecert,
        'verify_peer_name' => !$ignorecert,
        'allow_self_signed' => (bool) $ignorecert
      )
    ));

    if($secure) {
      $prefix = "tls://";
      $port = (is_int($port) && $port >= 0 && $port <= 65535) ? $port : 995;
    } else {
      $prefix = "tcp://";
      $port = (is_int($port) && $port >= 0 && $port <= 65535) ? $port : 110;
    }

    // Establish the connection to the POP3 server and check if it was
    // established successfully
    $this->conn = stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, ceil($timeout), STREAM_CLIENT_CONNECT, $context);
    if(!$this->conn) {
      throw new SmallPOP3Exception("The connection to the server " . $host . " on port " . $port . " cannot be made. Error details: " . $errno . " " . $errstr, 40);
    }

    stream_set_timeout($this->conn, floor($timeout), round((fmod($timeout, 1) * 1000000)));

    if($this->checkPOPError(stream_get_contents($this->conn))) {
      throw new SmallPOP3Exception("The server " . $host . " has replied with an error after establishing the connection.", 50);
    }

    // Authenticate the user
    $this->command("USER " . $user);
    $this->command("PASS " . $passwd);
  }


  // Gracefully end the connection to the server when destructing the instance
  public function __destruct() {
    $this->command("QUIT");
    fclose($this->conn);
  }


  // Function used to pass the commands to the server and return
  // (both plain and formatted, based on the user's preference) output
  public function command($command, $stripcontrollines = FALSE) {
    if(!is_string($command)) {
      throw new SmallPOP3Exception("The command provided is not a string!", 100);
    }
    $command = trim($command) . "\r\n";

    fwrite($this->conn, $command);
    $response = stream_get_contents($this->conn);

    if($this->checkPOPError($response)) {
      throw new SmallPOP3Exception("The server responsed with an error to your command: " . $response, 110);
    }

    return (($stripcontrollines) ? $this->stripControlLines($response) : $response);
  }


  // Function used to fetch the number of messages, the size of all messages
  // or both from the server
  public function messageCount($returnmode = 0, $formattedsizes = FALSE) {
    $response = $this->command("STAT");
    list(, $count, $size) = explode(" ", trim($response));

    $count = intval($count);
    $size = ($formattedsizes) ? $this->convertSize($size) : $this->checkSize($size);

    switch($returnmode) {
      case 0:
        return $count;

      case 1:
        return $size;

      case 2:
        return array("msgcount" => $count, "totalsize" => $size);

      default:
        throw new SmallPOP3Exception("The returnmode specified is invalid.", 200);
    }
  }


  // Get the sizes of all messages in the user's mailbox
  public function messageSizes($formattedsizes = FALSE) {
    $sizes = array();

    $response = $this->command("LIST", TRUE);

    $lines = explode("\r\n", $response);
    foreach($lines as $item) {
       list($number, $size) = explode(" ", trim($item), 2);
       $sizes[$number] = ($formattedsizes) ? $this->convertSize($size) : $this->checkSize($size);
    }

    return $sizes;
  }


  // Retrieve a single message from the user's mailbox identified by integer
  // bigger or equal to 1
  public function retrieve($number, $raw = FALSE) {
    $number = intval($number);
    if($number < 1) {
      throw new SmallPOP3Exception("The message identification number is invalid.", 250);
    }

    $response = $this->command("RETR " . $number, TRUE);

    return (($raw) ? $response : $this->parseEmail($response, TRUE));
  }


  // Retrieve all messages from the user's mailbox and return them in an array
  public function retrieveAll($raw = FALSE) {
    $count = $this->messageCount();
    $messages = array();

    for($i = 1; $i <= $count; $i++) {
      $messages[$i] = $this->retrieve($i, $raw);
    }

    return $messages;
  }


  // Delele a single message from the user's mailbox identified by integer
  // bigger or equal to 1
  public function delete($number) {
    $number = intval($number);
    if($number < 1) {
      throw new SmallPOP3Exception("The message identification number is invalid.", 250);
    }

    $this->command("DELE " . $number);
  }


  // Delete all messages from the user's mailbox
  public function deleteAll() {
    $count = $this->messageCount();

    for($i = 1; $i <= $count; $i++) {
      $this->delete($i);
    }
  }


  // Retrieve a single message's headers from the user's mailbox identified
  // by integer bigger or equal to 1
  public function headers($number, $raw = FALSE) {
    $number = intval($number);
    if($number < 1) {
      throw new SmallPOP3Exception("The message identification number is invalid.", 250);
    }

    $response = $this->command("TOP " . $number . " 0", TRUE);

    return (($raw) ? $response : $this->parseEmail($response, FALSE));
  }


  // Retrieve all messages' headers from the user's mailbox and return them
  // in an array
  public function headersAll($raw = FALSE) {
    $count = $this->messageCount();
    $headers = array();

    for($i = 1; $i <= $count; $i++) {
      $headers[$i] = $this->headers($i, $raw);
    }

    return $headers;
  }


  // Revert the deleted messages in the current session
  public function revertDeletes() {
    $this->command("RSET");
  }


  // Keep alive the connection to the POP3 server via the NOOP POP3 command
  public function keepAlive() {
    $this->command("NOOP");
  }


  /*
  *       PRIVATE FUNCTIONS
  *       -----------------
  */



  // Check, if the POP3 server didn't return an error after issuing an command
  private function checkPOPError($msg) {
    return (substr($msg, 0, 3) !== '+OK');
  }


  // Strip the POP3 control lines:
  // - First line of the response, which contains +OK/-ERR and some
  //   human-readable information
  // - Last line of multiline response, that contains the dot (.) character
  private function stripControlLines($data) {
    $lines = explode("\r\n", trim($data));

    if(count($lines) !== 1) {
      if(end($lines) === ".") {
        array_pop($lines);
      }
      if(substr(reset($lines), 0, 3) === "+OK") {
        array_shift($lines);
      }
    }

    return implode("\r\n", $lines);
  }


  // Parse the recieved e-mail or e-mail's headers and put them into an array
  private function parseEmail($email, $includebody) {
    $parts = array('From' => FALSE, 'To' => FALSE, 'Subject' => FALSE, 'Date' => FALSE, 'Content-Type' => FALSE, 'Content-Transfer-Encoding' => FALSE, 'HEADERS' => FALSE);

    if($includebody) {
      $parts['BODY'] = FALSE;
      list($parts["HEADERS"], $parts["BODY"]) = explode("\r\n\r\n", $email, 2);
    } else {
      $parts['HEADERS'] = $email;
    }

    $headerlines = explode("\r\n", $parts["HEADERS"]);
    foreach($headerlines as $line => $item) {
      $keyval = explode(": ", $item, 2);

      if(preg_match('/^(From|To|Subject|Date|Content-Type|Content-Transfer-Encoding)$/', $keyval[0])) {
        for($i = ($line + 1); $headerlines[$i][0] === " " || $headerlines[$i][0] === "\t"; $i++) {
          $keyval[1] .= $headerlines[$i];
        }
        $parts[$keyval[0]] = $keyval[1];
      }
    }

    return $parts;
  }


  // Check, if the size of an e-mail is valid
  private function checkSize($bytes) {
    if(!is_numeric($bytes) || $bytes < 0 || $bytes >= 2147483647) {
      throw new SmallPOP3Exception("The size of an e-mail is invalid (" . $bytes . ").", 290);
    }

    return $bytes;
  }


  // Convert the size to a human-readable format
  private function convertSize($bytes) {
    $this->checkSize($bytes);

    $units = array("B", "kB", "MB", "GB", "TB");
    $factor = floor((strlen($bytes) - 1) / 3);

    return round($bytes / pow(1000, $factor), self::FORMATTED_SIZE_PRECISION) . " " . $units[$factor];
  }
}


class SmallPOP3Exception extends Exception {}
