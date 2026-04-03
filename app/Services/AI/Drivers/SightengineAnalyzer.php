<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\DTOs\AnalysisResult;
use App\Services\AI\DTOs\ImageAnalysisResult;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\AnalyzerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SightengineAnalyzer implements MediaAnalyzerInterface
{
    /**
     * Map Sightengine type/description → scene categories.
     */
    protected const SCENE_MAP = [
        'forest'       => ['forest', 'woodland', 'jungle', 'tree', 'rainforest'],
        'sea'          => ['sea', 'ocean', 'beach', 'coast', 'wave', 'underwater'],
        'nature'       => ['nature', 'landscape', 'mountain', 'valley', 'river', 'lake', 'waterfall', 'sunset', 'sky', 'cloud', 'field', 'garden', 'flower', 'outdoor'],
        'urban'        => ['city', 'urban', 'street', 'road', 'traffic', 'skyline'],
        'architecture' => ['building', 'architecture', 'bridge', 'tower', 'church', 'mosque', 'monument', 'house', 'indoor'],
        'portrait'     => ['person', 'face', 'portrait', 'selfie', 'people'],
        'food'         => ['food', 'meal', 'dish', 'cuisine', 'dessert', 'fruit', 'drink'],
        'abstract'     => ['abstract', 'pattern', 'texture', 'art', 'painting'],
    ];

    public function getName(): string
    {
        return 'sightengine';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("SightengineAnalyzer only supports images currently in this failover implementation.");
        }

        $apiUser = config('services.sightengine.api_user');
        $apiSecret = config('services.sightengine.api_secret');

        if (empty($apiUser) || empty($apiSecret)) {
            throw new AnalyzerException("Sightengine credentials not configured.");
        }

        try {
            $path = $media->path;
            
            if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                // If it's a temp file path (pre-upload) or a full URL
                $url = $media->getRawOriginal('url') ?? $media->url;
                $content = @file_get_contents($url);
                if ($content === false) {
                    throw new AnalyzerException("Failed to read media content from URL/Path: {$url}");
                }
            } else {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            }

            $response = Http::attach(
                'media', 
                $content, 
                $media->filename ?? 'image.jpg'
            )->post('https://api.sightengine.com/1.0/check.json', [
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,text-content,properties,quality',
            ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    Log::channel('datadog')->error("Sightengine Quota Exceeded", ['driver' => $this->getName()]);
                    throw new QuotaExceededException("Sightengine Quota Exceeded.");
                }
                Log::channel('datadog')->error("Sightengine API Error", ['driver' => $this->getName(), 'status' => $response->status()]);
                throw new AnalyzerException("Sightengine API Error: " . $response->body());
            }

            $data = $response->json();
            
            if (($data['status'] ?? '') === 'failure') {
                if (($data['error']['code'] ?? '') == 429) {
                    Log::channel('datadog')->error("Sightengine Quota Exceeded (API)", ['driver' => $this->getName()]);
                    throw new QuotaExceededException("Sightengine Quota Exceeded via API response.");
                }
                Log::channel('datadog')->error("Sightengine Failure Response", ['driver' => $this->getName(), 'error' => $data['error']['message'] ?? 'Unknown error']);
                throw new AnalyzerException("Sightengine Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            $tags = [];
            if (isset($data['text']['profanity'])) {
                foreach ($data['text']['profanity'] as $p) $tags[] = $p['text'];
            }

            // Extract scene-related tags from properties
            $sceneType = $data['type'] ?? null;
            if (isset($data['properties']['description'])) {
                $tags[] = $data['properties']['description'];
            }

            // --- Strict Safety Analysis ---
            $safety = $this->evaluateSafety($data);
            $isSensitive = $safety['isSensitive'];
            $sensitivityReasons = $safety['sensitivityReasons'];
            $goreScore = $safety['goreScore'];
            $safetyVerdict = $safety['safetyVerdict'];

            // --- Quality Grade from Sightengine quality model ---
            $qualityGrade = 'high_quality';
            $blurScore = $data['quality']['blur'] ?? null;
            $noiseScore = $data['quality']['noise'] ?? null;
            if ($blurScore !== null) {
                if ($blurScore > 0.5 || ($noiseScore !== null && $noiseScore > 0.5)) {
                    $qualityGrade = 'low_quality';
                } elseif ($blurScore > 0.3 || ($noiseScore !== null && $noiseScore > 0.3)) {
                    $qualityGrade = 'medium_quality';
                }
            }

            // --- Scene Category ---
            $category = $this->deriveCategory($sceneType, $tags);

            // Inject safetyVerdict into rawResults for ContentSafetyService
            $data['_safety_verdict'] = $safetyVerdict;
            $data['_sensitivity_reasons'] = $sensitivityReasons;
            $data['_gore_score'] = $goreScore;

            Log::channel('datadog')->info("Sightengine Analysis Success", [
                'driver' => $this->getName(),
                'is_sensitive' => $isSensitive,
                'safety_verdict' => $safetyVerdict,
                'gore_score' => $goreScore,
                'quality_grade' => $qualityGrade,
                'category' => $category,
                'media_id' => $media->id ?? 'new',
            ]);

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('datadog')->error("Sightengine Analysis Exception", [
                'driver' => $this->getName(),
                'message' => $e->getMessage(),
            ]);
            Log::error("Sightengine Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("SightengineAnalyzer only supports images currently.");
        }

        $apiUser = config('services.sightengine.api_user');
        $apiSecret = config('services.sightengine.api_secret');

        if (empty($apiUser) || empty($apiSecret)) {
            throw new AnalyzerException("Sightengine credentials not configured.");
        }

        try {
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new AnalyzerException("Failed to read uploaded file content.");
            }

            $response = Http::attach(
                'media', 
                $content, 
                $file->getClientOriginalName()
            )->post('https://api.sightengine.com/1.0/check.json', [
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,text-content,properties,quality',
            ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Sightengine Quota Exceeded.");
                }
                throw new AnalyzerException("Sightengine API Error: " . $response->body());
            }

            $data = $response->json();
            
            if (($data['status'] ?? '') === 'failure') {
                if (($data['error']['code'] ?? '') == 429) {
                    throw new QuotaExceededException("Sightengine Quota Exceeded via API response.");
                }
                throw new AnalyzerException("Sightengine Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            $tags = [];
            if (isset($data['text']['profanity'])) {
                foreach ($data['text']['profanity'] as $p) $tags[] = $p['text'];
            }
          

            $sceneType = $data['type'] ?? null;
            if (isset($data['properties']['description'])) {
                $tags[] = $data['properties']['description'];
            }

            // --- Strict Safety Analysis ---
            $safety = $this->evaluateSafety($data);
            $isSensitive = $safety['isSensitive'];
            $sensitivityReasons = $safety['sensitivityReasons'];
            $goreScore = $safety['goreScore'];
            $safetyVerdict = $safety['safetyVerdict'];

            $qualityGrade = 'high_quality';
            $blurScore = $data['quality']['blur'] ?? null;
            $noiseScore = $data['quality']['noise'] ?? null;
            if ($blurScore !== null) {
                if ($blurScore > 0.5 || ($noiseScore !== null && $noiseScore > 0.5)) {
                    $qualityGrade = 'low_quality';
                } elseif ($blurScore > 0.3 || ($noiseScore !== null && $noiseScore > 0.3)) {
                    $qualityGrade = 'medium_quality';
                }
            }

            $category = $this->deriveCategory($sceneType, $tags);

            // Inject safetyVerdict into rawResults for ContentSafetyService
            $data['_safety_verdict'] = $safetyVerdict;
            $data['_sensitivity_reasons'] = $sensitivityReasons;
            $data['_gore_score'] = $goreScore;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Sightengine Analysis Exception (File): " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Derive scene category from Sightengine type and tags.
     */
    protected function deriveCategory(?string $sceneType, array $tags): string
    {
        $searchTerms = array_map('strtolower', array_filter(array_merge(
            $tags,
            $sceneType ? [$sceneType] : []
        )));

        foreach (self::SCENE_MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                foreach ($searchTerms as $term) {
                    if ($term === $keyword || str_contains($term, $keyword)) {
                        return $category;
                    }
                }
            }
        }

        return 'other';
    }

    /**
     * Unified, strict safety evaluation logic matching the modern Sightengine API structure.
     */
    protected function evaluateSafety(array $data): array
    {
        $isSensitive = false;
        $sensitivityReasons = [];

        // 1. Nudity Check (Robust Parsing)
        $nudityRisk = 0;
        if (isset($data['nudity'])) {
            $nudityRisk = max(
                $data['nudity']['sexual_activity'] ?? 0,
                $data['nudity']['sexual_display'] ?? 0,
                $data['nudity']['erotica'] ?? 0,
                $data['nudity']['suggestive'] ?? 0
            );
            
            $safeScore = $data['nudity']['safe'] ?? $data['nudity']['none'] ?? 1;
            
            if ($nudityRisk > 0.5 || $safeScore < 0.5) {
                $isSensitive = true;
                $sensitivityReasons[] = 'nudity';
            }
        }

        // 2. Weapon Check (Parse arrays or single probs properly, without masking)
        if (isset($data['weapon'])) {
            $weaponItems = $data['weapon']['classes'] ?? $data['weapon'];
            if (is_numeric($weaponItems)) {
                if ($weaponItems > 0.5) {
                    $isSensitive = true;
                    $sensitivityReasons[] = 'weapon';
                }
            } elseif (is_array($weaponItems)) {
                foreach ($weaponItems as $score) {
                    if (is_numeric($score) && $score > 0.5) {
                        $isSensitive = true;
                        $sensitivityReasons[] = 'weapon';
                        break;
                    }
                }
            }
        }

        // 3. Offensive Check (Parse properly without masking)
        if (isset($data['offensive'])) {
            $offItems = $data['offensive']['classes'] ?? $data['offensive'];
            if (is_numeric($offItems)) {
                if ($offItems > 0.5) {
                    $isSensitive = true;
                    $sensitivityReasons[] = 'offensive';
                }
            } elseif (is_array($offItems)) {
                foreach ($offItems as $score) {
                    if (is_numeric($score) && $score > 0.5) {
                        $isSensitive = true;
                        $sensitivityReasons[] = 'offensive';
                        break;
                    }
                }
            }
        }

        // 4. Gore / Violence Check
        $goreScore = 0;
        if (isset($data['gore'])) {
            if (isset($data['gore']['prob'])) {
                $goreScore = $data['gore']['prob'];
            } elseif (isset($data['gore']['classes'])) {
                $goreScore = max(
                    $data['gore']['classes']['very_bloody'] ?? 0,
                    $data['gore']['classes']['slightly_bloody'] ?? 0,
                    $data['gore']['classes']['corpse'] ?? 0,
                    $data['gore']['classes']['serious_injury'] ?? 0,
                    $data['gore']['classes']['superficial_injury'] ?? 0,
                    $data['gore']['classes']['body_organ'] ?? 0
                );
            } else {
                $goreScore = max(
                    $data['gore']['very_bloody'] ?? 0,
                    $data['gore']['slightly_bloody'] ?? 0,
                    $data['gore']['corpse'] ?? 0,
                    $data['gore']['serious_injury'] ?? 0,
                    $data['gore']['superficial_injury'] ?? 0,
                    $data['gore']['body_organ'] ?? 0
                );
            }

            if ($goreScore > 0.4) {
                $isSensitive = true;
                $sensitivityReasons[] = 'gore';
            }
        }

        // 5. Verdict Assignment
        $safetyVerdict = 'approved';
        if ($isSensitive) {
            // Hard reject conditions: High gore, high nudity risk
            if ($goreScore > 0.4 || $nudityRisk > 0.7 || in_array('gore', $sensitivityReasons)) {
                $safetyVerdict = 'rejected';
            } else {
                $safetyVerdict = 'pending_review';
            }
        }

        return [
            'isSensitive' => $isSensitive,
            'sensitivityReasons' => $sensitivityReasons,
            'goreScore' => $goreScore,
            'safetyVerdict' => $safetyVerdict,
        ];
    }
}
