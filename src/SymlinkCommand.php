<?php

/**
 * Terminus plugin symlink
 *
 * Author: Albert S. Causing / Pantheon
 * 
 **/

namespace Pantheon\TerminusSymlink\Commands;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manage secrets on a Pantheon instance
 */
class SymlinkCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;
    protected $info;
    protected $tmpDirs = [];
    /**sites/
     * Object constructor
     */
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Create symlink on a Pantheon site
     *
     * @command remote:symlink
     * @aliases symlink
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     * @param string $src Symlink source path
     *
     * @option boolean $transfer_existing Transfer existing source directory to target directory; equvalent to `mv wp-content/cache ../../files/cache
     * @option string $destination Custom target directory name
     *
     * @usage <site>.<env> <src> --destination=<customTargetName> An optional target directory name 
     * @usage <site>.<env> <src> --transfer_existing Transfer existing source directory to files/symlink_target/.     
     */
    public function symlinkCommand($site_env, $src, array $options = ['destination'=> null])
    {	
        $config = $this->var_prep($site_env, $src);
        
        $ENV = $config['env_id'];
		$SITE = $config['site_id'];
		$SRC = $config['src'];
		$LOCAL_TARGET = $config['local_target'];
		$DEST = $config['dest'];

		$this->log()->notice(print_r($config));

		$verbose = isset($options['verbose'])? 'v' : '';
		
		/**
		 * Alias for rsync options and connetions
		 **/		
		$connection = "-e 'ssh -p 2222' $ENV.$SITE@appserver.$ENV.$SITE.drush.in";
		$del_rsync = "rsync -".$verbose."r --delete --include '$LOCAL_TARGET/***' --exclude='*' $(mktemp -d)/";
		$updown_rsync = "rsync -rl".$verbose."z --size-only --ipv4";

		/**
		 * Downloading directory if exist, for backup-up or transfer 
		 **/				
	    $this->log()->notice("Downloading directory");
	    $download_directory =  "$updown_rsync $connection:$SRC ~/.pantheon_symlink/";                
	    passthru($download_directory);

		/**
		 * Removing directory if exist, will replace with symlink
		 **/				
		$this->log()->notice("Removing directories");
		$delete_directory =  "$del_rsync $connection:" . str_replace($LOCAL_TARGET,'',$SRC);
		passthru($delete_directory);

		$delete_directory = "$del_rsync $connection:files/symlink_target/" . $DEST;
		passthru($delete_directory);
		
		/**
		 * Transfering downloaded copy to files/symlink_target/
		 **/						
        if($options['transfer_existing']) {
			$this->log()->notice("Transfering directory to files/symlink_target/");
			$transfer_directory = "$updown_rsync ~/.pantheon_symlink/$LOCAL_TARGET --temp-dir=~/tmp/ $connection:files/symlink_target/";			
		} else {
			//or create the symlink target directory
			passthru("mkdir -p ~/.pantheon_symlink/empty/$DEST");
			$transfer_directory = "$updown_rsync ~/.pantheon_symlink/empty/$DEST --temp-dir=~/tmp/ $connection:files/symlink_target/";			
		}
		passthru($transfer_directory);
		
		
		/**
		 * Creating relative symlink
		 **/						
    	$src_token = explode('/', $SRC);
  	    $dotdot = "";
  	    for($i=0; $i<(count($src_token)-1); $i++) {
	  	    $dotdot .= "../";
  	    }
    	 	    
  	    if($options['destination'] != null) {
	  	    $DEST = $options['destination'];	
	  	    $SYMLINK_TARGET = $dotdot . "files/symlink_target/" . $DEST;	  	    
  	    } else {
		  	$SYMLINK_TARGET = $dotdot . "files/symlink_target/" . $LOCAL_TARGET;	  	        
  	    }
  	    
  		
  		$this->log()->notice('dest: '.$DEST);
  		$this->log()->notice('local: '.$LOCAL_TARGET); 
	  	    
		/**
		 * Creating symlink file for upload
		 **/						
  	    $this->log()->notice("Creating symlink locally");
  	    $create_symlink = "ln -sf $SYMLINK_TARGET $DEST";

  	    $this->log()->notice($create_symlink);

  	    passthru($create_symlink);
  	      	    
		/**
		 * Uploading symlink to DEV
		 **/						
  	    $this->log()->notice("Uploading symlink");
  	    $transfer_symlink = "$updown_rsync $LOCAL_TARGET --temp-dir=~/tmp/ $connection:" . str_replace($LOCAL_TARGET,'',$SRC);
  		passthru($transfer_symlink);
  		
  		$this->log()->notice("DONE!");
    }
    
    protected function var_prep($site_env,$src) 
    {
	    list($site, $env) = $this->getSiteEnv($site_env);
	    
        //Check if dev environment
        if (in_array($env->id, ['test', 'live',])) {
            throw new TerminusException(
                'Create symlink on DEV, currently in {env} environment',
                ['env' => $env->id,]
            );
        } else {
	        $envInfo = $env->serialize();
	        //Check if connection mode git, otherwise switch to sftp connection mode.
	        if ($envInfo['connection_mode'] == 'git') {
		        $workflow = $env->changeConnectionMode('sftp');
				if (is_string($workflow)) {
		            $this->log()->notice($workflow);
		        } else {
		            while (!$workflow->checkProgress()) {
		                // TODO: (ajbarry) Add workflow progress output
		            }
		            $this->log()->notice($workflow->getMessage());
		        }

	        }
	        $siteInfo = $site->serialize();
        }
              
        //make sure 'code' found in the path, remove ends slashes
        $trimed_source = rtrim(ltrim($src, '/'),'/');
	 
		if (!(strpos(substr($trimed_source,0,4), 'code') !== false)) { $trimed_source = 'code/'.$trimed_source; } 

		$src_token = explode('/', $trimed_source);
  
  	    $local_target = $src_token[count($src_token) -1];
  	    	 
		if (file_exists("symlink/$local_target")) {
		    passthru("rm -rf ~/.pantheon_symlink/$local_target");    
	    }

		$dest = !empty($options['destination']) ? $options['destination'] : $local_target;
	    
	    return array('env_id'=>$env->id, 'site_id'=>$siteInfo['id'], 'src'=>$trimed_source, 'src_token'=>$src_token, 'local_target'=>$local_target, 'dest'=>$dest);
    }

    protected function passthru($command)
    {
        $result = 0;
        passthru($command, $result);
        if ($result != 0) {
            throw new TerminusException('Command `{command}` failed with exit code {status}', ['command' => $command, 'status' => $result]);
        }
    }
}
