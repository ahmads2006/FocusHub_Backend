<?php

namespace App\Helpers;

class SecurityHelper
{
    /**
     * Obfuscate an email address (e.g. ahmad2006@gmail.com -> ah***@gmail.com).
     */
    public static function maskEmail(?string $email): ?string
    {
        if (empty($email) || strpos($email, '@') === false) {
            return $email;
        }

        list($first, $last) = explode('@', $email);
        $firstLength = strlen($first);
        
        if ($firstLength <= 2) {
            $maskedFirst = str_repeat('*', $firstLength);
        } else {
            $maskedFirst = substr($first, 0, 2) . str_repeat('*', max(1, $firstLength - 2));
        }

        return $maskedFirst . '@' . $last;
    }
    
    /**
     * Mask a phone number (e.g. +962791234567 -> +96279***4567).
     */
    public static function maskPhone(?string $phone): ?string
    {
        if (empty($phone) || strlen($phone) < 6) {
            return $phone;
        }
        
        $length = strlen($phone);
        $start = substr($phone, 0, 5);
        $end = substr($phone, -4);
        $stars = str_repeat('*', max(1, $length - 9));
        
        return $start . $stars . $end;
    }
}
