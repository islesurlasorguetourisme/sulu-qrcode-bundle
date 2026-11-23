# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- QR codes managed from the Sulu administration: list, search by label, filter by tag, create, edit, delete.
- Dynamic targets: any Sulu link (page, article, media, external URL), editable after printing.
- Stable public endpoint `/qr/{code}` with a 10-character code, temporary (302) uncached redirection.
- Optional redirection to the language of the visitor's phone, with fallback.
- Rendering options: module colour, background colour, transparent background, size, margin,
  error correction level, logo in the centre.
- PNG and SVG downloads; generated files are cached on the local disk.
- "Downloads" tab listing each format with a styled preview and its intended use.
- Scan statistics without personal data: total, last scan, per-day breakdown; crawlers excluded.
- `QrCodeScannedEvent` to plug analytics or alter the destination.
- English and French administration translations.

### Security

Hardening done before the first release, after an internal audit:

- Choosing a logo requires the permission to view the collection of the media.
- Logos are validated and re-encoded before use; the original file is never embedded in a download.
- Tags are referenced by identifier only: the API can no longer create tags.
- Redirection targets are validated when saved and again when redirecting.
- Image sizes are restricted to a fixed list and stored files are bounded per QR code.
