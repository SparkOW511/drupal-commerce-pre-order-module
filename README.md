# Commerce Pre-order Batch

A Drupal 11 module that enables Framework Computer-style pre-orders with intelligent batch management and cart-aware quantity validation for out-of-stock Commerce products.

## Features

### Core Functionality
- **Pre-order Support**: Customers can order out-of-stock products with guaranteed delivery dates
- **Batch Management**: Organize orders into production batches with shipping dates and capacity limits
- **Smart Order Assignment**: Orders automatically distributed across multiple batches when needed
- **Visual Timeline**: Interactive batch history and progress with modern blue-themed UI

### Cart-Aware Validation
- **Intelligent Quantity Limits**: Checks existing cart contents before allowing new additions
- **Real-time Feedback**: Shows "You have X items in cart, can add Y more" messages
- **Multi-layer Validation**: Client-side, server-side, and submit-time validation
- **Cart Full Protection**: Disables add-to-cart when batch capacity is reached
- **Race Condition Prevention**: Fresh data checks prevent overselling

### User Experience
- **Dynamic Button States**: "PRE-ORDER", "CART FULL", or "NOTIFY WHEN AVAILABLE"
- **Contextual Messaging**: Clear explanations of availability and cart status
- **Progress Indicators**: Visual batch fill status and timeline
- **Responsive Design**: Mobile-optimized interface

### Admin Features
- **Batch Dashboard**: Monitor all batches at `/admin/commerce/preorder-batches`
- **Progress Tracking**: Real-time capacity and order count updates
- **Flexible Configuration**: Set dates, capacities, and product assignments
- **Status Management**: Track batches through pending → processing → shipped → completed

## Installation

1. Place in `web/modules/custom/commerce_preorder_batch/`
2. Enable: `ddev drush en commerce_preorder_batch -y`
3. Clear cache: `ddev drush cr`

## Quick Setup

1. **Create Batch**: Go to `/admin/commerce/preorder-batches` → Add Pre-order Batch
2. **Configure**: Set name, shipping date, capacity, and select product variations
3. **Test**: Add items to cart to see cart-aware validation in action
4. **Monitor**: Watch batch progress fill up as orders are placed

## How It Works

### Customer Journey
1. **Product Page**: Customer sees batch timeline and availability
2. **Add to Cart**: System checks existing cart contents and shows remaining capacity
3. **Validation**: Multiple validation layers prevent over-ordering
4. **Checkout**: Standard Drupal Commerce checkout process
5. **Assignment**: Order automatically assigned to appropriate batches

### Smart Allocation Example
- Customer orders 25 items
- Current batch has 10 spots remaining
- Next batch has 50 spots available
- System automatically assigns:
  - 10 items to current batch (fills it)
  - 15 items to next batch
- Both batch counts updated automatically

### Cart Validation Example
- Batch capacity: 100 items
- Current orders: 85 items
- Customer has 8 items in cart
- Available to add: 7 more items
- System shows: "You have 8 items in your cart. You can add 7 more items (total available: 15)"

## Technical Architecture

### Core Components
- **PreorderBatch Entity**: Stores batch data (date, capacity, status)
- **PreorderBatchManager Service**: Handles business logic and allocation
- **Form Integration**: Modifies add-to-cart forms with validation
- **Order Processing**: Automatic batch assignment on order completion

### Validation Layers
1. **Client-side**: Real-time JavaScript validation as user types
2. **Server-side**: PHP validation before cart addition
3. **Submit-time**: Final check before form submission
4. **Fresh Data**: Race condition protection with current availability

## Requirements

- Drupal 11
- Commerce Core, Product, Order modules
- PHP 8.1+

## Configuration

- **Batch Management**: `/admin/commerce/preorder-batches`
- **Permissions**: Configure at `/admin/people/permissions`
- **Module Help**: Available at `/admin/help/commerce_preorder_batch`

## API Usage

### Check Product Availability
```php
$batch_manager = \Drupal::service('commerce_preorder_batch.manager');
$total_available = $batch_manager->getTotalAvailableCapacity($product_variation);
```

### Get Cart Quantity
```php
$cart_quantity = commerce_preorder_batch_get_cart_quantity($product_variation_id);
```

### Check if Preorder Enabled
```php
$is_preorder = $batch_manager->isPreorderEnabled($product_variation);
```

## Troubleshooting

### Common Issues
- **No batch info showing**: Ensure batches are created and assigned to product variations
- **Validation not working**: Check that JavaScript is enabled and libraries are loading
- **Orders not assigned**: Verify batch status is 'pending' and capacity available

### Debug Mode
Enable Drupal logging to see batch assignment details:
```bash
ddev drush config:set system.logging error_level verbose -y
```

## Contributing

This module follows Drupal coding standards and includes:
- Comprehensive form validation
- Responsive CSS design
- Accessible user interface
- Extensive error handling
- Clean, documented code

## License

GPL-2.0-or-later 