# startup-lcp-system

Team-based Entry System — WordPress Theme: `lcp`

**Maintained by:** magnus.liistamo@humangeo.su.se  
**Project:** STARTUP — Sustainable transitions: action research and training in urban perspective (Horizon Europe)

---

## Purpose

Provides a WordPress-based system for data entry and export used in the STARTUP empirical database “Local Cultural Practices” (LCP). It supports collaborative documentation and analysis in a private, team-based environment.

It provides a **private, team-based workspace** where contributors collect, structure, and maintain data about local cultural practices in European cities.  
The system is admin-only and not publicly accessible — all activity happens inside the WordPress dashboard.

---

## Overview

The theme converts WordPress into a **closed collaborative database**:

- Each contributor belongs to a predefined **team** (e.g. Stockholm, Dortmund, Strasbourg).  
- Teams are assigned via **invite codes** during registration.  
- Users can create, edit, and view entries linked to their team only.  
- Administrators can view all data, export content, and manage users.

All data stays internal and can be exported for analysis in **CSV** or **Excel** format through a custom REST endpoint and admin tools menu.

---

## Core Components

| Component | Description |
|------------|-------------|
| **`lcp_entry`** | Custom post type for team-based written entries. Private, non-published, draft-only. |
| **`lcp_city`** | Custom post type for structured city/region data. Private, draft-only. |
| **Invite code system** | Assigns users to teams automatically on registration. |
| **Contributor access control** | Restricts each user to their own team’s data. |
| **Dashboard widgets** | Central interface for all users — includes entries, cities, and team invite overview. |
| **Export module** | Enables administrators to export structured data as CSV or Excel for analysis. |
| **ACF integration** | Uses Advanced Custom Fields (ACF) for form and field management, including Google Maps fields (API key required). |

---

## Google Maps integration

ACF’s Google Map field is used for geolocated data within entries and cities.  
To enable maps, register a valid **Google Maps JavaScript API key** in `functions.php`:

```php
function lcp_acf_init() {
    acf_update_setting('google_api_key', 'YOUR_API_KEY');
}
add_action('acf/init', 'lcp_acf_init');
```

The API key should be restricted in Google Cloud Console (HTTP referrers + Maps JavaScript, Geocoding and Places APIs).

---

## Output and analysis

The database supports structured export of entries for comparative analysis.  
Data can be exported via the WordPress REST API in **CSV** and **Excel** formats,  
enabling integration with external analytical workflows or mapping frameworks.

---

## Theme Structure

```text
wp-content/
└── themes/
    └── lcp/
        ├── functions.php              # Loads all theme modules
        ├── style.css                  # Theme header (Theme Name: lcp)
        └── inc/
            ├── lcp-posttype.php         # CPT 'lcp_entry', save box, dashboard widget
            ├── lcp-city-posttype.php    # CPT 'lcp_city', save box, dashboard widget
            ├── team-registration.php    # Invite codes, team meta, registration logic
            ├── contributor-access.php   # Contributor capability enhancements
            ├── readme-dashboard.php     # Dashboard widget displaying internal README
            ├── todo-dashboard.php       # Contributor instruction widget (“How it works”)
            ├── admin-columns-team.php   # Adds sortable team columns to CPT tables
            └── export-lcp-data.php      # CSV/Excel export logic + REST endpoint
```

---

## Installation

1. Clone or copy the theme into `wp-content/themes/lcp`.  
2. Activate the theme in WordPress (admin only).  
3. Install and activate **Advanced Custom Fields (ACF)**.  
4. Add a **Google Maps API key** (see above).  
5. Configure team invite codes in `team-registration.php`.  
6. Create Contributor accounts using those codes.

---

## Licensing

This repository is licensed under the **Apache License, Version 2.0** (see `LICENSE`).  
Data collected through this system falls under **CC BY 4.0** — see `LICENSE-DATA.txt`.

---

## Attribution & citation

If you use this system, please cite:

> **STARTUP. (2025).**  
> *startup-lcp-system: Local Cultural Practices — WordPress-based team data collection system* [Software].  
> *In Sustainable Transitions. Action Research and Training in Urban Perspective (STARTUP).*  
> [https://github.com/Liistamo/startup-lcp-system](https://github.com/Liistamo/startup-lcp-system)

**Contact:** magnus.liistamo@humangeo.su.se

---

## EU funding acknowledgement

This project has received funding from the **European Union’s Horizon Europe**  
research and innovation programme under **Grant Agreement No 101178523**.

---
