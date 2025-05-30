<?php

namespace Drupal\commerce_preorder_batch\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Preorder batch entities.
 *
 * @ingroup commerce_preorder_batch
 */
class PreorderBatchDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->getEntity();
    
    // Add warning if batch has orders
    if ($entity->getOrderCount() > 0) {
      $form['warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#markup' => $this->t('<strong>Warning:</strong> This batch contains @count orders. Deleting this batch will unassign these orders from the batch.', [
            '@count' => $entity->getOrderCount(),
          ]),
        ],
        '#weight' => -10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    
    // Unassign orders from this batch before deletion
    if ($entity->getOrderCount() > 0) {
      $batch_manager = \Drupal::service('commerce_preorder_batch.manager');
      $orders = $batch_manager->getOrdersForBatch($entity);
      
      foreach ($orders as $order) {
        if ($order->hasField('preorder_batch')) {
          $order->set('preorder_batch', NULL);
          $order->save();
        }
      }
      
      $this->messenger()->addMessage($this->t('Unassigned @count orders from the deleted batch.', [
        '@count' => count($orders),
      ]));
    }

    parent::submitForm($form, $form_state);
  }

} 