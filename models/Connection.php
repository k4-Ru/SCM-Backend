<?php
define("SERVER", $_ENV["SERVER01"]);
define("USER", $_ENV["DBUSER"]);
define("PWORD", $_ENV["PASSWORD"]);
define("DBASE", $_ENV["DATABASE"]);
define("CHARSET", $_ENV["CHARSET"]);

class Connection {
  static $conn = false;

  public function connect() {
    $dbHost = SERVER;
    $dbPort = !empty($_ENV['DBPORT']) ? (int) $_ENV['DBPORT'] : 3306;





    
    if ($dbHost === 'localhost') {
      $dbHost = '127.0.0.1';
    }

    $cnString = "mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . DBASE . ";charset=" . CHARSET;
    $options = [
      \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,
      \PDO::ATTR_EMULATE_PREPARES=>false,
      \PDO::ATTR_STRINGIFY_FETCHES=>false,
      \PDO::ATTR_PERSISTENT=>false
    ];

    try {
      static::$conn = new \PDO($cnString, USER, PWORD, $options);
    } catch (\PDOException $er) {
      throw $er;
    }
    return static::$conn;
  }

  public function closeConnection() {
    static::$conn = null;
    return null;
  }
}