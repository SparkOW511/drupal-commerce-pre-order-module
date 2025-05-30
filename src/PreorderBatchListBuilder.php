<?php

namespace Drupal\commerce_preorder_batch;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Preorder batch entities.
 *
 * @ingroup commerce_preorder_batch
 */
class PreorderBatchListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Add the "Add Pre-order Batch" link
    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Pre-order Batch'),
      '#url' => Url::fromRoute('entity.preorder_batch.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#weight' => -10,
    ];
    
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['batch_date'] = $this->t('Batch Date');
    $header['progress'] = $this->t('Progress');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_preorder_batch\Entity\PreorderBatch $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.preorder_batch.edit_form',
      ['preorder_batch' => $entity->id()]
    );
    
    $batch_date = $entity->getBatchDate();
    $row['batch_date'] = $batch_date ? $batch_date->format('M j, Y') : '';
    
    // Progress information
    $current = $entity->getOrderCount();
    $capacity = $entity->getCapacity();
    $percentage = $capacity > 0 ? round(($current / $capacity) * 100, 1) : 0;
    
    $row['progress'] = [
      'data' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['preorder-batch-admin-progress']],
        'bar' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['preorder-batch-admin-progress__bar']],
          'fill' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['preorder-batch-admin-progress__fill'],
              'style' => "width: {$percentage}%",
            ],
          ],
        ],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('@current/@capacity (@percentage%)', [
            '@current' => $current,
            '@capacity' => $capacity,
            '@percentage' => $percentage,
          ]),
          '#attributes' => ['class' => ['preorder-batch-admin-progress__text']],
        ],
        '#attached' => [
          'library' => ['commerce_preorder_batch/admin'],
        ],
      ],
    ];
    
    // Status with styling
    $status = $entity->get('status')->value;
    $row['status'] = [
      'data' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => ucfirst($status),
        '#attributes' => [
          'class' => ['preorder-batch-status', 'preorder-batch-status--' . $status],
        ],
        '#attached' => [
          'library' => ['commerce_preorder_batch/admin'],
        ],
      ],
    ];
    
    $row['created'] = \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'short');
    
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Add custom operations
    if ($entity->access('view') && $entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 10,
        'url' => $entity->toUrl('canonical'),
      ];
    }
    
    return $operations;
  }

} 