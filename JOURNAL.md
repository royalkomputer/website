# Development Journal

## v2.2 — Current

- Product catalog with search, category filter, condition filter, price sort
- Real-time store status (operating hours, manual override, scheduled closures)
- Admin dashboard with catalog management, photo upload/reorder/delete
- Multi-role admin system (super admin, admin)
- Operating hours configuration (per-day)
- Temporary closure scheduling
- Photo auto-conversion to WEBP on upload
- PostgreSQL product database with JSON cache fallback
- Mobile-responsive UI with Tailwind CSS
- WhatsApp order integration
- Session-based authentication with bcrypt password hashing
- Image carousel in product detail modal
- Indonesian language UI, WIB timezone, IDR currency

### Notable technical decisions
- Flat PHP architecture (no framework) for simplicity
- File-based JSON storage for config, database for products
- WEBP-only image format for consistent performance
- Session auth with role-based access control

## v1.0 — Initial Release

- Basic product listing page
- Admin login and simple product management
- Core PostgreSQL integration
