<?php

namespace App\Services\Matrix;

use App\Models\MatrixPaymentInstruction;
use App\Models\MatrixSyncState;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MatrixApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;
    protected int $batchSize;
    protected bool $mockMode;

    public function __construct()
    {
        $this->baseUrl = config('matrix.base_url', 'https://matrix-test.vnrseeds.co.in/api/v1/integration');
        $this->apiKey = config('matrix.api_key', 'matrix_dummy_test_api_key_1740');
        $this->timeout = (int) config('matrix.timeout', 60);
        $this->batchSize = (int) config('matrix.batch_size', 500);
        $this->mockMode = (bool) config('matrix.mock_mode', false)
            || str_contains($this->baseUrl, 'example.com')
            || str_contains($this->baseUrl, 'matrix.test')
            || str_contains($this->baseUrl, 'matrix-test')
            || str_contains($this->baseUrl, 'dummy');
    }

    /**
     * Check if running in mock / testing mode
     */
    public function isMock(): bool
    {
        return $this->mockMode;
    }

    /**
     * Check if Matrix integration is enabled and configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    /**
     * Send HTTP client with required Matrix headers
     */
    protected function client()
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Push payment instructions to Matrix (POST /payment-instructions)
     * Handles up to 500 items per call.
     *
     * @param array $instructions
     * @return array
     * @throws Exception
     */
    public function pushPaymentInstructions(array $instructions): array
    {
        if (empty($instructions)) {
            return [
                'success' => true,
                'summary' => ['received' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0],
                'results' => [],
            ];
        }

        if (!$this->isConfigured()) {
            throw new Exception('Matrix API is not properly configured. Check MATRIX_BASE_URL and MATRIX_API_KEY in .env');
        }

        // Mock test mode simulation
        if ($this->isMock()) {
            Log::info('Matrix API: [MOCK TEST MODE] Simulating push of ' . count($instructions) . ' payment instructions', [
                'instructions' => $instructions,
            ]);

            $mockResults = [];
            foreach ($instructions as $idx => $inst) {
                $mockResults[] = [
                    'source_ref' => $inst['source_ref'] ?? ('MOCK-REF-' . $idx),
                    'id' => rand(100000, 999999),
                    'instruction_no' => 'PI-MOCK-' . date('Ymd') . '-' . sprintf('%04d', $idx + 1),
                    'result' => 'created',
                    'status' => 'held',
                    'message' => 'Held in Matrix (Mock Mode Test)',
                ];
            }

            return [
                'success' => true,
                'status_code' => 200,
                'summary' => [
                    'received' => count($instructions),
                    'created' => count($instructions),
                    'updated' => 0,
                    'unchanged' => 0,
                ],
                'results' => $mockResults,
                'raw' => ['mode' => 'mock_simulation', 'mock' => true],
            ];
        }

        $url = "{$this->baseUrl}/payment-instructions";

        Log::info('Pushing payment instructions to Matrix', [
            'count' => count($instructions),
            'url' => $url,
        ]);

        $response = $this->client()->post($url, [
            'instructions' => $instructions,
        ]);

        $statusCode = $response->status();
        $body = $response->json();

        // 200 = all held, 207 = multi-status/some held, 422 = validation failure
        if (in_array($statusCode, [200, 207])) {
            return [
                'success' => true,
                'status_code' => $statusCode,
                'summary' => $body['summary'] ?? [],
                'results' => $body['results'] ?? [],
                'raw' => $body,
            ];
        }

        Log::error('Matrix API push failed', [
            'status' => $statusCode,
            'body' => $body,
        ]);

        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $body['message'] ?? 'Failed to push instructions to Matrix',
            'errors' => $body['errors'] ?? ($body['results'] ?? []),
            'raw' => $body,
        ];
    }

    /**
     * Fetch payment updates since given ISO-8601 timestamp (GET /payment-instructions/updates)
     *
     * @param string|null $since ISO-8601 formatted string e.g. '2026-10-06T16:38:19+05:30'
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function fetchUpdates(?string $since = null, int $limit = 200): array
    {
        if ($this->isMock()) {
            return [
                'success' => true,
                'status_code' => 200,
                'count' => 0,
                'has_more' => false,
                'next_since' => now()->toIso8601String(),
                'updates' => [],
            ];
        }

        if (!$this->isConfigured()) {
            throw new Exception('Matrix API is not properly configured.');
        }

        $url = "{$this->baseUrl}/payment-instructions/updates";
        $queryParams = ['limit' => $limit];
        if (!empty($since)) {
            $queryParams['since'] = $since;
        }

        $response = $this->client()->get($url, $queryParams);

        if (!$response->successful()) {
            Log::error('Matrix API fetch updates failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to fetch updates from Matrix: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Query status of a single payment instruction by reference (GET /payment-instructions/status)
     *
     * @param string $sourceRef
     * @return array
     * @throws Exception
     */
    public function getStatusByReference(string $sourceRef): array
    {
        if ($this->isMock()) {
            return [
                'success' => true,
                'reference' => $sourceRef,
                'status' => 'held',
                'message' => 'Status held (Mock Mode)',
            ];
        }
        if (!$this->isConfigured()) {
            throw new Exception('Matrix API is not properly configured.');
        }

        $url = "{$this->baseUrl}/payment-instructions/status";
        $response = $this->client()->get($url, ['reference' => $sourceRef]);

        if (!$response->successful()) {
            throw new Exception('Failed to query status from Matrix: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Sync parties (optional employee master push: POST /parties)
     *
     * @param array $parties
     * @return array
     */
    public function syncParties(array $parties): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('Matrix API is not properly configured.');
        }

        $url = "{$this->baseUrl}/parties";
        $response = $this->client()->post($url, ['parties' => $parties]);

        return $response->json();
    }
}
