<?php
/**
 * sfPluploadUploadedFile is a class for dealing with uploads from plupload
 *
 * @package     sfPluploadPlugin
 * @subpackage  uploaded file
 * @author      Kevin Dew  <kev@dewsolutions.co.uk>
 */
class sfPluploadUploadedFile
{
  /**
   * @var int
   */
  protected $_chunk = 0;

  /**
   * @var int
   */
  protected $_chunks = 0;

  /**
   * @var string
   */
  protected $_filename = '';

  /**
   * @var string
   */
  protected $_originalFilename = '';

  /**
   * @var int
   */
  protected $_maxFileAge;

  /**
   * @var string
   */
  protected $_targetDir;

  /**
   * @var bool
   */
  protected $_uploadProcessed = false;

  /**
   * @var int
   */
  protected $_timeLimit = -1;

  /**
   * @var string
   */
  protected $_mimeType = 'application/octet-stream';

  /**
   * Constructor
   *
   * @param int     $chunk
   * @param int     $chunks
   * @param string  $name
   */
  public function __construct($chunk, $chunks, $filename)
  {
    $this
      ->setChunk($chunk)
      ->setChunks($chunks)
      ->setFilename($filename)
      ->setMaxFileAge(
          sfConfig::get('app_sfPluploadPlugin_max_file_age', 60 * 60)
        )
      ->setTargetDir(
          sys_get_temp_dir()
          . (substr(sys_get_temp_dir(), -1) == DIRECTORY_SEPARATOR
              ? '' 
              : DIRECTORY_SEPARATOR
            )
          . sfConfig::get('app_sfPluploadPlugin_target_dir', 'plupload')
        )
      ->setTimeLimit(
          sfConfig::get('app_sfPluploadPlugin_time_limit', -1)
        )
    ;
  }

  /**
   * @return  int
   */
  public function getChunk()
  {
    return $this->_chunk;
  }

  /**
   * @param   int   $chunk
   * @return  self
   */
  public function setChunk($chunk)
  {
    $this->_chunk = (int) $chunk;
    return $this;
  }

  /**
   * @return  int
   */
  public function getChunks()
  {
    return $this->_chunks;
  }

  /**
   * @param   int   $chunks
   * @return  self
   */
  public function setChunks($chunks)
  {
    $this->_chunks = (int) $chunks;
    return $this;
  }

  /**
   * @return  string
   */
  public function getFilename()
  {
    return $this->_filename;
  }

  /**
   * @param   string  $filename
   * @return  self
   */
  public function setFilename($filename)
  {
    // clean filename
    $filename = preg_replace('/[^\w\._]+/', '', $filename);
    $this->_filename = (string) $filename;
    return $this;
  }

  /**
   * @return  string
   */
  public function getOriginalFilename()
  {
    return $this->_originalFilename;
  }

  /**
   * @param   string  $originalFilename
   * @return  self
   */
  public function setOriginalFilename($originalFilename)
  {
    $this->_originalFilename = (string) $originalFilename;
    return $this;
  }

  /**
   * @return  string
   */
  public function getFilePath()
  {
    return $this->getTargetDir() . DIRECTORY_SEPARATOR . $this->getFilename();
  }

  /**
   * @return  int
   */
  public function getMaxFileAge()
  {
    return $this->_maxFileAge;
  }

  /**
   * @param   int   $maxFileAge
   * @return  self
   */
  public function setMaxFileAge($maxFileAge)
  {
    $this->_maxFileAge = (int) $maxFileAge;
    return $this;
  }

  /**
   * @return  string
   */
  public function getTargetDir()
  {
    return $this->_targetDir;
  }

  /**
   * @param   string  $targetDir
   * @return  self
   */
  public function setTargetDir($targetDir)
  {
    $this->_targetDir = (string) $targetDir;
    return $this;
  }

  /**
   * @return  bool
   */
  public function getUploadProcessed()
  {
    return $this->_uploadProcessed;
  }

  /**
   * @param   string  $uploadProcessed
   * @return  self
   */
  public function setUploadProcessed($uploadProcessed)
  {
    $this->_uploadProcessed = (bool) $uploadProcessed;
    return $this;
  }

  /**
   * @return  int
   */
  public function getTimeLimit()
  {
    return $this->_timeLimit;
  }

  /**
   * @param   int   $timeLimit
   * @return  self
   */
  public function setTimeLimit($timeLimit)
  {
    $this->_timeLimit = (int) $timeLimit;
    return $this;
  }

  /**
   * @return  string
   */
  public function getMimeType()
  {
    return $this->_mimeType;
  }

  /**
   * @param   string  $mimeType
   * @return  self
   */
  public function setMimeType($mimeType)
  {
    $this->_mimeType = (string) $mimeType;
    return $this;
  }

  /**
   * Process the current upload
   *
   * @param   array   $file             A file uploaded file array (a
   *                                    sfWebRequest->getFile())
   * @param   string  $contentType      The string of the content type of the
   *                                    request
   * @param   bool    $cleanUpOldFiles  (Optional) Whether to clean up old files
   *                                    (default true)
   * @return  void
   * @throws  Exception
   */
  public function processUpload(
    array $file, $contentType, $cleanUpOldFiles = true
  )
  {
    $this->_initTimeLimit();

    $this->_createTargetDir();

    if ($cleanUpOldFiles)
    {
      $this->cleanUpOldFiles();
    }

    $this->setFilename($this->_generateUniqueFilename());

    $this->setOriginalFilename($this->getFilename());

    if (strpos($contentType, "multipart") !== false)
    {
      $this->_processMultipart($file);
    }
    else
    {
      $this->_processStream();
    }

    $this->setUploadProcessed(true);
  }

  /**
   * Delete old uploaded files
   *
   * @return  void;
   */
  public function cleanUpOldFiles()
  {
    if ($this->getMaxFileAge() < 0)
    {
      return;
    }

    $this->_createTargetDir();

    if (!is_dir($this->getTargetDir()))
    {
      throw new Exception('Temporary directory doesn\'t exist');
    }

    $dir = opendir($this->getTargetDir());

    if (!$dir)
    {
      throw new Exception('Could not open temporary directory');
    }

		while (($file = readdir($dir)) !== false) {
			$filePath = $this->getTargetDir() . DIRECTORY_SEPARATOR . $file;

			if (
        (filemtime($filePath) < time() - $this->getMaxFileAge())
      )
      {
				unlink($filePath);
      }
		}

		closedir($dir);

    return;
  }

  /**
   * Get the content type from the request
   *
   * @param   sfWebRequest  $request
   * @return  string
   */
  public function getContentType(sfWebRequest $request)
  {
    $pathInfo = $request->getPathInfoArray();

    $contentType = '';

    if (isset($pathInfo['CONTENT_TYPE']))
    {
      $contentType = $pathInfo['CONTENT_TYPE'];
    }
    elseif (isset($pathInfo['HTTP_CONTENT_TYPE']))
    {
      $contentType = $pathInfo['HTTP_CONTENT_TYPE'];
    }

    return $contentType;
  }

  /**
   * Whether the upload is complete
   *
   * @return  bool
   */
  public function isComplete()
  {
    if (!$this->getUploadProcessed())
    {
      return false;
    }

    if ($this->getChunks() < 2)
    {
      return true;
    }

    if ($this->getChunks() == ($this->getChunk() + 1))
    {
      return true;
    }

    return false;
  }

  /**
   * Generates a unique file name for the file in the temp directory
   *
   * Can only be used on files that aren't chunked
   *
   * @return  string
   */
  protected function _generateUniqueFilename()
  {
    if ($this->getChunks() >= 2 || !file_exists($this->getFilePath()))
    {
      return $this->getFilename();
    }

    $extPos = strrpos($this->getFilename(), '.');

    $filename = substr($this->getFilename(), 0, $extPos);
    $ext = substr($this->getFilename(), $extPos);

    $count = 1;
    do
    {
      $generatedFilename = $filename . '_' . $count . $ext;
      $count++;
    }
    while (
      file_exists(
        $this->getTargetDir() . DIRECTORY_SEPARATOR . $generatedFilename
      )
    );

    return $generatedFilename;

  }

  /**
   * Create the target directory for the upload
   *
   * @return  void
   */
  protected function _createTargetDir()
  {
    $targetDir = $this->getTargetDir();

    if (!file_exists($targetDir))
    {
      mkdir($targetDir);
    }
  }

  /**
   * Process a multipart submission to the form
   *
   * @param   array     $file   A file upload array
   * @return  void
   * @throws  Exception
   */
  protected function _processMultipart(array $file)
  {
    if (isset($file['error']) && $file['error'])
    {
      throw new Exception('Upload error code: ' . $file['error']);
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
    {
      throw new Exception('Could not locate an uploaded file');
    }

    $outputFile = fopen(
      $this->getFilePath(),
      ($this->getChunk() == 0 ? 'wb' : 'ab')
    );

    if (!$outputFile)
    {
      throw new Exception('Could not open/create output file');
    }

    $inputFile = fopen($file['tmp_name'], 'rb');

    if (!$inputFile)
    {
      throw new Exception('Could not open temporary file');
    }

    while ($buff = fread($inputFile, 4096))
    {
      fwrite($outputFile, $buff);
    }

    if (isset($file['name']))
    {
      $this->setOriginalFilename($file['name']);
    }

    if (isset($file['type']))
    {
      $this->setMimeType($file['type']);
    }
  }

  /**
   * Process a stream upload to the form
   *
   * @return  void
   */
  protected function _processStream()
  {
    $outputFile = fopen(
      $this->getFilePath(),
      ($this->getChunk() == 0 ? 'wb' : 'ab')
    );

    if (!$outputFile)
    {
      throw new Exception('Could not open/create output file');
    }

    $inputStream = fopen('php://input', 'rb');

    if (!$inputStream)
    {
      throw new Exception('Could not open input stream');
    }

    while ($buff = fread($inputStream, 4096))
    {
      fwrite($outputFile, $buff);
    }
  }

  /**
   * Set a time limit for this script
   *
   * @return  void
   */
  protected function _initTimeLimit()
  {
    if ($this->getTimeLimit() < 0)
    {
      return;
    }

    set_time_limit($this->getTimeLimit());
    return;
  }
}