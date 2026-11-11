<?php

namespace App\Exports\Concerns;

/**
 * STAMP-01: marker for files that are uploaded into another system (Focus ERP vouchers, bank
 * payment files). These keep their exact layout, so no "Generated on" line is added.
 */
interface NoReportStamp
{
}
