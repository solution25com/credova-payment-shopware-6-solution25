[![Packagist Version](https://img.shields.io/packagist/v/solution25/credova.svg)](https://packagist.org/packages/solution25/credova)
[![Packagist Downloads](https://img.shields.io/packagist/dt/solution25/credova.svg)](https://packagist.org/packages/solution25/credova)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/solution25com/credova-payment-shopware-6-solution25/blob/main/LICENSE.md)

# Credova Payment for Shopware 6

Offers Credova financing (buy now, pay later) as a payment method in Shopware 6. The customer is redirected to Credova to apply during checkout, Credova reports the outcome through a webhook that drives the payment transaction state, and refunds in Shopware are reported back to Credova as return requests.

## Compatibility

| Plugin version | Shopware | PHP |
| --- | --- | --- |
| 1.3.x | 6.6.5.0 – 6.7.x | as required by the Shopware version (8.2+) |
| 1.2.x and earlier | 6.6.x | 8.2+ |

One release covers both Shopware lines. The administration ships in both build formats: webpack assets for Shopware 6.6 and Vite assets for Shopware 6.7.

## Features

- **Credova payment method** (technical name `credova_payment`), created on installation and enabled or disabled together with the plugin.
- **Redirect checkout.** On order placement the plugin creates a Credova application with the customer, billing address, date of birth and order lines (plus shipping and sales tax lines) and redirects the customer to Credova.
- **Checkout eligibility checks.** On the confirm page, Credova is only offered when the cart total is inside the configured finance range and the customer has a complete US billing address (two-letter state code, 10-digit phone number, zip code) and a date of birth showing an age of at least 18. Missing data is listed on the confirm page and the order button is hidden.
- **Webhook-driven transaction states** with two custom states, *Approved* (`credova_approved`) and *Signed* (`credova_signed`), added to the order transaction state machine by migration.
- **Signed cancel link.** Customers who cancel at Credova return through a link signed with the application secret; the order and its transaction are cancelled.
- **Returns.** Moving a Credova transaction to *Refunded* or *Refunded (partially)* sends a return request to Credova while the latest Credova status stored on the order is *Signed* (see [Known limitations](#known-limitations)).
- **Product listing prequalification widget** from Credova's storefront script, shown for products whose price is inside the finance range.
- **Administration.** Credova transaction states are coloured in the order list, the order detail state selector and the state history. A *Credova Settings* module under *Settings > Extensions* holds the display settings.
- Dedicated log file `var/log/CredovaPayment-<environment>-<date>.log`.

## Requirements

- Shopware 6.6.5.0 or later, up to 6.7.x.
- A Credova merchant account (API username, password and store code). Credova operates in the United States, so customers need a US billing address.

## Installation

### With Composer

```bash
composer require solution25/credova
bin/console plugin:refresh
bin/console plugin:install --activate Credova
bin/console cache:clear
```

### From a release ZIP

1. Upload the ZIP in *Extensions > My extensions*, or extract it to `custom/plugins/`.
2. Install and activate the plugin:

   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate Credova
   bin/console cache:clear
   ```

### From a source checkout

Clone the repository into `custom/plugins/`. Compiled administration assets are part of the repository. The storefront JavaScript is not, so build the storefront after installing:

```bash
git clone https://github.com/solution25com/credova-payment-shopware-6-solution25.git custom/plugins/credova-payment-shopware-6-solution25
bin/console plugin:refresh
bin/console plugin:install --activate Credova
bin/build-storefront.sh
bin/console cache:clear
```

### Updating from 1.2.x

```bash
bin/console plugin:refresh
bin/console plugin:update Credova
bin/console cache:clear
```

The update runs two migrations that add the *Approved → Failed* and *Approved → Paid* transitions.

**Set *Application Secret* in the plugin configuration right after updating from 1.2.1.** It replaces the `APP_SECRET` environment variable that 1.2.1 used to sign cancel links; payments fail while it is empty, and cancel links issued before the update stop working.

The update does not change whether the Credova payment method is active. Check *Settings > Payment methods* afterwards and assign the method to your sales channels if it should be offered.

## Configuration

### Plugin configuration

*Extensions > My extensions > Credova > Configure*. All values can be set globally or per sales channel.

| Setting | Description |
| --- | --- |
| Mode | `sandbox` or `production`; selects the Credova API host. |
| Credova Username / Password | API credentials provided by Credova. |
| Store Code | Your Credova store code, used for applications and the storefront widget. |
| Final State for Transaction | State a transaction moves to when Credova reports *Signed*: `paid` (default) or `signed`. |
| Application Secret | Required. Any strong random value; it signs the cancel link sent to Credova. Payment fails when it is empty. |
| Webhook Execute Token | Stored, but not yet verified by the webhook endpoint (see [Known limitations](#known-limitations)). |

<img width="1902" height="933" alt="image" src="https://github.com/user-attachments/assets/e150c5a6-e945-470b-b4d0-7ea6b6237215" />

### Credova Settings module

*Settings > Extensions > Credova Settings*, per sales channel:

| Setting | Description |
| --- | --- |
| Minimum / Maximum Finance Amount | Cart total and product price range (between 300 and 5000) in which Credova is offered and the widget is shown. |
| Custom text | Message shown in the product listing widget. When set, the *Show Credova logo* switch is forced on, which hides the logo as Credova requires for custom text. |
| Show Credova logo | Passed to the Credova widget as `data-hide-brand`: when enabled, the Credova logo is hidden. |
| Checkout Flow Type, Popup Type | Stored for future use; not evaluated by the current version. |

<img width="1911" height="924" alt="image" src="https://github.com/user-attachments/assets/45e2a798-8a34-4993-a55b-9dc900a8606e" />

### Payment method

Assign *Credova Payment* to the sales channels that should offer it in *Sales Channels > [channel] > Payment methods*.

## Payment flow

1. The customer selects Credova on the confirm page and places the order; the transaction starts in *Open*.
2. The plugin creates the Credova application and redirects the customer to Credova. The application's public ID is stored on the order transaction.
3. Credova sends webhooks to `/credova/webhook`:

   | Credova status | Transaction transition |
   | --- | --- |
   | Approved | Open / In progress → *Approved* |
   | Signed | → *Paid* or *Signed*, depending on *Final State for Transaction*; the shipping address and order number are then sent to Credova |
   | Funded | → *Paid* |
   | Declined | → *Failed* |
   | Returned | → *Cancelled* |

   A status is ignored when the transaction's current state does not allow the transition, and a repeated delivery of the same status is ignored.
4. If the customer cancels at Credova, the signed cancel link cancels the transaction and the order and returns the customer to the order page.

## Endpoints

| Route | Method | Purpose |
| --- | --- | --- |
| `/credova/webhook` | POST | Credova status callback. Always answers HTTP 200 with a JSON `success` flag, as Credova requires. The URL is sent to Credova with each application. |
| `/credova/cancel/{orderTransactionId}/{orderId}/{token}` | GET | Customer cancel link; the token is an HMAC of the order ID keyed with the application secret. |

## Known limitations

- Credova does not sign webhook requests, and the webhook endpoint does not yet verify the *Webhook Execute Token*. Webhooks are only matched against existing Credova public IDs stored in Shopware.
- The webhook, cancel and return paths read the plugin configuration globally instead of per sales channel. Configure *Mode*, the API credentials and the *Application Secret* globally, or identically for every sales channel.
- The prequalification button on the product detail page is not displayed; the widget is only shown in product listings.
- Return requests are only sent while the latest Credova status stored on the order is *Signed*. Once Credova reports *Funded*, refunds in Shopware are no longer reported to Credova and must be requested in the Credova portal.
- Every webhook overwrites the stored Credova fields with the values of that delivery, including a status whose transition was rejected by the state guard.
- The *Show Credova logo* switch works inverted to its label (see [Credova Settings module](#credova-settings-module)).

## Development

From the plugin directory:

```bash
composer install
vendor/bin/phpunit
sh ./pre-push-checks/phpcs-check.sh
docker run --rm -v "$(pwd)":/ext ghcr.io/shopware/shopware-cli extension validate --full --check-against highest /ext
```

The unit tests need no database. `composer install` also installs a pre-push hook that runs the extension verifier (requires Docker) and PHPCS. The GitHub Actions workflow validates the extension against the lowest and highest supported Shopware versions on every push and pull request.

To rebuild the administration assets, run `bin/build-administration.sh` in a Shopware 6.6 installation (webpack output in `administration/js` and `administration/css`) and in a Shopware 6.7 installation (Vite output in `administration/assets` and `administration/.vite`), and commit both outputs.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

See [LICENSE.md](LICENSE.md).
