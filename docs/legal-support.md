# Public policies and purchase support

`/contact`, `/privacy`, `/terms`, and `/refunds` are public, including for guests and unverified accounts. Links appear on the home page, account pages, and authentication pages. Support uses **whoschanting.support@geniousverse.app**. Contact links open the visitor's email application; the withdrawal form submits directly to the application.

## Operator details to supply

Set these server environment values before treating the policies as complete:

```dotenv
LEGAL_OPERATOR_NAME="Full legal person or business name"
LEGAL_BUSINESS_ADDRESS="Complete business postal address"
LEGAL_REGISTRATION_NUMBER="Applicable registration number"
LEGAL_MINIMUM_AGE=
```

Leave registration blank only if it does not apply. Set the age after deciding the game's age policy. Missing values are omitted from the public pages, not replaced with fictional details. Verify that the privacy notice matches the actual production hosting, email delivery, international transfers, and retention practices. The current copy describes the code's behavior and preserves applicable consumer rights; it is not a certification of legal compliance.

Document content and shared metadata live in `app/Support/LegalDocuments.php` and `config/legal.php`. Bump `legal.version` when updating contractual terms. Registration and paid checkout require an unchecked acceptance of the current terms; the server records its version and acceptance time. Privacy links are informational, not bundled consent to optional tracking. Existing accounts are not forced through a new login gate, but every new paid checkout requires acceptance.

## Withdrawal requests

Paid checkout now also requires a separate digital-content acknowledgment. Purchase history, receipt delivery, match-use evidence and the additional 14-day, two-game refund policy are described in [purchase history](purchase-history.md). Update the terms version and its separate `updated_at` date when changing contractual terms.

The public form collects the name, purchase email, purchase reference, and optional message. It presents the declaration for review before the visitor confirms. A UUID protects identical retries; the server fixes the declaration and receipt timestamp. The latest receipt reference and timestamp survive refresh in the same browser session. The form does not approve refunds or modify purchases. Support must review and process valid refunds through Stripe; the existing signed refund webhook updates cosmetic ownership.

Two plain-text acknowledgments are queued: one to support, with the customer as Reply-To, and one to the customer. Both include the submitted declaration, purchase reference, receipt reference, and timestamp. No emails are sent synchronously during the request. Queue insertion and the request marker share a database transaction, so an ordinary retry does not enqueue duplicates.

Run a **database queue worker**, even if another connection handles game broadcasts. Leave `DB_QUEUE_CONNECTION` unset so the database queue uses the application's database connection and participates in the transaction. Configure working production mail delivery and monitor failed jobs; an accepted request is not proof that email reached the recipient.

The daily `support:prune` task removes form records after 365 days. Ensure the existing scheduler is running. This does not remove email correspondence, failed-job payloads, backups, or accounting records; manage those according to the production retention policy. Customer browser storage and archived game content are also not automatically erased by account deletion, as the privacy notice explains.

## Deployment

1. Supply the operator details above, verify the support inbox receives mail, and configure the remaining Stripe Price IDs and endpoint signing secret described in [cosmetic store](cosmetic-store.md).
2. Deploy the application and run `php artisan migrate --force`. The legal migration adds nullable acceptance fields and the withdrawal request table; it does not rewrite existing accounts or orders.
3. Build assets with `npm run build`, refresh cached configuration, and restart long-running application processes. Fonts now ship from the application; builds download the font assets, visitors do not request Google Fonts.
4. Keep `php artisan queue:work database --tries=5` and the existing scheduler supervised. Confirm the four public pages and a controlled withdrawal request work on the deployed domain, including receipt email delivery.

Feature coverage checks public access, current-version consent, immutable request retries, receipt session isolation, actual database queue insertion, and retention. Stripe calls and email delivery are mocked or queued in the test database; tests do not exercise live payment or SMTP delivery. Browser checks use the actual Vue components with fixture APIs.

Policy references: [EU distance-selling guidance](https://europa.eu/youreurope/business/selling-in-eu/selling-goods-services/ecommerce-distance-selling/index_en.htm), [EU online withdrawal function directive](https://eur-lex.europa.eu/legal-content/EN/LSU/?uri=CELEX%3A32023L2673), and [European Commission data-protection obligations](https://commission.europa.eu/law/law-topic/data-protection/information-business-and-organisations/obligations_en).
