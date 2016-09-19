<?php

/**
 * @file
 * Contains \Drupal\cer\Form\CerSettingsForm.
 */

namespace Drupal\cer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CerSettingsForm extends FormBase {

  protected $field_manager;
  protected $config;

  public function __construct(EntityFieldManager $field_manager, ConfigFactory $config) {
    $this->field_manager = $field_manager;
    $this->config = $config->getEditable('cer.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $channels = array();

    foreach ($this->getFields() as $field) {
      foreach ($field['bundles'] as $entity_type => $bundles) {
        foreach ($bundles as $bundle) {
          $instance = $this->field_manager
            ->getFieldDefinitions($entity_type, $bundle)[$field['field_name']];

          if ($instance instanceOf \Drupal\field\Entity\FieldConfig) {
            $channels = array_merge($channels, $this->findChannels($instance));
          }
        }
      }
    }

    if (empty($channels)) {
      drupal_set_message(t('There are no entity reference fields that can correspond'), 'warning');
    }
    else {
      $mapping = array();
      $presets = $this->config->get('presets');

      foreach ($channels as $count => $key) {
        $formatted_key = str_replace(' ', '*', $key);
        $mapping[$count] = $formatted_key;

        $form["enabled_{$count}"] = array(
          '#title' => Xss::filter(_cer_label($key)),
          '#type' => 'checkbox',
          '#default_value' => empty($presets[$formatted_key]) ? FALSE : $presets[$formatted_key],
        );
      }
    }

    if (!empty($mapping)) {
      $form['mapping'] = array(
        '#type' => 'hidden',
        '#value' => serialize($mapping)
      );

      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save')
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // no validation for now
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = array();
    $values = $form_state->getValues();
    $mapping = unserialize($values['mapping']);

    foreach ($values as $key => $value) {
      $keys = explode('_', $key);
      if ($keys[0] == 'enabled') {
        $config[$mapping[$keys[1]]] = $value;
      }
    }

    $this->config->set('presets', $config);
    $this->config->save();
  }

  private function getTargetBundles($field) {
    $target_bundles = array();

    // @todo re-implement the views stuff if needed?

    if (isset($field['handler_settings']['target_bundles'])) {
      $target_bundles = $field['handler_settings']['target_bundles'];
    }

    return $target_bundles;
  }

  private function findChannels($instance) {
    $channels = array();

    $my_id = $instance->getTargetEntityTypeId();
    $my_id .= ' ' . $instance->getTargetBundle();
    $my_id .= ' ' . $instance->getName();

    $my_info = $instance->getSettings();
    $my_targets = $this->getTargetBundles($my_info);
    $my_target_type = $my_info['target_type'];

    $referrers = $this->findReferrers(
      $instance->getTargetEntityTypeId(),
      $instance->getTargetBundle(),
      $my_target_type
    );

    foreach ($referrers as $referrer) {
      if (isset($referrer['bundles'][$my_target_type])) {
        if (empty($my_targets)) {
          dpm('empty'); dpm($my_targets);
          $bundles = $referrer['bundles'][$my_target_type];
        }
        else {
          $bundles = array_intersect(
            $referrer['bundles'][$my_target_type], $my_targets);
        }

        foreach ($bundles as $bundle) {
          $channels[] = "{$my_id} {$my_target_type} {$bundle} "
            . $referrer['field_name'];
        }
      }
    }

    return $channels;
  }

  private function findReferrers($entity_type, $bundle, $target_type) {
    $referrers = array();

    foreach ($this->getFields() as $field) {
      if ($entity_type == $target_type) {
        $target_bundles = $this->getTargetBundles($field);

        if (empty($target_bundles) || in_array($bundle, $target_bundles)) {
          $referrers[] = $field;
        }
      }
    }

    return $referrers;
  }

  private function getFields() {
    $fields = $this->field_manager->getFieldMapByFieldType('entity_reference');
    $return = array();

    foreach ($fields as $entity_type => $field) {
      foreach ($field as $field_name => $field_data) {
        // lets only deal with custom fields
        // assuming they're all prefixed with 'field_'
        if (!preg_match('/^field_.*$/', $field_name)) {
          continue;
        }

        foreach ($field_data['bundles'] as $bundle) {
          $field_data['bundles'][$entity_type][] = $bundle;
          $field_data['field_name'] = $field_name;
          unset($field_data['bundles'][$bundle]);
        }

        $return[] = $field_data;
      }
    }

    return $return;
  }

}


