<?php

namespace Drupal\commerce_preorder_batch;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_preorder_batch\Entity\PreorderBatch;

/**
 * Service for managing preorder batches.
 */
class PreorderBatchManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new PreorderBatchManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Gets the next available batch for a product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation
   *   The product variation.
   *
   * @return \Drupal\commerce_preorder_batch\Entity\PreorderBatch|null
   *   The next available batch or NULL if none found.
   */
  public function getNextAvailableBatch(ProductVariationInterface $product_variation) {
    $storage = $this->entityTypeManager->getStorage('preorder_batch');
    
    $query = $storage->getQuery()
      ->condition('status', 'pending')
      ->condition('product_variations', $product_variation->id())
      ->condition('batch_date', date('Y-m-d'), '>=')
      ->sort('batch_date', 'ASC')
      ->range(0, 1)
      ->accessCheck(FALSE);

    $batch_ids = $query->execute();
    
    if (!empty($batch_ids)) {
      $batch_id = reset($batch_ids);
      $batch = $storage->load($batch_id);
      
      // Check if batch is not full
      if (!$batch->isFull()) {
        return $batch;
      }
    }

    return NULL;
  }

  /**
   * Assigns an order to a batch.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to assign.
   * @param \Drupal\commerce_preorder_batch\Entity\PreorderBatch $batch
   *   The batch to assign to.
   *
   * @return bool
   *   TRUE if assignment was successful, FALSE otherwise.
   */
  public function assignOrderToBatch(OrderInterface $order, PreorderBatch $batch) {
    if ($batch->isFull()) {
      return FALSE;
    }

    // Add batch reference to order
    if ($order->hasField('preorder_batch')) {
      $order->set('preorder_batch', $batch->id());
      $order->save();
    }

    // Update batch order count
    $current_count = $batch->getOrderCount();
    $batch->setOrderCount($current_count + 1);
    $batch->save();

    $this->loggerFactory->get('commerce_preorder_batch')
      ->info('Order @order_id assigned to batch @batch_id', [
        '@order_id' => $order->id(),
        '@batch_id' => $batch->id(),
      ]);

    return TRUE;
  }

  /**
   * Gets all batches ready for processing.
   *
   * @return \Drupal\commerce_preorder_batch\Entity\PreorderBatch[]
   *   Array of batches ready for processing.
   */
  public function getBatchesReadyForProcessing() {
    $storage = $this->entityTypeManager->getStorage('preorder_batch');
    
    $query = $storage->getQuery()
      ->condition('status', 'pending')
      ->condition('batch_date', date('Y-m-d'), '<=')
      ->accessCheck(FALSE);

    $batch_ids = $query->execute();
    
    return $storage->loadMultiple($batch_ids);
  }

  /**
   * Gets orders for a specific batch.
   *
   * @param \Drupal\commerce_preorder_batch\Entity\PreorderBatch $batch
   *   The batch.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface[]
   *   Array of orders in the batch.
   */
  public function getOrdersForBatch(PreorderBatch $batch) {
    $storage = $this->entityTypeManager->getStorage('commerce_order');
    
    $query = $storage->getQuery()
      ->condition('preorder_batch', $batch->id())
      ->accessCheck(FALSE);

    $order_ids = $query->execute();
    
    return $storage->loadMultiple($order_ids);
  }

  /**
   * Processes a batch (triggers payments, etc.).
   *
   * @param \Drupal\commerce_preorder_batch\Entity\PreorderBatch $batch
   *   The batch to process.
   *
   * @return bool
   *   TRUE if processing was successful, FALSE otherwise.
   */
  public function processBatch(PreorderBatch $batch) {
    if (!$batch->isReadyForProcessing()) {
      return FALSE;
    }

    $orders = $this->getOrdersForBatch($batch);
    $processed_count = 0;

    foreach ($orders as $order) {
      if ($this->processOrderPayment($order)) {
        $processed_count++;
      }
    }

    // Update batch status
    $batch->set('status', 'processing');
    $batch->save();

    $this->loggerFactory->get('commerce_preorder_batch')
      ->info('Processed batch @batch_id with @count orders', [
        '@batch_id' => $batch->id(),
        '@count' => $processed_count,
      ]);

    return TRUE;
  }

  /**
   * Processes payment for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to process payment for.
   *
   * @return bool
   *   TRUE if payment was successful, FALSE otherwise.
   */
  protected function processOrderPayment(OrderInterface $order) {
    // This would integrate with your payment processing logic
    // For now, we'll just mark the order as ready for fulfillment
    
    if ($order->hasField('preorder_payment_processed')) {
      $order->set('preorder_payment_processed', TRUE);
      $order->save();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if a product variation allows pre-orders.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation
   *   The product variation.
   *
   * @return bool
   *   TRUE if pre-orders are allowed, FALSE otherwise.
   */
  public function isPreorderEnabled(ProductVariationInterface $product_variation) {
    // Check if product has pre-order field enabled
    if ($product_variation->hasField('allow_preorder')) {
      return (bool) $product_variation->get('allow_preorder')->value;
    }
    
    // Default: allow pre-orders if there's a batch available
    return $this->getNextAvailableBatch($product_variation) !== NULL;
  }

  /**
   * Gets batch progress information.
   *
   * @param \Drupal\commerce_preorder_batch\Entity\PreorderBatch $batch
   *   The batch.
   *
   * @return array
   *   Array with progress information.
   */
  public function getBatchProgress(PreorderBatch $batch) {
    $capacity = $batch->getCapacity();
    $current = $batch->getOrderCount();
    $percentage = $capacity > 0 ? round(($current / $capacity) * 100, 1) : 0;

    return [
      'current' => $current,
      'capacity' => $capacity,
      'percentage' => $percentage,
      'remaining' => $capacity - $current,
      'is_full' => $batch->isFull(),
    ];
  }

} 