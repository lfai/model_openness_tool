<?php

declare(strict_types=1);

namespace Drupal\mof\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mof\ModelInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the model entity type.
 *
 * @ContentEntityType(
 *   id = "model",
 *   label = @Translation("Model"),
 *   label_collection = @Translation("Models"),
 *   label_singular = @Translation("model"),
 *   label_plural = @Translation("models"),
 *   label_count = @PluralTranslation(
 *     singular = "@count model",
 *     plural = "@count models",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mof\ModelListBuilder",
 *     "admin_list_builder" = "Drupal\mof\ModelAdminListBuilder",
 *     "view_builder" = "Drupal\mof\ModelViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\mof\Access\ModelAccessHandler",
 *     "form" = {
 *       "add" = "Drupal\mof\Form\ModelSubmitForm",
 *       "edit" = "Drupal\mof\Form\ModelSubmitForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "evaluate" = "Drupal\mof\Form\ModelEvaluateForm",
 *       "admin" = "Drupal\mof\Form\ModelAdminEditForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\mof\Routing\ModelEntityRouteProvider",
 *     },
 *   },
 *   base_table = "model",
 *   data_table = "model_field_data",
 *   revision_table = "model_revision",
 *   revision_data_table = "model_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer model",
 *   collection_permission = "view model collection",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/models",
 *     "add-form" = "/model/submit",
 *     "canonical" = "/model/{model}",
 *     "edit-form" = "/model/{model}/edit",
 *     "delete-form" = "/model/{model}/delete",
 *     "admin-form" = "/admin/model/{model}/edit",
 *     "admin-collection" = "/admin/models",
 *     "delete-multiple-form" = "/admin/content/model/delete-multiple",
 *     "json" = "/model/{model}/json",
 *     "yaml" = "/model/{model}/yaml",
 *   },
 *   field_ui_base_route = "entity.model.settings",
 * )
 */
final class Model extends RevisionableContentEntityBase implements ModelInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  const STATUS_APPROVED = 'approved';

  const STATUS_UNAPPROVED = 'unapproved';

  const STATUS_REJECTED = 'rejected';

  /**
   * Get license data for the model.
   */
  public function getLicenses(): ?array {
    return $this->get('license_data')->licenses;
  }

  /**
   * Get included component IDs for the model.
   */
  public function getComponents(): array {
    return array_column($this->get('components')->getValue(), 'value');
  }

  /**
   * Determine if the model is pending evaluation.
   *
   * A model is pending evaluation if any component license is set to
   * `Pending evaluation`
   *
   */
  public function isPending(): bool {
    return in_array('Pending evaluation', array_column($this->getLicenses(), 'license'));
  }

  /**
   * Get model description.
   */
  public function getDescription(): ?string {
    return $this->get('description')->value;
  }

  /**
   * Get model version.
   */
  public function getVersion(): ?string {
    return $this->get('version')->value;
  }

  /**
   * Get model type.
   */
  public function getType(): ?string {
    return $this->get('type')->value;
  }

  /**
   * Get model architecture.
   */
  public function getArchitecture(): ?string {
    return $this->get('architecture')->value;
  }

  /**
   * Get model origin.
   */
  public function getOrigin(): ?string {
    return $this->get('origin')->value;
  }

  /**
   * Get model organization.
   */
  public function getOrganization(): ?string {
    return $this->get('organization')->value;
  }

  /**
   * Get model classification.
   */
  public function getClassification(): string {
    return $this->get('classification')->value ?? '';
  }

  /**
   * Get models huggingface slug.
   */
  public function getHuggingfaceSlug(): ?string {
    return $this->get('huggingface')->value ?? NULL;
  }

  /**
   * Get models github repo slug.
   */
  public function getGithubSlug(): ?string {
    return $this->get('github')->value ?? NULL;
  }

  /**
   * Get a model's status.
   */
  public function getStatus(bool $label = FALSE): string|TranslatableMarkup {
    $status = $this->get('status')->value;

    if ($label) {
      return $this
        ->get('status')
        ->getFieldDefinition()
        ->getSetting('allowed_values')[$status];
    }

    return $status;
  }

  /**
   * Get a model's approver.
   */
  public function getApprover(): ?AccountInterface {
    return $this->get('approver')->entity;
  }

  /**
   * Set a model's approver.
   */
  public function setApprover(AccountInterface $account): self {
    $this->set('approver', $account->id());
    return $this;
  }

  /**
   * Set a model's status.
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }

    /*
    $evaluator = \Drupal::service('model_evaluator')->setModel($this);
    $class = $evaluator->getClassification(FALSE);
    $this->set('classification_no', $class);
    $this->set('classification', $evaluator->getClassLabel($class));
    $this->set('total_progress', $evaluator->getTotalProgress());
     */
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Name'))
      ->setDescription(t('Full name of model including base, parameter count and type of fine tune if applicable (ex. OpenGPT-10B-Instruct) *no spaces*'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -105,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -100,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('ModelNameConstraint');

    $fields['github'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setLabel(t('Project repository'))
      ->setRequired(FALSE)
      ->setReadOnly(TRUE)
      ->setSetting('allowed_values_function', 'mof_github_repo_list')
      ->setDisplayConfigurable('form', FALSE)
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -110,
      ]);

    $fields['huggingface'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setLabel(t('Hugging Face Link'))
      ->setDescription(t('A link to the Hugging Face page where the model is hosted.'))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['organization'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Organization'))
      ->setDescription(t('The organization that developed the model.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -95,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -95,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Description'))
      ->setDescription(t('Free text description of the model and its included components.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -90,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => -90,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Version/Parameters'))
      ->setDescription(t('The number of paramaters of the model or its version number or both.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -80,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'inline',
        'weight' => -80,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Type'))
      ->setDescription(t('Type of model, generally its modality.'))
      ->setRequired(FALSE)
      ->setSetting('allowed_values', [
        'language' => t('Language model'),
        'vision' => t('Vision model'),
        'image' => t('Image model'),
        'audio' => t('Audio model'),
        'video' => t('Video model'),
        '3d' => t('3D model'),
        'code' => t('Code model'),
        'multimodal' => t('Multimodal model'),
        'other' => t('Other model'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -60,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -60,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['architecture'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Architecture'))
      ->setDescription(t('The model\'s architecture.'))
      ->setRequired(FALSE)
      ->setSetting('allowed_values', [
        'transformer' => t('Transformer'),
        'transformer decoder' => t('Transformer (Decoder-only)'),
        'transformer encoder-decoder' => t('Transformer (Encoder-Decoder)'),
        'decoder' => t('Decoder-only'),
        'encoder' => t('Encoder-only'),
        'diffusion' => t('Diffusion'),
        'RNN' => t('RNN'),
        'CNN' => t('CNN'),
        'LSTM' => t('LSTM'),
        'NeRF' => t('NeRF'),
        'hybrid' => t('Hybrid'),
        'undisclosed' => t('Undisclosed'),
        'other' => t('Other'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -50,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -50,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['treatment'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Treatment'))
      ->setDescription(t('The training treatment, includes pre-training, fine-tuning, RLHF or other training techniques.'))
      ->setRequired(FALSE)
      ->setSetting('allowed_values', [
        'pre-trained' => t('Pre-trained'),
        'instruct fine-tuned' => t('Instruct fine-tuned'),
        'chat fine-tuned' => t('Chat fine-tuned'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -40,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -40,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['origin'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Base model'))
      ->setDescription(t('The pretrained version of the model which this model is based on, reference itself if this is the pre-trained model.'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -30,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', ['region' => 'hidden'])
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['approver'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Approver'))
      ->setSetting('target_type', 'user')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', ['region' => 'hidden'])
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the model was created.'))
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', ['region' => 'hidden'])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Last updated'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 60,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDescription(t('The time that the model was last edited.'));

    $fields['components'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Components'))
      ->setTranslatable(FALSE)
      ->setDescription(t('Model Components'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', [
        'region' => 'hidden',
        'weight' => 100,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['classification'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Classification'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['classification_no'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Classification'))
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setSettings(['max' => 3, 'min' => 0])
      ->setDefaultValue(0);

    $fields['total_progress'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Total progress'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDefaultValue(0);

    $fields['license_data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Licenses'))
      ->setDescription(t('License data'));

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('Model status'))
      ->setRequired(FALSE)
      ->setDefaultValue(static::STATUS_UNAPPROVED)
      ->setSetting('allowed_values', [
        static::STATUS_UNAPPROVED => t('Unapproved'),
        static::STATUS_APPROVED => t('Approved'),
        static::STATUS_REJECTED => t('Rejected'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -99,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -60,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
