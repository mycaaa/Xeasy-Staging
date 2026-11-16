<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Btc\NovaInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-07 (16 Nov 2026): Nova (DMS) sends invoices and their progress (received / digitised / transferred).
 *
 *   POST /api/btc/nova/invoices        header X-Api-Key: <BTC_NOVA_API_KEY>
 *   body: one invoice object, a list of them, or {"invoices": [...]}
 *   each: document_id, status, invoice_no, invoice_date, vendor_name, vendor_gstin, taxable_amount, tax_amount,
 *         gross_amount, document_url, received_on, request_id (BTC case no., optional), company_id (optional)
 *
 * Answers per call: received / imported / updated / duplicates / failed and the reasons. Every call is logged on
 * BTC > Integration Monitoring, where failed invoices can be retried.
 */
class NovaInvoiceApiController extends Controller
{
    public function store(Request $request)
    {
        $key = (string) config('btc.nova.api_key', '');
        if ($key === '' || ! hash_equals($key, (string) $request->header('X-Api-Key', ''))) {
            return response()->json(['success' => false, 'message' => $key === '' ? 'Nova feed is not switched on (BTC_NOVA_API_KEY not set).' : 'Invalid API key.'], $key === '' ? 503 : 401);
        }
        if (! Schema::hasTable('btc_invoices')) {
            return response()->json(['success' => false, 'message' => 'BTC invoice tables are not set up on this server (run the database setup).'], 503);
        }
        $body = $request->json()->all();
        $records = isset($body['invoices']) && is_array($body['invoices']) ? $body['invoices'] : (array_is_list($body) ? $body : [$body]);
        $records = array_values(array_filter($records, 'is_array'));
        if (! $records) {
            DB::table('btc_integration_logs')->insert(['channel' => 'nova_api', 'status' => 'failure', 'received' => 0, 'error' => 'Empty or invalid JSON body',
                'request_body' => mb_substr($request->getContent(), 0, 5000), 'ip' => $request->ip(), 'created_at' => now()]);

            return response()->json(['success' => false, 'message' => 'Send one invoice object or a list of them as JSON.'], 422);
        }
        $r = app(NovaInbox::class)->ingest($records, 'nova_api', $request->getContent(), (int) ($body['company_id'] ?? 1));

        return response()->json(['success' => $r['failed'] === 0, 'log_id' => $r['log_id'], 'received' => $r['received'], 'imported' => $r['imported'],
            'updated' => $r['updated'], 'duplicates' => $r['duplicates'], 'failed' => $r['failed'], 'errors' => $r['errors']], $r['failed'] && $r['failed'] === $r['received'] ? 422 : 200);
    }
}
