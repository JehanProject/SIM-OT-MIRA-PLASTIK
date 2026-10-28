# JavaScript module layout

Client-side modules live under `public/assets/js/modules/`, grouped by the feature's primary access role:

- `core/`: application entry point, shared state, routing, authorization checks, and event handlers.
- `shared/`: dashboard and stock overview pages used across roles.
- `cashier/`: point-of-sale, receivables, sales reports, and void/return pages used by Cashiers and Owners.
- `warehouse/`: stock receipt, transfer, and adjustment pages used by warehouse staff and Owners.
- `owner/`: finance reports, account management, activity logs, and backup tools restricted to Owners.

`public/assets/js/modules/core/mira.js` loads the feature modules and enforces role-based navigation and page access. Folder placement organizes client code; it is not a security boundary. Server-side authorization is required before moving operational data to a backend.
