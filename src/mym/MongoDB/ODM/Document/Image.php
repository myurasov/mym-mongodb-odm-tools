<?php

/**
 * Image document
 *
 * @copyright 2012-2014, Mikhail Yurasov
 */

namespace mym\MongoDB\ODM\Document;

use Doctrine\MongoDB\GridFSFile;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ODM\Document(
 *  collection="images"
 * )
 *
 * @ODM\MappedSuperclass
 * @ODM\HasLifecycleCallbacks
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Image extends File
{
  /**
   * @ODM\Int
   * @Serializer\Expose
   */
  protected $width;

  /**
   * @ODM\Int
   * @Serializer\Expose
   */
  protected $height;

  protected $jpegQuality = 85;
  protected $copyOnResize = false;
  protected $doNotEnlarge = true;
  protected $bestFill = false; // fill dimensions with image

  /**
   * Resize image
   *
   * @param int $width
   * @param int $height
   * @throws \Exception
   */
  public function resize($width, $height)
  {
    if (is_string($this->file)) {

      if (!file_exists($this->file)) {
        throw new \Exception("Source file doesn't exist");
      }

      if ($this->copyOnResize) {
        // copy file
        $this->_tmpFile = tempnam(null, '');
        copy($this->file, $this->_tmpFile);

        $this->localFileIsTemporary = true;
        $this->file                 = $this->_tmpFile;
      }
    }

    // mime type shouild be set before resizing
    if ($this->getMimeType() == 'image/jpeg') {
      $format = 'jpeg';
    } else {
      if ($this->getMimeType() == 'image/png') {
        $format = 'png';
      } else {
        throw new \Exception(sprintf('Unsupported mime type: "%s"', $this->getMimeType()));
      }
    }

    // resize image

    $im = new \Imagick();

    // read file
    if ($this->file instanceof GridFSFile) {
      $im->readImageBlob($this->file->getBytes());
    } else {
      if (is_string($this->file)) {
        $im->readImage($this->file);
      }
    }

    $im->setImageFormat($format);

    if ($format == "jpeg") {
      $im->setImageCompressionQuality($this->jpegQuality);
    }

    // get image dimensions
    $imageWidth  = $im->getImageWidth();
    $imageHeight = $im->getImageHeight();

    if ($this->bestFill) { // best fill

      // find the resize ratio
      $widthRatio  = $imageWidth / $width;
      $heightRatio = $imageHeight / $height;
      $ratio       = min($widthRatio, $heightRatio);

      // find the new dimensions for the image
      $imageWidth  = round($imageWidth / $ratio);
      $imageHeight = round($imageHeight / $ratio);

      // resize image
      $im->thumbnailImage($imageWidth, $imageHeight);

      // cut excess
      $excessWidth  = $imageWidth - $width;
      $excessHeight = $imageHeight - $height;
      $im->cropImage($width, $height, round($excessWidth / 2), round($excessHeight / 2));

    } else { // best fit

      // do not enlarge images
      if ($this->doNotEnlarge) {
        $width  = min($imageWidth, $width);
        $height = min($imageHeight, $height);
      }

      $im->thumbnailImage($width, $height, true);
    }

    // correct orientation
    $this->correctOrientation($im);

    // remove any profiles

    $profiles = ($im->getImageProfiles("*", false));

    for ($i = 0; $i < count($profiles); $i++) {
      $im->profileImage($profiles[$i], null);
    }

    // write to file

    if ($this->file instanceof GridFSFile) {
      $this->file->setBytes($im->getImagesBlob());
    } else {
      if (is_string($this->file)) {
        $im->writeImage($this->file);
        clearstatcache(false, $this->file); // update file info
      }
    }

    // get dimensions
    $this->width  = $im->getImageWidth();
    $this->height = $im->getImageHeight();

    $im->destroy();
  }

  /**
   * @ODM\PreFlush
   */
  public function onPreFlush()
  {
    parent::onPreFlush();
    $this->updateDimensions();
  }

  public function getWidth()
  {
    if (is_null($this->width)) {
      $this->updateDimensions();
    }

    return $this->width;
  }

  public function getHeight()
  {
    if (is_null($this->height)) {
      $this->updateDimensions();
    }

    return $this->height;
  }

  /**
   * Detect image dimensions
   */
  private function updateDimensions()
  {
    if (is_null($this->width) || is_null($this->height)) {

      $im = new \Imagick();

      // read file
      if ($this->file instanceof GridFSFile) {
        $im->readImageBlob($this->file->getBytes());
      } else {
        if (is_string($this->file)) {
          $im->readImage($this->file);
        }
      }

      $this->width  = $im->getImageWidth();
      $this->height = $im->getImageHeight();
      $im->destroy();
    }
  }

  /**
   * Corrects orientation of an image
   * @param \Imagick $im
   */

  private function correctOrientation(\Imagick & $im)
  {
    switch ($im->getImageOrientation()) {

      case \Imagick::ORIENTATION_TOPRIGHT:
        $im->flipImage();
        break;

      case \Imagick::ORIENTATION_BOTTOMRIGHT:
        $im->rotateImage(new \ImagickPixel("none"), 180);
        break;

      case \Imagick::ORIENTATION_BOTTOMLEFT:
        $im->rotateImage(new \ImagickPixel("none"), 180);
        $im->flipImage();
        break;

      case \Imagick::ORIENTATION_LEFTTOP:
        $im->rotateImage(new \ImagickPixel("none"), 90);
        $im->flipImage();
        break;

      case \Imagick::ORIENTATION_RIGHTTOP:
        $im->rotateImage(new \ImagickPixel("none"), 90);
        break;

      case \Imagick::ORIENTATION_RIGHTBOTTOM:
        $im->rotateImage(new \ImagickPixel("none"), -90);
        $im->flipImage();
        break;

      case \Imagick::ORIENTATION_LEFTBOTTOM:
        $im->rotateImage(new \ImagickPixel("none"), -90);
        break;

      default:
    }
  }

  // <editor-fold defaultstate="collapsed" desc="accessors">

  public function setWidth($width)
  {
    $this->width = $width;
  }

  public function setHeight($height)
  {
    $this->height = $height;
  }

  public function getCopyOnResize()
  {
    return $this->copyOnResize;
  }

  public function setCopyOnResize($copyOnResize)
  {
    $this->copyOnResize = $copyOnResize;
  }

  public function getJpegQuality()
  {
    return $this->jpegQuality;
  }

  public function setJpegQuality($jpegQuality)
  {
    $this->jpegQuality = $jpegQuality;
  }

  public function getDoNotEnlarge()
  {
    return $this->doNotEnlarge;
  }

  public function setDoNotEnlarge($doNotEnlarge)
  {
    $this->doNotEnlarge = $doNotEnlarge;
  }

  public function getBestFill()
  {
    return $this->bestFill;
  }

  public function setBestFill($bestFill)
  {
    $this->bestFill = $bestFill;
  }

  // </editor-fold>
}
