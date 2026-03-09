<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecurityService
{
    /**
     * Whitelist of allowed MIME types and their corresponding Magic Bytes (Hex)
     */
    private const ALLOWED_TYPES = [
        'jpg'  => ['mime' => 'image/jpeg',   'magic' => ['FFD8FF']],
        'jpeg' => ['mime' => 'image/jpeg',   'magic' => ['FFD8FF']],
        'png'  => ['mime' => 'image/png',    'magic' => ['89504E47']],
        'webp' => ['mime' => 'image/webp',   'magic' => ['52494646', '57454250']], // RIFF...WEBP
        'gif'  => ['mime' => 'image/gif',    'magic' => ['47494638']],
        'bmp'  => ['mime' => 'image/bmp',    'magic' => ['424D']],
        'heic' => ['mime' => 'image/heic',   'magic' => ['000000', '6674797068656963']], // ftypheic
        'heif' => ['mime' => 'image/heif',   'magic' => ['000000', '6674797068656966']], // ftypheif
        'tiff' => ['mime' => 'image/tiff',   'magic' => ['49492A00', '4D4D002A']],
        'svg'  => ['mime' => 'image/svg+xml', 'magic' => null], // SVGs are text/XML
    ];

    /**
     * Perform multi-layered security verification on the uploaded file.
     * 
     * @throws ValidationException
     */
    public function verify(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        
        // Layer 1: MIME Type Sniffing (finfo)
        $detectedMime = $this->sniffMimeType($path);
        
        // Layer 2: Magic Bytes Verification
        $extension = $this->verifyMagicBytes($path, $detectedMime);

        // Layer 3: Virus Scanning (ClamAV)
        $this->scanForViruses($path);

        return $extension;
    }

    /**
     * Sniff true MIME type using libmagic (finfo_file)
     */
    private function sniffMimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (!$mime || !str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $this->logAndThrow("Security Alert: Failed MIME sniffing. Detected: " . ($mime ?: 'Unknown'));
        }

        return $mime;
    }

    /**
     * Verify file header magic bytes against a strict whitelist.
     */
    private function verifyMagicBytes(string $path, string $mime): string
    {
        // Special case: SVG is XML text
        if ($mime === 'image/svg+xml') {
            return $this->verifySvgContent($path);
        }

        $handle = fopen($path, 'rb');
        $header = fread($handle, 16); // Read first 16 bytes
        fclose($handle);

        $hexHeader = strtoupper(bin2hex($header));

        foreach (self::ALLOWED_TYPES as $ext => $config) {
            if ($config['mime'] !== $mime || $config['magic'] === null) continue;

            foreach ($config['magic'] as $magic) {
                if (str_starts_with($hexHeader, $magic)) {
                    return $ext;
                }
            }
        }

        $this->logAndThrow("Security Alert: Magic Bytes mismatch for MIME: $mime");
        return '';
    }

    /**
     * Strict XML/SVG content verification to prevent Polyglot or XSS payloads.
     */
    private function verifySvgContent(string $path): string
    {
        $content = file_get_contents($path);
        
        // Ensure it contains mandatory SVG tags and no malicious scripts
        if (!str_contains($content, '<svg') || !str_contains($content, 'xmlns')) {
            $this->logAndThrow("Security Alert: Invalid SVG structure.");
        }

        $maliciousPatterns = ['<script', 'javascript:', 'onclick', 'onerror', 'onmouseover'];
        foreach ($maliciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $this->logAndThrow("Security Alert: Malicious payload detected in SVG: $pattern");
            }
        }

        return 'svg';
    }

    /**
     * Scan file for viruses using ClamAV (if installed)
     */
    private function scanForViruses(string $path): void
    {
        // For development/demonstration, we assume ClamAV is called via socket or CLI
        // If ClamAV is not configured, we log a warning but don't block (optional policy)
        
        if (!file_exists($path)) return;

        try {
            // Example using CLI clamscan (slow for production, use clamd socket in prod)
            // We use a small timeout to avoid blocking the user indefinitely
            $process = new \Symfony\Component\Process\Process(['clamscan', '--no-summary', $path]);
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                $output = $process->getOutput();
                if (str_contains($output, 'FOUND')) {
                    $this->logAndThrow("CRITICAL Security: Virus or Malicious signature detected by ClamAV!");
                }
            }
        } catch (\Exception $e) {
            // If ClamAV is missing on this specific environment, we log it
            if (str_contains($e->getMessage(), 'not found')) {
                Log::warning("Security: ClamAV is NOT installed. Virus scanning skipped.");
            } else {
                Log::error("Security: Virus scanning failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Centralized logging and exception trigger
     */
    private function logAndThrow(string $message): void
    {
        Log::critical($message);
        throw ValidationException::withMessages([
            'image' => __('تنبيه أمني: تم رصد نشاط مريب في الملف المرفوع. تم حظر العملية وتسجيل الحدث للمراجعة.')
        ]);
    }
}
