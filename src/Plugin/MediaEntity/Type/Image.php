<?php

/**
 * @file
 * Contains \Drupal\media_entity_image\Plugin\MediaEntity\Type\Image.
 */

namespace Drupal\media_entity_image\Plugin\MediaEntity\Type;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media_entity\MediaBundleInterface;
use Drupal\media_entity\MediaInterface;
use Drupal\media_entity\MediaTypeException;
use Drupal\media_entity\MediaTypeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides media type plugin for Image.
 *
 * @MediaType(
 *   id = "image",
 *   label = @Translation("Image"),
 *   description = @Translation("Provides business logic and metadata for local images.")
 * )
 */
class Image extends PluginBase implements MediaTypeInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Plugin label.
   *
   * @var string
   */
  protected $label;

  /**
   * The entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityManager;
   */
  protected $entityManager;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManager $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function providedFields() {
    $fields = array();
    foreach ($this->configuration['exif_field_map'] as $field_name => $exif_name) {
      $fields[$field_name] = $exif_name;
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getField(MediaInterface $media, $name) {
    $source_field = $this->configuration['source_field'];
    $property_name = $media->{$source_field}->first()->mainPropertyName();

    // Get the exif.
    $file = $this->entityManager->getStorage('file')->load($media->{$source_field}->first()->{$property_name});
    $exif = $this->getExif($file->getFileUri());

    // Return the field.
    if (!empty($this->configuration['exif_field_map'][$name]) && !empty($exif[$this->configuration['exif_field_map'][$name]])) {
      return $exif[$this->configuration['exif_field_map'][$name]];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(MediaBundleInterface $bundle) {
    $form = array();

    $options = array();
    $allowed_field_types = array('file', 'image');
    foreach ($this->entityManager->getFieldDefinitions('media', $bundle->id()) as $field_name => $field) {
      if (in_array($field->getType(), $allowed_field_types) && !$field->getFieldStorageDefinition()->isBaseField()) {
        $options[$field_name] = $field->getLabel();
      }
    }

    $form['source_field'] = array(
      '#type' => 'select',
      '#title' => t('Field with source information'),
      '#description' => t('Field on media entity that stores Image file.'),
      '#default_value' => empty($this->configuration['image']['source_field']) ? NULL : $this->configuration['source_field'],
      '#options' => $options,
    );

    $form['exif_field_map'] = array(
      '#type' => 'fieldset',
      '#title' => t('Match your fields with the exif fields'),
      '#description' => t('Create a Text(plain) field for every Exif property that you want to save.'),
    );

    // @todo We should combine this with the UI for the field mapping when we
    // have one.
    foreach ($this->entityManager->getFieldDefinitions('media', $bundle->id()) as $field_name => $field) {
      if ($field->getType() == 'string' && !$field->getFieldStorageDefinition()->isBaseField()) {
        $form['exif_field_map'][$field_name] = array(
          '#type' => 'textfield',
          '#title' => $field->getLabel(),
          '#size' => 60,
          '#default_value' => empty($this->configuration['exif_field_map'][$field_name]) ? NULL : $this->configuration['exif_field_map'][$field_name],
        );
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(MediaInterface $media) {
    // This should be handled by Drupal core.
  }

  /**
   * Read EXIF.
   *
   * @param string $uri
   *   The uri for the file that we are getting the Exif.
   *
   * @return array|bool
   *   An associative array where the array indexes are the header names and
   *   the array values are the values associated with those headers or FALSE
   *   if the data can't be read.
   */
  protected function getExif($uri) {
    // @todo We should probably make this pluggable.
    return exif_read_data($uri);
  }
}
