<?php

namespace Drupal\media_entity_image\Plugin\EntityBrowser\Widget;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload as FileUpload;
use Drupal\media_entity\Entity\Media;

/**
 * Uses upload to create media entity images.
 *
 * @EntityBrowserWidget(
 *   id = "media_entity_image_upload_fields",
 *   label = @Translation("Upload images and edit fields"),
 *   description = @Translation("Upload widget that creates media entity images and allows you to bulk set entity fields.")
 * )
 */
class UploadWithFields extends Upload {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_display' => 'entity_browser',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $aditional_widget_parameters);

    // Create an empty entity that will be used to generate teh entity form.
    $entity = Media::create([
      'bundle' => $this->configuration['media_bundle'],
    ]);

    $form['entity_form'] = [];
    $form_display = EntityFormDisplay::collectRenderDisplay($entity, $this->configuration['form_display']);
    $form_display->buildForm($entity, $form['entity_form'], $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    parent::submit($element, $form, $form_state);

    $images = $form_state->get(['entity_browser', 'selected_entities']);

    // Create an empty entity that will be used to generate teh entity form.
    $bundle = $this->configuration['media_bundle'];
    $field_list = $this->entityManager->getFieldDefinitions('media', $bundle);

    // Get the list of fields that have been configured for this entity
    $configured_fields = [];
    foreach ($field_list as $field_name => $field_definition) {
      if ( $form_state->hasValue($field_name) ) {
        $configured_fields[] =  $field_name;
      }
    }
    // No need for this anymore
    unset($field_list);

    // propagate the configured values to all selected image entities
    if ( !empty($configured_fields) ) {
      foreach ($images as $image_entity) {
        foreach ($configured_fields as $field_name) {
          $image_entity->set($field_name, $form_state->getValue($field_name));
        }
        // Save each image after setting the fields
        $image_entity->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['form_display'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form Display'),
      '#default_value' => $this->configuration['form_display'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * @param \Drupal\media_entity\Entity\Media $image
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  private function applyFields(Media $image, array $form, FormStateInterface $form_state) {

  }

}
