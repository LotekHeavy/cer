<?php

namespace Drupal\cer\Plugin\Handlers;

/**
 * @class
 * Base class for CER handlers. All this does is parse the preset
 * and store instance info about the local and remote fields.
 */
abstract class CerHandlerBase {

  /**
   * Local field instance definition.
   */
  protected $local;

  /**
   * Remote field instance definition.
   */
  protected $remote;

  public function __construct($preset) {
    $keys = explode('*', $preset);

    if (sizeof($keys) != 6) {
      throw new CerException(t('Invalid configuration: @preset', array('@preset' => $preset)));
    }

    $this->local = \Drupal\field\Entity\FieldConfig::loadByName($keys[0], $keys[1], $keys[2]);
    $this->remote = \Drupal\field\Entity\FieldConfig::loadByName($keys[3], $keys[4], $keys[5]);
  }

}

