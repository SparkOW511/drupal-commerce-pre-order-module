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
      ->accessCheck(FALSE);

    $batch_ids = $query->execute();
    
    if (!empty($batch_ids)) {
      // Check each batch in order until we find one that's not full
      foreach ($batch_ids as $batch_id) {
        $batch = $storage->load($batch_id);
        
        // Check if batch is not full
        if (!$batch->isFull()) {
          return $batch;
        }
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

    // Calculate total quantity for this order
    $total_quantity = 0;
    $product_variation = NULL;
    foreach ($order->getItems() as $order_item) {
      $variation = $order_item->getPurchasedEntity();
      if ($variation) {
        // Check if this product variation is in the batch
        $batch_variations = $batch->getProductVariations();
        foreach ($batch_variations as $batch_variation) {
          if ($batch_variation->id() == $variation->id()) {
            $total_quantity += (int) $order_item->getQuantity();
            $product_variation = $variation;
            break;
          }
        }
      }
    }

    if ($total_quantity == 0 || !$product_variation) {
      return FALSE;
    }

    // Use the new method to assign quantities across multiple batches
    return $this->assignQuantityToBatches($product_variation, $total_quantity, $order);
  }

  /**
   * Assigns a quantity to batches, splitting across multiple batches if needed.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation
   *   The product variation.
   * @param int $quantity
   *   The quantity to assign.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to assign.
   *
   * @return bool
   *   TRUE if all quantity was assigned, FALSE otherwise.
   */
  public function assignQuantityToBatches(ProductVariationInterface $product_variation, $quantity, OrderInterface $order) {
    // Safety check: ensure we don't assign more than total available capacity
    $total_available = $this->getTotalAvailableCapacity($product_variation);
    if ($quantity > $total_available) {
      $this->loggerFactory->get('commerce_preorder_batch')
        ->warning('Attempted to assign @quantity items but only @available available for product variation @variation_id', [
          '@quantity' => $quantity,
          '@available' => $total_available,
          '@variation_id' => $product_variation->id(),
        ]);
      return FALSE;
    }
    
    $remaining_quantity = $quantity;
    $assigned_batches = [];
    
    // Get all available batches for this product variation
    $storage = $this->entityTypeManager->getStorage('preorder_batch');
    $query = $storage->getQuery()
      ->condition('status', 'pending')
      ->condition('product_variations', $product_variation->id())
      ->condition('batch_date', date('Y-m-d'), '>=')
      ->sort('batch_date', 'ASC')
      ->accessCheck(FALSE);

    $batch_ids = $query->execute();
    
    if (empty($batch_ids)) {
      return FALSE;
    }

    // Try to assign quantity to batches in order
    foreach ($batch_ids as $batch_id) {
      if ($remaining_quantity <= 0) {
        break;
      }

      $batch = $storage->load($batch_id);
      if (!$batch || $batch->isFull()) {
        continue;
      }

      $current_count = $batch->getOrderCount();
      $capacity = $batch->getCapacity();
      $available_space = $capacity - $current_count;

      if ($available_space > 0) {
        // Assign as much as possible to this batch
        $quantity_to_assign = min($remaining_quantity, $available_space);
        
        // Update batch order count
        $batch->setOrderCount($current_count + $quantity_to_assign);
        $batch->save();
        
        // Track this assignment
        $assigned_batches[] = [
          'batch_id' => $batch->id(),
          'quantity' => $quantity_to_assign,
        ];
        
        $remaining_quantity -= $quantity_to_assign;
        
        $this->loggerFactory->get('commerce_preorder_batch')
          ->info('Assigned @quantity items from order @order_id to batch @batch_id', [
            '@quantity' => $quantity_to_assign,
            '@order_id' => $order->id(),
            '@batch_id' => $batch->id(),
          ]);
      }
    }

    // Add batch references to order if any assignments were made
    if (!empty($assigned_batches) && $order->hasField('preorder_batch')) {
      $current_batches = $order->get('preorder_batch')->getValue();
      foreach ($assigned_batches as $assignment) {
        $current_batches[] = ['target_id' => $assignment['batch_id']];
      }
      $order->set('preorder_batch', $current_batches);
      // Don't save here to avoid recursion - let the calling code handle saving
    }

    // Return TRUE if all quantity was assigned
    return $remaining_quantity == 0;
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

  /**
   * Gets total available capacity across all batches for a product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation
   *   The product variation.
   *
   * @return int
   *   Total available capacity across all batches.
   */
  public function getTotalAvailableCapacity(ProductVariationInterface $product_variation) {
    $storage = $this->entityTypeManager->getStorage('preorder_batch');
    
    $query = $storage->getQuery()
      ->condition('status', 'pending')
      ->condition('product_variations', $product_variation->id())
      ->condition('batch_date', date('Y-m-d'), '>=')
      ->accessCheck(FALSE);

    $batch_ids = $query->execute();
    
    if (empty($batch_ids)) {
      return 0;
    }

    $total_available = 0;
    foreach ($batch_ids as $batch_id) {
      $batch = $storage->load($batch_id);
      if ($batch && !$batch->isFull()) {
        $current_count = $batch->getOrderCount();
        $capacity = $batch->getCapacity();
        $available_space = $capacity - $current_count;
        $total_available += $available_space;
      }
    }

    return $total_available;
  }

} 