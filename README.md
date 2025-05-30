# Commerce Pre-order Batch

A Drupal 11 module that enables Framework Computer-style pre-orders with batch management for out-of-stock Commerce products.

## Features

- **Pre-order Support**: Customers can order out-of-stock products
- **Batch Management**: Organize orders into production batches with shipping dates
- **Visual Timeline**: Show batch history and progress with blue-themed UI
- **Automatic Assignment**: Orders automatically assigned to next available batch
- **Admin Interface**: Manage batches at `/admin/commerce/preorder-batches`

## Installation

1. Place in `web/modules/custom/commerce_preorder_batch/`
2. Enable: `ddev drush en commerce_preorder_batch -y`
3. Clear cache: `ddev drush cr`

## Quick Setup

1. **Create Batch**: Go to `/admin/commerce/preorder-batches` → Add Pre-order Batch
2. **Configure**: Set name, date, capacity, and select product variations
3. **View**: Batch info appears on product pages automatically

## Customer Experience

- Add-to-cart button becomes "PRE-ORDER" for out-of-stock items
- Shows batch timeline with shipping dates and progress
- Blue-themed design matching modern e-commerce standards

## Requirements

- Drupal 11
- Commerce Core, Product, Order modules

## Configuration

Module settings available at `/admin/commerce/config/preorder-batch` 