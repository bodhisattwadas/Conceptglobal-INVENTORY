# Menu Module Guide

This guide documents the modules currently available from the top navigation menu. Disabled menu items are listed separately as placeholders.

## Dashboard

Route: `/dashboard`

Purpose:
The Dashboard is the landing page after login. It gives users a quick operational view of current activity, stock health, and recent business movement.

Use this module to:
- Review high-level business status after login.
- Check low-stock indicators.
- See recent sales or operational summaries where available.
- Navigate into the main workflows from the top menu.

Typical workflow:
1. Open `Dashboard`.
2. Review summary cards and alerts.
3. Use the top menu to move into Buying, Stock, or Admin tasks.

Notes:
- Dashboard numbers depend on the underlying purchase, inventory, product, and sales records.
- Stock-related alerts use product/inventory quantities and minimum quantity settings.

## Buying

The Buying menu groups vendor procurement work: purchase orders, vendor invoices, and vendors/suppliers.

### Purchase Orders

Route: `/purchases`

Purpose:
Purchase Orders manage the buying cycle from draft PO creation through ordering and receiving goods.

Use this module to:
- Create a purchase order.
- Select vendor/supplier, brand, and products.
- Auto-generate PO references.
- Move draft PO to ordered/active.
- Receive ordered goods.
- Download PO PDF.
- View supplier, store, and order details.

Main records:
- Purchase order header: supplier, brand/company, PO date, required delivery date, status.
- Purchase items: product, ordered quantity, received quantity, MRP, discount, unit price, line total.

Important statuses:
- `Draft`: PO is being prepared.
- `Ordered`: PO is sent/active and can be received.
- `Received`: goods are received and inventory is updated.
- `Cancelled`: PO is cancelled.

Typical workflow:
1. Open `Buying -> Purchase Orders`.
2. Click `Create Purchase Order`.
3. Select supplier/vendor.
4. Select brand/company.
5. Add products and quantities.
6. Save as draft.
7. Open the PO and mark it ordered.
8. Click `Receive` when goods arrive.
9. Enter received quantities.
10. Upload optional proof image and vendor invoice file.
11. Confirm receipt.

System results after receipt:
- Received quantities are added to Inventory.
- Inventory movement records are created.
- A Vendor Invoice is created as unpaid.

### Vendor Invoices

Route: `/vendor-invoices`

Purpose:
Vendor Invoices track supplier invoices created after goods are received. This module handles invoice amount, payment status, partial payments, and full settlement.

Use this module to:
- View invoices created from received purchase orders.
- Open the related purchase order.
- Open uploaded vendor invoice files.
- Track invoice amount, paid amount, due amount, and payment status.
- Record completed payments.
- Mark invoices partially paid or fully paid based on amount paid.

Main fields:
- PO Reference
- Vendor Invoice Number
- Supplier/Vendor
- Amount
- Paid
- Due
- Status
- Paid At

Payment statuses:
- `Unpaid`: no payment recorded.
- `Partially Paid`: paid amount is less than total invoice amount.
- `Fully Paid`: paid amount equals total invoice amount.

Payment rules:
- Amount Paid defaults to the current due amount.
- Amount Paid cannot be greater than the due amount.
- If Amount Paid is less than due, invoice becomes Partially Paid.
- If Amount Paid clears the due amount, invoice becomes Fully Paid.

Typical workflow:
1. Open `Buying -> Vendor Invoices`.
2. Search or filter invoices.
3. Click the view icon.
4. Enter Amount Paid.
5. Select Payment Method.
6. Add Payment Reference if applicable.
7. Confirm Payment Date.
8. Add Payment Notes if needed.
9. Click `Save & Complete Payment`.

### Vendors / Suppliers

Route: `/master/suppliers`

Purpose:
Vendors/Suppliers is the master data module for organizations that supply products. These records are used in purchase orders and vendor invoices.

Use this module to:
- Create suppliers/vendors.
- Edit supplier/vendor profile details.
- Link suppliers to brands/companies they supply.
- Store contact information.
- Store bank information.
- Upload blank cheque and GST documents.
- View supplier profile.
- Download supplier profile PDF.

Main sections:
- Supplier Details
- Contact Details
- Address
- Bank Details
- Supplier Documents
- Brands / Companies Supplied
- Notes

Important notes:
- Bank account number is shown in full on supplier details and downloaded profile where required.
- Supplier document links are shown on the profile page, not inside the downloaded profile PDF.
- Uploaded files are served through `/media/...` on deployed hosting.

Typical workflow:
1. Open `Buying -> Vendors / Suppliers`.
2. Create or edit supplier.
3. Enter identity and contact details.
4. Add bank details.
5. Upload blank cheque and GST document if available.
6. Select supplied brands/companies.
7. Save.

## Stock

The Stock menu groups product catalog and inventory setup.

### Inventory

Route: `/inventory`

Purpose:
Inventory shows current stock quantity by product. It is updated when purchase orders are received.

Use this module to:
- View current product stock.
- Check product, SKU, brand, category, quantity, unit, and minimum quantity.
- Identify low-stock products.
- Export inventory data.

Main fields:
- SKU
- Product
- Brand / Company
- Category
- Quantity
- Unit
- Minimum Quantity
- Status

Stock behavior:
- Receiving a purchase order increases inventory quantity.
- Existing product quantities were backfilled into inventory.
- Product quantity is still kept synchronized for current sales compatibility.

Typical workflow:
1. Open `Stock -> Inventory`.
2. Search for product or SKU.
3. Review quantity and low-stock status.
4. Use product module to adjust product master details if needed.

### Products

Route: `/master/products`

Purpose:
Products is the master catalog for items bought, stocked, and sold.

Use this module to:
- Create products.
- Edit products.
- Upload product images.
- Set brand/company, category, and unit.
- Set MRP.
- Set minimum quantity.
- Activate or deactivate products.
- View product details.

Main fields:
- SKU
- Product Name
- Brand
- Category
- Unit
- MRP
- Minimum Quantity
- Active status
- Description
- Internal Notes
- Product Image

Important notes:
- SKU is auto-generated for new products.
- Quantity is no longer manually edited in the product form; stock belongs to Inventory.
- Product image preview appears as a small thumbnail after upload.
- Uploaded images are served through `/media/...` on deployed hosting.

Typical workflow:
1. Open `Stock -> Products`.
2. Create or edit product.
3. Select brand, category, and unit.
4. Add MRP and minimum quantity.
5. Upload image if needed.
6. Save.

### Brands

Route: `/companies`

Purpose:
Brands stores brands or companies linked to products and suppliers.

Use this module to:
- Create brands/companies.
- Edit brand/company records.
- View brand/company detail in a modal/page.
- Link brands to products.
- Link brands to suppliers/vendors.
- Use brand selection inside purchase orders.

Main fields:
- Code
- Company Name
- Brand Name
- Company Type
- GSTIN / PAN where available
- Phone / Email
- Website
- Address
- Status

Typical workflow:
1. Open `Stock -> Brands`.
2. Create or edit brand/company.
3. Use that brand in products.
4. Link vendors/suppliers to supplied brands.

### Categories

Route: `/master/categories`

Purpose:
Categories group products for searching, reporting, and catalog organization.

Use this module to:
- Create product categories.
- Edit categories.
- Activate or deactivate categories.
- Organize products under meaningful groups.

Typical workflow:
1. Open `Stock -> Categories`.
2. Add category name and details.
3. Save.
4. Select this category while creating or editing products.

### Units

Route: `/master/units`

Purpose:
Units define measurement or counting units used by products.

Use this module to:
- Create units such as pcs, box, bottle, kg, ml.
- Edit units.
- Assign units to products.

Typical workflow:
1. Open `Stock -> Units`.
2. Add unit name and symbol.
3. Save.
4. Select this unit while creating or editing products.

## Sales

The Sales menu groups point-of-sale checkout, sales history, and customers.

### POS

Route: `/sales/create`

Purpose:
POS (Point of Sale) is the screen used to create a new sale: pick products, set quantities and discounts, choose a customer, and take payment.

Use this module to:
- Search and add products to the cart.
- Apply a per-item discount and/or a global discount.
- Select or quick-create a customer.
- Choose a payment method and record cash received.
- Save the sale as `Pending` or `Completed`.
- Print the sale receipt after saving.

Typical workflow:
1. Open `Sales -> POS`.
2. Search and add products to the cart.
3. Adjust quantities and discounts.
4. Select a customer (optional).
5. Choose payment method and enter cash received if paying by cash.
6. Save the sale.
7. Print the receipt if needed.

### Sales

Route: `/sales`

Purpose:
Sales lists all recorded sales and lets staff review, print, complete, or cancel/restore them.

Use this module to:
- View sale invoice number, customer, date, status, and total.
- Open a sale to see line items.
- Print a sale receipt.
- Mark a `Pending` sale as `Completed`.
- Cancel a sale (restores stock) or restore a cancelled sale back to `Pending`.

Sale statuses:
- `Pending`: sale is recorded but not yet completed/paid.
- `Completed`: sale is finalized and recorded in Finance.
- `Cancelled`: sale is voided and stock/finance entries are reversed.

Typical workflow:
1. Open `Sales -> Sales`.
2. Search or filter sales.
3. Open a sale to view details.
4. Mark as completed, print, or cancel/restore as needed.

### Customers

Route: `/master/customers`

Purpose:
Customers is the master data module for people or organizations sales are recorded against.

Use this module to:
- Create and edit customer records.
- Select a customer during POS checkout.

Typical workflow:
1. Open `Sales -> Customers`.
2. Create or edit a customer.
3. Select this customer during POS checkout.

## Money

The Money menu groups finance category setup and finance transaction tracking.

### Transactions

Route: `/finance/transactions`

Purpose:
Transactions lists finance ledger entries (income/expense), including entries automatically recorded from completed sales and received purchases.

Use this module to:
- View transaction date, category, type, amount, and reference.
- Record manual income/expense transactions.
- Print a transaction record.

### Categories

Route: `/finance/categories`

Purpose:
Categories groups finance transactions (e.g. Sales Income, Purchase Expense, Utilities) for reporting.

Use this module to:
- Create finance categories.
- Edit categories.
- Set category type (income/expense).

Typical workflow:
1. Open `Money -> Categories`.
2. Create categories needed for reporting.
3. Open `Money -> Transactions`.
4. Record manual transactions against a category, or review auto-recorded sale/purchase entries.

## Admin

The Admin menu groups user and system configuration.

### Users

Route: `/users`

Purpose:
Users manages application user accounts.

Use this module to:
- Create users.
- Edit user information.
- Assign roles where available.
- Upload profile photos.
- Activate or manage access depending on configured fields.

Typical workflow:
1. Open `Admin -> Users`.
2. Create or edit a user.
3. Add name, email, role, and profile photo if needed.
4. Save.

### Settings

Route: `/settings`

Purpose:
Settings stores system-wide configuration values used across PDFs, reports, and screens.

Use this module to:
- Update store name.
- Update store address.
- Update store phone.
- Update store email.
- Update other application settings made available by the settings table.

Important settings:
- `store_name`
- `store_address`
- `store_phone`
- `store_email`

These values appear in:
- Purchase order PDF
- Purchase order view page
- Supplier profile PDF footer
- Finance reports where enabled

Typical workflow:
1. Open `Admin -> Settings`.
2. Select a setting.
3. Edit the value.
4. Save.



Uploaded files are stored in Laravel under:

```text
storage/app/public
```

The app serves them through:

```text
/media/{path}
```

Examples:

```text
/media/products/product-image.png
/media/supplier-documents/blank-cheques/cheque.png
/media/vendor-invoices/invoice.pdf
```

This avoids using `/storage/...` directly on hosting environments where the real `storage` directory is blocked by the web server.
