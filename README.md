# startup-lcp-system

Team-based Entry System — WordPress Theme: `lcp`

**Maintained by:** magnus.liistamo@humangeo.su.se  
**Project:** STARTUP — Sustainable transitions: action research and training in urban perspective (Horizon Europe)

---

## Purpose

Provides a WordPress-based system for data entry and export used in the STARTUP empirical database “Local Cultural Practices” (LCP).  
It supports collaborative documentation and analysis in a private, team-based environment.

It provides a **private, team-based workspace** where contributors collect, structure, and maintain data about local cultural practices in European cities.  
The system is admin-only and not publicly accessible — all activity happens inside the WordPress dashboard.

---

## Overview

The theme turns WordPress into a **closed collaborative database**:

- Each contributor belongs to a predefined **team** (e.g., Stockholm, Dortmund, Strasbourg).  
- Teams are assigned via **invite codes** during registration.  
- Users can create, edit, and view entries linked to their team only.  
- Administrators can view all data, export content, and manage users.

All data stays internal and can be exported for analysis in **CSV** format through a private REST endpoint and an admin tools page.

---

## Core Components

| Component | Description |
|---|---|
| **`lcp_entry`** | Custom post type for team-based written entries. Private, draft-only. |
| **`lcp_city`** | Custom post type for structured city/region data. Private, draft-only. |
| **Invite code system** | Assigns users to teams automatically on registration. |
| **Contributor access control** | Restricts each user to their own team’s data. |
| **Dashboard widgets** | Central interface for entries, cities, and team invites. |
| **Export module** | Admin-only CSV export with team filtering, fixed column order, and URL normalization. |
| **ACF integration** | Forms and fields via Advanced Custom Fields (ACF), including Google Maps. |

---

## Export

- Available under **Tools → Export LCP Data** (admin only).  
- Generates **CSV (UTF-8 with BOM)** and shows a 20-row preview.  
- **Column order:** `id`, `title`, `team`, then ACF fields in the same order as in the editor.  
- **Google Maps:** only **`<field>_lat`** and **`<field>_lng`** are exported.  
- **Links:** ACF **Link** values are flattened to plain URLs; **URL** fields are preferred for performance and clean exports.  
- **Filtering:** export all data or filter by **team**.

---

## Google Maps Integration

ACF’s Google Map field can be used in entries and cities. To enable maps, register a valid **Google Maps JavaScript API key**:

```php
function lcp_acf_init() {
    acf_update_setting('google_api_key', 'YOUR_API_KEY');
}
add_action('acf/init', 'lcp_acf_init');
```

**Restrict the key** in Google Cloud Console:
- Application restrictions: HTTP referrers (your admin domain[s])
- API restrictions: Maps JavaScript API (optionally Places/Geocoding if used)

> **Export note:** only latitude/longitude are exported from map fields.

---

## Output & Analysis

Data can be exported via the admin tool (CSV) and used in external analytical workflows or mapping frameworks.  
The export uses a private REST endpoint behind admin permissions.

---

## Theme Structure

```text
wp-content/
└── themes/
    └── lcp/
        ├── functions.php
        ├── style.css
        └── inc/
            ├── lcp-posttype.php         # CPT 'lcp_entry'
            ├── lcp-city-posttype.php    # CPT 'lcp_city'
            ├── team-registration.php    # Invite codes, team meta
            ├── contributor-access.php   # Capability restrictions
            ├── readme-dashboard.php     # Internal README widget
            ├── todo-dashboard.php       # “How it works” widget
            ├── admin-columns-team.php   # Sortable team columns
            └── export-lcp-data.php      # CSV export + REST endpoint
```

---

## Installation

1. Copy/clone the theme to `wp-content/themes/lcp`.  
2. Activate the theme (admin only).  
3. Install and activate **Advanced Custom Fields (ACF)**.  
4. Add a **Google Maps API key** (see above).  
5. Configure team invite codes in `team-registration.php`.  
6. Create Contributor accounts using those codes.

---

## Licensing

This repository is licensed under the **Apache License 2.0** (see `LICENSE`).  
Data collected through this system falls under **CC BY 4.0** — see `LICENSE-DATA.txt`.

---

## Attribution & Citation

If you use this system, please cite:

> **STARTUP (2025)**  
> *startup-lcp-system: Local Cultural Practices — WordPress-based team data collection system* [Software].  
> *In Sustainable Transitions. Action Research and Training in Urban Perspective (STARTUP).*  
> [https://github.com/Liistamo/startup-lcp-system](https://github.com/Liistamo/startup-lcp-system)

**Contact:** magnus.liistamo@humangeo.su.se

---

## EU Funding Acknowledgement

This project has received funding from the **European Union’s Horizon Europe** research and innovation programme under **Grant Agreement No. 101178523**.

Views and opinions expressed are those of the author(s) only and do not necessarily reflect those of the European Union or the granting authority.  
Neither the European Union nor the granting authority can be held responsible for them.
