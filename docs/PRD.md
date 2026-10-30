# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# KOPIPIRO — Coffee Shop Ordering & Management System

**Project:** PWEB Week 9–10 Individual Case Study  
**Repository:** `pweb-casestudy1`  
**Technology:** PHP Native, MySQL, HTML, CSS, JavaScript  
**Primary Goal:** Build a complete, functional, secure, and demonstrable coffee shop ordering application.

---

## 1. INSTRUCTIONS FOR CODEX

You are acting as a senior full-stack PHP engineer responsible for completing an existing university web programming project.

**IMPORTANT: THIS IS AN EXISTING PROJECT, NOT A GREENFIELD PROJECT.**

Before writing code:

1. Inspect the entire existing repository.
2. Read README.md and examine every existing PHP, CSS, JavaScript, SQL, and configuration file.
3. Identify which features are already implemented, partially implemented, broken, or missing.
4. Preserve working functionality and existing data.
5. Reuse existing conventions where reasonable.
6. Create a short implementation checklist based on the repository's actual condition.
7. Implement all remaining requirements, not just a prototype.
8. Verify the application through tests.
9. Document how to install, run, test, and demonstrate the finished application.

Do not stop after producing a plan. Implement the application.

Do not claim a feature works unless it has been tested or clearly report that testing was blocked by the environment.

Do not unnecessarily rewrite working code or introduce a completely different framework.

### Technology constraints

Use:

- PHP Native (no Laravel)
- PHP 8.2+ compatible syntax
- MySQL or MariaDB
- PDO with prepared statements
- PHP sessions
- HTML5
- CSS3
- Vanilla JavaScript
- Chart.js for analytics
- PhpSpreadsheet via Composer for XLSX export, when dependencies are installable

Do not introduce React, Next.js, Node.js backend, or other unnecessary frameworks.

Composer is allowed for appropriate PHP libraries, but the core application must remain PHP Native.

---

## 2. PRODUCT OVERVIEW

Kopipiro is a web application for a coffee shop that sells:

- Coffee
- Non-coffee beverages
- Toast
- Snacks
- Other food and beverages

The application supports three authenticated roles:

1. Owner
2. Cashier
3. Customer

It must also support unauthenticated guest purchasing.

### Core business rule

Authenticated customers receive a **10% discount** on their eligible order subtotal.

Guests can browse and order products but pay the normal price.

The discount must be calculated securely on the server, not trusted from frontend-submitted totals.

---

## 3. USER ROLES AND PERMISSIONS

### 3.1 Guest

A guest can:

- Browse products.
- See product images, names, descriptions, prices, categories, and ratings.
- Filter products by category.
- Search for products.
- Add multiple products to a cart.
- Modify item quantities.
- Remove items from the cart.
- Proceed to checkout without registering.
- Submit an order using a name and contact information.
- View the order confirmation and order number.

Guests do not receive the 10% discount.

Guests must not access internal management dashboards.

### 3.2 Customer

A registered and logged-in customer can:

- Do everything a guest can do.
- Receive a 10% discount at checkout.
- View their own order history.
- View their own order details.
- Submit product ratings and reviews for previously purchased products, where applicable.
- Log out.

Customers must not see another customer's orders.

### 3.3 Cashier

A cashier can:

- Log in.
- Access the cashier dashboard.
- View only orders assigned to that cashier.
- View order item details.
- Update order status through allowed workflow transitions.
- Mark orders as processing, ready, or completed as appropriate.
- View order totals and customer/guest information needed to process assigned orders.

A cashier must NOT:

- View orders assigned to other cashiers.
- Modify product catalog data.
- Access owner analytics.
- Export owner sales reports.
- Assign arbitrary orders to themselves.
- Change order prices or discounts.

Restrictions must be enforced in backend PHP queries, not only in the frontend.

### 3.4 Owner

An owner can:

- Log in to the owner dashboard.
- Create, read, update, and delete/deactivate products.
- Upload and replace product images.
- Manage categories.
- View all orders.
- Assign and reassign orders to cashiers.
- View the status and details of all orders.
- View sales summaries.
- View sales charts.
- Filter reports by date.
- Download sales reports in Excel format.
- View available cashier accounts.

Only the owner can create cashier accounts or promote users to privileged roles.

Public registration must always create a customer account.

---

## 4. DATABASE DESIGN

Inspect and preserve the existing `kopipiro` database where possible.

Existing tables are expected to include:

- categories
- products

The current products table may already include:

- id
- category_id
- name
- description
- price
- image
- is_available
- created_at

Extend the schema safely instead of dropping existing tables.

### 4.1 users

Required fields:

- id — primary key
- name
- email — unique
- password — password hash
- role — owner, cashier, customer
- created_at

Optional:

- phone
- is_active

### 4.2 categories

- id
- name
- created_at (optional)

### 4.3 products

- id
- category_id
- name
- description
- price — DECIMAL(12,2)
- image — nullable image filename
- is_available
- created_at
- updated_at (optional)

Existing product data must be preserved.

### 4.4 orders

- id
- order_number — unique
- user_id — nullable for guest checkout
- guest_name — nullable
- guest_email — nullable
- guest_phone — nullable
- cashier_id — nullable, assigned cashier
- subtotal — DECIMAL(12,2)
- discount_amount — DECIMAL(12,2)
- total_amount — DECIMAL(12,2)
- status
- created_at
- updated_at

Recommended order statuses:

- pending
- processing
- ready
- completed
- cancelled

An order can be created without a cashier assignment.

An owner assigns the order to a cashier.

### 4.5 order_items

- id
- order_id
- product_id — nullable if appropriate for historical preservation
- product_name — snapshot at purchase
- quantity
- unit_price — price at purchase
- line_total

Order items must preserve historical product names and prices even if the product is later edited.

### 4.6 reviews

- id
- user_id
- product_id
- rating — integer 1–5
- comment
- created_at

Only eligible authenticated customers who purchased the product can submit reviews.

Prevent duplicate reviews from the same user for the same product unless explicitly supporting edit/review updates.

Calculate average product ratings from stored review data.

### Database safety rules

- Use foreign keys when appropriate.
- Use indexes for common lookups, especially order status, user ID, cashier ID, and timestamps.
- Never store plaintext passwords.
- Never store currency as FLOAT.
- Preserve historical order information.
- Use safe migrations or an additive SQL schema file.
- Do not delete or overwrite existing user data.
- Provide a fresh-install SQL file and an upgrade path for the existing database.
- Use soft deletion/product deactivation when a product is referenced by historical orders.

---

## 5. FEATURE REQUIREMENTS

### FEATURE 1 — Public Product Catalog

Create an attractive, responsive homepage.

Requirements:

- Display available products.
- Show product images.
- Show product name.
- Show product description.
- Show product price in IDR.
- Show category.
- Show average rating and review count.
- Provide search functionality.
- Provide category filters.
- Provide Add to Cart buttons.
- Display a fallback image or placeholder if no image exists.

The homepage must query product data dynamically from MySQL.

Do not hardcode product lists in HTML.

### FEATURE 2 — Product CRUD

Owner must be able to:

**Create**
- Add product name.
- Select category.
- Enter description.
- Set price.
- Upload image.
- Set availability.

**Read**
- View all products.
- See product images, prices, categories, and statuses.

**Update**
- Edit all product fields.
- Replace product image.
- Preserve the old image if no replacement is uploaded.

**Delete**
- Deactivate products safely.
- Prevent historical order corruption.
- Clearly explain unavailable products.

Validation:

- Product name cannot be empty.
- Price must be greater than zero.
- Category must exist.
- Product image must pass upload validation.

### FEATURE 3 — Secure Image Upload

Implement:

- PHP `$_FILES`.
- `multipart/form-data`.
- MIME validation using file content.
- JPG, PNG, and WebP support.
- Maximum 2 MB file size.
- Random generated filenames.
- `move_uploaded_file()`.
- Secure directory handling.
- Upload error handling.
- Preview existing image on edit.
- Cleanup of newly uploaded files if database save fails.

Never trust the original filename or client-reported MIME type.

Prevent uploaded files from being executed as PHP scripts.

### FEATURE 4 — Authentication

Create:

- register.php
- login.php
- logout.php

Requirements:

- Public registration creates only customer accounts.
- Validate email.
- Prevent duplicate email registration.
- Use `password_hash()`.
- Use `password_verify()`.
- Use PHP sessions.
- Regenerate session ID after login.
- Destroy session securely on logout.
- Redirect users according to role.

Owner → Owner Dashboard

Cashier → Cashier Dashboard

Customer → Customer Storefront

Implement reusable authorization helpers.

Examples:

- requireLogin()
- requireRole('owner')
- requireRole('cashier')

Authorization must run server-side before protected actions.

### FEATURE 5 — Shopping Cart

Guests and customers must be able to:

- Add products to cart.
- Add multiple different products.
- Increase quantity.
- Decrease quantity.
- Remove individual products.
- Clear cart.
- See subtotal.
- Continue shopping.
- Checkout.

Use PHP sessions to store cart product IDs and quantities.

Do not trust client-submitted prices.

At checkout, retrieve current prices and availability from MySQL.

Validate that quantities are positive integers and enforce reasonable limits.

### FEATURE 6 — Discount Calculation

Business rule:

**Customer logged in = 10% discount.**

**Guest = 0% discount.**

Example:

Product A: Rp25,000 × 2 = Rp50,000

Product B: Rp20,000 × 1 = Rp20,000

Subtotal: Rp70,000

Logged-in customer discount: Rp7,000

Total: Rp63,000

Guest total: Rp70,000

Calculation:

- subtotal = sum(unit price × quantity)
- discount = subtotal × 0.10 for authenticated customer role
- total = subtotal − discount

Round deterministically to Indonesian rupiah.

Never trust a discount value supplied by the browser.

Store applied discount and final totals in the database.

Show both original subtotal and discount at checkout.

### FEATURE 7 — Checkout

Checkout page must display:

- Product names.
- Quantity.
- Unit prices.
- Line totals.
- Subtotal.
- Discount amount.
- Final total.

Guest checkout requires:

- Guest name.
- Contact email or phone.

Authenticated customer checkout uses their account identity.

On order submission:

1. Validate cart.
2. Retrieve products and prices from database.
3. Verify availability.
4. Calculate totals on server.
5. Start database transaction.
6. Insert order.
7. Insert order items.
8. Commit transaction.
9. Clear cart only after successful commit.
10. Redirect to confirmation page.

Avoid duplicate orders from form resubmission.

Show a unique order number after checkout.

Payment gateway integration is NOT required.

The application uses a simulated/manual offline order workflow, without claiming any real online payment has occurred.

### FEATURE 8 — Order Management

Owner can:

- View all orders.
- Filter by status.
- Search by order number.
- Filter by date.
- View order details.
- Assign pending orders to cashiers.
- Reassign orders as needed.

Cashier can:

- See only orders where cashier_id equals their authenticated user ID.
- Open only those order details.
- Perform allowed status changes.

Suggested workflow:

pending → processing → ready → completed

Allow cancellation through explicitly defined authorized transitions.

Cashier must not be able to access another cashier's order by changing the URL or submitting a different order ID.

Use server-side authorization checks and carefully scoped SQL queries.

### FEATURE 9 — Customer Order History

Authenticated customers should be able to:

- View their own orders.
- View order status.
- View order items and quantities.
- View discount and total.
- Open order details.

Enforce `user_id = current authenticated user ID` for every customer order lookup.

Guest order confirmation must not expose sensitive order details to someone merely guessing an order ID. Use a securely generated guest order access token or equivalent protection if guest order retrieval is implemented.

### FEATURE 10 — Ratings and Reviews

Customers should be able to:

- Submit a 1–5 star rating for purchased products.
- Write an optional comment.
- View their existing reviews.

Product catalog should show:

- Average rating.
- Number of reviews.

Validate review ownership and eligibility on the server.

Guest reviews are not required.

### FEATURE 11 — Owner Dashboard

Create a responsive dashboard containing:

- Total orders.
- Completed orders.
- Revenue from completed orders.
- Number of available products.
- Pending orders.
- Recent orders.

Display a sales chart using Chart.js.

Chart requirements:

- Sales by date.
- Total revenue per day.
- Date-range filter if feasible.
- Data from real MySQL queries.
- No hardcoded analytics values.

Cancelled or incomplete orders must not count as completed revenue.

Use stored order total amounts for historical sales.

### FEATURE 12 — Excel Export

Owner can export sales/order reports.

Generate XLSX using PhpSpreadsheet if dependencies are available.

Export columns:

- Order Number
- Order Date
- Customer / Guest Name
- Cashier
- Order Status
- Subtotal
- Discount
- Final Total

Implement:

- Owner-only authorization.
- Date filtering.
- Correct content headers.
- Valid download.
- Appropriate Indonesian rupiah number formatting.
- Safe handling of spreadsheet formula injection.

If XLSX dependencies cannot be installed, report this blocker explicitly and provide CSV export as a fallback, without claiming CSV satisfies the XLSX requirement.

### FEATURE 13 — UI/UX

Design a coherent coffee shop brand.

Visual direction:

- Warm cream backgrounds.
- Espresso brown.
- Caramel accents.
- Modern typography.
- Rounded cards.
- Simple navigation.
- Product photography.
- Responsive layouts.
- Consistent forms and buttons.

Required pages:

- Public homepage.
- Product catalog.
- Shopping cart.
- Checkout.
- Order confirmation.
- Register.
- Login.
- Customer order history.
- Owner dashboard.
- Owner product management.
- Owner order management.
- Cashier dashboard.
- Cashier order details.

Use real project data.

No fake operational analytics or nonfunctional interface buttons.

Provide helpful empty states and validation feedback.

Ensure basic accessibility, including semantic labels, keyboard-accessible controls, and adequate contrast.

---

## 6. SECURITY REQUIREMENTS

Security is mandatory, not optional.

Implement:

1. PDO prepared statements for database queries involving user input.
2. Output escaping with `htmlspecialchars()`.
3. Password hashing and verification.
4. Secure session handling.
5. Session ID regeneration after authentication.
6. Role-based authorization on server routes and actions.
7. CSRF protection for state-changing forms.
8. File upload size/type/content validation.
9. Safe generated upload filenames.
10. Server-side validation independent of HTML validation.
11. Protected order ownership checks.
12. Safe handling of errors without revealing database credentials.
13. Proper HTTP method enforcement.
14. Database transaction handling at checkout.
15. Secure guest order access.
16. Duplicate-submission protection.
17. Protection against spreadsheet formula injection in exports.

Use PHP configuration appropriate for local development and explain production-specific requirements such as HTTPS, secure cookies, and disabling verbose error display.

Never hardcode production secrets.

Do not commit credentials or `.env` files containing secrets.

---

## 7. PROJECT ARCHITECTURE

Adapt to the existing repository rather than replacing it blindly.

Suggested structure:

```text
pweb-casestudy1/
├── admin/
│   ├── dashboard.php
│   ├── products.php
│   ├── create_product.php
│   ├── edit_product.php
│   ├── delete_product.php
│   ├── upload_helper.php
│   ├── orders.php
│   ├── order_detail.php
│   ├── assign_order.php
│   ├── cashiers.php
│   └── export_excel.php
├── cashier/
│   ├── dashboard.php
│   ├── order_detail.php
│   └── update_status.php
├── auth/
│   ├── register.php
│   ├── login.php
│   └── logout.php
├── cart/
│   ├── index.php
│   ├── add.php
│   ├── update.php
│   └── remove.php
├── customer/
│   ├── orders.php
│   └── order_detail.php
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── config/
│   └── database.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/
│   └── products/
├── database/
│   ├── schema.sql
│   ├── upgrade.sql
│   └── seed.php
├── tests/
├── vendor/
├── index.php
├── products.php
├── checkout.php
├── order_success.php
├── composer.json
├── README.md
└── .gitignore
```

Directories and filenames may be adjusted when justified.

Keep business logic reusable and avoid excessive duplication.

No need to build a complex MVC framework for this assignment.

---

## 8. IMPLEMENTATION PHASES

### Phase 1 — Repository Audit

- Inspect existing files.
- Confirm database structure.
- Identify working and incomplete features.
- Preserve working CRUD and catalog behavior.
- Identify security issues.
- Create a TODO checklist.

### Phase 2 — Complete Product Management

- Fix Create, Read, Update, Delete.
- Complete image upload.
- Implement category management.
- Add server-side validation.
- Improve admin product list.

### Phase 3 — Authentication and Roles

- Create users table.
- Implement registration.
- Implement login and logout.
- Add password hashing.
- Add session management.
- Add role authorization.
- Provide safe local owner and cashier seeding.

### Phase 4 — Customer Shopping Experience

- Build storefront UI.
- Add category filters/search.
- Implement cart.
- Implement discount.
- Implement checkout.
- Persist orders and order items.

### Phase 5 — Order Workflows

- Create owner order listing.
- Implement cashier assignment.
- Create cashier dashboard.
- Implement status workflow.
- Enforce assigned-order access.
- Implement customer order history.

### Phase 6 — Reporting

- Build dashboard metrics.
- Integrate Chart.js.
- Add sales charts.
- Add date filtering.
- Implement Excel export.

### Phase 7 — Ratings, UI Polish, and QA

- Implement ratings/reviews.
- Improve responsiveness.
- Test all user flows.
- Fix errors.
- Complete documentation.

Complete all phases within the repository.

---

## 9. ACCEPTANCE CRITERIA

The project is complete only when the following tests pass.

### Product Management

- Owner can create a product with an image.
- Owner can edit product details.
- Owner can replace an image.
- Owner can deactivate a product.
- Product changes appear on the public catalog.
- Non-owner users cannot modify products.

### Authentication

- Customer can register and log in.
- Wrong password is rejected.
- Passwords are hashed.
- Public registration cannot create owners or cashiers.
- Role-specific routes are protected.

### Checkout

- Guest can order multiple products.
- Guest receives no discount.
- Logged-in customer receives exactly 10%.
- Cart totals are calculated correctly.
- Order and items are saved together.
- Refreshing order confirmation does not create another order.

### Cashier

- Owner can assign an order to Cashier A.
- Cashier A can view the assigned order.
- Cashier B cannot view the order.
- Cashier B cannot modify its status through a forged request.

### Owner

- Owner can see all orders.
- Dashboard metrics match completed order data.
- Sales chart uses database values.
- Excel export downloads successfully.
- Export content matches selected filters.

### General

- Navigation links work.
- No PHP syntax errors.
- No fatal database errors.
- Images load correctly.
- Forms have validation.
- Major workflows work on desktop and mobile.
- Existing sample products remain available.
- No obvious SQL injection, XSS, CSRF, or authorization vulnerabilities.

---

## 10. TESTING REQUIREMENTS

Run automated checks where feasible.

At minimum:

- PHP syntax lint on all PHP files.
- Database connectivity verification.
- Create/update/deactivate product tests.
- Image upload validation tests.
- Authentication and role access tests.
- Cart and discount calculation tests.
- Guest checkout and customer checkout tests.
- Order ownership and cashier isolation tests.
- XLSX export verification.
- Regression testing for existing features.

For actions that require manual browser verification, provide exact steps and expected results.

If the environment lacks MySQL, PHP extensions, Composer, or browser tooling, identify that limitation and still complete all code that can be implemented and statically checked.

Do not fabricate test success.

---

## 11. README AND DOCUMENTATION

Write a useful README.md in Indonesian covering:

1. Project description.
2. Feature list.
3. Technology stack.
4. Folder structure.
5. Database schema and relationships.
6. How to set up locally using XAMPP or MAMP.
7. Database configuration.
8. How to import SQL.
9. How to install Composer dependencies.
10. How to run the PHP application.
11. How to create local owner/cashier demo accounts safely.
12. Role-based access explanation.
13. Explanation of CRUD and PDO prepared statements.
14. Explanation of the 10% discount.
15. Explanation of checkout and order workflows.
16. Explanation of dashboard reporting.
17. How to test the application.
18. Known limitations.

Include a concise user guide for the university project demonstration.

Do not commit fixed, publicly known privileged passwords.

Provide a safe one-time local initialization process for test accounts, with credentials supplied through local environment variables or an interactive setup process.

---

## 12. FINAL DELIVERABLES

Produce:

1. Complete functional PHP application.
2. Updated MySQL database schema/migrations.
3. Functional owner dashboard.
4. Functional cashier dashboard.
5. Functional customer storefront.
6. Authentication and role-based access.
7. Product management and secure image uploads.
8. Shopping cart and checkout.
9. Correct guest/customer pricing.
10. Order assignment and order-status management.
11. Sales charts.
12. Excel exports.
13. Responsive CSS.
14. README documentation.
15. Manual and/or automated testing instructions.
16. Summary of implemented features and test results.

### Definition of Done

The project is considered finished when the original PWEB case study requirements are satisfied, core role-based workflows function correctly, and remaining limitations are explicitly documented.

---

## 13. EXECUTION RULES FOR CODEX

Follow these rules throughout implementation:

- Inspect before editing.
- Preserve existing working code.
- Make incremental, logically grouped changes.
- Avoid unnecessary complexity.
- Use clear function and variable names.
- Keep PHP Native.
- Use prepared statements.
- Keep validation server-side.
- Ensure buttons have actual working behavior.
- Do not substitute mock data for required functionality.
- Do not stop after one feature.
- Continue through all implementation phases.
- Check regressions as you progress.
- Respect any existing user changes.
- Do not perform destructive database operations without an appropriate migration and backup strategy.
- If a dependency cannot be installed, implement all unaffected features and report the blocker clearly.
- Update README and verification results.

After implementation, provide:

**A. Completed feature checklist**  
**B. Files created or modified**  
**C. Database migration instructions**  
**D. Tests performed and results**  
**E. Manual verification steps**  
**F. Remaining issues or environment blockers**

**START NOW: inspect the current repository, identify the gaps, and complete Kopipiro end-to-end.**
