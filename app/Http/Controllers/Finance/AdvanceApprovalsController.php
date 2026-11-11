<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdvanceApprovalsController extends Controller
{
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = url("/api/advance/finance"); // STAGING-URL-01: own server, not production
    }

    /**
     * Display the finance advance approvals page
     */
    public function index()
    {
        return view('finance.advance-approvals');
    }


    public function treasury()
    {
        return view('treasury.instructed-treasury');
    }

    /**
     * Get pending finance approvals
     */
    public function getPending(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/pending', [
                'user_id' => auth()->id(),
                'year_id' => session('year_id', date('Y'))
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending requests',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching pending requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get approved finance requests
     */
    public function getApproved(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/approved', [
                'user_id' => auth()->id(),
                'year_id' => session('year_id', date('Y'))
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch approved requests',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching approved requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get rejected finance requests
     */
    public function getRejected(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/rejected', [
                'user_id' => auth()->id(),
                'year_id' => session('year_id', date('Y'))
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rejected requests',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching rejected requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get released payments (Instructed Payment)
     */
    public function getReleasedPayments(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/released-payments', [
                'year_id' => session('year_id', date('Y')),
                'status' => 'Ready for Payment',
                'limit' => 100
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch released payments',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching released payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get confirmed payments
     */
    public function getConfirmedPayments(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/confirmed-payments', [
                'year_id' => session('year_id', date('Y')),
                'limit' => 100
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch confirmed payments',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching confirmed payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get unsettled payments
     */
    public function getUnsettledPayments(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/unsettled-payments', [
                'year_id' => session('year_id', date('Y')),
                'limit' => 100
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unsettled payments',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error fetching unsettled payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Approve finance request
     */
    public function approve(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->apiBaseUrl . '/approve', $request->all());

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error('Error approving request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject finance request
     */
    public function reject(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->apiBaseUrl . '/reject', $request->all());

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error('Error rejecting request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release payment for selected requests
     */
    public function releasePayment(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->apiBaseUrl . '/release-payment', $request->all());

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error('Error releasing payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve discrepancy
     */
    public function resolveDiscrepancy(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->apiBaseUrl . '/resolve-discrepancy', $request->all());

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error('Error resolving discrepancy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export payments to Focus
     */
    public function exportPayments(Request $request)
    {
        try {
            $yearId = session('year_id', date('Y'));
            $format = $request->input('format', 'excel');

            if ($format === 'excel') {
                // For Excel download, redirect to the API URL
                return redirect($this->apiBaseUrl . '/export-payments?year_id=' . $yearId . '&format=excel');
            }

            // For JSON check
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($this->apiBaseUrl . '/export-payments', [
                'year_id' => $yearId
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to check export data',
                'data' => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error exporting payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import payment confirmation
     */
    public function importPayment(Request $request)
    {
        try {
            $request->validate([
                'payment_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            $file = $request->file('payment_file');

            $response = Http::attach(
                'payment_file',
                file_get_contents($file->getPathname()),
                $file->getClientOriginalName()
            )->post($this->apiBaseUrl . '/confirm-payments', [
                'year_id' => session('year_id', date('Y')),
                'confirmed_by' => auth()->id()
            ]);

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error('Error importing payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
