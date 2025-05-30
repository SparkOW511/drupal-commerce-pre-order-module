<?php

namespace Drupal\commerce_preorder_batch\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the Preorder batch entity.
 *
 * @ContentEntityType(
 *   id = "preorder_batch",
 *   label = @Translation("Preorder batch"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_preorder_batch\PreorderBatchListBuilder",
 *     "views_data" = "Drupal\commerce_preorder_batch\Entity\PreorderBatchViewsData",
 *     "form" = {
 *       "default" = "Drupal\commerce_preorder_batch\Form\PreorderBatchForm",
 *       "add" = "Drupal\commerce_preorder_batch\Form\PreorderBatchForm",
 *       "edit" = "Drupal\commerce_preorder_batch\Form\PreorderBatchForm",
 *       "delete" = "Drupal\commerce_preorder_batch\Form\PreorderBatchDeleteForm",
 *     },
 *     "access" = "Drupal\commerce_preorder_batch\PreorderBatchAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\commerce_preorder_batch\PreorderBatchHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "preorder_batch",
 *   admin_permission = "administer preorder_batch entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/preorder-batch/{preorder_batch}",
 *     "add-form" = "/admin/commerce/preorder-batch/add",
 *     "edit-form" = "/admin/commerce/preorder-batch/{preorder_batch}/edit",
 *     "delete-form" = "/admin/commerce/preorder-batch/{preorder_batch}/delete",
 *     "collection" = "/admin/commerce/preorder-batches",
 *   },
 *   field_ui_base_route = "preorder_batch.settings"
 * )
 */
class PreorderBatch extends ContentEntityBase implements ContentEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * Gets the batch date.
   */
  public function getBatchDate() {
    return $this->get('batch_date')->date;
  }

  /**
   * Sets the batch date.
   */
  public function setBatchDate($date) {
    $this->set('batch_date', $date);
    return $this;
  }

  /**
   * Gets the product variations.
   */
  public function getProductVariations() {
    return $this->get('product_variations')->referencedEntities();
  }

  /**
   * Gets the batch capacity.
   */
  public function getCapacity() {
    return (int) $this->get('capacity')->value;
  }

  /**
   * Sets the batch capacity.
   */
  public function setCapacity($capacity) {
    $this->set('capacity', $capacity);
    return $this;
  }

  /**
   * Gets the current order count.
   */
  public function getOrderCount() {
    return (int) $this->get('order_count')->value;
  }

  /**
   * Sets the order count.
   */
  public function setOrderCount($count) {
    $this->set('order_count', $count);
    return $this;
  }

  /**
   * Checks if batch is full.
   */
  public function isFull() {
    return $this->getOrderCount() >= $this->getCapacity();
  }

  /**
   * Checks if batch is ready for processing.
   */
  public function isReadyForProcessing() {
    $now = new \DateTime();
    return $this->getBatchDate() <= $now && $this->get('status')->value === 'pending';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user ID of author of the Preorder batch entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Preorder batch entity.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['batch_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Batch Date'))
      ->setDescription(t('The date when this batch will be processed and shipped.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['product_variations'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Product Variations'))
      ->setDescription(t('The product variations included in this batch.'))
      ->setSetting('target_type', 'commerce_product_variation')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -2,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Capacity'))
      ->setDescription(t('Maximum number of orders this batch can handle.'))
      ->setRequired(TRUE)
      ->setDefaultValue(100)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => -1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Order Count'))
      ->setDescription(t('Current number of orders assigned to this batch.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the batch.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'completed' => 'Completed',
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

} 