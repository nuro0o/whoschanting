<?php

namespace App\Support;

use App\Game\PurchasePolicy;

class LegalDocuments
{
    /** @return array<string,mixed> */
    public function details(): array
    {
        return [
            'support_email' => config('legal.support_email'), 'version' => config('legal.version'),
            'operator_name' => config('legal.operator_name'), 'business_address' => config('legal.business_address'),
            'registration_number' => config('legal.registration_number'),
            'minimum_age' => filled(config('legal.minimum_age')) ? (int) config('legal.minimum_age') : null,
            'purchase_policy_version' => PurchasePolicy::VERSION,
            'purchase_consent_text' => PurchasePolicy::CONSENT,
        ];
    }

    /** @return array<string,mixed> */
    public function document(string $id): array
    {
        $email = config('legal.support_email');
        $operator = config('legal.operator_name');
        $identity = filled($operator) ? "Who’s Chanting is operated by {$operator}." : 'This notice covers the Who’s Chanting game and its Crown Store.';
        $contact = "Contact us at {$email} about the service, a purchase or your personal data.";
        $documents = [
            'contact' => [
                'title' => 'Contact us', 'summary' => 'Help with the village, your account or a purchase. You can reach us without signing in.',
                'sections' => [
                    ['id' => 'support', 'title' => 'Get in touch', 'paragraphs' => [$contact,
                        'Tell us what happened and what you need help with. For a game issue, include the room code, approximate time and your device or browser if you know them.'],
                        'links' => [['label' => 'Email support', 'href' => "mailto:{$email}"]]],
                    ['id' => 'purchases', 'title' => 'Purchases and refunds', 'paragraphs' => [
                        'Include the email used at checkout and an order or Stripe receipt reference. We can help with missing cosmetics, duplicate charges, refunds and payment questions. Do not send your full card number or bank security codes.'],
                        'links' => [['label' => 'Refunds and withdrawal requests', 'href' => '/refunds']]],
                    ['id' => 'privacy-help', 'title' => 'Privacy and account help', 'paragraphs' => [
                        'Use the same support email for access, correction, deletion or other privacy requests. We may ask for proportionate information to verify that the request relates to your account. Never send your password, recovery codes or one-time login codes.'],
                        'links' => [['label' => 'Read the privacy policy', 'href' => '/privacy']]],
                    ['id' => 'operator', 'title' => 'About this service', 'paragraphs' => [$identity]],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy policy', 'summary' => 'What the game stores, why it needs it, and the choices you have.',
                'sections' => [
                    ['id' => 'who', 'title' => 'Who is responsible', 'paragraphs' => [$identity, $contact]],
                    ['id' => 'data', 'title' => 'Information we handle', 'paragraphs' => ['The information depends on whether you play as a guest, create an account, make a purchase or contact us.'],
                        'bullets' => [
                            'Accounts: name, email address, password hash, verification and security settings, and optional passkey or two-factor authentication records.',
                            'Gameplay: guest or account identifiers, player names, room membership, connection activity, chat, roles, actions, votes, claims, defenses, predictions, feedback and match recaps. Profiles also record progression, achievements, Crowns and cosmetic choices.',
                            'Purchases: account email, bundle, price, currency, payment status, Stripe payment references, purchase timestamps, accepted terms and digital-content consent, and a saved purchase confirmation. We record which paid cosmetics are used in each match, the match reference and first-use time, and delivery of the confirmation email. These records help deliver purchases and review refund requests and repeated refunded purchases. Stripe collects payment details on its checkout page; this application does not collect or store full card numbers or card security codes.',
                            'Support: the contact details and information you send us. The withdrawal form records your name, email, order reference, optional message, declaration and submission time.',
                            'Service security: session identifiers, IP addresses, browser information and diagnostic records used to keep accounts and the game working and to limit abuse.',
                        ]],
                    ['id' => 'purposes', 'title' => 'Why we use it', 'paragraphs' => [
                        'We use account, gameplay and order information to provide the game, maintain your profile and deliver purchases. Where a contract applies, this processing is necessary to perform it or take steps you request before entering it.',
                        'We use proportionate security, moderation and diagnostic information for our legitimate interests in preventing abuse, protecting accounts and operating a reliable service. We also process information needed to meet applicable legal obligations, including consumer requests and payment disputes. Where processing requires consent, we will ask for it separately. Accepting the terms is not consent to advertising.',
                    ]],
                    ['id' => 'visibility', 'title' => 'What other players can see', 'paragraphs' => [
                        'Other room members can see your player name, equipped appearance, public game messages and other information the rules reveal. Hidden roles and private actions are protected during a match; roles and recorded events are revealed in the final recap. A private room limits discovery, but other members can still copy or share what they see.',
                        'Private journal notes and suspicion boards stay in this browser’s local storage and are not sent to the game server by those features. Avoid putting sensitive personal information in player names, chat or notes on a shared device.',
                    ]],
                    ['id' => 'providers', 'title' => 'Service providers and disclosures', 'paragraphs' => [
                        'Hosting, database and email providers handle information needed to run the service and deliver account or support messages. Stripe receives your checkout email, product and order information and processes payments, fraud checks and its own transaction records. We disclose information when legally required or necessary to address fraud or protect people’s rights.',
                        'Stripe and other service providers may process information outside your country. Applicable transfer requirements and safeguards depend on the provider and destination. Contact support for information about the providers and safeguards relevant to your data; Stripe explains its own processing in its privacy policy.',
                        'The game does not sell personal data. The current application does not embed advertising pixels or behavioural analytics. An advert promoting the game on another platform is governed by that platform’s own privacy settings. Fonts are served with the website rather than requested from Google by your browser.',
                    ], 'links' => [['label' => 'Stripe privacy policy', 'href' => 'https://stripe.com/privacy']]],
                    ['id' => 'cookies', 'title' => 'Cookies and browser storage', 'paragraphs' => [
                        'Session and security cookies keep you signed in, protect form submissions and let a guest reconnect to their seat. Optional remember-login functionality can keep a login across visits. Preference cookies and local storage remember appearance, navigation, sound and readability choices.',
                        'Local storage also keeps tutorial hints, journal notes and suspicion boards until you clear them or your browser removes them. Clear this site’s cookies and storage using your browser settings. This can sign you out, remove local notes and prevent a guest from reclaiming an existing seat. We do not currently use non-essential tracking cookies in the game.',
                    ]],
                    ['id' => 'retention', 'title' => 'How long information stays', 'paragraphs' => [
                        'Account and progression information is retained while your account is active. Deleting your account removes the account, linked progression, credentials and local purchase records. It does not automatically erase names or messages already stored in room histories and archived match recaps, or clear browser storage on your devices. Contact support if you also want us to review that content for erasure.',
                        'Room and match histories support recaps and abuse investigations and do not currently expire automatically. Security logs, email correspondence and backups have separate operational retention periods. We assess deletion requests against the need to keep information for an ongoing issue, dispute or legal obligation.',
                        'Withdrawal-form records in the application are removed after '.config('legal.request_retention_days').' days. Support correspondence and Stripe’s independent payment records may be kept separately where needed to resolve a request or comply with legal obligations. We do not promise that deleting a game account deletes Stripe’s records.',
                    ]],
                    ['id' => 'rights', 'title' => 'Your privacy rights', 'paragraphs' => [
                        'Depending on the law that applies, you can request access, correction, erasure, restriction, portability or object to processing, including processing based on legitimate interests. Where we rely on consent, you can withdraw it without affecting earlier lawful processing. Some rights have legal exceptions.',
                        "Send requests to {$email}. We may need to verify your identity. For requests covered by the GDPR, we normally respond within one month; we will explain any permitted extension. You can also complain to your local data-protection authority. In the Netherlands, this is the Autoriteit Persoonsgegevens.",
                    ], 'links' => [['label' => 'Autoriteit Persoonsgegevens', 'href' => 'https://www.autoriteitpersoonsgegevens.nl/en']]],
                    ['id' => 'automation', 'title' => 'Moderation and automated checks', 'paragraphs' => [
                        'The game applies automatic text filters, request limits and Crown-earning cooldowns to reduce abuse. A text filter can replace a player name or mask words; a Crown cooldown temporarily stops new Crown awards without changing your existing balance. Contact support if a check affects you incorrectly. We do not use these checks to make decisions with legal or similarly significant effects.',
                    ]],
                    ['id' => 'children-changes', 'title' => 'Young players and changes', 'paragraphs' => [
                        'Parents and guardians can contact us with concerns about a child’s account or information. Players must meet the eligibility rules in our terms. We may update this notice when the service or its data handling changes; the date at the top identifies this version.',
                    ], 'links' => [['label' => 'Terms of service', 'href' => '/terms']]],
                ],
            ],
            'terms' => [
                'title' => 'Terms of service', 'summary' => 'The agreement for using the village and buying optional cosmetics.',
                'sections' => [
                    ['id' => 'agreement', 'title' => 'The service and this agreement', 'paragraphs' => [$identity,
                        'These terms cover guest play, registered accounts and purchases in the Crown Store. Review them before creating an account or purchasing. We record the terms version accepted at registration and at checkout. Your statutory rights remain in place.', $contact]],
                    ['id' => 'eligibility', 'title' => 'Accounts and eligibility', 'paragraphs' => [
                        filled(config('legal.minimum_age')) ? 'Players must be at least '.config('legal.minimum_age').' years old.' : 'You must be legally permitted to use the service where you live.',
                        'If you are below the age of legal adulthood, involve a parent or legal guardian in reviewing these terms and obtain any permission required where you live. Purchases must be made by someone authorised to use the payment method and enter the purchase agreement.',
                        'Use an email address you control, protect your credentials and tell us if you suspect unauthorised access. Guests rely on their browser session to return to a seat. Account progress and purchases require a verified account.',
                    ]],
                    ['id' => 'conduct', 'title' => 'Fair play and conduct', 'paragraphs' => ['Keep the game welcoming and play within its rules.'], 'bullets' => [
                        'Do not harass or threaten people, share another person’s private information, or post unlawful content.',
                        'Do not exploit bugs, automate Crown farming, interfere with the service, bypass access controls or manipulate payments.',
                        'Only submit names, messages and other content you have the right to use. You allow us to store and display it as needed to operate the game, provide recaps and address abuse.',
                    ]],
                    ['id' => 'purchases', 'title' => 'Digital purchases and Crowns', 'paragraphs' => [
                        'Core gameplay is free. Optional bundles are one-time purchases; they do not start a subscription. Check the bundle contents, currency and final total, including applicable tax, before paying through Stripe. The purchase is confirmed when payment succeeds; delivery may wait for payment confirmation.',
                        'Cosmetics do not improve roles, abilities, matchmaking, XP or Crown earnings. You receive permission to use the purchased cosmetics with your account. They are not transferable, redeemable for cash or an investment. Crowns are earned in-game currency with no cash value or withdrawal option.',
                        'Paid faction expansions add optional match rules. One eligible owner at the table unlocks the expansion for everyone in that room, including guests; added roles are assigned randomly. Sharing access with a room does not transfer ownership. Started matches retain their rules if the owner disconnects or the purchase is later refunded; future matches require a current owner.',
                        'Permanent cosmetics have no season expiry or recurring charge. Their use depends on the game continuing to operate and your account remaining eligible. This does not promise that the service will operate forever, and it does not remove any remedy you have if paid content is not supplied as agreed.',
                        'A full refund can remove the corresponding cosmetic entitlement. A payment dispute can suspend it while the purchase is reviewed. Contact support if this affects you incorrectly.',
                    ]],
                    ['id' => 'withdrawal', 'title' => 'Withdrawal and faulty purchases', 'paragraphs' => [
                        'For paid digital content, checkout asks for your express consent to immediate supply during the withdrawal period and your acknowledgment that the statutory withdrawal right is lost once supply begins, subject to the legally required confirmation. We include this acknowledgment in your purchase confirmation. Use alone, or acceptance of these terms alone, is not a valid waiver. If the exception does not apply, applicable statutory withdrawal rights remain.',
                        PurchasePolicy::POLICY,
                        'You also retain applicable rights if digital content is missing, faulty or does not match what was promised. These rights can apply beyond a withdrawal period. Our refunds page explains how to submit a request.',
                    ], 'links' => [['label' => 'Refunds and cancellation', 'href' => '/refunds']]],
                    ['id' => 'availability', 'title' => 'Availability, changes and enforcement', 'paragraphs' => [
                        'Maintenance, technical problems and updates can interrupt play. We may change game features and balance rules, and take proportionate action against abuse, including removing content or limiting an account. Where appropriate, we will explain an account restriction and provide a way to contact support about it.',
                        'If a material change or closure affects purchased content, we will communicate the change and address any remedy required by applicable law. We do not exclude liability or consumer protections that cannot lawfully be excluded.',
                    ]],
                    ['id' => 'ending', 'title' => 'Leaving the village', 'paragraphs' => [
                        'You can stop playing at any time and delete your account from Profile settings. Deletion removes your account access, progress and local purchase records, so contact support about an outstanding purchase issue first. Account deletion alone is not a refund request.',
                        'The privacy policy explains what may remain in shared game histories, correspondence and payment-provider records.',
                    ], 'links' => [['label' => 'Privacy policy', 'href' => '/privacy']]],
                    ['id' => 'disputes', 'title' => 'Questions and changes to these terms', 'paragraphs' => [
                        'Please contact support first so we can investigate a problem. These terms do not take away mandatory protections or the right to bring a consumer claim in a court available to you under applicable law.',
                        'The version date identifies the terms presented to you. Material updates will be made available on the site; new purchases use the version shown at checkout. Changes do not retrospectively remove your statutory rights.',
                    ]],
                ],
            ],
            'refunds' => [
                'title' => 'Refunds and cancellation', 'summary' => 'Request help with a purchase or notify us that you want to withdraw. No account login is needed.',
                'sections' => [
                    ['id' => 'withdrawal-right', 'title' => 'Your right to withdraw', 'paragraphs' => [
                        'If EU consumer withdrawal rules apply, you generally have 14 days from entering the contract to withdraw without giving a reason. For paid digital content, the right can end when supply begins only with your prior express consent, acknowledgment of losing the right, and the required purchase confirmation. Buying, equipping or using content does not by itself establish that all these requirements were met. Rights required by your local law continue to apply.',
                        'Use the form below or email a clear withdrawal statement before the applicable deadline. Give your name, checkout email and a reference that helps identify the purchase. A reason is not required. You may use the wording: “I notify you that I withdraw from my purchase of [bundle], ordered on [date], under reference [reference].”',
                    ]],
                    ['id' => 'unused-packs', 'title' => 'Our additional two-game refund policy', 'paragraphs' => [PurchasePolicy::POLICY,
                        'Your Purchases page shows the first recorded use, distinct games used and refund guidance. A refund request is not automatically rejected because a usage record exists. Support checks the purchase, applicable terms and confirmation, and can correct a mistaken usage record. Existing purchases retain their original terms.'],
                        'links' => [['label' => 'View your purchases', 'href' => '/account/purchases']]],
                    ['id' => 'purchase-help', 'title' => 'Missing, faulty or duplicate purchases', 'paragraphs' => [
                        "For a missing unlock, duplicate charge, unauthorised purchase or faulty content, email {$email}. Include the bundle, checkout email and receipt reference. Do not send card numbers or security codes.",
                        'A failed or cancelled checkout does not unlock a bundle. A delayed payment can still be processing; check the store’s payment status before trying again. Your rights concerning faulty digital content are separate from withdrawal rights.',
                    ]],
                    ['id' => 'after-request', 'title' => 'What happens after a request', 'paragraphs' => [
                        'The online form records your declaration and returns a dated reference. We send an email acknowledgment and review the purchase. This acknowledgment confirms receipt of your request; it is not a statement that a refund has already been issued.',
                        'For a valid statutory withdrawal, we reimburse the applicable payment without undue delay and within 14 days of receiving your withdrawal notice, using the original payment method unless otherwise agreed without extra fees. Your bank may take additional time to display it. Refunding a bundle removes access to its paid cosmetics.',
                        "You can also contact {$email} about a request. If you no longer have an order reference, email us with enough information to identify the purchase. Deleting your game account is not necessary to request a refund.",
                    ]],
                ],
            ],
        ];
        abort_unless(isset($documents[$id]), 404);

        return ['id' => $id, 'updated_at' => config('legal.updated_at'), ...$documents[$id]];
    }
}
