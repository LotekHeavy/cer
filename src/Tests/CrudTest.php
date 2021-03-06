<?php

namespace Drupal\cer\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests basic CER functionality
 *
 * @group cer
 *
 */

class CrudTest extends WebTestBase {

  public static $modules = array('field', 'cer');

  public function setUp() {
    parent::setUp();

    field_create_field(array(
      'field_name' => 'field_user',
      'type' => 'entityreference',
      'cardinality' => -1,
      'settings' => array(
        'target_type' => 'user',
      ),
    ));
    field_create_field(array(
      'field_name' => 'field_node',
      'type' => 'entityreference',
      'cardinality' => -1,
      'settings' => array(
        'target_type' => 'node',
      ),
    ));

    field_create_instance(array(
      'field_name' => 'field_user',
      'entity_type' => 'node',
      'bundle' => 'page',
    ));
    field_create_instance(array(
      'field_name' => 'field_node',
      'entity_type' => 'user',
      'bundle' => 'user',
    ));

    db_insert('cer')->fields(array(
      'entity_types_content_fields' => 'node*page*field_user*user*user*field_node',
      'enabled' => TRUE,
    ))->execute();
  }

  public function testImplicitReferenceCreation() {
    $uid = $this->drupalCreateUser()->uid;
    
    $referrers = array();
    for ($i = 0; $i < 5; $i++) {
      $referrers[] = $this->drupalCreateNode(array(
        'type' => 'page',
        'field_user' => array(
          'und' => array(
            array('target_id' => $uid),
          ),
        ),
      ))->nid;
    }

    $references = array();
    foreach (user_load($uid, TRUE)->field_node['und'] as $reference) {
      $references[] = $reference['target_id'];
    }
    $this->assertFalse(array_diff($referrers, $references), 'Creating 5 referrers to a single entity creates 5 corresponding references on that entity.', 'CER');
  }

  public function testDuplicateReferencePrevention() {
    $uid = $this->drupalCreateUser()->uid;

    $this->drupalCreateNode(array(
      'type' => 'page',
      'field_user' => array(
        'und' => array(
          array('target_id' => $uid),
          array('target_id' => $uid),
        ),
      ),
    ));

    $account = user_load($uid, TRUE);
    $this->assertEqual(sizeof($account->field_node['und']), 1, 'Creating two references to an entity from a single referrer creates one corresponding reference.', 'CER');
  }

  public function testExplicitReferenceCreation() {
    $uid = $this->drupalCreateNode()->uid;

    $node = $this->drupalCreateNode(array('type' => 'page'));
    $node->field_user['und'][0]['target_id'] = $uid;
    node_save($node);

    $account = user_load($uid, TRUE);
    $this->assertEqual($account->field_node['und'][0]['target_id'], $node->nid, 'Creating an explicit reference between to unrelated entities creates a corresponding reference.', 'CER');
  }

  public function testExplicitDereference() {
    $uid = $this->drupalCreateUser()->uid;

    $nid = $this->drupalCreateNode(array(
      'type' => 'page',
      'field_user' => array(
        'und' => array(
          array('target_id' => $uid),
        ),
      ),
    ))->nid;

    $account = user_load($uid, TRUE);
    $account->field_node = array();
    user_save($account);

    $node = node_load($nid, NULL, TRUE);
    $this->assertFalse($node->field_user, 'Explicitly clearing a reference from the referenced entity clears the corresponding reference on the referrer.', 'CER');
  }

  public function testReferrerDeletion() {
    $uid = $this->drupalCreateUser()->uid;
    
    $referrers = array();
    for ($i = 0; $i < 5; $i++) {
      $referrers[] = $this->drupalCreateNode(array(
        'type' => 'page',
        'field_user' => array(
          'und' => array(
            array('target_id' => $uid),
          ),
        ),
      ))->nid;
    }

    node_delete($referrers[0]);

    $references = array();
    foreach (user_load($uid, TRUE)->field_node['und'] as $reference) {
      $references[] = $reference['target_id'];
    }
    $this->assertFalse(in_array($referrers[0], $references), 'Deleting a referrer clears corresponding reference on the referenced entity.', 'CER');
  }

  public function testReferencedEntityDeletion() {
    $uid = $this->drupalCreateUser()->uid;

    $referrers = array();
    for ($i = 0; $i < 5; $i++) {
      $referrers[] = $this->drupalCreateNode(array(
        'type' => 'page',
        'field_user' => array(
          'und' => array(
            array('target_id' => $uid),
          ),
        ),
      ))->nid;
    }
    user_delete($uid);

    $cleared = 0;
    foreach ($referrers as $nid) {
      $node = node_load($nid, NULL, TRUE);
      $cleared += (int) empty($node->field_user);
    }
    $this->assertEqual($cleared, sizeof($referrers), 'Deleting a referenced entity clears all references to it.', 'CER');
  }

}

