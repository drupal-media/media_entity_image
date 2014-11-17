<?php

/**
 * @file
 * Contains \Drupal\media_entity_image\Plugin\MediaEntity\Type\Image.
 */

namespace Drupal\media_entity_image\Plugin\MediaEntity\Type;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Datetime\DrupalDateTime;
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
   * The exif data.
   *
   * @var array.
   */
  protected $exif;

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
    $fields = array(
      'mime' => t('File MIME'),
      'width' => t('Width'),
      'height' => t('Height'),
    );

    if (!empty($this->configuration['gather_exif'])) {
      $fields += array(
        'model' => t('Came model'),
        'created' => t('Image creation datetime'),
        'iso' => t('Iso'),
        'shutter_speed' => t('Shutter speed value'),
        'apperture' => t('Apperture value'),
        'focal_lenght' => t('Focal lenght'),
      );
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getField(MediaInterface $media, $name) {
    $source_field = $this->configuration['source_field'];
    $property_name = $media->{$source_field}->first()->mainPropertyName();

    // Get the file, image and exif data.
    $file = $this->entityManager->getStorage('file')->load($media->{$source_field}->first()->{$property_name});
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    $uri = $file->getFileUri();

    // Return the field.
    switch ($name) {
      case 'mime':
        return !$file->filemime->isEmpty() ? $file->getMimeType() : FALSE;

      case 'width':
        $width = $image->getWidth();
        return $width ? $width : FALSE;

      case 'height':
        $height = $image->getHeight();
        return $height ? $height : FALSE;

      case 'size':
        $size = $file->getSize();
        return $size ? $size : FALSE;
    }

    if (!empty($this->configuration['gather_exif'])) {
      switch ($name) {
        case 'model':
          return $this->getExifField($uri, 'Model');

        case 'created':
          $date = new DrupalDateTime($this->getExifField($uri, 'DateTimeOriginal'));
          return $date->getTimestamp();

        case 'iso':
          return $this->getExifField($uri, 'ISOSpeedRatings');

        case 'shutter_speed':
          return $this->getExifField($uri, 'ShutterSpeedValue');

        case 'apperture':
          return $this->getExifField($uri, 'ApertureValue');

        case 'focal_lenght':
          return $this->getExifField($uri, 'FocalLength');
      }
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

    $form['gather_exif'] = array(
      '#type' => 'select',
      '#title' => t('Whether to gather exif data.'),
      '#description' => t('Gather exif data using exif_read_data().'),
      '#default_value' => empty($this->configuration['gather_exif']) || !function_exists('exif_read_data') ? 0 : $this->configuration['gather_exif'],
      '#options' => array(
        0 => t('No'),
        1 => t('Yes'),
      ),
      '#disabled' => (function_exists('exif_read_data')) ? FALSE : TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(MediaInterface $media) {
    // This should be handled by Drupal core.
  }

  /**
   * Get exif field value.
   *
   * @param string $uri
   *   The uri for the file that we are getting the Exif.
   *
   * @param string $field
   *   The name of the exif field.
   *
   * @return string|bool
   *   The value for the requested field or FALSE if is not set.
   */
  protected function getExifField($uri, $field) {
    if (empty($this->exif)) {
      $this->exif = $this->getExif($uri);
    }
    return !empty($this->exif[$field]) ? $this->exif[$field] : FALSE;
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
    return exif_read_data($uri, 'EXIF');
  }
}
