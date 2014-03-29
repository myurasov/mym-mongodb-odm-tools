<?php

namespace mym\MongoDB\ODM\Document;

use Doctrine\MongoDB\GridFSFile;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use JMS\Serializer\Annotation as Serializer;
use mym\MongoDB\ODM\Exception\FileDownloadErrorException;
use mym\MongoDB\ODM\Traits\TimestampTrait;

/**
 * @ODM\Document(collection="files")
 * @Serializer\ExclusionPolicy("all")
 */
class File
{
  use TimestampTrait;

  // temporary file path
  protected $_tmpFile;

  // file is temporary - delete on destroy
  protected $localFileIsTemporary = false;

  /**
   * @ODM\Id
   * @Serializer\Expose
   */
  protected $id;

  /** @ODM\File */
  protected $file;

  /**
   * @ODM\String
   * @Serializer\Expose
   */
  protected $mimeType;

  /**
   * @ODM\Int
   * @Serializer\Expose
   */
  protected $length;

  /**
   * @ODM\String
   * @ODM\Index
   *
   * @Serializer\Expose
   */
  protected $md5;

  /**
   * @ODM\Boolean
   * @Serializer\Expose
   */
  protected $temporary = false;

  /**
   * Create file
   *
   * @param string|GridFSFile $file
   * @throws FileDownloadErrorException
   */
  public function __construct($file = null)
  {
    // download remote file
    if (is_string($file) && preg_match('#^http(s)?://#', $file)) {

      // create temporary file
      $this->_tmpFile = tempnam(null, '');

      $content = @file_get_contents($file, null, null, 0, 1024 * 1024);

      if (false == $content || !file_put_contents($this->_tmpFile, $content)) {

        if (file_exists($this->_tmpFile)) {
          @unlink($this->_tmpFile);
        }

        throw new FileDownloadErrorException();
      }

      $this->localFileIsTemporary = true;
      $file                       = $this->_tmpFile;
    }

    $this->file = $file;
  }

  /**
   * Remove temporary file
   */
  public function cleanup()
  {
    if ($this->localFileIsTemporary && !is_null($this->_tmpFile) && file_exists($this->_tmpFile)) {
      @unlink($this->_tmpFile);
      $this->_tmpFile = null;
    }
  }

  /**
   * @ODM\PreFlush
   */
  public function onPreFlush()
  {
    $this->_updateFileInfo();
  }

  /**
   * Update file information
   */
  protected function _updateFileInfo()
  {
    if (is_string($this->file)) {
      if (file_exists($this->file)) {
        // length
        if (is_null($this->length)) {
          $this->length = filesize($this->file);
        }

        // mime type
        if (is_null($this->mimeType)) {
          $fi             = finfo_open(\FILEINFO_MIME_TYPE);
          $this->mimeType = finfo_file($fi, $this->file);
          finfo_close($fi);
        }

        // md5
        if (is_null($this->md5)) {
          $this->md5 = md5_file($this->file);
        }
      } else {
        throw new \Exception('File does not exist');
      }
    } else {
      if ($this->file instanceof GridFSFile) {
        // mime type
        if (is_null($this->mimeType)) {
          $fi             = finfo_open(\FILEINFO_MIME_TYPE);
          $this->mimeType = finfo_buffer($fi, $this->file->getBytes());
          finfo_close($fi);
        }

        // length
        if (is_null($this->length)) {
          $this->length = $this->file->getSize();
        }

        // md5
        if (is_null($this->md5)) {
          $this->md5 = md5($this->file->getBytes());
        }
      }
    }
  }

  public function getMimeType()
  {
    if (is_null($this->mimeType)) {
      $this->_updateFileInfo();
    }

    return $this->mimeType;
  }

  public function getLength()
  {
    if (is_null($this->length)) {
      $this->_updateFileInfo();
    }

    return $this->length;
  }

  private function resetFileInfo()
  {
    $this->length   = null;
    $this->md5      = null;
    $this->mimeType = null;
  }

  public function setFile($file)
  {
    $this->file = $file;
    $this->resetFileInfo();
  }

  public function getMd5()
  {
    if (is_null($this->md5)) {
      $this->_updateFileInfo();
    }

    return $this->md5;
  }

  // <editor-fold defaultstate="collapsed" desc="Accessors">

  public function getId()
  {
    return $this->id;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function getFile()
  {
    return $this->file;
  }

  public function setMimeType($mimeType)
  {
    $this->mimeType = $mimeType;
  }

  public function getLocalFileIsTemporary()
  {
    return $this->localFileIsTemporary;
  }

  public function setLocalFileIsTemporary($localFileIsTemporary)
  {
    $this->localFileIsTemporary = $localFileIsTemporary;
  }

  public function setLength($length)
  {
    $this->length = $length;
  }

  public function get_tmpFile()
  {
    return $this->_tmpFile;
  }

  public function set_tmpFile($_tmpFile)
  {
    $this->_tmpFile = $_tmpFile;
  }

  public function setMd5($md5)
  {
    $this->md5 = $md5;
  }

  public function getTemporary()
  {
    return $this->temporary;
  }

  public function setTemporary($temporary)
  {
    $this->temporary = $temporary;
  }

  // </editor-fold>
}
