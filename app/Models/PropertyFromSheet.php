<?php

namespace App\Models;

use Illuminate\Support\Collection;

/**
 * Wrapper class to make Google Sheets property data compatible with Property model interface
 */
class PropertyFromSheet
{
    protected $attributes = [];
    protected $relations = [];

    public function __construct(array $data)
    {
        $this->attributes = $data;
    }

    /**
     * Get an attribute value
     */
    public function __get($key)
    {
        // Check relations first
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        // Check attributes
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        // Handle special accessors
        switch ($key) {
            case 'url':
                return $this->attributes['link'] ?? null;
            case 'formatted_price':
                return $this->getFormattedPriceAttribute();
            case 'formatted_status':
                return $this->getFormattedStatusAttribute();
            case 'photos_array':
                return $this->getPhotosArrayAttribute();
            case 'all_photos_array':
                return $this->getAllPhotosArrayAttribute();
            case 'high_quality_photos_array':
                return $this->getHighQualityPhotosArrayAttribute();
            case 'formatted_photos_array':
                return $this->getFormattedPhotosArrayAttribute();
        }

        return null;
    }

    /**
     * Set an attribute value
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if attribute exists
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /**
     * Set a relation
     */
    public function setRelation($name, $value)
    {
        $this->relations[$name] = $value;
    }

    /**
     * Get a relation
     */
    public function getRelation($name)
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * Check if relation exists
     */
    public function relationLoaded($name)
    {
        return isset($this->relations[$name]);
    }

    /**
     * Get formatted price
     */
    protected function getFormattedPriceAttribute()
    {
        $price = $this->attributes['price'] ?? null;
        
        if (empty($price) || $price === 'N/A' || $price === 'undefined' || $price === 'null') {
            return 'N/A';
        }
        
        if (is_array($price)) {
            return 'N/A';
        }
        
        if (is_string($price) && (strpos($price, '£') !== false || strpos($price, 'pcm') !== false)) {
            return $price;
        }
        
        if (is_numeric($price) && $price > 0) {
            // Whole pounds: "£1,100.00" is noise on a rent.
            return '£' . number_format($price, fmod((float) $price, 1) == 0 ? 0 : 2);
        }
        
        return 'N/A';
    }

    /**
     * Get formatted status
     */
    protected function getFormattedStatusAttribute()
    {
        $status = $this->attributes['status'] ?? null;
        
        if (empty($status) || $status === 'N/A' || $status === 'undefined' || $status === 'null') {
            return 'Available';
        }
        
        if (is_array($status)) {
            return 'Available';
        }
        
        return ucfirst($status);
    }

    /**
     * Get photos as array
     */
    protected function getPhotosArrayAttribute()
    {
        $photos = $this->attributes['photos'] ?? null;
        
        if (empty($photos)) {
            return [];
        }
        
        if (is_array($photos)) {
            return $photos;
        }
        
        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }

    /**
     * Get all photos as array
     */
    protected function getAllPhotosArrayAttribute()
    {
        $allPhotos = $this->attributes['all_photos'] ?? null;
        
        if (!empty($allPhotos)) {
            if (is_array($allPhotos)) {
                return $allPhotos;
            }
            
            if (is_string($allPhotos)) {
                $photos = explode(',', $allPhotos);
                return array_filter(array_map('trim', $photos), function($photo) {
                    return !empty($photo) && filter_var($photo, FILTER_VALIDATE_URL);
                });
            }
        }
        
        // Fallback to photos
        return $this->getPhotosArrayAttribute();
    }

    /**
     * Get high quality photos array
     */
    protected function getHighQualityPhotosArrayAttribute()
    {
        $photos = $this->getAllPhotosArrayAttribute();
        
        if (empty($photos)) {
            $photos = $this->getPhotosArrayAttribute();
        }
        
        $highQualityPhotos = [];
        
        foreach ($photos as $photo) {
            if (empty($photo)) continue;
            
            if (strpos($photo, 'spareroom.co.uk') !== false && strpos($photo, '/square/') !== false) {
                $highQualityPhotos[] = str_replace('/square/', '/large/', $photo);
            } else {
                $highQualityPhotos[] = $photo;
            }
        }
        
        return $highQualityPhotos;
    }

    /**
     * Get formatted photos array
     */
    protected function getFormattedPhotosArrayAttribute()
    {
        return $this->getPhotosArrayAttribute();
    }

    /**
     * Check if has valid coordinates
     */
    public function hasValidCoordinates(): bool
    {
        $lat = $this->attributes['latitude'] ?? null;
        $lng = $this->attributes['longitude'] ?? null;
        
        if (empty($lat) || empty($lng)) {
            return false;
        }
        
        try {
            $latFloat = (float) $lat;
            $lngFloat = (float) $lng;
            
            return (-90 <= $latFloat && $latFloat <= 90) && (-180 <= $lngFloat && $lngFloat <= 180);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all attributes as array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Convert to JSON
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert to string (returns ID for route model binding compatibility)
     */
    public function __toString()
    {
        return (string) ($this->attributes['id'] ?? '');
    }

    /**
     * Get the ID for route model binding
     */
    public function getRouteKey()
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get the route key name
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get the value of the model's primary key
     */
    public function getKey()
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get the name of the primary key
     */
    public function getKeyName()
    {
        return 'id';
    }

    /**
     * Magic method for property access
     */
    public function getAttribute($key)
    {
        return $this->__get($key);
    }

    /**
     * Magic method for setting attributes
     */
    public function setAttribute($key, $value)
    {
        $this->__set($key, $value);
    }

    /**
     * Check if property has agent
     */
    public function hasAgent(): bool
    {
        return !empty($this->attributes['agent_id']) || !empty($this->attributes['agent_name']);
    }

    /**
     * Get company color (static method compatibility)
     */
    public function getCompanyColor()
    {
        $company = $this->attributes['management_company'] ?? null;
        return Property::getCompanyColor($company);
    }
}
