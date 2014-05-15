<?php

/**
 * Random string Id generator
 * @copyright 2014 Mikhail Yurasov <me@yurasov.me>
 */

namespace mym\MongoDB\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Id\AbstractIdGenerator;
use mym\Util\Strings;

class RandomStringIdGenerator extends AbstractIdGenerator
{
  const ALPHABET_BIN = '01';
  const ALPHABET_OC = '01234567';
  const ALPHABET_HEX = '01234567890abcdef';
  const ALPHABET_ALNUM = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  const ALPHABET_ALNUM_LOWCASE = '0123456789abcdefghijklmnopqrstuvwxyz';
  const ALPHABET_DIGITS = '0123456789';

  private $bitStrength = 128;
  private $alphabet = self::ALPHABET_ALNUM;

  /**
   * Generates an identifier for a document.
   *
   * @param \Doctrine\ODM\MongoDB\DocumentManager $dm
   * @param object $document
   * @return string
   */
  public function generate(DocumentManager $dm, $document)
  {
    return Strings::createRandomString(null, $this->alphabet, $this->bitStrength);
  }

  public function setBitStrength($bitStrength)
  {
    $this->bitStrength = $bitStrength;
  }

  public function setAlphabet($alphabet)
  {
    $this->alphabet = $alphabet;
  }
}