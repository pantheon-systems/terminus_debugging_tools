<?php
namespace Terminus\Commands;
/**
 * This debugging command can find and remove problematic blob values in a sql
 * information database that may be causing innoDB errors.
 *
 * These debugging commands can be invoked with:
 * `terminus blob-column-show`
 * `terminus blob-cells-show`
 * `terminus blob-row-remove`
 */

use Terminus\Commands\TerminusCommand;
use Terminus\Models\Collections\Sites;


class BlobBlotterCommand extends TerminusCommand {

  /**
   * Object constructor
   *
   * @param array $options Options to construct the command object
   * @return BlobBlotterCommand
   */
  public function __construct(array $options = []) {
    $options['require_login'] = true;
    parent::__construct($options);
    $this->sites = new Sites();
  }

  /**
   * Retrieve connection info for mysql.
   *
   * @param array $assoc_args
   * @return array of mysql_params
   */
  public function openConnection($assoc_args) {
    $site        = $this->sites->get(
      $this->input()->siteName(array('args' => $assoc_args))
    );
    $env_id      = $this->input()->env(
      array('args' => $assoc_args, 'site' => $site));

    $environment = $site->environments->get($env_id);
    $info        = $environment->connectionInfo();
    $connection  = $info['mysql_params'];

    $connect = mysqli_connect(
      $connection['mysql_host'],
      $connection['mysql_username'],
      $connection['mysql_password'],
      'pantheon'
    );

    return $connect;
  }

  /**
   * Finds the table-columns using data types that could be over 10MB.
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : name of the site
   *
   * [--env=<env>]
   * : environment for which to fetch connection info
   *
   * @command blob-column-show
   */
  public function blobShow($args, $assoc_args) {
    $connect = $this->openConnection($assoc_args);

    $result = mysqli_query(
      'select DISTINCT TABLE_NAME, COLUMN_NAME, DATA_TYPE
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA="pantheon"
      AND DATA_TYPE in ("mediumblob", "mediumtext", "longblob", "longtext")'
    );
    mysqli_close($connect);

    $this->output()->outputRecord($result);
  }

  /**
   * Loop through those table-columns and search for the biggest cells.
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : name of the site
   *
   * [--env=<env>]
   * : environment for which to fetch connection info
   *
   * [--column=<col>]
   * : Column name with potentially large blob of data
   *
   * @command blob-cells-show
   */
  public function blobCellsShow($args, $assoc_args) {
    $connect = $this->openConnection($assoc_args);
    $column  = $assoc_args['column'];

    $result = mysql_query(
      'SELECT *, length({$column})
      FROM pantheon.{$column}
      ORDER BY length({$column}) DESC limit 10;'
    );
    mysqli_close($connect);

    $this->output()->outputRecord($result);
  }

  /**
   * Loop through those table-columns and search for the biggest cells.
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : name of the site
   *
   * [--env=<env>]
   * : environment for which to fetch connection info
   *
   * [--row=<row>]
   * : Row with large blob of data to remove
   *
   * @command blob-row-remove
   */
  public function blobRowRemove($args, $assoc_args) {
    $connect = $this->openConnection($assoc_args);
    $column  = $assoc_args['row'];

    $result = mysql_query(
      // Query to delete offending row
    );
    mysqli_close($connect);

    $this->output()->outputRecord($result);
  }
}
