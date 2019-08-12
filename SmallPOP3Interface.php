<?php
interface SmallPOP3Interface {
  public function messageCount($returnmode = 0, $formattedsizes = FALSE); // int/string/array
  public function messageSizes($formattedsizes = FALSE); // string
  public function command($command, $stripcontrollines = FALSE); // string
  public function retrieve($number, $raw = FALSE); // string/array
  public function retrieveAll($raw = FALSE); // array
  public function delete($number); // NULL
  public function deleteAll(); // NULL
  public function headers($number, $raw = FALSE); // string/array
  public function headersAll($raw = FALSE); // array
  public function revertDeletes(); // NULL
  public function keepAlive(); // NULL
}
