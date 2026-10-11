# Electrical Shop development phases

## Working rules

Keep frontend and backend simple, implement one small phase at a time, and verify it before adding the next feature. The shared product plan proposes a stock and billing SaaS. Storefront, branches, pricing and tax details remain open decisions.

## Phase 1 Basic setup

Scope: create Angular and Laravel projects, configure `electrical_shop_dev`, add a safe database health endpoint and connect a simple Angular setup screen.

Assessment on 11 October 2026:

- Both supplied project folders were empty.
- MySQL compatible MariaDB 10.4.32 was running; the named database existed with no tables.
- Installed PHP was 8.2.12; installed Node was 24.19.0.
- Laravel 12 and Angular 21 were selected for runtime compatibility. Angular 22 was assessed first, but its native build tools were blocked by Windows Application Control.
- No existing application data or code required migration.

Status: complete and verified on 11 October 2026.

Verification:

- Backend tests: 6 passed, 10 assertions.
- Backend formatting and Composer configuration checks passed.
- Base migrations completed in MySQL; 9 framework tables and no users.
- Live `/api/health` returned HTTP 200 with database connected.
- Backend uses port 8010 because another existing Laravel project owns port 8000.
- Angular production build passed; 4 frontend tests passed across 2 test files.
- Frontend uses port 4210 because port 4200 was already occupied.
- The frontend app shell returned HTTP 200.
- The frontend proxy at `http://localhost:4210/api/health` returned HTTP 200 with database connected. Allowed frontend origin was verified as `http://localhost:4210`.
- Angular uses the official Rollup WebAssembly package via a dependency override; no Windows security policy was changed.

Local preview: `http://localhost:4210`. Backend health: `http://127.0.0.1:8010/api/health`.

Local PHP prints an existing duplicate mongodb extension warning. It does not stop the API or tests; machine PHP configuration has not been changed.

The stock/billing, user login, shop registration and SaaS subscription features are not part of this first setup phase.

## Phase 2A and 2B: owner account and shop workspace

Status: complete and verified on 11 October 2026. Phase 2C remains next.

- Added shop registration, owner login/logout and session restoration after refresh.
- Registration creates the shop and owner together in a database transaction. The server assigns the owner role and shop identity.
- Added a protected dashboard and editable shop name, phone and address. Shop reads and updates always use the signed-in owner's shop.
- Added server validation, login rate limits, session regeneration, CSRF protection and checks for inactive accounts/shops.
- Frontend follows the supplied visual reference: light background, white cards, blue accents, compact Lato typography, SVG icons, top navigation and sidebar. Shared styles and icons should be reused in later screens. The source screenshot's exact font is unknown; Lato is the chosen local font with its license included.
- Layout works on desktop and mobile. Future modules are labelled with their planned phase; stock and sales numbers are not fabricated.

Verification:

- Backend: 17 tests passed with 88 assertions; formatting passed. Tests cover atomic registration, validation, login/logout, rate limits, CSRF, inactive accounts and isolation between two shops.
- Frontend: 12 tests passed across 5 files; production build passed without budget warnings. Tests cover session behavior, route protection, validation and duplicate submission prevention.
- Browser: registered a development demo shop, updated its profile, refreshed to restore the session, signed out, verified protected-route redirection and signed in again.
- Desktop and mobile layouts were visually checked.
- MySQL migration applied to `electrical_shop_dev`. A development-only Demo Electrical Shop account remains for preview; it is not real business data.

Deployment contract: serve Angular and Laravel `/api` from the same origin. Development uses the Angular proxy. Authentication uses Laravel session cookies and CSRF cookies, without storing access tokens in browser local storage.

## Phase 2C: staff roles and access

Status: complete and verified on 11 October 2026.

- Two fixed roles: owner and staff. Public shop registration always creates an owner; only an owner can create staff in their own shop.
- Owner-only Staff access screen supports account creation, paginated listing, active/inactive access and optional shop profile editing.
- Default staff access is dashboard and read-only shop profile. Staff management cannot be granted to staff. Unknown permissions, injected roles and tenant IDs are rejected.
- Shop editing is checked on the server as well as in the frontend. Permission denial refreshes account permissions without logging out an otherwise active staff member.
- Inactive users and inactive shops cannot sign in or continue protected requests. Logout remains available. Access changes are checked on the next request, not pushed live to an idle page.
- Staff records are looked up within the current shop and staff role. Other shops' accounts and owners return 404 for owner access changes.
- Existing users remain intact; one nullable JSON permissions column was added to users. No extra role-management package or permission tables.

Verification:

- Backend: 25 tests passed, 158 assertions; formatting passed. Includes staff validation, password hashing, session retention for the creator, permission grant/revocation, disabling/reactivating, role injection, owner protection and two-shop isolation.
- Frontend: 18 tests passed across 8 files. Includes owner-only routing, staff submission validation, duplicate-submit protection, failed-save retry and permission refresh after 403.
- Production build passed without budget warnings.
- Browser: created development-only Demo Staff, confirmed default read-only profile and blocked direct staff-management navigation; disabled account login was rejected; reactivation plus profile-edit permission allowed a successful profile save.
- A development demo staff account remains in Demo Electrical Shop for preview. No real staff or business data was used.

Limits for this small phase: email must be unique across accounts (one shop membership per account); owner creates the initial password and shares it privately. Invitations, password recovery/change, audit history and stock/billing permissions remain future work. No automated email is sent. Stock/billing permissions will be added when those modules exist.

Reference documentation: [Laravel authorization](https://laravel.com/docs/12.x/authorization), [Angular route guards](https://angular.dev/guide/routing/route-guards).

## Next small phases

1. Phase 2A: complete, backend owner account and shop scoping.
2. Phase 2B: complete, frontend account screens and shop workspace.
3. Phase 2C: complete, staff roles, profile permissions and shop isolation for implemented endpoints.
4. Phase 3A: categories and products.
5. Phase 3B: customers and suppliers.
6. Phase 4A: purchases and opening stock.
7. Phase 4B: sales, invoices and payments.
8. Phase 4C: returns, cancellation and stock adjustments.
9. Phase 5: useful reports and reconciliation.
10. Phase 6: plans, trial and manual subscriptions.
11. Phase 7: verified subscription payments.
12. Phase 8: pilot deployment, backups and restore check.

Every phase must record what changed, checks performed and unresolved issues. Business rules requiring a decision should be clarified before dependent implementation.
