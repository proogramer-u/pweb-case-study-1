# Browser QA assertions

Run with CUA read-only DOM evaluation after opening each page at the stated viewport.

- At 390 × 844 on catalog and cart: `document.documentElement.scrollWidth <= document.documentElement.clientWidth`. Tables may scroll inside `.table-wrap`.
- On catalog: every rendered `img` is complete and `naturalWidth > 0`.
- On cart: add Iced Americano, quantity 1; Rp18.000 shown; checkout link opens summary and guest identity form.
- At default desktop viewport: hero and menu cards align; no horizontal document overflow.
- On auth/login.php: email/password labels and register link render; no horizontal overflow on mobile.

Observed initial cart failure: document scrollWidth 418 > clientWidth 375; grid child's default min-width expanded the table wrapper. Fix: min-width:0 on split children, then repeat DOM assertion and screenshot.
