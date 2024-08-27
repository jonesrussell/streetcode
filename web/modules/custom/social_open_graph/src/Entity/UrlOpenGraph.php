<?php

namespace Drupal\social_open_graph\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the URL Open Graph entity.
 *
 * @ingroup social_open_graph
 *
 * @ContentEntityType(
 *   id = "social_open_graph_url",
 *   label = @Translation("URL Open Graph"),
 *   base_table = "social_open_graph_url",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class UrlOpenGraph extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the URL Open Graph entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the URL Open Graph entity.'))
      ->setReadOnly(TRUE);

    $fields['url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('URL'))
      ->setDescription(t('The URL of the Open Graph entity'));

    $fields['provider_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider Name'))
      ->setDescription(t('Open Graph Provider Name.'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Open Graph Title.'))
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDescription(t('Open Graph Description.'));

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('Open Graph Image.'));

    return $fields;
  }

}
