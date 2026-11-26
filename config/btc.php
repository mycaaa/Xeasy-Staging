<?php

/**
 * BTC-01: Bill To Company (BTC) process — VNR Finance & Accounts, "Current BTC process" note (5 Nov 2026).
 *
 * Routes
 *   A  admin_booking     Employee requirement → Admin books hotel / vendor → service → Admin gets bill → DMS
 *   B  employee_advance  Employee requirement → vendor quotation → manager approval → advance from Finance
 *                        → service → final bill → DMS
 *   C  direct_bill       Service done → employee / vendor sends final bill → DMS
 *
 * After the bill: DMS → Finance verification → Focus JV → Finance checker → Treasury
 *   → department approval → payment → advance adjustment / supporting closure.
 */
return [
    'routes' => [
        'admin_booking' => 'A · Admin-arranged booking',
        'employee_advance' => 'B · Employee-arranged with advance',
        'direct_bill' => 'C · Final bill after the activity',
    ],

    // BTC-ADV-02 (17 Nov 2026): an advance not settled by a bill within this many days blocks a new advance
    'advance_settle_days' => (int) env('BTC_ADVANCE_SETTLE_DAYS', 30),

    // BTC-TRY-01 (17 Nov 2026): a Treasury checker (another person) authorises each payment entered by the maker
    'treasury_checker_unused' => (bool) env('BTC_TREASURY_CHECKER', true),
    // BTC-FLOW-01 (18 Nov 2026): Finance may verify an air case before the boarding pass is in (it is then chased and
    // still needed for closure). Default true = the old rule (no verification without the boarding pass).
    // BTC-FOCUS-02 (24 Nov 2026, testing point 8): default false - only the expense booking (employee JV) waits for the
    // boarding pass; verification, the vendor JV, approval and payment go on ("do not hold all entries")
    'boarding_pass_before_verify' => (bool) env('BTC_BOARDING_PASS_BEFORE_VERIFY', false),

    // BTC-READER-01 (18 Nov 2026): invoices read inside Xeasy (same bill-reading service as the claim screens)
    'reader' => [
        'enabled' => (bool) env('BTC_READER_ENABLED', true),
        'base_url' => env('BTC_READER_BASE_URL', 'https://api-gf4tdduqha-uc.a.run.app/api/v1'),
        'public_base' => env('BTC_READER_PUBLIC_BASE', ''),   // internet address of this app if APP_URL is internal
        'timeout' => (int) env('BTC_READER_TIMEOUT', 60),
    ],
    // BTC-EXC-01: a bill above the booked / quoted / estimated amount by more than this % is a request mismatch
    'mismatch_pct' => (int) env('BTC_MISMATCH_PCT', 10),

    // BTC-NOTIFY-01 (17 Nov 2026): hand-off and escalation mails; off until switched on in BTC Settings.
    // redirect_to: send every mail to this address instead (testing)
    'notify' => ['enabled' => (bool) env('BTC_NOTIFY', false), 'redirect_to' => env('BTC_NOTIFY_REDIRECT', '')],
    // days allowed per stage before escalation (BtcNotifier::STAGES has the defaults)
    'sla' => [],
    // BTC-BATCH-01: the company account the bank debits for BTC payments
    'bank' => ['debit_account' => env('BTC_BANK_DEBIT_ACCOUNT', '')],

    // BTC-PAY-01 (17 Nov 2026): payment modes on Payment Confirmation
    'payment_modes' => ['neft' => 'NEFT', 'rtgs' => 'RTGS', 'imps' => 'IMPS', 'upi' => 'UPI', 'cheque' => 'Cheque', 'other' => 'Other'],

    // BTC-REQ-10 (17 Nov 2026): short route names in the lists
    'route_short' => ['admin_booking' => 'Admin booking', 'employee_advance' => 'Advance paid to vendor', 'direct_bill' => 'Final bill'],

    // BTC-FORM-01: routes an employee may choose on the request form (the BTC admin may choose all)
    // BTC-REQ-03 (16 Nov 2026, "BTC requirements" tab): the new-requirement form gives two clear choices,
    // "Admin Booking Required" (A) and "Advance Required" (B); C (final bill after the activity) stays with the BTC admin
    'employee_routes' => ['admin_booking', 'employee_advance'],
    'route_choices' => [
        'admin_booking' => ['Admin Booking Required', 'The BTC admin books the hotel / vehicle / ticket with the vendor; you only state the need.'],
        'employee_advance' => ['Advance Required', 'You arrange the vendor and attach its quotation; Finance pays an advance after your manager approves.'],
        // BTC-REQ-23 (18 Nov 2026): 'Final bill after the activity' removed from the request form (old route C cases still open and work)
    ],

    // BTC-FORM-02: vendor types (vendor_list.vendor_type) offered for each category; [] = all vendors
    // BTC-VENDOR-CORE-01 (20 Nov 2026, BTC requirement item 29): vendor list from the core application. The key is
    // set in .env (BTC_CORE_VENDOR_KEY), never in the code. Core category => BTC categories it serves ([] = all).
    'core_vendors' => [
        'url' => env('BTC_CORE_VENDOR_URL', 'https://core.vnrin.in/api/btc_vendor'),
        'key' => env('BTC_CORE_VENDOR_KEY', ''),
        'category_map' => [
            'Hotel Vendor' => ['hotel', 'meals', 'meeting'],
            'Hired Vehicle Vendor' => ['vehicle'],
            'Transport Service Vendor' => ['vehicle', 'bus'],
            'Marketing Vendor' => ['meeting', 'meals'],
        ],
        'not_btc' => ['Rent Vendor', 'Courier Vendor'],   // not used for BTC requests
    ],

    'vendor_types_by_category' => [
        'hotel' => ['hotel'],
        'vehicle' => ['agency', 'agent'],
        'air' => ['agency', 'agent'],
        'train' => ['agency', 'agent'],
        'bus' => ['agency', 'agent'],
        'meals' => [],
        'meeting' => [],
        'other' => [],
    ],

    // Category rules (section 4 of the note). key => [label, gate document, original bill needed before closure]
    'categories' => [
        // BTC-REQ-25 (19 Nov 2026): Hotel / Accommodation, HRV, Air, Train, Bus, Meals, Meeting Expense on the new-case form
        'hotel' => ['label' => 'Hotel / Accommodation', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
        'vehicle' => ['label' => 'HRV (Hired Vehicle)', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => false],
        'air' => ['label' => 'Air', 'boarding_pass' => true, 'original_required' => false, 'scan_ok' => true],
        'train' => ['label' => 'Train', 'boarding_pass' => false, 'original_required' => false, 'scan_ok' => true],
        'bus' => ['label' => 'Bus', 'boarding_pass' => false, 'original_required' => false, 'scan_ok' => true],
        'meals' => ['label' => 'Meals', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
        'meeting' => ['label' => 'Meeting Expense', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
        'other' => ['label' => 'Other service (old cases)', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true, 'new_case' => false],
    ],

    // BTC-REQ-25: purpose of the requirement (new-case form); "Other" needs the remarks
    'purposes' => [
        'Farmer Meeting Expenses', 'Dealer Meeting Expenses', 'Distributor Meeting Expenses', 'Retailer Meeting Expenses',
        'Market Development Expenses', 'Internal Training Expenses', 'External Training Expenses', 'Meeting & Seminar Expenses',
        'Self', 'Other (specify in remarks)',
    ],

    // Stages in order. key => label
    // BTC-V2 (19 Nov 2026, BTC requirement sheet item 24): revised workflow - no DMS processing step, no Nova, no
    // department head / Treasury checker. Bill uploaded in Xeasy -> data extraction -> Finance Verifier 1 (+ Focus
    // entry) -> Finance Approval -> Treasury payment. The old codes stay only to label history.
    'statuses' => [
        'draft' => 'Draft',
        'pending_manager' => 'Waiting for manager approval',
        'booking_requested' => 'Waiting for admin booking',
        'advance_requested' => 'Advance requested',
        'advance_paid' => 'Advance paid',
        'booked' => 'Booked / service in progress',
        'bill_with_dms' => 'Physical bill with DMS',
        'bill_with_finance' => 'Bill with Finance (to scan and upload)',
        'scanned' => 'Scanned - to classify',
        'bill_submitted' => 'Classified - to punch',
        'extracted' => 'Finance level verification',
        'finance_verified' => 'Verified - Focus entry',
        'focus_done' => 'Finance Approval',
        'fin_approved' => 'Treasury payment',
        'paid' => 'Paid',
        'closed' => 'Closed',
        'returned' => 'Returned for correction',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        // old workflow (history only)
        'dms_processed' => 'DMS processed (old)',
        'checked' => 'Checked by Finance (old)',
        'with_treasury' => 'With Treasury (old)',
        'dept_approved' => 'Department approved (old)',
    ],

    // BTC-UI-01 (26 Nov 2026, testing point 22): the colour family of each status pill (words always shown with it)
    'status_tones' => [
        'draft' => 'draft', 'pending_manager' => 'wait', 'booking_requested' => 'admin', 'advance_requested' => 'wait', 'advance_paid' => 'admin',
        'booked' => 'admin', 'bill_with_dms' => 'admin', 'bill_with_finance' => 'fin', 'scanned' => 'fin', 'bill_submitted' => 'fin', 'extracted' => 'fin',
        'finance_verified' => 'fin', 'focus_done' => 'fin', 'fin_approved' => 'fin', 'paid' => 'done', 'closed' => 'done',
        'returned' => 'back', 'rejected' => 'back', 'cancelled' => 'stop',
    ],

    // BTC-V2: where the bill came from (Bill Upload by Finance)
    'bill_sources' => ['dms' => 'DMS (physical bill)', 'vendor' => 'Vendor', 'email' => 'E-mail', 'admin' => 'Admin', 'department' => 'Other department', 'employee' => 'Employee'],

    // BTC-V2: the Finance data format - fields read from the invoice, and which must be filled before Verifier 1
    'extraction' => [
        'fields' => [
            'vendor_name' => 'Vendor name', 'vendor_gstin' => 'Vendor GSTIN', 'bill_no' => 'Invoice no.', 'bill_date' => 'Invoice date',
            'bill_particulars' => 'Description / particulars', 'taxable_amount' => 'Taxable amount', 'cgst_amount' => 'CGST', 'sgst_amount' => 'SGST',
            'igst_amount' => 'IGST', 'other_charges' => 'Other charges', 'bill_amount' => 'Total invoice amount', 'bill_period' => 'Invoice / service period',
        ],
        'required' => ['vendor_name', 'bill_no', 'bill_date', 'taxable_amount', 'bill_amount'],
        'company_state' => env('BTC_COMPANY_GST_STATE', '22'),   // GSTIN state code of the buyer: same state = CGST + SGST, else IGST
        // BTC-SCAN-01 (19 Nov 2026): every bill is punched (checked) by a person on the Punch Screen; true = a bill read
        // completely goes straight to Finance Verifier 1
        'auto_forward' => (bool) env('BTC_EXTRACT_AUTO_FORWARD', false),
    ],

    // ---- BTC-SCAN-01 (19 Nov 2026, "Data Punching Form & Finance approval screens" tab) ------------------------------
    // Scanner -> Classification -> Punch Screen -> Finance level verification. The punch form of each bill type shows
    // the fields as printed on the bill (the reading service's fields, scan_ocr/<type>); the Finance data format
    // (extraction.fields) is filled from them. key => [label, number field?]
    'classification' => ['hotel', 'vehicle', 'air', 'train', 'bus', 'meals', 'meeting'],
    // BTC-TRIP-01 (25 Nov 2026, testing points 20 / 21): the request form of these has Travel From / To and one-way / round
    // trip in place of Location and activity dates; for an admin booking the manager chooses corporate or normal booking
    'trip_categories' => ['air', 'train'],
    'booking_types' => ['corporate' => 'Corporate booking', 'normal' => 'Normal booking'],
    // BTC-TRIP-02 (26 Nov 2026, testing point 28): the manager chooses the booking type for Air only
    'booking_type_categories' => ['air'],
    // BTC-PUNCH-02 (25 Nov 2026, testing point 17): the punch form of each type in the layout of the 19 Nov spec - header
    // fields, then repeatable tables (journey legs, rooms, line items) with their totals. Keys of the header fields
    // stay as before (lists, verification and the narration use them); the tables are kept in punch_json._t.
    'punch_forms' => [
        'vehicle' => ['title' => 'Hired vehicle (HRV)', 'fields' => [
            'Agency Name' => 'Agency name', 'Agency Address' => 'Agency address', 'Billing Name' => 'Billing name', 'Billing Address' => 'Billing address',
            'Employee Name' => 'Employee name', 'Emp Code' => 'Emp code', 'Vehicle No' => 'Vehicle no.', 'Location' => 'Location',
            'Invoice No' => 'Invoice no.', 'Invoice Date' => 'Invoice date', 'Per KM Rate' => 'Per km rate', 'Booking Date' => 'Booking date', 'End Date' => 'End date',
            'Start Reading' => 'Start reading', 'Closing Reading' => 'Closing reading', 'Total KM' => 'Total km',
            'Other Charges' => 'Other charges (total - km x rate)', 'Total Amount' => 'Total amount',
            'Route From' => 'Route from (manual)', 'Route To' => 'Route to (manual)', 'Purpose' => 'Purpose (manual)',
        ], 'manual' => ['Route From', 'Route To', 'Purpose']],
        'air' => ['title' => 'Air / Rail / Bus', 'fields' => [
            'Agent Name' => 'Agent name', 'Location' => 'Location', 'GST Calculation Level' => 'GST calculation level', 'Invoice No' => 'Invoice no.', 'Invoice Date' => 'Invoice date',
            'Service Charge' => 'Service charge (invoice)', 'Service Charge GST' => 'GST on service charge', 'Total Fare' => 'Invoice total',
        ], 'tables' => [
            'legs' => ['title' => 'Journey leg / line item', 'block' => true, 'cols' => [
                'Mode' => 'Mode', 'PNR Number' => 'PNR', 'Date of Booking' => 'Date of booking', 'Journey Date' => 'Journey date', 'Airline' => 'Airline / carrier',
                'Ticket Number' => 'Ticket no.', 'Journey From' => 'Journey from', 'Journey Upto' => 'Journey upto', 'Travel Class' => 'Travel class',
                'Passenger Details' => 'Passenger details', 'Employees' => 'Employees for this leg (codes)', 'Employee Remark' => 'Remark',
                'Particulars' => 'Particulars', 'Base Fare' => 'Base fare', 'GST' => 'GST (Rs.)', 'GST Type' => 'GST type', 'Surcharge' => 'Surcharge', 'CUTE' => 'CUTE',
                'Luggage' => 'Luggage', 'Reschedule' => 'Reschedule', 'Refund' => 'Refund (-)', 'Other' => 'Other', 'Leg Total' => 'Leg total',
            ], 'numbers' => ['Base Fare', 'GST', 'Surcharge', 'CUTE', 'Luggage', 'Reschedule', 'Refund', 'Other', 'Leg Total'],
                'total' => 'Leg Total', 'plus' => ['Base Fare', 'GST', 'Surcharge', 'CUTE', 'Luggage', 'Reschedule', 'Other'], 'minus' => ['Refund']],
        ]],
        'hotel' => ['title' => 'Hotel / Lodging', 'fields' => [
            'Location' => 'Location', 'Bill No' => 'Bill no.', 'Bill Date' => 'Bill date', 'Billing Name' => 'Billing name', 'Billing Address' => 'Billing address',
            'Hotel Name' => 'Hotel name', 'Hotel Address' => 'Hotel address', 'Billing Instruction' => 'Billing instruction', 'Booking ID' => 'Booking ID',
            'Chk. In Date' => 'Check in date / time', 'Chk. Out Date' => 'Check out date / time', 'Duration of Stay' => 'Duration of stay', 'Number of Rooms' => 'Number of rooms',
            'Grand Total' => 'Overall grand total',
        ], 'tables' => [
            'rooms' => ['title' => 'Room details', 'cols' => ['Room No' => 'Room no.', 'Room Type' => 'Room type', 'Meal Plan' => 'Meal plan', 'Pax' => 'Pax',
                'Employees' => 'Employee(s) in the room (codes)', 'Guest Name' => 'Guest name as on the bill', 'Remark' => 'Remark'], 'numbers' => ['Pax']],
            'items' => ['title' => 'Line items and GST', 'cols' => ['Particulars' => 'Particulars', 'Room Type' => 'Room type', 'Meal Plan' => 'Meal plan', 'Date' => 'Date',
                'Amount' => 'Amount', 'Other' => 'Other (+)', 'Discount' => 'Discount (-)', 'GST %' => 'GST %', 'GST Type' => 'GST type', 'Grand Total' => 'Grand total'],
                'numbers' => ['Amount', 'Other', 'Discount', 'GST %', 'Grand Total'], 'total' => 'Grand Total', 'plus' => ['Amount', 'Other'], 'minus' => ['Discount'], 'gst' => 'GST %'],
        ]],
        'meals' => ['title' => 'Meals / General invoice', 'fields' => [
            'Bill No.' => 'Invoice no.', 'Bill Date' => 'Invoice date', 'Purchase Order No' => 'Purchase order no.', 'Purchase Order Date' => 'Purchase order date',
            'GRN No' => 'GRN / SRN no.', 'GRN Date' => 'GRN / SRN date', 'Buyer' => 'Buyer', 'Buyer Address' => 'Buyer address', 'Hotel Name' => 'Vendor',
            'Vendor Address' => 'Vendor address', 'Dispatch Details' => 'Dispatch details', 'Dispatch Through' => 'Dispatch through', 'Delivery Note Date' => 'Delivery note date',
            'LR Number' => 'LR number', 'LR Date' => 'LR date', 'Pax' => 'No. of pax (manual)', 'Purpose' => 'Purpose (manual)',
            'Additional Discount' => 'Additional discount', 'Amount' => 'Grand total',
        ], 'manual' => ['Pax', 'Purpose'], 'tables' => [
            'items' => ['title' => 'Items', 'cols' => ['Particular' => 'Particular', 'HSN/SAC' => 'HSN / SAC', 'Qty' => 'Qty', 'Unit' => 'Unit', 'MRP' => 'MRP',
                'Dis Rs' => 'Dis (Rs)', 'Dis %' => 'Dis (%)', 'Dis On' => 'Dis on', 'Amt' => 'Amt', 'CGST %' => 'CGST %', 'SGST %' => 'SGST %', 'IGST %' => 'IGST %',
                'Cess %' => 'Cess %', 'Total Amt' => 'Total amt'],
                'numbers' => ['Qty', 'MRP', 'Dis Rs', 'Dis %', 'Amt', 'CGST %', 'SGST %', 'IGST %', 'Cess %', 'Total Amt'], 'total' => 'Total Amt'],
        ]],
        'meeting' => ['title' => 'Team meeting expense', 'fields' => [
            'Vendor' => 'Vendor / venue', 'Voucher No' => 'Bill / voucher no.', 'Voucher Date' => 'Bill date', 'Meeting Type' => 'Meeting type',
            'Meeting Date' => 'Meeting date', 'Location' => 'Place', 'Participants' => 'No. of participants', 'Particular' => 'Particulars (hall, food, ...)',
            'Amount' => 'Amount',
        ]],
    ],
    // BTC-PUNCH-02: old flat keys of the Air / Rail / Bus form, now the first journey leg
    'punch_leg_from_flat' => ['Mode' => 'Mode', 'PNR Number' => 'PNR Number', 'Date of Booking' => 'Date of Booking', 'Journey Date' => 'Journey Date', 'Airline' => 'Airline',
        'Ticket Number' => 'Ticket Number', 'Journey From' => 'Journey From', 'Journey Upto' => 'Journey Upto', 'Travel Class' => 'Travel Class', 'Base Fare' => 'Base Fare',
        'GST' => 'GST', 'Surcharge' => 'Fees & Surcharge', 'CUTE' => 'CUTE Charge', 'Luggage' => 'Extra Luggage', 'Other' => 'Other'],
    // BTC-FOCUS-05 (25 Nov 2026, testing point 19): {Mode} of the narration, from the category (the punch user may change it)
    'narration_mode' => ['hotel' => 'lodging exp.', 'air' => 'air fare exp.', 'train' => 'rail fare exp.', 'bus' => 'bus fare exp.', 'vehicle' => 'hired vehicle exp.',
        'meals' => 'fooding exp.', 'meeting' => 'meeting exp.', 'other' => 'misc. exp.'],
    // BTC-FORMAT-01 (23 Nov 2026): the key fields of each format shown in the lists
    'punch_summary' => [
        'hotel' => ['Hotel Name', 'Chk. In Date', 'Chk. Out Date', 'Number of Rooms', 'Grand Total'],
        'air' => ['Agent Name', 'Invoice No', 'Total Fare'],
        'vehicle' => ['Agency Name', 'Vehicle No', 'Total KM', 'Total Amount'],
        'meals' => ['Hotel Name', 'Bill Date', 'Amount'],
        'meeting' => ['Vendor', 'Meeting Date', 'Participants', 'Amount'],
    ],
    'punch_form_of' => ['hotel' => 'hotel', 'vehicle' => 'vehicle', 'air' => 'air', 'train' => 'air', 'bus' => 'air', 'meals' => 'meals', 'meeting' => 'meeting', 'other' => 'meeting'],
    // amount-like punch fields (number inputs)
    'punch_numbers' => ['Per KM Rate', 'Start Reading', 'Closing Reading', 'Total KM', 'Other Charges', 'Total Amount', 'Base Fare', 'GST', 'Fees & Surcharge',
        'CUTE Charge', 'Extra Luggage', 'Other', 'Total Fare', 'Number of Rooms', 'Rate', 'Amount', 'Other Charges (+)', 'Discount (-)', 'GST (%)', 'Grand Total', 'Participants',
        'Service Charge', 'Service Charge GST', 'Additional Discount', 'Pax'],

    // Focus JV ledgers (names/codes as in Focus; Finance to confirm)
    'focus' => [
        // BTC-LEDGER-01 (Finance 10 Nov 2026): a separate BTC ledger per employee, named like "Arnav_BTC".
        // {first_name} = first name; when two active employees share it, the employee code is added ("Arnav 1234_BTC").
        'employee_btc_ledger' => env('BTC_LEDGER_EMPLOYEE', '{first_name}_BTC'),
        'tds_ledger' => env('BTC_LEDGER_TDS', 'TDS Payable'),
        'gst_input_ledger' => env('BTC_LEDGER_GST', 'GST Input Credit'),
        'vendor_advance_ledger' => env('BTC_LEDGER_VENDOR_ADV', 'Advance to Vendors'),
        'expense_ledgers' => [
            'hotel' => 'Lodging & Boarding (BTC)',
            'vehicle' => 'Vehicle Hire Charges',
            'air' => 'Travelling - Air',
            'train' => 'Travelling - Train',
            'bus' => 'Travelling - Bus',
            'meals' => 'Boarding & Meals (BTC)',
            'meeting' => 'Meeting & Conference Expenses',
            'other' => 'Miscellaneous Expenses',
        ],
    ],

    'tds_sections' => ['194C' => 'Contractors (194C)', '194J' => 'Professional / technical (194J)', '194I' => 'Rent (194I)', 'none' => 'No TDS'],

    // BTC-TDS-01 (Finance 10 Nov 2026): TDS is decided per transaction from the nature of the payment.
    // Income-tax Act, 2025 (tax year 2026-27): all under section 393(1); the old 1961 sections are shown too.
    // rate = [individual / HUF, others] in %; threshold = single bill (and year total) in rupees. Finance to confirm.
    'tds_natures' => [
        'contract' => ['label' => 'Contract work / hired vehicle / event service (393(1) Sl. 6(i), old 194C)', 'section' => '194C', 'rate' => [1, 2], 'threshold' => [30000, 100000]],
        'professional' => ['label' => 'Professional fees (393(1) Sl. 11, old 194J)', 'section' => '194J', 'rate' => [10, 10], 'threshold' => [50000, 50000]],
        'technical' => ['label' => 'Technical services (393(1) Sl. 11, old 194J)', 'section' => '194J', 'rate' => [2, 2], 'threshold' => [50000, 50000]],
        'rent_plant' => ['label' => 'Rent of plant / machinery / equipment (393(1) Sl. 10, old 194I)', 'section' => '194I', 'rate' => [2, 2], 'threshold' => [50000, 50000]],
        'rent_premises' => ['label' => 'Rent of premises / hotel rooms on a regular basis (393(1) Sl. 10, old 194I)', 'section' => '194I', 'rate' => [10, 10], 'threshold' => [50000, 50000]],
        'none' => ['label' => 'No TDS (tickets, occasional hotel stay, below threshold, goods)', 'section' => 'none', 'rate' => [0, 0], 'threshold' => [0, 0]],
    ],
    // default nature per BTC category (Finance can change it on each case)
    'tds_nature_by_category' => ['hotel' => 'none', 'vehicle' => 'contract', 'air' => 'none', 'train' => 'none', 'bus' => 'none', 'meals' => 'none', 'meeting' => 'contract', 'other' => 'contract'],

    // remind DMS / Finance about missing originals and boarding passes after this many days
    'follow_up_days' => (int) env('BTC_FOLLOW_UP_DAYS', 7),

    // ---- BTC-REQ-01 / 02 (16 Nov 2026, "BTC requirements" tab): roles and access -----------------------------------
    // Screen-wise and action-wise permissions shown on BTC > Access. Existing names are kept (btc.dms ... btc.admin).
    'permission_catalog' => [
        'Screens' => [
            'btc.dashboard' => 'BTC Dashboard',
            'btc.register' => 'BTC Request Register (all requests)',
            'btc.booking' => 'Admin Booking Queue',
            'btc.advance' => 'Advance Request & Processing',
                        'btc.access' => 'BTC roles, users and access history',
            'btc.exceptions' => 'Validation & Exception Queue',                                   // BTC-EXC-01
            'btc.audit' => 'Internal Audit: read every BTC record and report (no changes)',      // BTC-PERM-02
            'btc.export' => 'Download BTC reports (Excel)',
            'btc.configure' => 'BTC settings and masters',
        ],
        'Actions' => [
            'btc.dms' => 'DMS: physical bill received / handed to Finance; Scanner and Classification',
            'btc.bill_upload' => 'Scanner, Classification and Punch Screen (check / correct the read fields)',   // BTC-V2 / BTC-SCAN-01
            'btc.verifier' => 'Finance Verifier 1: verify the extracted data, prepare and export the Focus entry',
            'btc.fin_approver' => 'Finance Approval (bills) and Finance Advance Approval',               // BTC-V2 / BTC-SCAN-01
            'btc.finance' => 'Finance: bill upload, data extraction, closure',
            'btc.checker' => 'Finance Head: oversight, document waivers, hold',
            'btc.treasury' => 'Treasury payment: record payment, UTR',
            'btc.admin' => 'All BTC cases and every step (full control)',
        ],
    ],
    // The recommended roles (sheet item 2). Employee, Reporting Manager and Authorised Approver also act through the
    // case itself (the requester, the manager on the case, the department approver chosen by Treasury).
    'roles' => [
        'BTC Super Admin' => ['Complete BTC module control', ['btc.admin', 'btc.access', 'btc.dashboard', 'btc.register', 'btc.booking', 'btc.advance', 'btc.bill_upload', 'btc.verifier', 'btc.dms', 'btc.finance', 'btc.checker', 'btc.fin_approver', 'btc.treasury', 'btc.exceptions', 'btc.audit', 'btc.export', 'btc.configure']],
        'BTC DMS Admin' => ['Physical bill receiving and handover to Finance', ['btc.dms', 'btc.register', 'btc.dashboard']],
        'BTC Admin' => ['Request management, booking and operational coordination', ['btc.booking', 'btc.register', 'btc.dashboard']],
        'BTC Employee' => ['Create request, upload documents and track own BTC cases', []],
        'BTC Reporting Manager' => ['Request approval and business justification', []],
        'BTC Finance Advance' => ['Advance processing and payment', ['btc.advance', 'btc.register']],
        'BTC Bill Upload (Finance)' => ['Bill Upload by Finance and data extraction', ['btc.bill_upload', 'btc.register', 'btc.dashboard', 'btc.exceptions']],
        'BTC Verifier' => ['Finance Verifier 1: extracted data and Focus entry', ['btc.verifier', 'btc.register', 'btc.exceptions']],
        'BTC Finance Approver' => ['Final Finance Approval before Treasury', ['btc.fin_approver', 'btc.register', 'btc.dashboard', 'btc.exceptions']],
        'BTC Finance Verifier' => ['Finance: bill upload, data extraction, closure', ['btc.finance', 'btc.bill_upload', 'btc.register', 'btc.dashboard']],
        'BTC Payment User' => ['Treasury payment: payment entry and UTR', ['btc.treasury', 'btc.register']],
        'BTC Internal Audit' => ['Read-only BTC records and reports', ['btc.audit', 'btc.register', 'btc.dashboard', 'btc.export', 'btc.exceptions']],
        'BTC Finance Head' => ['Financial approval and oversight', ['btc.checker', 'btc.fin_approver', 'btc.dashboard', 'btc.register', 'btc.advance']],
    ],

    // ---- BTC-REQ-07..09: Nova (DMS) invoice feed ----------------------------------------------------------------------
    // Nova posts invoices to POST /api/btc/nova/invoices with header X-Api-Key: <BTC_NOVA_API_KEY>. Until the key is
    // set the endpoint is closed; the inbox also takes a CSV export from Nova.
    'nova' => [
        'api_key' => env('BTC_NOVA_API_KEY'),
        // an invoice is expected this many days after the service ended (used for "Nova has not received the invoice")
        'expected_after_days' => 7,
        // matching: suggest a case from this score (0-100); auto-link only when one case scores at least auto_link
        'suggest_from' => 40,
        'auto_link' => 90,
    ],

    // ---- BTC-PAY-02 (20 Nov 2026, tracker tab "Data Punching Form & Finance approval screens" 6 d-f) -----------------
    // Treasury pays a bill only after Finance gives the payment instruction (Payment Approval > Approved).
    'payment_instruction_required' => (bool) env('BTC_PAY_INSTRUCTION_REQUIRED', true),

    // ---- BTC-FOCUS-01 (20 Nov 2026, same tab, item 7): Focus JV (vendor) and Employee JV import files ------------------
    // Ledger / sub-ledger codes as written in the tracker (from New_Focus_Ledgers_.xlsx). Keyed by Type of Expense,
    // then entity (VNR / VVNR): [ledger code, sub-ledger code or 'N/A']. A missing entity means "not given": the export
    // stops for that case and lists it, it never guesses a code.
    'focus_export' => [
        'business_entity' => ['VNR' => '120', 'VVNR' => env('BTC_FOCUS_ENTITY_VVNR', '')],   // VVNR: Finance to confirm
        'vendor_series' => 'JV/BTC/{fy}/',           // (before 25 Nov) JV/BTC/2026-27/0001
        // BTC-FOCUS-05 (25 Nov 2026, testing point 19): JV/BTC/26-27/2511/0026 - FY, DDMM of the import day, running no. for the FY
        'vendor_doc' => env('BTC_FOCUS_JV_DOC', 'JV/BTC/{fys}/{ddmm}/'),
        'employee_series' => 'EJV/BTC/{fy}/',        // EJV/BTC/2026-27/0001
        'bank_series' => 'BP/BTC/{fy}/',             // payment release (Focus Bank Payment - non cheque)
        'employee_account' => 'BTC-E{code4}',        // employee BTC account in Focus, e.g. BTC-E1301
        'cash_bank_ac' => env('BTC_FOCUS_BANK_AC', 'BANK-26'),
        'pmt_category' => env('BTC_FOCUS_PMT_CATEGORY', 'Staff Expense reimbursement'),   // as in the Treasury sample - Finance to confirm for BTC
        // payment voucher dimensions of the paying office (Treasury sample "Payment Export")
        'bank_dims' => ['department' => 'FIN', 'location' => 'RAIPUR', 'state' => '22', 'function' => 'BSF', 'vertical' => 'CM', 'sub_department' => 'SUB_DEPT_FIN_124',
            'region' => 'N/A', 'business_unit' => 'N/A', 'cost_center' => 'N/A', 'zone' => '', 'crop' => 'All Crop'],
        'activity_default' => 'All Activity',
        // Activity Head (OSA / PSA / PDA / PLA) is entered as its Focus code (ledger file col D); suggestions only
        'activity_heads' => ['PRE SALE ACTIVITY', 'Product Launch Activity'],
        'expense_types' => ['Lodging', 'Air Fare', 'Rail Fare', 'Bus Fare', 'Hired Vehicle', 'Team Meeting Expenses', 'Conveyance', 'Fooding', 'Gift',
            'Business Entertainment', 'Hall & Event Setup', 'Merchandise Cost (Branding & Transport)', 'Misc.', 'Training - Internal', 'Training - External'],
        'expense_type_by_category' => ['hotel' => 'Lodging', 'air' => 'Air Fare', 'train' => 'Rail Fare', 'bus' => 'Bus Fare', 'vehicle' => 'Hired Vehicle',
            'meeting' => 'Team Meeting Expenses', 'meals' => 'Fooding', 'other' => 'Misc.'],
        'activity_types' => ['Farmer Meeting', 'Distributor / Dealer Meeting', 'Retailer Meeting', 'Market Meeting', 'Aao Khel Khele', 'Jeep Campaign', 'MDO Training'],
        'ledgers' => [
            'Lodging' => ['VNR' => ['INDIRECT-TRA-25', 'SLC-0273'], 'VVNR' => ['INDIRECT-TRA-26', 'SLC-0282']],
            'Air Fare' => ['VNR' => ['INDIRECT-TRA-25', 'SLC-0272'], 'VVNR' => ['INDIRECT-TRA-26', 'SLC-0281']],
            'Rail Fare' => ['VNR' => ['INDIRECT-TRA-25', 'SLC-0271'], 'VVNR' => ['INDIRECT-TRA-26', 'SLC-0280']],
            'Bus Fare' => ['VNR' => ['INDIRECT-TRA-25', 'SLC-0271'], 'VVNR' => ['INDIRECT-TRA-26', 'SLC-0280']],
            'Hired Vehicle' => ['VNR' => ['INDIRECT-TRA-14', 'N/A'], 'VVNR' => ['INDIRECT-TRA-14', 'N/A']],
            'Team Meeting Expenses' => ['VNR' => ['INDIRECT-MET-04', 'N/A'], 'VVNR' => ['INDIRECT-MET-04', 'N/A']],
            'Training - Internal' => ['VNR' => ['INDIRECT-MET-05', 'SLC-0134']],
            'Training - External' => ['VNR' => ['INDIRECT-MET-05', 'SLC-0133']],
        ],
        // meeting / market expenses: Type of Activity + Type of Expense (VNR only in the ledger file)
        'activity_ledgers' => [
            'Farmer Meeting|Fooding' => ['VNR' => ['INDIRECT-MET-06', 'SLC-0257']],
        ],
    ],

    // ---- BTC-MATRIX-01 (20 Nov 2026, same tab, item 9): payment confirmation sent to Matrix -----------------------------
    // POST {url}/verify (one) and {url}/verify-bulk (many). Off until switched on with the key in .env.
    'matrix_verify' => [
        'enabled' => (bool) env('BTC_MATRIX_VERIFY_ENABLED', false),
        'url' => rtrim((string) env('BTC_MATRIX_VERIFY_URL', 'https://matrix.vnin.in/api/v1/payment'), '/'),
        'key' => env('BTC_MATRIX_VERIFY_KEY', ''),
        'key_header' => env('BTC_MATRIX_VERIFY_KEY_HEADER', 'X-API-Key'),
        'source' => 'Xeasy_BTC',
        'bulk_size' => 100,
    ],
];
