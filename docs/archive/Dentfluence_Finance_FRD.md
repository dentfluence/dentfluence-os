# Dentfluence OS

# Functional Requirements Document

# Finance Module (Developer Specification)

## Purpose

This document defines the target Finance Module architecture and
business rules. The objective is to minimize developer interpretation
and avoid rework.

------------------------------------------------------------------------

# 1. Design Principles

-   Simple for reception staff.
-   Accurate for owner.
-   Audit friendly for CA.
-   Minimal duplicate screens.
-   One source of truth for every transaction.

------------------------------------------------------------------------

# 2. Navigation

## Keep

-   Income
-   Expenses
-   Vendors
-   Membership
-   Wallet
-   Coupon Codes
-   CA Export

## Move to Settings

-   Banking Accounts

## Remove

-   Separate Voucher
-   Payroll
-   Cashbook
-   GST navigation (unless activated later)

------------------------------------------------------------------------

# 3. Income Module

## Ledger

Current ledger UI remains.

Remove old invoice listing.

Invoice back navigation must return to Income Ledger with previous
filters.

### Filters

-   Paid / Unpaid
-   A-Z
-   Low to High
-   Daily
-   Weekly
-   Monthly
-   Quarterly
-   Financial Year

### Export

-   PDF
-   Excel

### Documents

-   INV
-   RCP

Receipt stores: - Mode - Clinic account - UTR/reference

------------------------------------------------------------------------

# 4. Expense Module

## Workflow

PO/LO -\> Vendor Bill -\> Mark Paid -\> Payment Voucher

## Payment form

Mandatory: - Date - Amount - Mode - Clinic account - UTR/Cheque when
applicable

## Voucher

Auto generated.

Contains: - Vendor - Reason - Linked document - Amount - Date - Mode -
Account - Reference

Voucher accessible from expense row.

Remove standalone voucher module.

Fix truncated print layout.

------------------------------------------------------------------------

# 5. Vendor Architecture

Inventory vendors: - Inventory only - Auto sync Finance

Lab vendors: - Lab only - Auto sync Finance

Finance vendors: - All payable entities

Finance never syncs back.

Correct rendering filters.

------------------------------------------------------------------------

# 6. Banking

Admin only.

Stores payment accounts.

Balances derived from transactions.

------------------------------------------------------------------------

# 7. CA Export

Single export.

Includes: - Income - Expenses - Receipts - Payment vouchers

Date range and FY support.

------------------------------------------------------------------------

# 8. Membership

Keep module.

Cards: - Total plans - Active plans - Active members - Revenue -
Expiring this month

------------------------------------------------------------------------

# 9. Coupon

Cards: - Total coupons - Active - Expired - Total uses - Discount
given - Most used

------------------------------------------------------------------------

# 10. Wallet

Cards: - Patients with balance - Total balance - Credits - Usage -
Active balance

Transaction register: - Date - Patient - Credit - Debit - Balance -
Invoice

Patient wallet ledger required.

------------------------------------------------------------------------

# 11. Documents

Income: - INV - RCP

Expense: - PO - LO - BIL - PV

------------------------------------------------------------------------

# 12. Acceptance Criteria

-   No duplicate invoice screen.
-   No standalone voucher module.
-   Expense payment always creates voucher.
-   Vendor segregation enforced.
-   Membership, Wallet and Coupon retained.
-   Banking hidden under settings.
-   Navigation simplified.
-   All exports functional.
-   Print layouts not truncated.
-   Filters preserved during navigation.

------------------------------------------------------------------------

# 13. Future Scope

-   GST enablement
-   Bank reconciliation
-   Auto feeds
-   Advanced analytics
-   Multi clinic consolidation

End of specification.
