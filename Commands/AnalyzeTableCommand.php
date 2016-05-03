<?php
namespace Terminus\Commands;

use Terminus\Commands\TerminusCommand;
use Terminus\Models\Collections\Sites;

/**
 * Analyzes tables from the client database.
 * 
 * `terminus analyze TABLE_1, TABLE_2`
 *
 * @command analyze 
 */
class AnalyzeTableCommand extends TerminusCommand {

  /**
   * Object constructor
   *
   * @param array $options Options to construct the command object
   */
  public function __construct(array $options = []) {
    $options['require_login'] = true;
    parent::__construct($options);
    $this->sites = new Sites();
  }

  /**
   * Analyzes tables from the client database.
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : name of the site
   *
   * [--env=<env>]
   * : environment for which to run the command
   *
   * [--table=<table>]
   * : List of tables to ANALYZE. Leave empty to ANALYZE the entire database.
   *
   * @param array $options Options to construct the command object
   */
  public function __invoke($args, $assoc_args) {
    if (empty($assoc_args['table'])) {
      $answer = $this->input()->confirm(
        [
          'message' => "You haven't specified which tables to analyze. Are you sure you want to analyze the entire database?\n",
          'context' => [],
        ]
      );
      $tables = 'all';
    }
    else {
      $tables = $assoc_args['table'];
    }

    $connect = $this->_openConnection($assoc_args);

    if ($tables == 'all') {
      $tables = $this->_getTables($connect);
      $tables = implode(',', $tables);
    }

    $this->log()->info("Running the following query (please be patient, this might take a while): \n'ANALYZE TABLE " . $tables . "'");

    if ($this->_analyzeTables($connect, $tables)) {
      $this->log()->info("Finished.");
    }

    $this->_closeConnection($connect);
  }

  /**
   * Retrieve connection info for mysql.
   *
   * @param array $assoc_args
   * @return database connection link
   */
  protected function _openConnection($assoc_args) {
    $site = $this->sites->get(
      $this->input()->siteName(array('args' => $assoc_args))
    );

    $env_id = $this->input()->env(
      array('args' => $assoc_args, 'site' => $site)
    );

    $environment = $site->environments->get($env_id);
    $info        = $environment->connectionInfo();

    $connect = mysqli_connect(
      $info['mysql_host'],
      $info['mysql_username'],
      $info['mysql_password'],
      'pantheon',
      $info['mysql_port']
    );

    if (!$connect) {
      $this->log()->error('ERROR: Can\'t connect to the specified environment\'s database. Please make sure it\'s not sleeping.');
      exit;  
    }

    return $connect;
  }

  /**
   * Closes the mysql connection.
   */
  protected function _closeConnection($connect) {
    mysqli_close($connect);
  }

  /**
   * Runs the ANALYZE TABLE query.
   */
  protected function _analyzeTables($connect, $tables) {
    if (empty($tables)) {
      return FALSE;
    }

    $query = 'ANALYZE TABLE ' . $tables;
    $result = mysqli_query($connect, $query);

    if ($result) return TRUE;
    return FALSE;
  }

  /**
   * Returns all the tables from the database.
   */
  protected function _getTables($connect) {
    $query = 'SHOW TABLES';
    $return = [];
    if ($result = mysqli_query($connect, $query)) {
      while ($row = $result->fetch_row()) {
        $return[] = $row[0];
      }
      $result->free();
    }
    return $return;
  }



}