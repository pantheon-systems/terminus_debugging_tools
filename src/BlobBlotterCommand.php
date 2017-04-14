<?php

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Models\Collections\Sites;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

use Consolidation\TestUtils\PropertyListWithCsvCells;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;

use Symfony\Component\Console\Output\ConsoleOutput;

class BlobBlotterCommand extends TerminusCommand implements SiteAwareInterface {

  use SiteAwareTrait;

  /**
   * Finds the biggest blob/text columns from the database.
   *
   * @authorize
   *
   * @command blob:columns
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @field-labels
   *     table: Table
   *     column: Column
   *     biggest_entry_(KB): Biggest entry (KB)
   * @return RowsOfFields
   *
   * @usage <site>.<env> Finds the biggest blob/text columns from the database.
   */
  public function columns($site_env) {
    $connect = $this->_openConnection($site_env);

    $columns = $this->_getBlobColumns($connect);
    if (!empty($columns)) {
      $return = [];
      foreach ($columns as $key => $value) {
        $table  = $value['TABLE_NAME'];
        $column = $value['COLUMN_NAME'];
        $query = "SELECT length($column)/1024 AS column_KB FROM $table ORDER BY column_KB DESC LIMIT 1";
        if ($result = mysqli_query($connect, $query)) {
          $row = mysqli_fetch_row($result);
          if (!empty($row[0])) {
            $row = $row[0];
          } else {
            $row = 0;
          }
          mysqli_free_result($result);

          $return[] = [
            'table' => $table,
            'column' => $column,
            'biggest_entry_(KB)' => $row,
          ];
        }
      }
    }

    // Sorting based on biggest data.
    foreach ($return as $key => $value) {
      $biggest_entry[$key] = $value['biggest_entry_(KB)'];
    }
    array_multisort($biggest_entry, SORT_DESC, $return);
    $this->_closeConnection($connect);
    return new RowsOfFields($return);
  }

  /**
   * Finds the biggest cells for a given table/column.
   *
   * @authorize
   *
   * @command blob:cells
   *
   * @default-format: table
   * 
   * @return RowsOfFields
   * 
   * @param string $site_env Site & environment in the format `site-name.env`
   * @param string $table Table to be analyzed
   * @param string $column Column to be analyzed
   *
   * @usage <site>.<env> <table> <column> --format=table Finds the biggest cells for a given table/column.
   */
  public function cells($site_env, $table, $column) {
    $connect = $this->_openConnection($site_env);

    $table  = mysqli_real_escape_string($connect, $table);
    $column = mysqli_real_escape_string($connect, $column);

    $query = "SELECT column_name FROM information_schema.columns WHERE table_name = '$table' AND column_name != '$column'";
    if ($result = mysqli_query($connect, $query)) {
      $cols = [];
      while ($row = mysqli_fetch_row($result)) {
        $cols[] = $row[0];
      }
      mysqli_free_result($result);
    }
    else {
      $this->log()->error('Sorry, we weren\'t able to retrieve the results. ERROR: ' . mysqli_error($connect));
      return;
    }

    $cols = implode(', ', $cols);
    $query = "SELECT $cols, length($column)/1024 AS column_KB FROM $table ORDER BY column_KB DESC LIMIT 50";
    if ($result = mysqli_query($connect, $query)) {
      while ($row = mysqli_fetch_assoc($result)) {
        $return[] = $row;
      }
      mysqli_free_result($result);
    }
    else {
      $this->log()->error('Sorry, we weren\'t able to retrieve the results. ERROR: ' . mysqli_error($connect));
      return;
    }
    $this->_closeConnection($connect);
    return new RowsOfFields($return);
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
   * Returns the mediumblob/mediumtext/longblob/longtext columns from the pantheon database.
   */
  protected function _getBlobColumns($connect) {
    $query = 'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = "pantheon" AND DATA_TYPE in ("mediumblob", "mediumtext", "longblob", "longtext")';
    if ($result = mysqli_query($connect, $query)) {
      $return = [];
      while ($row = $result->fetch_assoc()) {
        $return[] = $row;
      }
      $result->free();
    }
    else {
      $this->log()->error('Sorry, we weren\'t able to retrieve the results. ERROR: ' . mysqli_error($connect));
      return;
    }
    return $return;
  }

}