<?php
/*
	Filer - Reliable flatfile data storage for multi-threaded 
	environments

	Copyright (c) 2017, Martin Wandelt

	...................................................................
	The MIT License (MIT)

	Permission is hereby granted, free of charge, to any person
	obtaining a copy of this software and associated documentation files
	(the "Software"), to deal in the Software without restriction,
	including without limitation the rights to use, copy, modify, merge,
	publish, distribute, sublicense, and/or sell copies of the Software,
	and to permit persons to whom the Software is furnished to do so,
	subject to the following conditions:

	The above copyright notice and this permission notice shall be
	included in all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
	EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
	NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
	BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
	ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
	CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
	SOFTWARE.
	...................................................................
*/

class Filer {

	public $dir;
	public $autoUnlockPeriod = 24*60*60;
	public $timeOutPeriod = 5;
	public $lockFolderName = '.lock';
	public $backupPrefix = '~';
	public $tempPrefix = '~~';
	public $lastErrorNo = NULL;
	public $lastErrorMsg = NULL;
	
	private $hasLock;
	private $keepLock;
	

	public function __construct( $dir )
	{
		$this->dir = rtrim( $dir , '/' );
		register_shutdown_function( array ( $this, 'unlock' ) );
	}


	public function write( $fileName, $string )
	{
		if ( ! is_string( $string ) )
		{
			$this->lastErrorNo = 100; 
			$this->lastErrorMsg = 'Data must be of type string';
		}
		
		$this->lastErrorNo = NULL;
		$this->lastErrorMsg = NULL;

		if ( ! $this->_lock() )
		{
			$this->lastErrorNo = 101; 
			$this->lastErrorMsg = 'Could not get exclusive lock';
			return FALSE;
		}
		
		if ( pathinfo( $fileName, PATHINFO_EXTENSION ) == 'php' )
		{
			$string = "<?php die('Forbidden'); ?>\n{$string}";
		}

		$storageFile = $this->dir . '/' . $fileName;
		$backupFile = $this->dir . '/' . $this->backupPrefix . $fileName;
		$tempFile = $this->dir . '/' . $this->tempPrefix . $fileName;

		if ( file_put_contents( $tempFile, $string ) === FALSE )
		{
			$this->lastErrorNo = 103;
			$this->lastErrorMsg = 'Could not write to temp file';
			$this->_unlock();
			return FALSE;
		}
		
		if ( file_exists( $storageFile ) )
		{
			if ( file_exists( $backupFile ) &&  ! unlink( $backupFile ) )
			{
				$this->lastErrorNo = 104;
				$this->lastErrorMsg = 'Could not delete existing backup file';
				$this->_unlock();
				return FALSE;
			}
			
			if ( ! rename( $storageFile, $backupFile ) )
			{
				$this->lastErrorNo = 105;
				$this->lastErrorMsg = 'Could not rename existing storage file';
				$this->_unlock();
				return FALSE;
			}
		}
		
		if ( ! rename( $tempFile, $storageFile ) )
		{
			$this->lastErrorNo = 106;
			$this->lastErrorMsg = 'Could not rename temp file to new storage file';
			$this->_unlock();
			return FALSE;
		}
		
		if ( file_exists( $backupFile ) )
		{
			unlink( $backupFile );
		}

		$this->_unlock();
		return TRUE;
	}
	

	public function read( $fileName, $keepLock = FALSE )
	{
		if ( $keepLock )
		{
			$this->lock();
		}

		$storageFile = $this->dir . '/' . $fileName;
		$backupFile = $this->dir . '/' . $this->backupPrefix . $fileName;

		if ( ! file_exists( $storageFile ) )
		{
			if ( ! file_exists( $backupFile ) || ! $this->lock() 
					|| ! rename( $backupFile, $storageFile ) )
			{
				return FALSE;
			}

			$this->_unlock();
		}

		$string = file_get_contents( $this->dir . '/' . $fileName );

		if ( $string === FALSE )
		{
			return FALSE;
		}
		
		if ( substr( $string, 0, 5 ) == '<?php' )
		{
			$string = substr( $string, strpos( $string, "\n" ) );
		}

		return $string;
	}


	public function lock()
	{
		if ( ! $this->_lock() )
		{
			return FALSE;
		}
		
		$this->keepLock = TRUE;
		return TRUE;
	}


	public function unlock()
	{
		$this->keepLock = FALSE;
		$this->_unlock();;
	}


	private function _lock()
	{
		if ( $this->hasLock )
		{
			return TRUE;
		}
		
		$lockFolder = $this->dir . '/' . $this->lockFolderName;
		
		if ( file_exists( $lockFolder ) && $this->autoUnlockPeriod
		 		&& time() - filemtime( $lockFolder ) > $this->autoUnlockPeriod )
		{
			$this->delete_temp_files();
			rmdir( $lockFolder );
		}

		$oldUmask = umask(0);
		$connectTime = time();

		while ( ! @mkdir( $lockFolder, 0777 ) )
		{
			if ( time() - $connectTime > $this->timeOutPeriod )
			{
				return FALSE;
			}
			
			usleep(100000);
		}
		
		umask( $oldUmask );
		$this->hasLock = TRUE;
		return TRUE;
	}


	private function _unlock()
	{
		if ( ! $this->hasLock || $this->keepLock )
		{
			return;
		}

		$lockFolder = $this->dir . '/' . $this->lockFolderName;
		@rmdir( $lockFolder );
		$this->hasLock = FALSE;
	}
	
}

// end of file filer.class.php
