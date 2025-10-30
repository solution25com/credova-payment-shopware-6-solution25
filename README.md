[![Packagist Version](https://img.shields.io/packagist/v/solution25/credova.svg)](https://packagist.org/packages/solution25/credova)
[![Packagist Downloads](https://img.shields.io/packagist/dt/solution25/credova.svg)](https://packagist.org/packages/solution25/credova)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)]([https://github.com/solution25/Credova/blob/main/LICENSE](https://github.com/solution25com/credova-payment-shopware-6-solution25/blob/main/LICENSE.md))

# Credova Payment

## Introduction

The **Credova Payment Plugin for Shopware 6** integrates the **Buy Now, Pay Later (BNPL)** capabilities of **Credova** directly into your Shopware storefront.  
Credova allows customers to apply for financing and pay over time — offering merchants higher conversions and customers flexible payment options.


## Key Features

- **Buy Now, Pay Later (BNPL)** — Enable Credova as a flexible financing option at checkout.  
- **Webhook-Driven Order Updates** — Orders are marked as *Paid* only after Credova sends a successful webhook event.
- **Sandbox Mode** — Test your integration safely before going live.
- **Widget Support** — Render “As Low As” Credova financing widgets on PLP, PDP, Mini Cart, and Cart Page.
- **Availability Rules Integration** — Use Shopware Rule Builder to restrict when Credova is available (e.g., exclude subscriptions or low-value carts).
- **Min/Max Widget Display Logic** — Control widget rendering based on product price thresholds.
- **Branding Control** — Optionally hide Credova branding for a cleaner storefront look.
- **Compliant Payment Flow** — Redirect shoppers to Credova for credit approval and automatically handle post-approval updates.

---

##  Compatibility
- ✅ **Shopware 6.6.x**
- ✅ **Shopware 6.7.x**

---

## Get Started

### Installation & Activation

1. **Download**

   Clone the plugin repository into your Shopware `custom/plugins` directory:
   ```bash
   git clone https://github.com/solution25com/credova-payment-shopware-6-solution25.git
   ```

## Packagist
 ```
composer require solution25/credova
  ```
   
2. **Install the Plugin in Shopware 6**

- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the newly cloned plugin and click Install.

3. **Activate the Plugin**
- Once installed, toggle the plugin to activate it.

4. **Verify Installation**

- After activation, you will see Credova in the list of installed plugins.
- The plugin name, version, and installation date should appear.

## Plugin Configuration

1. **Access Plugin Environment Settings by clicking Configure in My extensions**
- Mode
- Credova Username
- Credova Password
- Store Code
<img width="1902" height="933" alt="image" src="https://github.com/user-attachments/assets/e150c5a6-e945-470b-b4d0-7ea6b6237215" />

---

2. **Access Settings > Extensions > Credova Settings**
- Minimum Finance Amount ($)
- Maximum Finance Amount ($)
- Checkout Flow Type
- Popup Type
- Enter a custom message
<img width="1911" height="924" alt="image" src="https://github.com/user-attachments/assets/45e2a798-8a34-4993-a55b-9dc900a8606e" />



