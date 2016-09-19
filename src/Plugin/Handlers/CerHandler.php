<?php

namespace Drupal\cer\Plugin\Handlers;

use Drupal\cer\Plugin\Handlers\CerHandlerBase;
use Drupal\cer\Plugin\Handlers\CerHandlerInterface;
use Drupal\cer\Exception\CerException;

/**
 * @file
 * Contains base code for CER handlers, which are objects responsible for
 * creating, updating and deleting corresponding references between entities.
 */

/**
 * @class
 * Generic CER handler with rudimentary language handling.
 */
class CerHandler extends CerHandlerBase implements CerHandlerInterface {

  /**
   * The local (home) entity.
   */
  protected $entity;

  /**
   * The local entity's ID.
   */
  protected $id;

  /**
   * Implements CerHandlerInterface::__construct().
   */
  public function __construct($preset, $entity) {
    parent::__construct($preset);
    $this->id = $entity->id();
    $this->entity = $entity;
  }

  /**
   * Implements CerHandlerInterface::insert().
   */
  public function insert($ids = NULL) {
    if (empty($ids)) {
      $entities = $this->getReferencedEntities();
    }
    else {
      $entities = entity_load_multiple($this->remote->getTargetEntityTypeId(), $ids);
    }
  
    foreach ($entities as $referenced_entity) {
      $this->reference($referenced_entity);
      _cer_update($this->remote->getTargetEntityTypeId(), $referenced_entity);
    }
  }

  /**
   * Implements CerHandlerInterface::update().
   */
  public function update() {
    $original = isset($this->entity->original) ? $this->entity->original : $this->entity;

    $deleted = array_diff($this->getReferenceIDs($original, $this->local), $this->getLocalReferenceIDs());
    if ($deleted) {
      $entities = entity_load_multiple($this->remote->getTargetEntityTypeId(), $deleted);
      foreach ($entities as $referenced_entity) {
        $this->dereference($referenced_entity);
        _cer_update($this->remote->getTargetEntityTypeId(), $referenced_entity);
      }
    }

    $added = array_diff($this->getLocalReferenceIDs(), $this->getReferenceIDs($original, $this->local));
    if (!empty($added)) {
      $this->insert($added);
    }
  }

  /**
   * Implements CerHandlerInterface::delete().
   */
  public function delete() {
    foreach ($this->getReferencedEntities() as $referenced_entity) {
      $this->dereference($referenced_entity);
      _cer_update($this->remote->getTargetEntityTypeId(), $referenced_entity);
    }
  }

  /**
   * Implements CerHandlerInterface::references().
   */  
  public function references($entity) {
    return in_array($this->getRemoteEntityID($entity), $this->getLocalReferenceIDs());
  }

  /**
   * Implements CerHandlerInterface::referencedBy().
   */
  public function referencedBy($entity) {
    return in_array($this->id, $this->getRemoteReferenceIDs($entity));
  }

  /**
   * Implements CerHandlerInterface::referenceable().
   */
  public function referenceable($entity) {
    // @todo re-implement this for real
    return TRUE;
    /*
    $id = $this->getRemoteEntityID($entity);

    $allowed = array(
      entityreference_get_selection_handler(
        $this->local['field'],
        $this->local,
        $this->local['entity_type'],
        $this->entity
      )
        ->validateReferencableEntities(array($id)),
      entityreference_get_selection_handler(
        $this->remote['field'],
        $this->remote,
        $this->remote['entity_type'],
        $entity
      )
        ->validateReferencableEntities(array($this->id)),
    );

    return in_array($id, $allowed[0]) && in_array($this->id, $allowed[1]);
     */
  }

  /**
   * Implements CerHandlerInterface::reference().
   */
  public function reference($entity) {
    if ($this->referenceable($entity)) {
      try {
        $this->addReferenceTo($entity);
      }
      catch (CerException $e) {
        // Fail silently
      }
    
      try {
        $this->addReferenceFrom($entity);
      }
      catch (CerException $e) {
        // Fail silently
      }
    }
    else {
      $variables = array(
        '!local_field' => $this->local['field_name'],
        '!local_type' => $this->local['entity_type'],
        '!local_id' => $this->id,
        '!remote_field' => $this->remote['field_name'],
        '!remote_type' => $this->remote['entity_type'],
        '!remote_id' => $this->getRemoteEntityID($entity),
      );
      watchdog('cer', 'Failed to reference !remote_field on !remote_type !remote_id from !local_field on !local_type !local_id.', $variables, WATCHDOG_ERROR);
    }
  }

  /**
   * Implements CerHandlerInterface::dereference().
   */
  public function dereference($entity) {
    if ($this->references($entity)) {
      $field_name = $this->local->getName();
      $id = $this->getRemoteEntityID($entity);

      foreach ($this->entity->get($field_name)->getValue() as $delta => $reference) {
        if ($reference['target_id'] == $id) {
          $this->entity->get($field_name)->removeItem($delta);
        }
      }
    }

    if ($this->referencedBy($entity)) {
      $field_name = $this->remote->getName();

      foreach ($entity->get($field_name)->getValue() as $delta => $reference) {
        if ($reference['target_id'] == $this->id) {
          $entity->get($field_name)->removeItem($delta);
        }
      }
    }
  }

  /**
   * Creates a reference to the local entity on the remote entity. Throws CerException
   * if the local entity is already referenced by the remote entity, or if the remote
   * field cannot hold any more values.
   *
   * @param object $entity
   *  The remote entity.
   */ 
  protected function addReferenceFrom($entity) {
    if ($this->referencedBy($entity)) {
      throw new CerException(t('Cannot create duplicate reference from remote entity.'));
    }
    /*
     * @todo reimplement this
    elseif ($this->filled($this->getRemoteReferenceIDs($entity), $this->remote)) {
      //throw new CerException(t('Remote field cannot support any more references.'));
    }
    */
    else {
      $field_name = $this->remote->getName();
      $entity->get($field_name)->appendItem($this->id);
    }
  }

  /**
   * Creates a reference to the remote entity on the local entity. Throws CerException
   * if the local entity already references the remote entity, or if the field cannot
   * hold any more values.
   *
   * @param object $entity
   *  The remote entity.
   */
  protected function addReferenceTo($entity) {
    $id = $this->getRemoteEntityID($entity);

    if ($this->references($entity)) {
      throw new CerException(t('Cannot create duplicate reference to remote entity.'));
    }
    /*
     * @todo reimplement this
    elseif ($this->filled($this->getLocalReferenceIDs(), $this->local['field'])) {
      //throw new CerException(t('Local field cannot support any more references.'));
    }
    */
    else {
      $field_name = $this->local->getName();
      $this->entity->get($field_name)->appendItem($id);
    }
  }

  /**
   * Get the ID of the remote entity. If the entity is of the wrong type,
   * EntityMalformedException will be thrown.
   *
   * @param object $entity
   *  The remote entity.
   *
   * @return mixed
   *  The remote entity ID.
   */
  protected function getRemoteEntityID($entity) {
    return $entity->id();
  }

  /**
   * Gets all the entities referenced by the local entity.
   *
   * @return array
   *  Array of fully loaded referenced entities keyed by ID, or empty
   *  array if nothing has been referenced.
   */
  protected function getReferencedEntities() {
    $IDs = $this->getLocalReferenceIDs();
    return $IDs ? entity_load_multiple($this->entity->getEntityTypeId(), $IDs) : array();
  }

  /**
   * Gets the IDs of the entities referenced by the local entity.
   *
   * @return array
   *  Array of entity IDs, empty array if there are no references.
   */
  protected function getLocalReferenceIDs() {
    return $this->getReferenceIDs($this->entity, $this->local);
  }

  /**
   * Gets the IDs of the entities referenced by $entity.
   *
   * @param object $entity
   *  The remote entity.
   *
   * @return array
   *  Array of entity IDs, empty array if there are no references.
   */
  protected function getRemoteReferenceIDs($entity) {
    return $this->getReferenceIDs($entity, $this->remote);
  }

  /**
   * Check if a field can support any more values. Formerly known as
   * "reference overloading".
   *
   * @param array $references
   *  The values in the field.
   *
   * @param $field
   *  Field definition (i.e., from field_info_field).
   *
   * @return boolean
   */
  private function filled($references, $field) {
    return false;
    //return $field['cardinality'] != FIELD_CARDINALITY_UNLIMITED && sizeof($references) >= $field['cardinality'];
  }

  /**
   * Gets all the referenced entity IDs from a specific field on $entity.
   *
   * @param object $entity
   *  The entity to scan for references.
   *
   * @param array $field
   *  Field or instance definition.
   *
   * @return array
   *  Array of unique IDs, empty if there are no references or the field
   *  does not exist on $entity.
   */
  private function getReferenceIDs($entity, $field) {
    $IDs = array();
    $field_name = $field->getName();
  
    foreach ($entity->get($field_name)->getValue() as $reference) {
      $IDs[] = $reference['target_id'];
    }

    return array_unique(array_filter($IDs));
  }

}
