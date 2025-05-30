<?php

namespace Drupal\commerce_preorder_batch\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PreorderBatchSettingsForm.
 *
 * @ingroup commerce_preorder_batch
 */
class PreorderBatchSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_preorder_batch.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'preorder_batch_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_preorder_batch.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Settings'),
    ];

    $form['general']['enable_preorders'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pre-orders globally'),
      '#description' => $this->t('Allow customers to place pre-orders when products are out of stock.'),
      '#default_value' => $config->get('enable_preorders'),
    ];

    $form['general']['auto_assign_batches'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically assign orders to batches'),
      '#description' => $this->t('Automatically assign new pre-orders to the next available batch.'),
      '#default_value' => $config->get('auto_assign_batches'),
    ];

    $form['general']['preorder_button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pre-order button text'),
      '#description' => $this->t('Text to display on add to cart buttons for pre-order items.'),
      '#default_value' => $config->get('preorder_button_text') ?: $this->t('Pre-order'),
    ];

    $form['payment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Payment Settings'),
    ];

    $form['payment']['payment_timing'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment timing'),
      '#description' => $this->t('When should customers be charged for pre-orders?'),
      '#options' => [
        'immediate' => $this->t('Immediately when order is placed'),
        'batch_date' => $this->t('When batch date arrives'),
        'before_shipping' => $this->t('X days before shipping'),
      ],
      '#default_value' => $config->get('payment_timing') ?: 'batch_date',
    ];

    $form['payment']['days_before_shipping'] = [
      '#type' => 'number',
      '#title' => $this->t('Days before shipping to charge'),
      '#description' => $this->t('Number of days before shipping to charge the customer.'),
      '#default_value' => $config->get('days_before_shipping') ?: 3,
      '#states' => [
        'visible' => [
          ':input[name="payment_timing"]' => ['value' => 'before_shipping'],
        ],
      ],
    ];

    $form['notifications'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification Settings'),
    ];

    $form['notifications']['notify_customers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification emails to customers'),
      '#description' => $this->t('Send emails when batches are ready for processing.'),
      '#default_value' => $config->get('notify_customers'),
    ];

    $form['notifications']['admin_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Administrator email'),
      '#description' => $this->t('Email address to notify when batches are ready for processing.'),
      '#default_value' => $config->get('admin_email'),
    ];

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Settings'),
    ];

    $form['display']['show_batch_info'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show batch information on product pages'),
      '#description' => $this->t('Display expected shipping dates and batch information to customers.'),
      '#default_value' => $config->get('show_batch_info'),
    ];

    $form['display']['show_progress_bar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show batch progress bar'),
      '#description' => $this->t('Display a progress bar showing how full each batch is.'),
      '#default_value' => $config->get('show_progress_bar'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('commerce_preorder_batch.settings')
      ->set('enable_preorders', $form_state->getValue('enable_preorders'))
      ->set('auto_assign_batches', $form_state->getValue('auto_assign_batches'))
      ->set('preorder_button_text', $form_state->getValue('preorder_button_text'))
      ->set('payment_timing', $form_state->getValue('payment_timing'))
      ->set('days_before_shipping', $form_state->getValue('days_before_shipping'))
      ->set('notify_customers', $form_state->getValue('notify_customers'))
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('show_batch_info', $form_state->getValue('show_batch_info'))
      ->set('show_progress_bar', $form_state->getValue('show_progress_bar'))
      ->save();
  }

} 