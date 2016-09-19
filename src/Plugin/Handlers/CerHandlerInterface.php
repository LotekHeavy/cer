<?php

namespace Drupal\cer\Plugin\Handlers;

interface CerHandlerInterface {

  /**
   * @constructor
   *
   * @param string $preset
   *  The CER preset string, in the format:
   *  entity_a*bundle_a*field_a*entity_b*bundle_b*field_b.
   *
   * @param $entity.
   *  The local (home) entity to be wrapped by this instance.
   */
  public function __construct($preset, $entity);

  /**
   * Create reciprocal references on referenced entities after the
   * local entity has been created.
   */
  public function insert();

  /**
   * Delete reciprocal references on entities the local entity is no
   * longer referencing, and create new reciprocal references, after
   * the local entity has been updated.
   */
  public function update();

  /**
   * Delete all reciprocal references after the local entity is deleted.
   */
  public function delete();
  
  /**
   * Check if $entity is referenced by the local entity.
   *
   * @param object $entity
   *  The remote entity.
   *
   * @return boolean
   */
  public function references($entity);

  /**
   * Check if the local entity is referenced by $entity.
   *
   * @param object $entity
   *  The remote entiy.
   *
   * @return boolean
   */
  public function referencedBy($entity);
  
  /**
   * Check if the remote entity can reference the local entity, and vice-versa.
   *
   * @param object $entity
   *  The remote entity.
   *
   * @return boolean
   */
  public function referenceable($entity);

  /**
   * Create a reference to the local entity on the remote entity, and vice-versa
   * if needed. Should throw CerException if the reference(s) can't be created
   * for any reason.
   *
   * @param object $entity
   */
  public function reference($entity);

  /**
   * Delete all references to the remote entity from the local entity,
   * and delete reciprocal references from the remote entity.
   *
   * @param object $entity.
   */
  public function dereference($entity);

}

