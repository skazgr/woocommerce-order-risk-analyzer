# WooCommerce Order Risk Analyzer

**Contributors:** skazgr  
**Tags:** woocommerce, risk, orders, fraud, admin  
**Requires at least:** 5.8  
**Tested up to:** 6.8.1  
**Requires PHP:** 7.2  
**Stable tag:** 1.0.0  
**License:** GPLv3 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

---

## Description

**WooCommerce Order Risk Analyzer** helps store managers quickly spot potentially risky orders right in the WooCommerce orders list:

- **Column Indicator:** shows a colored badge with risk % on each order.  
- **Popover Detail:** on hover or focus, explains which factors contributed.  
- **Configurable Factors:**  
  - Cancellations (multiplier + cap)  
  - Guest checkouts  
  - Repeat customers (reduction + cap)  
- **Admin Settings:** Bootstrap accordion UI to tweak points, caps, and thresholds.  
- **Internationalization:** fully translatable, Greek (`el`) included.

Ideal for spotting fraud or trouble orders before processing.

---

## Installation

1. Upload the `woocommerce-order-risk-analyzer` folder to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the **Plugins** screen in WordPress.  
3. Go to **WooCommerce > Order Risk Settings** to configure your weights, caps, and thresholds.

---

## Frequently Asked Questions

### Can I disable a factor?

Yes. In the settings page, simply set its point to `0`.

### Are translations included?

Greek (`el`) translations are bundled. To translate into another language, use [Loco Translate](https://wordpress.org/plugins/loco-translate/) or edit the `.po/.mo` files in the `languages/` folder.

### Will it affect performance?

The plugin caches risk scores on order save and only recalculates when an order’s status changes, so performance impact is minimal.

---

## Screenshots

1. Settings page accordion UI  
2. Orders list risk badge & popover

---

## Changelog

### 1.0.0

* Initial release  
* Added risk calculation factors: cancellations, guests, repeat customers  
* Admin settings page with Bootstrap accordion and icons  
* Internationalization support

---

## Upgrade Notice

### 1.0.0

First version. No upgrade actions necessary.

---