<?php

declare(strict_types=1);

namespace Drupal\mof\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mof\LicenseInterface;

/**
 * Defines the license entity type.
 *
 * @ContentEntityType(
 *   id = "license",
 *   label = @Translation("License"),
 *   label_collection = @Translation("License administration"),
 *   label_singular = @Translation("license"),
 *   label_plural = @Translation("licenses"),
 *   label_count = @PluralTranslation(
 *     singular = "@count license",
 *     plural = "@count licenses",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mof\LicenseListBuilder",
 *     "access" = "Drupal\mof\Access\LicenseAccessHandler",
 *     "form" = {
 *       "default" = "Drupal\mof\Form\LicenseForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   },
 *   base_table = "license",
 *   translatable = FALSE,
 *   collection_permission = "administer licenses",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/licenses",
 *     "admin-collection" = "/admin/licenses",
 *     "add-form" = "/admin/license/add",
 *     "edit-form" = "/admin/license/{license}/edit",
 *     "delete-form" = "/admin/license/{license}/delete",
 *     "delete-multiple-form" = "/admin/content/license/delete-multiple",
 *   }
 * )
 */
final class License extends ContentEntityBase implements LicenseInterface {

  /**
   * Converts a license entity to its license id when casted to a string.
   *
   * @return string
   *   The License ID.
   */
  public function __toString(): string {
    return $this->getLicenseId();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLicenseId(): string {
    return $this->get('license_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isOsiApproved(): bool {
    return (bool)$this->get('osi_approved')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isFsfApproved(): bool {
    return (bool)$this->get('fsf_libre')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeprecated(): bool {
    return (bool)$this->get('deprecated_license_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentType(): array {
    $values = [];
    foreach ($this->get('content_type') as $item) {
      if (!empty($item->value)) {
        $values[] = $item->value;
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'name' => $this->getName(),
      'licenseId' => $this->getLicenseId(),
      'isOsiApproved' => $this->isOsiApproved(),
      'isFsfLibre' => $this->isFsfApproved(),
      'ContentType' => $this->getContentType(),
      'isDeprecated' => $this->isDeprecated(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -105,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['license_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('License ID'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -105,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['reference'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Reference'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 2048,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'settings' => ['placeholder' => 'https://spdx.org/...'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['reference_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Reference number'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['deprecated_license_id'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is deprecated license ID'))
      ->setDisplayOptions('form', [
        'type' => 'boolean',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['osi_approved'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is OSI approved'))
      ->setDisplayOptions('form', [
        'type' => 'boolean',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['fsf_libre'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is FSF libre'))
      ->setDisplayOptions('form', [
        'type' => 'boolean',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['details_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Details URL'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 2048,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'settings' => ['placeholder' => 'https://spdx.org/...'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['see_also'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('See also'))
      ->setRequired(FALSE)
      ->setCardinality(-1)
      ->setSettings([
        'max_length' => 2048,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'settings' => ['placeholder' => 'https://...'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['content_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Content type'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'code' => t('Code'),
        'document' => t('Documentation'),
        'data' => t('Data'),
      ])
      ->setCardinality(3) // Allow up to 3 values
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => -60,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
