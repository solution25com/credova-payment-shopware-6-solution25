# Changelog

All notable changes to the Credova Payment plugin for Shopware 6 are documented in this file.
The plugin follows semantic versioning; the plugin version is independent of the Shopware version it supports.

## [1.3.0] - 2026-09-24

### Added
- **Shopware 6.7 support.** The plugin installs and runs on Shopware 6.7.x and remains compatible with Shopware 6.6. Both lines are covered by one code base and one release.
- Added the Vite-format administration assets (`Resources/public/administration/assets/` and `.vite/`) that Shopware 6.7 loads, alongside the webpack assets (`administration/js/` and `administration/css/`) that Shopware 6.6 loads. Each Shopware version picks up the format it understands and ignores the other. 1.2.1 shipped only the webpack assets, so its administration extensions did not load on Shopware 6.7.
- Added a PHPUnit unit test suite (`tests/Unit`) covering the plugin lifecycle, the payment handler (application payload, cancel link signing, all failure paths), the webhook controller (every Credova status, duplicate deliveries, transitions blocked by the state guard, legacy order-level identifiers) and the webhook state transition guard. The suite passes on Shopware 6.6.5.0, 6.6.10 and 6.7.
- Added `.shopware-extension.yaml` with the build and packaging configuration used by `shopware-cli extension zip`.

### Changed
- Supported Shopware range is now **6.6.5.0 – 6.7.x** (`>=6.6.5.0 <6.8.0`). The previously declared `^6.6 || ^6.7` allowed installation on Shopware 6.6.0.0 – 6.6.4.x, where the plugin cannot run: the payment handler extends `AbstractPaymentHandler`, which only exists from Shopware 6.6.5.0 onwards.
- Credova payment states in the administration are now registered through Shopware's state style service instead of overriding the order list and order detail templates. *Approved* is shown in the in-progress style and *Signed* in the completed style in the order list, the order detail state selector and the state history, on both Shopware 6.6 and 6.7.
- Replaced every deprecated `EntitySearchResult::first()` call with `getEntities()->first()`, the supported form in Shopware 6.7. The deprecated form is scheduled for removal in 6.8.

### Fixed
- **Fixed the payment status column being empty for every order in the Shopware 6.7 order list.** The plugin replaced the `sw_order_list_grid_columns_transaction_state` block with a slot for the column `transactions.last().stateMachineState.name`. Shopware 6.7 renamed that column to `primaryOrderTransaction.stateMachineState.name`, so the replacement slot matched no column and the payment status disappeared for all orders, not only Credova orders. The template override has been removed.
- **Fixed the Credova settings page failing on Shopware 6.7.** The settings form called `this.$set()`, a Vue 2 API that only worked on Shopware 6.6 through the Vue compatibility build. Shopware 6.7 removed the compatibility build, so loading the configuration and enforcing the "custom text hides the logo" rule threw an error. The form now assigns the values directly, which works on both versions.
- **Fixed activating and deactivating the plugin not enabling or disabling the Credova payment method.** The lifecycle looked the payment method up by `CredovaPaymentMethod::class`, while the payment method is stored with the handler identifier `CredovaHandler::class`. The lookup never matched, so activation left the payment method inactive, deactivation and uninstallation left it active, and reinstalling the plugin tried to create a second payment method with the same technical name. The lookup now uses the stored handler identifier.
- Fixed the order detail payment state selector override missing the `v-if="transaction"` guard and the loading state that Shopware 6.7 added to the same block; the override has been removed together with the order list override.

### Changed since 1.2.1 (previously on the development branch without a version bump)
- **New required setting *Application Secret*** (`appSecret`) in the plugin configuration. It signs the cancel link sent to Credova and replaces the `APP_SECRET` environment variable used by 1.2.1. Payment fails with "Application secret not configured" while it is empty.
- New setting *Final State for Transaction* (`paid` by default, or `signed`) that controls the state a transaction moves to when Credova reports *Signed*.
- New setting *Webhook Execute Token*. It is stored but not yet verified by the webhook endpoint.
- Two new migrations add the transitions *Approved → Failed* (`fail`) and *Approved → Paid* (`paid`) to the order transaction state machine.
- Credova identifiers and webhook data are stored on the order transaction instead of the order. Webhooks for orders created before this change are still matched through the order-level Credova public ID, which is then copied to the transaction.
- Webhook state changes are checked against the current transaction state (`WebhookStateTransitionGuard`), and repeated deliveries of the same public ID and status are ignored.
- The cancel link must belong to the referenced order and is rejected when a logged-in customer does not own the order.
- Checkout validation errors are shown as a list on the confirm page, with a link to the account profile when the date of birth is missing, and validation now also runs for guest customers.
- The product listing widget escapes the custom message and passes `data-hide-brand` as `true`/`false`; the custom message is no longer rendered on product detail pages.
- Removed the webhook "signature" check. It did not verify anything: it only rejected webhooks when neither `CREDOVA_WEBHOOK_SECRET` nor `APP_SECRET` was set in the environment. Credova does not sign webhook requests.
- Removed the *Test API connection* button from the plugin configuration, together with its administration component and the `/api/_action/credova-test-connection/test-connection` controller.
- Removed the custom exception classes under `src/Exception` (`CredovaApiException`, `CredovaAuthException`, `CredovaValidationException`, `CredovaWebhookSignatureException`). The API client now returns Credova errors as an `error` entry in the response, and the payment handler fails the transaction and throws a `RuntimeException`.
- Static analysis and coding standard fixes in the payment handler, webhook controller and refund subscriber.

### Upgrade notes
- Run `bin/console plugin:update Credova` followed by `bin/console cache:clear`. The update runs the two migrations listed above when upgrading from 1.2.1 or earlier.
- **When upgrading from 1.2.1, set *Application Secret* in the plugin configuration before accepting Credova payments.** Cancel links issued by 1.2.1 were signed with the `APP_SECRET` environment variable and stop working after the update.
- Existing installations keep the current active state of the Credova payment method; updating does not change it. Check *Settings > Payment methods* after the update if the method should be offered.
- The storefront JavaScript is compiled by `bin/build-storefront.sh` or `shopware-cli extension zip` and is not kept in version control; rebuild the storefront after installing the plugin from a source checkout.

### Internal
- Removed the unused `PaymentMethods` registry class.
- Replaced the test bootstrap, which required a full Shopware test database, with a lightweight autoloader bootstrap for the unit suite, and added `phpunit/phpunit` to `require-dev`.
- Stopped excluding `src/Resources/public/` from version control and started excluding `.phpunit.result.cache`.

## [1.2.1] - 2026-01-21
- Aligned the plugin version with the published GitHub version.
- Added tax and shipping as separate lines of the Credova application and corrected the application amount.
- Added exception handling to the cancel flow.
- Fixed Shopware extension verifier findings.

## [1.2.0] - 2025-10-31
- Consolidated the plugin to a single Credova payment method, with cleanup and webhook improvements.

## [1.1.8] - 2025-10-16
- Reworked the custom payment state transitions, refactored the plugin code and added validation and design updates.
- Added PHPStan, PHPCS and extension verifier checks.

## [1.1.6] - 2025-10-14
- Added a migration for the Credova payment state transitions.

## [1.1.5] - 2025-10-09
- Added the custom Credova payment transaction states *Approved* and *Signed* and state handling in the webhook.

## [1.0.0] - 2025-07-31
- Initial release: Credova payment method, storefront display, plugin configuration and administration settings.
