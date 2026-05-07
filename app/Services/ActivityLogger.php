<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log an activity.
     *
     * @param string $event The event name (created, updated, deleted, etc.)
     * @param string|null $description A human-readable description
     * @param Model|null $subject The model being acted upon
     * @param array|null $properties Additional data or changes
     * @return ActivityLog
     */
    public static function log($event, $description = null, $subject = null, $properties = null)
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => Request::ip(),
        ]);
    }
}
