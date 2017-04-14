<?php

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Models\Collections\Sites;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class AnalyzeTableCommand extends TerminusCommand implements SiteAwareInterface {

  use SiteAwareTrait;

  /**
   * Analyzes tables from the client database.
   *
   * @authorize
   *
   * @command analyze-table:analyze
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   * @param string $table Table to be analyzed
   *
   * @field-labels
   *     table: Table
   *     status: Status
   * @return RowsOfFields
   *
   * @usage <site>.<env> <table=TABLE_NAME|all> Runs ANALYZE TABLE $table from the client database. For multiple tables you can use table_1,table_2,table_3 as argument.
   */
  public function analyze($site_env, $table) {
    
    $connect = $this->_openConnection($site_env);

    if ($table == 'all') {
      if (!$this->confirm('Are you sure you want to run ANALIZE TABLE on the entire database?')) {
        return;
      }
      $table = $this->_getTables($connect);
      $table = implode(',', $table);
    }


    //$this->log()->info("Running the following query (please be patient, this might take a while): \n'ANALYZE TABLE " . $tables . "'");
    if ($results = $this->_analyzeTables($connect, $table)) {
      // Output the results in table format.
      $rows = array();
      $labels = [
        'table'  => 'Table',
        'status' => 'Status',
      ];
      foreach ($results as $table => $status) {
        $rows[] = [
          'table'  => $table,
          'status' => $status,
        ];
      }
      $this->_closeConnection($connect);
      return new RowsOfFields($rows);
    }
  }

  /**
   * Retrieve connection info for mysql.
   *
   * @param array $assoc_args
   * @return array of mysql_params
   */
  protected function _openConnection($site_env) {
    list(, $env) = $this->getSiteEnv($site_env);
    $info = $env->connectionInfo();

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
    $results = array();
    $query = 'ANALYZE TABLE ' . $tables;
    if ($result = mysqli_query($connect, $query, MYSQLI_USE_RESULT)) {
      while ($row = mysqli_fetch_object($result)) {
        $results[$row->Table] = $row->Msg_text;
      }
    }
    else {
      $results[$row->Table] = 'ERROR';
    }
    return $results;
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