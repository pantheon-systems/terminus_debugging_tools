<?php

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Models\Collections\Sites;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

class RedisMemoryAnalysisCommand extends TerminusCommand implements SiteAwareInterface {

  use SiteAwareTrait;

  /**
   * Analyzes the redis memory usage by key and hash.
   *
   * @authorize
   *
   * @command redis:analyze
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   * 
   * @usage <site>.<env> Analyzes the redis memory usage by key and hash.
   *
   * @return string
   */
  public function analyze($site_env) {
    // Check if docker is installed. 
    exec("docker --version >/dev/null 2>&1", $output, $return);
    
    if ($return !== 0) {
      $this->log()->error("It looks like you don't have docker installed. Please visit this url to download & install it: https://www.docker.com/");
      return;
    }

    list(, $env) = $this->getSiteEnv($site_env);
    $info = $env->connectionInfo();

    $env->wake();

    if (empty ($info['redis_command'])) {
      $this->log()->error("It looks like the site doesn't have Redis enabled. Stopping.");
      return;
    }

    $redis_cli = $info['redis_command'];
    
    $this->log()->notice("Pulling necessary libraries. Don't worry, we'll clean them up later.");
    
    // Pull the container where we're importing redis.
    $cmd = "docker pull dicix/rma";
    exec($cmd, $output, $return);
    if ($return !== 0) {
      $this->log()->error("Error pulling rma container. Check your internet connection and if docker is properly installed.");
      return;
    }

    // Clean up.
    $cmd = "docker rm rma >/dev/null 2>&1";
    exec($cmd, $output, $return);
    
    $this->log()->notice('Crunching data. Please wait ...');

    // Run the report.
    $cmd = "docker run -it --name rma dicix/rma /report " . $redis_cli;
    exec($cmd, $report, $return);
    if ($return !== 0) {
      $this->log()->error("Error running the report.");
      return;
    }

    $this->log()->notice('Cleaning up.');

    // Clean up.
    $cmd = "docker rm rma";
    exec($cmd, $output, $return);
    if ($return !== 0) {
      $this->log()->error("Error cleaning up.");
      return;
    }

    $this->log()->notice('Done.');

    return implode("\n", $report);
  }

}
