<?php

/**
 * Helper functions for handling price values with precision
 */
class PriceHelper {
    /**
     * Safely converts a price value to a float with proper rounding
     * Handles strings, floats, and null values from database
     * 
     * @param mixed $value The price value (string, float, or null)
     * @param int $decimals Number of decimal places (default: 2)
     * @return float The rounded price value
     */
    public static function toFloat($value, $decimals = 2) {
        if ($value === null || $value === '') {
            return 0.0;
        }
        
        // If it's already a float, round it
        if (is_float($value)) {
            return round($value, $decimals);
        }
        
        // If it's a string, parse it carefully
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === 'null') {
                return 0.0;
            }
            $parsed = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($parsed === false) {
                return 0.0;
            }
            return round($parsed, $decimals);
        }
        
        // For numeric values, cast and round
        if (is_numeric($value)) {
            return round((float)$value, $decimals);
        }
        
        return 0.0;
    }
    
    /**
     * Formats a price value for JSON output
     * Ensures proper precision is maintained
     * 
     * @param mixed $value The price value
     * @param int $decimals Number of decimal places (default: 2)
     * @return float The formatted price value
     */
    public static function formatForJson($value, $decimals = 2) {
        return self::toFloat($value, $decimals);
    }
    
    /**
     * Gets the effective price (offer_price if available, otherwise price)
     * 
     * @param array $package Package data array
     * @return float The effective price
     */
    public static function getEffectivePrice($package) {
        $offerPrice = self::toFloat($package['offer_price'] ?? null);
        $regularPrice = self::toFloat($package['price'] ?? null);
        
        return $offerPrice > 0 ? $offerPrice : $regularPrice;
    }
}

