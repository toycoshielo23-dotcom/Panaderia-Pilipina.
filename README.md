# Web-Based Boarding House Management System

A simple PHP-based boarding house management website built to match the requirements in the project brief. It uses JSON files instead of MySQL, supports secure login for the admin and tenants, and includes room management, billing, announcements, and a public landing page.

## Features
- Public landing page with boarding house details, room rates, and house rules
- SEO-ready page headings, meta descriptions, and image alt text support
- Admin dashboard for managing rooms, tenants, bills, digital receipts, and announcements
- Tenant portal showing one tenant profile with monthly bills grouped in its billing history, plus board notifications
- Admin tenant records grouped by room, with per-room tenant counts and unassigned tenant records
- Room photo upload (JPG, PNG, or WEBP up to 5 MB)
- Rent estimate calculator with extra charges and discounts
- Rent pricing per bed, with full-room totals calculated from the room's bed count
- Tenant search and status filters
- Downloadable PDF payment receipts
- Dashboard analytics for occupancy, collections, unpaid balances, overdue bills, and six-month income
- JSON-backed storage for rooms, tenants, users, bills, and announcements
- Local PHP testing via `php -S localhost:8000`

## Default Login Credentials
- Admin: `admin` / `admin123`
- Tenant: `maria` / `tenant123`

## Run locally
```bash
php -S localhost:8000
```
Then open:
- `http://localhost:8000/index.php`
- `http://localhost:8000/login.php`

## Deploy to Render
1. Push this project to GitHub and create a Render **Web Service** from that repository.
2. Select the **Docker** runtime and the branch you want Render to deploy.
3. Add a persistent disk to the service with mount path `/var/data`. Persistent disks require a paid Render service plan.
4. Set the service's **Auto-Deploy** option to deploy on commits to the selected branch.

The included Docker setup listens on Render's `PORT` and stores JSON data and room photos under `/var/data`. On the first start, it copies the repository's existing JSON data and uploads to the disk; later deploys preserve the disk's versions.

## Project Structure
- `index.php` – public landing page
- `login.php` – login page for admin and tenants
- `admin.php` – admin control panel
- `tenant.php` – tenant dashboard
- `includes/bootstrap.php` – shared app logic and JSON helpers
- `assets/css/styles.css` – app styling
- `assets/js/app.js` – page interactions
- `data/` – JSON files used for persistence
- `uploads/rooms/` – room photos uploaded by the admin
