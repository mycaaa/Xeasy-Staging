<?php

namespace App\Helpers;

class PaymentImportHelper
{
    /**
     * Expected column headers for validation
     */
    public static function getExpectedHeaders(): array
    {
        return [
            'record_no',
            'beneficiary_account_number',
            'transaction_refno',
            'bank_name',
            'ifsc_code',
            'debit_account_number',
            'value_date',
            'amount',
            'beneficiary_name',
            'payment_type',
            'rbi_utr_reference_number',
            'currency',
            'error_description',
            'transaction_status',
            'beneficiary_aadhaar_number',
            'destination_bank',
            'credit_reference'
        ];
    }

    /**
     * Validate required fields
     */
    public static function validateRequiredFields(array $row): array
    {
        $errors = [];

        $requiredFields = [
            'beneficiary_account_number',
            'value_date',
            'amount',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($row[$field]) || trim((string) $row[$field]) === '') {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Validate account / reference field
        if (!empty($row['beneficiary_account_number'])) {
            $account = trim((string) $row['beneficiary_account_number']);
            $upper = strtoupper($account);

            // reject placeholders
            $invalidValues = ['-', '--', '- -', 'N/A', 'NA', 'NULL', '0'];

            if (in_array($upper, $invalidValues)) {
                $errors[] = 'Invalid beneficiary account number';
            }
            // reject if only symbols/spaces
            elseif (!preg_match('/[A-Z0-9]/i', $account)) {
                $errors[] = 'Invalid beneficiary account number';
            }
        }

        return $errors;
    }

    /**
     * Get sample data format
     */
    public static function getSampleData(): array
    {
        return [
            'headers' => self::getExpectedHeaders(),
            'sample_row' => [
                '1',
                '10070735518',
                'BT26012849793988',
                'IDFB Bank',
                'IDFB0042344',
                '9876543210',
                '28/01/2026',
                '753447.00',
                'Nayanaben Patel Kondheshwar farm',
                'RTGS',
                'BARBR22026012820262332',
                'INR',
                '',
                'SUCCESS',
                '123456789012',
                'HDFC Bank',
                'REF123'
            ]
        ];
    }
}
