# Changelog

All notable changes to the Polylang WooCommerce Data Sync plugin will be documented in this file.

## [1.0.0] - 2026-01-05

### Added
- Initial release of Polylang WooCommerce Data Sync
- Bi-directional synchronization of WooCommerce product data across languages
- Support for syncing:
  - Product SKU
  - Product prices (regular and sale)
  - Product stock quantities
  - Product images and galleries
  - Product attributes
  - Variable product data
- Conflict detection system for simultaneous edits across languages
- Admin dashboard page for monitoring sync conflicts
- Compatibility with Polylang free and pro versions
- Compatibility with WooCommerce 3.0+
- Admin notices for missing dependencies
- Proper error handling and logging

### Requirements
- WordPress 5.8+
- PHP 7.4+
- Polylang (free or pro)
- WooCommerce 3.0+

### Fixed
- N/A (Initial release)

### Changed
- N/A (Initial release)

### Deprecated
- N/A (Initial release)

### Removed
- N/A (Initial release)

### Security
- Implemented security headers to prevent direct file access
- Added proper WordPress capability checks
- Input sanitization and output escaping implemented
