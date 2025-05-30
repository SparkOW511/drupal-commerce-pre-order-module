<?php

namespace Drupal\commerce_preorder_batch\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Preorder batch edit forms.
 *
 * @ingroup commerce_preorder_batch
 */
class PreorderBatchForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    // Add some help text for batch creation
    if ($entity->isNew()) {
      $form['help'] = [
        '#type' => 'item',
        '#markup' => $this->t('Create a new pre-order batch. Customers will be able to place orders for the selected product variations, and their orders will be processed when the batch date arrives.'),
        '#weight' => -10,
      ];
    }

    // Add progress information for existing batches
    if (!$entity->isNew() && $entity->getCapacity() > 0) {
      $batch_manager = \Drupal::service('commerce_preorder_batch.manager');
      $progress = $batch_manager->getBatchProgress($entity);
      
      $form['progress'] = [
        '#type' => 'item',
        '#title' => $this->t('Batch Progress'),
        '#markup' => $this->t('@current of @capacity orders (@percentage% full)', [
          '@current' => $progress['current'],
          '@capacity' => $progress['capacity'],
          '@percentage' => $progress['percentage'],
        ]),
        '#weight' => -5,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $entity = $this->entity;
    
    // Validate batch date is in the future
    $batch_date = $form_state->getValue(['batch_date', 0, 'value']);
    if ($batch_date) {
      $batch_date_obj = new \DateTime($batch_date);
      $now = new \DateTime();
      
      if ($batch_date_obj <= $now) {
        $form_state->setErrorByName('batch_date', $this->t('Batch date must be in the future.'));
      }
    }

    // Validate capacity is positive
    $capacity = $form_state->getValue(['capacity', 0, 'value']);
    if ($capacity !== NULL && $capacity <= 0) {
      $form_state->setErrorByName('capacity', $this->t('Capacity must be a positive number.'));
    }

    // Validate at least one product variation is selected
    $product_variations = $form_state->getValue(['product_variations']);
    if (empty($product_variations) || (count($product_variations) == 1 && empty($product_variations[0]['target_id']))) {
      $form_state->setErrorByName('product_variations', $this->t('At least one product variation must be selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Preorder batch.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Preorder batch.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.preorder_batch.canonical', ['preorder_batch' => $entity->id()]);
  }

} 