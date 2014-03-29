<?php

/**
 * Radable Id generation
 * @copyright 2013 Mikhail Yurasov <me@yurasov.me>
 */

namespace mym\MongoDB\ODM\Traits;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

trait ReadableIdTrait
{
  /**
   * @ODM\String
   * @ODM\Index
   * @Assert\NotNull
   * @Serializer\Expose
   */
  protected $readableId;

  public function createReadableId()
  {
    $this->readableId = "";

    $variants = $this->getReadableIdVariants();

    // try variants
    foreach ($variants as $variant) {
      $variant = $this->formatReadableId($variant);

      if (!$this->readableIdExists($variant)) {
        $this->readableId = $variant;

        return;
      }
    }

    // generate id with digits
    $this->generateDigitalReadableId($variants[0]);
  }

  protected function readableIdExists()
  {
    return false;
  }

  protected function formatReadableId($id)
  {
    $id = transliterator_transliterate(
      "Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();",
    $id);

    $id = preg_replace('/([^a-z0-9])/iu', '-', $id);
    $id = preg_replace('/-+/u', '-', $id);
    $id = trim($id, '-');

    return $id;
  }

  protected function getReadableIdVariants()
  {
    if (property_exists($this, 'name')) {
      return [$this->name];
    } else {
      throw new \Exception('"name" property doesn\'t exist, please redefine ' . __CLASS__ . '::' . __METHOD__ . '()');
    }
  }

  protected function generateDigitalReadableId($variant)
  {
    $this->readableId = "";
    $variant = $this->formatReadableId($variant);


    $i = 0;

    // name-2
    do {
      $id = $this->formatReadableId($variant . ' ' . ($i + 2));
    } while ($this->readableIdExists($id) && ++$i < 50);

    // try to create id with random 2-digit postfix
    if ($i == 50) {
      do {
        $id = $this->formatReadableId($variant . ' ' . mt_rand(0, 99));
      } while ($this->readableIdExists($id) && ++$i < 1000000);
    }

    // failure, iterate postfix until match not found
    if ($i == 1000000) {
      do {
        $id = $this->formatReadableId($variant . ' ' . $i++);
      } while ($this->readableIdExists($id));
    }

    // save id

    $this->readableId = $id;
  }

  public function getReadableId()
  {
    return $this->readableId;
  }

  public function setReadableId($readableId)
  {
    $this->readableId = $readableId;
  }

  /**
   * @ODM\PrePersist
   */
  public function updateReadableId()
  {
    if ($this->readableId === null) {
      $this->createReadableId();
    }
  }
}
