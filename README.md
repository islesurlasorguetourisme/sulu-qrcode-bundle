# Sulu QR Code Bundle

[![Latest version](https://img.shields.io/packagist/v/islesurlasorguetourisme/sulu-qrcode-bundle?label=version)](https://packagist.org/packages/islesurlasorguetourisme/sulu-qrcode-bundle)
[![CI](https://github.com/islesurlasorguetourisme/sulu-qrcode-bundle/actions/workflows/ci.yaml/badge.svg?branch=main)](https://github.com/islesurlasorguetourisme/sulu-qrcode-bundle/actions/workflows/ci.yaml)
[![PHP](https://img.shields.io/packagist/dependency-v/islesurlasorguetourisme/sulu-qrcode-bundle/php?label=php)](composer.json)
[![Sulu](https://img.shields.io/packagist/dependency-v/islesurlasorguetourisme/sulu-qrcode-bundle/sulu%2Fsulu?label=sulu)](https://sulu.io)
[![License](https://img.shields.io/packagist/l/islesurlasorguetourisme/sulu-qrcode-bundle)](LICENSE)

Dynamic QR codes for [Sulu](https://sulu.io) 3.

A QR code printed on a poster cannot be changed, but the page it leads to often has to. This bundle
makes every QR code point to a short, stable URL of your own website (`https://example.com/qr/k7qm2xw9pb`)
which redirects to a target you can edit at any time from the Sulu administration. Nothing is hosted
by a third party: codes, files and statistics live in your application.

## Features

- **QR codes in the administration**: a dedicated navigation entry with a list (thumbnail, search by
  label, filter by tag, sortable scan counters), a creation form and an edit form.
- **Dynamic targets**: the destination is a native Sulu link - page, article, media or external URL -
  resolved at scan time, so renamed or moved pages keep working.
- **Language aware**: optionally send visitors to the translation matching the language of their
  phone, falling back to the language you picked.
- **Rendering options**: module colour, background colour, transparent background (black on
  transparent by default), size, margin, error correction level, logo in the centre.
- **PNG and SVG downloads**, generated locally and cached on disk. The list offers a one-click PNG;
  the **Downloads** tab of a QR code lists every format with a styled preview and a plain-language
  description of what it is for.
- **Scan statistics without personal data**: total, last scan and a per-day breakdown. No IP address,
  no user agent, no cookie. Crawlers and link previewers are not counted.
- **No JavaScript to build**: the administration only uses field types, list adapters and actions
  shipped with Sulu. Installing the bundle never forces you to rebuild the admin frontend.

## Requirements

- PHP 8.2 or higher, with the `gd` extension
- Sulu 3.0 or higher

## Installation

### 1. Install the package

```bash
composer require islesurlasorguetourisme/sulu-qrcode-bundle
```

### 2. Register the bundle

```php
// config/bundles.php
return [
    // ...
    Islesurlasorguetourisme\SuluQrCodeBundle\SuluQrCodeBundle::class => ['all' => true],
];
```

### 3. Import the routes

The public route must be imported in **both** contexts: the website serves it, and the administration
needs it to know which URL to write into the images.

```yaml
# config/routes/sulu_admin.yaml
sulu_qr_code_api:
    resource: "@SuluQrCodeBundle/config/routing_admin_api.yaml"
    prefix: /admin/api

sulu_qr_code_website:
    resource: "@SuluQrCodeBundle/config/routing_website.yaml"
```

```yaml
# config/routes/sulu_website.yaml
sulu_qr_code_website:
    resource: "@SuluQrCodeBundle/config/routing_website.yaml"
```

### 4. Update the database

The bundle adds three tables (`qr_codes`, `qr_code_tags`, `qr_code_daily_scans`).

```bash
# with migrations
php bin/adminconsole doctrine:migrations:diff
php bin/adminconsole doctrine:migrations:migrate

# or, on a development database
php bin/adminconsole doctrine:schema:update --force
```

### 5. Grant the permissions

In the administration, open **Settings › User roles**, edit the roles that should manage QR codes and
enable the permissions of the **QR codes** section. Reload the administration (log in again if the
navigation entry does not show up).

### 6. Pin the public host (production)

```yaml
# config/packages/sulu_qr_code.yaml
sulu_qr_code:
    base_url: 'https://www.example.com'
```

Without it, the bundle writes the host used to browse the administration into the images. That is
convenient in development and dangerous in production: set it explicitly.

## Configuration

```yaml
sulu_qr_code:
    # Scheme and host written into the QR codes. null = host of the current request.
    base_url: null

    # Where the generated PNG/SVG files are stored.
    storage_directory: '%kernel.project_dir%/var/storage/qr_codes'

    # Where a scan goes when its target is unpublished or deleted. null = 404.
    fallback_url: null

    # Do not count crawlers, link previewers and monitoring tools as scans.
    ignore_bots: true
```

## Read this before printing

Three things are baked into every printed QR code and can never change afterwards:

| What | Why it matters |
|---|---|
| The host (`base_url`) | If the domain changes, keep the old one redirecting to the new one. |
| The path `/qr/` | Do not add a prefix when importing `routing_website.yaml` once codes are in circulation. |
| The code itself | It is generated once and never modified, whatever you edit. |

Everything else - target, label, tags, language behaviour - can be changed freely. Changing the
colours, the size or the logo produces a new image but does not invalidate the printed ones.

Practical advice: keep a strong contrast between the code and whatever it is printed on (a transparent
background takes the colour of the paper), keep a margin around it, and always scan a proof before
sending a file to the printer - especially with a logo.

## How it works

- **Identifiers.** Each QR code has a UUID v7 used internally, and a random 10-character public code
  made of unambiguous lower-case characters. A short code keeps the URL short, hence the QR code
  sparse and easy to scan at small sizes. Lookups are case-insensitive.
- **Redirection.** The endpoint answers `302 Found` with `Cache-Control: no-store`. A permanent or
  cacheable redirection would freeze the target in browsers and hide later scans.
- **Resilience.** A failure of the statistics never prevents the redirection. A target that cannot be
  resolved in the expected language falls back to another published translation, then to
  `fallback_url`.
- **Storage.** Generated files are a cache keyed by a fingerprint of everything that influences the
  pixels. They are rendered on first use and can be deleted at any time; the database is the source
  of truth.
- **Statistics.** One counter per QR code and per day, incremented atomically in SQL. Days follow the
  default time zone of PHP.

## Extending

### React to scans

```php
use Islesurlasorguetourisme\SuluQrCodeBundle\Event\QrCodeScannedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class TrackQrCodeScan
{
    public function __invoke(QrCodeScannedEvent $event): void
    {
        if (!$event->isCounted()) {
            return; // crawler or HEAD request
        }

        // Forward to your analytics tool…
        // …or send the visitor somewhere else:
        // $event->setTargetUrl('https://example.com/campaign');
    }
}
```

### Replace a service

Alias one of these interfaces to your own implementation:

| Interface | Default | Purpose |
|---|---|---|
| `Renderer\QrCodeRendererInterface` | endroid/qr-code | Draws the image |
| `Storage\QrCodeStorageInterface` | Local disk | Stores the generated files |
| `Scan\ScanRecorderInterface` | SQL counters | Records a scan |
| `Repository\QrCodeRepositoryInterface` | Doctrine ORM | Loads and saves QR codes |

## Security

What the bundle enforces, and what remains your responsibility.

**Permissions.** Every administration endpoint, image downloads included, requires the matching
permission of the **QR codes** security context (view, add, edit, delete). The public endpoint is the
only unauthenticated entry point.

**Redirections.** A QR code is, by nature, a redirection chosen by an editor: anyone allowed to add or
edit QR codes can make your domain redirect to an external site. Grant these permissions accordingly.
Targets are restricted to `http(s)`, `mailto:` and `tel:` links and to pages of the site; control
characters, white space, `javascript:`, `data:` and protocol-relative URLs are refused when saving
**and again** when redirecting, whatever produced the URL (stored link, link provider, event listener).

**Logos.** A logo is an uploaded file, hence untrusted. Choosing a media as logo requires the permission
to view its collection, since the logo ends up in the downloadable image. The file is never passed to
the renderer as is: its size and declared dimensions are checked before decoding (decompression bombs),
then it is re-encoded as a PNG of at most 1200 px. Metadata (EXIF, GPS position) and anything that is
not pixels are dropped, and the original bytes never leave the application.

**Generated files.** Images are written to disk while answering requests, so their number is bounded:
only a fixed list of preview sizes is served, and a single version is kept per QR code. Identifiers
used in file names are generated by the server and sanitised.

**Tags and media** are only ever referenced by identifier. The bundle never creates them, so it cannot
be used to get around their own permissions.

**Statistics** can be inflated by anyone who requests the public URL in a loop: counting without
storing anything about visitors means scans cannot be deduplicated. Treat the numbers as an order of
magnitude, and rate-limit `/qr/` at your reverse proxy if abuse matters to you.

**Cross-site request forgery.** Like the rest of the Sulu administration API, write endpoints rely on
the session cookie being `SameSite=Lax` (the default). Do not relax that setting.

**Production checklist**

- Set `sulu_qr_code.base_url`, so that the host written into the images does not depend on the request.
- Keep `storage_directory` outside of the web root (the default is under `var/`).
- Give the add/edit permissions only to people you would let publish a link on your website.

To report a vulnerability, please use the private "Report a vulnerability" form of the GitHub
repository rather than a public issue.

## Known limitations

- The edit form shows a plain black and white preview (Sulu's native `qrcode` field). The styled
  image is shown in the list and in the **Downloads** tab, after saving. A live preview requires
  custom admin JavaScript and is planned as an opt-in.
- Sulu can only attach bare icons, without label or tooltip, to list rows. That is why the list has a
  single download icon (PNG) and the other formats live in the **Downloads** tab.
- Logos must be raster images (PNG, JPEG, GIF, WebP). In SVG files the logo is drawn over the
  modules; in PNG files the modules behind it are cleared.
- The list offers filtering by tag, not sorting by tag: a QR code can carry several tags.
- Images are served in the size configured on the QR code and in three preview sizes (80, 160 and
  480 px) only. Change the size of the QR code to download a bigger PNG, or use the SVG.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan   # static analysis
composer cs        # coding standards (dry run)
```

## License

MIT. See [LICENSE](LICENSE).
