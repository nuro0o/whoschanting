# Geniousverse Stripe assets

A compact checkout identity based on the existing Geniousverse emblem and Bodoni wordmark. The checkout wordmark removes both taglines, strengthens the lettering for small display sizes, and uses opaque ink colors. Existing application logos are unchanged.

## Recommended uploads

| Stripe field | Upload | Size | Background |
| --- | --- | --- | --- |
| Logo | `wordmark.png` | 1400 × 280 | Transparent; dark lettering for a light checkout |
| Icon | `icon.png` | 512 × 512 | Indigo with white emblem |

Alternatives: `wordmark-white.png` for a dark background; `mark-indigo.png` and `mark-white.png` when only the emblem is wanted. All five PNGs are below 127 KB. Each has a scalable SVG master with lettering converted to paths, so the artwork has no installed-font dependency. Upload the PNGs to Stripe; SVGs are editable source files.

## Suggested Stripe appearance

In **Settings → Business → Branding** (or search settings for “Branding”):

1. Upload `wordmark.png` as the logo and `icon.png` as the icon.
2. Use **#1616D7** as the brand/button color.
3. Use **#F6F7FB** as the light checkout background/accent color, or white.
4. Use a simple system/sans-serif checkout font and softly rounded controls.
5. Review desktop/mobile Checkout and the receipt/invoice previews before saving. Use the white wordmark only where the chosen background is dark.

Branding settings affect the Stripe account's customer-facing surfaces, including other apps using the same account. This folder prepares the assets; no files have been uploaded to Stripe and no live account settings have been changed.

Stripe's [branding documentation](https://docs.stripe.com/get-started/account/branding) requires PNG or JPG uploads under 512 KB and at least 128 × 128 pixels. These PNGs meet those constraints. Its [Checkout appearance guide](https://docs.stripe.com/payments/checkout/customization/appearance) describes logo, background, button, font and shape settings.

## Scope

These are Geniousverse company branding assets. Individual Who's Chanting pack illustrations are separate Stripe Product images. Checkout currently uses existing Price IDs, so changing the company branding does not require altering prices or checkout code.

`preview.png` is an asset contact sheet, not an upload or a screenshot of a live Stripe account. `assets.json` records export dimensions, file sizes and palette. Source emblem: `Geniousverse/apps/GeniousverseApp/public/mainLogo/GeniousverseHeroLogo.svg`. No AI image generation was used; SVG artwork was adapted and exported with Inkscape.
