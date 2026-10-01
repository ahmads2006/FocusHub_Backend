<?php

namespace App\Services\Firebase;

use App\Models\MongoDBOutbox;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirestoreService
{
    protected string $projectId;
    protected string $credentialsPath;
    protected Client $httpClient;
    protected string $database = '(default)';

    public function __construct(?string $projectId = null, ?string $credentialsPath = null)
    {
        $this->projectId = $projectId ?: config('firebase.projects.app.project_id', env('FIREBASE_PROJECT_ID', 'opalshot-e3133'));
        $this->credentialsPath = $credentialsPath ?: base_path(config('firebase.projects.app.credentials', env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase-credentials.json')));
        $this->httpClient = new Client([
            'timeout' => 8.0,
            'connect_timeout' => 3.0,
        ]);
    }

    protected static ?string $cachedToken = null;
    protected static ?int $tokenExpiry = null;

    /**
     * Get OAuth2 Access Token for Firestore API calls.
     */
    public function getAccessToken(): string
    {
        // 1. In-memory check
        if (self::$cachedToken && self::$tokenExpiry && time() < self::$tokenExpiry) {
            return self::$cachedToken;
        }

        // 2. Try file or default cache store
        try {
            return Cache::store('file')->remember('firebase_firestore_access_token', 3300, function () {
                return $this->fetchFreshToken();
            });
        } catch (\Throwable $e) {
            return $this->fetchFreshToken();
        }
    }

    protected function fetchFreshToken(): string
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \RuntimeException("Firebase credentials file not found at: {$this->credentialsPath}");
        }

        $credentials = new ServiceAccountCredentials(
            [
                'https://www.googleapis.com/auth/datastore',
                'https://www.googleapis.com/auth/cloud-platform',
            ],
            $this->credentialsPath
        );

        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new \RuntimeException("Failed to fetch OAuth2 token for Firestore.");
        }

        self::$cachedToken = $token['access_token'];
        self::$tokenExpiry = time() + 3300;

        return $token['access_token'];
    }

    /**
     * Base URL for the default Firestore database.
     */
    protected function getBaseUrl(): string
    {
        return "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->database}/documents";
    }

    /**
     * Insert or replace a document in Firestore.
     */
    public function setDocument(string $collection, string $docId, array $data): array
    {
        $url = "{$this->getBaseUrl()}/{$collection}/{$docId}";
        $token = $this->getAccessToken();

        $response = $this->httpClient->patch($url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'fields' => $this->toFirestoreFields($data),
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        return $this->fromFirestoreDocument($body);
    }

    /**
     * Retrieve a document by ID.
     */
    public function getDocument(string $collection, string $docId): ?array
    {
        $url = "{$this->getBaseUrl()}/{$collection}/{$docId}";
        $token = $this->getAccessToken();

        try {
            $response = $this->httpClient->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            return $this->fromFirestoreDocument($body);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Delete a document by ID.
     */
    public function deleteDocument(string $collection, string $docId): bool
    {
        $url = "{$this->getBaseUrl()}/{$collection}/{$docId}";
        $token = $this->getAccessToken();

        try {
            $this->httpClient->delete($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * List documents from a collection.
     */
    public function listDocuments(string $collection, int $pageSize = 100, ?string $pageToken = null): array
    {
        $url = "{$this->getBaseUrl()}/{$collection}";
        $token = $this->getAccessToken();

        $query = ['pageSize' => $pageSize];
        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        try {
            $response = $this->httpClient->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'query' => $query,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $documents = [];
            foreach ($body['documents'] ?? [] as $doc) {
                $documents[] = $this->fromFirestoreDocument($doc);
            }

            return [
                'documents'     => $documents,
                'nextPageToken' => $body['nextPageToken'] ?? null,
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return ['documents' => [], 'nextPageToken' => null];
            }
            throw $e;
        }
    }

    /**
     * Run a structured query on a collection.
     */
    public function runQuery(string $collection, array $filters = [], array $orderBy = [], ?int $limit = null): array
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->database}/documents:runQuery";
        $token = $this->getAccessToken();

        $structuredQuery = [
            'from' => [
                ['collectionId' => $collection],
            ],
        ];

        // Build Where filter
        if (!empty($filters)) {
            if (count($filters) === 1) {
                $structuredQuery['where'] = $this->buildFieldFilter($filters[0]);
            } else {
                $subFilters = array_map([$this, 'buildFieldFilter'], $filters);
                $structuredQuery['where'] = [
                    'compositeFilter' => [
                        'op'      => 'AND',
                        'filters' => $subFilters,
                    ],
                ];
            }
        }

        // Build Order By
        if (!empty($orderBy)) {
            $structuredQuery['orderBy'] = array_map(function ($order) {
                return [
                    'field'     => ['fieldPath' => $order['field']],
                    'direction' => strtoupper($order['direction'] ?? 'ASCENDING') === 'DESC' ? 'DESCENDING' : 'ASCENDING',
                ];
            }, $orderBy);
        }

        // Limit
        if ($limit !== null) {
            $structuredQuery['limit'] = $limit;
        }

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'structuredQuery' => $structuredQuery,
                ],
            ]);

            $results = json_decode((string) $response->getBody(), true);
            $documents = [];

            foreach ($results as $item) {
                if (isset($item['document'])) {
                    $documents[] = $this->fromFirestoreDocument($item['document']);
                }
            }

            return $documents;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::channel('single')->error('Firestore runQuery failed', [
                'error' => $e->getMessage(),
                'response' => (string) $e->getResponse()?->getBody(),
            ]);
            throw $e;
        }
    }

    /**
     * Direct insert with outbox fallback.
     */
    public function directInsert(string $collection, array $payload): void
    {
        $docId = $payload['id'] ?? (string) Str::uuid();
        $payload['id'] = $docId;
        $payload['_id'] = $docId;

        try {
            $this->setDocument($collection, $docId, $payload);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Firestore direct insert failed, falling back to Outbox', [
                'collection' => $collection,
                'error'      => $e->getMessage(),
            ]);

            $this->outboxInsert($collection, $payload);
        }
    }

    /**
     * Outbox insert pattern for resilient asynchronous processing.
     */
    public function outboxInsert(string $collection, array $payload): void
    {
        if (!isset($payload['id'])) {
            $payload['id'] = (string) Str::uuid();
        }
        if (!isset($payload['_id'])) {
            $payload['_id'] = $payload['id'];
        }

        MongoDBOutbox::create([
            'collection' => $collection,
            'operation'  => 'insert',
            'payload'    => $payload,
            'status'     => 'pending',
        ]);

        \App\Jobs\ProcessFirebaseOutbox::dispatch();
    }

    /**
     * Helper to build a single field filter.
     */
    protected function buildFieldFilter(array $f): array
    {
        $op = strtoupper($f['op'] ?? 'EQUAL');
        $operatorMap = [
            '='       => 'EQUAL',
            '=='      => 'EQUAL',
            'EQUAL'   => 'EQUAL',
            '<'       => 'LESS_THAN',
            '<='      => 'LESS_THAN_OR_EQUAL',
            '>'       => 'GREATER_THAN',
            '>='      => 'GREATER_THAN_OR_EQUAL',
            '!='      => 'NOT_EQUAL',
            'IN'      => 'IN',
        ];

        return [
            'fieldFilter' => [
                'field' => ['fieldPath' => $f['field']],
                'op'    => $operatorMap[$op] ?? 'EQUAL',
                'value' => $this->toFirestoreValue($f['value']),
            ],
        ];
    }

    /**
     * Convert PHP associative array to Firestore typed fields.
     */
    public function toFirestoreFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->toFirestoreValue($value);
        }
        return $fields;
    }

    /**
     * Convert PHP primitive/object into Firestore value representation.
     */
    public function toFirestoreValue(mixed $value): array
    {
        if (is_null($value)) {
            return ['nullValue' => null];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return ['timestampValue' => $value->format('Y-m-d\TH:i:s.u\Z')];
        }

        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $items = [];
                foreach ($value as $item) {
                    $items[] = $this->toFirestoreValue($item);
                }
                return ['arrayValue' => ['values' => $items]];
            } else {
                return ['mapValue' => ['fields' => $this->toFirestoreFields($value)]];
            }
        }

        return ['stringValue' => (string) $value];
    }

    /**
     * Convert Firestore document structure into clean associative PHP array.
     */
    public function fromFirestoreDocument(array $doc): array
    {
        $result = [];

        if (isset($doc['name'])) {
            $parts = explode('/', $doc['name']);
            $result['id'] = end($parts);
            $result['_id'] = $result['id'];
        }

        foreach ($doc['fields'] ?? [] as $key => $typedValue) {
            $result[$key] = $this->fromFirestoreValue($typedValue);
        }

        return $result;
    }

    /**
     * Convert a single Firestore typed value to native PHP type.
     */
    public function fromFirestoreValue(array $typedValue): mixed
    {
        if (array_key_exists('stringValue', $typedValue)) {
            return $typedValue['stringValue'];
        }

        if (array_key_exists('integerValue', $typedValue)) {
            return (int) $typedValue['integerValue'];
        }

        if (array_key_exists('doubleValue', $typedValue)) {
            return (float) $typedValue['doubleValue'];
        }

        if (array_key_exists('booleanValue', $typedValue)) {
            return (bool) $typedValue['booleanValue'];
        }

        if (array_key_exists('nullValue', $typedValue)) {
            return null;
        }

        if (array_key_exists('timestampValue', $typedValue)) {
            return $typedValue['timestampValue'];
        }

        if (array_key_exists('mapValue', $typedValue)) {
            $map = [];
            foreach ($typedValue['mapValue']['fields'] ?? [] as $k => $v) {
                $map[$k] = $this->fromFirestoreValue($v);
            }
            return $map;
        }

        if (array_key_exists('arrayValue', $typedValue)) {
            $list = [];
            foreach ($typedValue['arrayValue']['values'] ?? [] as $v) {
                $list[] = $this->fromFirestoreValue($v);
            }
            return $list;
        }

        return null;
    }
}
