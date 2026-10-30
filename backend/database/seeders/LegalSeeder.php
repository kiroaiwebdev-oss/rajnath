<?php

namespace Database\Seeders;

use App\Domain\Legal\LegalDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * First versions of the legal pages. Privacy and terms are published (the text that was on the
 * site, updated for self-service export/deletion and online payments). Refunds and shipping start
 * as drafts: the owner checks them in Admin › Legal pages and publishes (Razorpay asks for both
 * before switching on live payments). Runs only for kinds that have no rows yet.
 */
class LegalSeeder extends Seeder
{
    private const VERSION = '2026-10-30';

    public function run(): void
    {
        $hours = (int) config('booking.change_hours', 24);
        $docs = [
            'privacy' => ['Privacy policy', 'published', <<<'MD'
This policy explains what personal information {brand} ("we") collects through this website and app, why, and the choices you have. Contact: {email}, {phone}.

## What we collect

- **Account details:** name, email address and/or mobile number, language and notification preferences.
- **Birth details** you choose to save: name, date, time and place of birth, and gender.
- **Requests and messages** you send us, including the service, preferred date and your question.
- **Photos you send for a service**, such as palm photos for a palmistry report.
- **Orders and payments:** what you bought, amounts, invoices and the payment reference. Card and UPI details are handled by our payment provider (Razorpay); we never see or store them.
- **Delivery address** for gemstone orders.
- **Security information:** sign-in times, signed-in devices, IP address and browser type, and records of important account actions.

## Why we use it

- To provide the consultations, reports, pujas and products you ask for, and to contact you about them.
- To sign you in with one-time codes and keep your account secure.
- To issue invoices and meet legal, tax and accounting obligations.
- To send optional updates only if you switch them on in your account.

We do not sell your personal information.

## Who processes it for us

We use service providers only to run the platform: our hosting provider, the email and SMS providers that deliver sign-in codes and notices, our payment provider (Razorpay), our courier partner for gemstone deliveries, and, for in-app calls, our calling provider. They may process your data only on our instructions.

## Confidentiality of consultations

Birth details and what you discuss are confidential. Staff access to personal records is limited by role and recorded in an audit log. Consultations are not recorded unless you are told beforehand and agree.

## Reviews

If you write a review, we publish it only after checking it, with the name and city you choose. You can delete it at any time from your account.

## How long we keep it

We keep information while your account is active and as long as needed for the purposes above. When you delete your account, we delete or anonymise your information except what the law requires us to keep (for example, invoices and payment records, without your name or contact).

## Your choices and rights

- View and correct your details and birth profiles in your account: {account_url}
- Change notification preferences, and sign out other devices.
- Download a copy of your data, or delete your account, from **Account › Privacy** on the website or in the app.
- For anything else, write to {email}.

## Security

Connections are encrypted, secrets are stored encrypted, and staff accounts require two-factor authentication. No system is perfectly secure; please keep your sign-in codes private. We will never ask you for them.

## Place data

Birth-place search uses GeoNames data (CC BY 4.0).
MD],
            'terms' => ['Terms of use', 'published', <<<'MD'
## Nature of the services

Astrology, numerology, palmistry, Vastu, mantra and puja are traditional and interpretive practices. Guidance is given in good faith but no particular outcome is promised. It is not medical, psychological, legal, financial or engineering advice and must not replace a qualified professional. Decisions about medical treatment, including the timing of childbirth, remain with your doctors.

## Your account

You sign in with a one-time code sent to your email or mobile. Keep these codes private. You are responsible for the accuracy of the birth details you provide; results depend on them.

## Orders, fees and refunds

Prices are in Indian rupees and are shown, with any taxes, before you pay. Payments are processed by Razorpay. Cancellation, rescheduling and refund conditions are in our cancellation and refund policy ({refunds_url}) and are shown before you pay.

## Reviews

Reviews must be your own honest experience. We may decline reviews that are abusive, contain personal data of others, or are not about our services.

## Acceptable use

Do not misuse the website or app, attempt to access other people's data, or harass practitioners or staff. We may suspend accounts that do.

## Source code

The software running this website is free software under the GNU Affero General Public License v3. You can obtain the source code at {source_url}. This licence covers the software only, not our brand, photographs or content.

## Contact

{email} · {phone} · {address}
MD],
            'refunds' => ['Cancellation and refund policy', 'draft', <<<MD
> Draft prepared from how the platform works today. Please check every point before publishing.

## Consultations

- You can cancel or reschedule a booked consultation from your account up to **{$hours} hours** before the start time. The full amount goes back to your wallet, and you can use it for any service.
- Later than that, please contact us at {email} or {phone}; we will help where we can.
- If we have to cancel, you get a full refund.

## Reports, palmistry and written answers

Once work on a report has started, it cannot be cancelled. If a report is late or incomplete, write to us and we will fix it or refund you.

## Puja and chadhava

A puja can be cancelled for a full refund until it is scheduled. After the priest has been booked, a refund depends on the costs already paid; we will tell you the amount before you decide.

## Gemstones

If the stone you receive is damaged or does not match its certificate, write to us within 7 days of delivery with photos. We will replace it or refund you after it is returned in the same condition.

## How refunds are paid

Refunds go back to your original payment method (card, UPI or net banking) or to your wallet, as agreed with you. Bank refunds usually take 5–7 working days to reach you after we send them.

## Contact

{email} · {phone}
MD],
            'shipping' => ['Shipping and delivery policy', 'draft', <<<'MD'
> Draft. Please check the delivery times with your courier before publishing.

## Where we deliver

We deliver gemstones within India only. The price shown includes shipping.

## When it ships

Orders are packed and handed to our courier partner within 2 working days of payment. You get the tracking number in your account and by email or SMS.

## Delivery time

Most orders arrive in 3–7 working days, depending on your PIN code.

## Digital services

Consultations, reports and puja updates are delivered online through your account, email and the app. Nothing is shipped.

## Problems with a delivery

If the parcel is damaged or does not arrive, write to us at {email} or {phone} and we will resolve it with the courier.
MD],
        ];
        foreach ($docs as $kind => [$title, $status, $body]) {
            if (DB::table('legal_documents')->where('kind', $kind)->exists()) {
                continue;
            }
            DB::table('legal_documents')->insert(['kind' => $kind, 'locale' => 'en', 'version' => $status === 'published' ? self::VERSION : 'draft-seed', 'title' => $title,
                'body' => $body, 'change_note' => $status === 'published' ? 'First version in the admin.' : null, 'status' => $status,
                'effective_on' => $status === 'published' ? self::VERSION : null, 'published_at' => $status === 'published' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        LegalDocuments::forget();
    }
}
