<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class RestrictedNameHelper
{
    /**
     * List of restricted keywords and patterns.
     */
    public static function getRestrictedWords(): array
    {
        return [
            // Admin variants
            'admin', 'administrator', 'superadmin', 'super admin', 'root', 'moderator' ,'support',
            // System/App/Company variants
            'opticvault', 'optic vault', 'optic-vault', 'optic_vault',
            // Arabic Admin variants
            'مدير', 'إدارة', 'المدير', 'مشرف', 'المشرف',
            // Explicit / Violent / Inappropriate words (Basic list)
            'sex', 'porn', 'fuck', 'bitch', 'whore', 'slut', 'nude', 'naked', 'dick', 'pussy', 'vagina', 'penis', 'boobs', 'tits', 'asshole',
            'rape', 'murder', 'kill', 'terrorist', 'nazi', 'hitler', 'isis',
            // Arabic bad/violent words
            'جنس', 'نيك', 'شرموطة', 'قحبة', 'سكس', 'زب', 'كس', 'دعارة', 'طيز', 'ممحون', 'قتل', 'إرهاب', 'داعش'
        ];
    }

    /**
     * Check if a name contains any restricted words.
     * Returns true if the name is RESTRICTED (bad).
     */
    public static function isRestricted(string $name): bool
    {
        // Normalize name by removing all spaces and special characters for strict checking
        $normalizedName = Str::lower(str_replace(['_', '-', '.', ' '], '', $name));

        foreach (self::getRestrictedWords() as $word) {
            $normalizedWord = Str::lower(str_replace(['_', '-', '.', ' '], '', $word));
            
            // Check if restricted word is inside the normalized name
            if (Str::contains($normalizedName, $normalizedWord)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Provide a safe fallback name if the original is restricted.
     */
    public static function getSafeFallbackName(string $originalName, ?string $email = null): string
    {
        $name = $originalName ?: ($email ? explode('@', $email)[0] : 'User');
        
        if (!self::isRestricted($name)) {
            return $name;
        }

        // If restricted, try to use email prefix if it's safe
        if ($email) {
            $emailPrefix = explode('@', $email)[0];
            if (!self::isRestricted($emailPrefix)) {
                return $emailPrefix;
            }
        }

        // Ultimate fallback
        return 'User_' . Str::upper(Str::random(6));
    }

    /**
     * Generate a unique handle starting with @ based on a display name.
     */
    public static function generateUniqueHandle(string $displayName): string
    {
        // 1. Clean the name: remove spaces, special chars, and convert to lowercase/studly
        $base = str_replace([' ', '.', '-', '@'], '', $displayName);
        if (empty($base)) {
            $base = 'User' . Str::random(4);
        }

        $baseHandle = '@' . $base;
        $handle = $baseHandle;
        
        // 2. Ensure it's not restricted
        if (self::isRestricted($handle)) {
            $handle = '@User_' . Str::random(6);
            $baseHandle = $handle;
        }

        // 3. Ensure uniqueness in DB
        $counter = 1;
        while (\Illuminate\Support\Facades\DB::table('user_profiles')->where('username', $handle)->exists()) {
            $handle = $baseHandle . $counter;
            $counter++;
        }

        return $handle;
    }

    /**
     * Validate the format of a handle.
     */
    public static function isValidHandleFormat(string $handle): bool
    {
        // Must start with @ and contain only alphanumeric and underscores/dots elsewhere
        return (bool) preg_match('/^@[a-zA-Z0-9._]+$/', $handle);
    }
}
