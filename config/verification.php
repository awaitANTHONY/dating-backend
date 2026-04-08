<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure verification system behavior, including AI model selection,
    | confidence thresholds, and approval automation.
    |
    */

    'model' => env('VERIFICATION_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    |
    | control automatic approval/rejection and manual review queue:
    | - >= auto_approve: Immediately approve verification
    | - Between manual_review and auto_approve: Queue for manual admin review
    | - < manual_review: Automatically reject verification
    |
    */
    'confidence_thresholds' => [
        'auto_approve' => env('VERIFICATION_AUTO_APPROVE_THRESHOLD', 0.95),
        'manual_review' => env('VERIFICATION_MANUAL_REVIEW_THRESHOLD', 0.85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Liveness & Face Match Thresholds (for VerificationService)
    |--------------------------------------------------------------------------
    |
    | Minimum confidence scores required for passing individual checks.
    |
    */
    'liveness_threshold' => env('VERIFICATION_LIVENESS_THRESHOLD', 0.75),
    'face_match_threshold' => env('VERIFICATION_FACE_MATCH_THRESHOLD', 0.70),

    /*
    |--------------------------------------------------------------------------
    | Admin Queue Notification Settings
    |--------------------------------------------------------------------------
    |
    | Notification behavior when verifications are queued for manual review.
    |
    */
    'notify_admin_on_queue' => env('VERIFICATION_NOTIFY_ADMIN', true),

    /*
    |--------------------------------------------------------------------------
    | Attempt Limits & Abuse Protection
    |--------------------------------------------------------------------------
    |
    | Controls how many times a user may fail verification before restrictions
    | are applied. All values are configurable via .env.
    |
    | - cooldown_after: Rejections before a cooldown period is imposed
    | - ban_after:      Total rejections before the account is permanently banned
    | - cooldown_days:  How many days the cooldown lasts
    |
    */
    'attempt_limits' => [
        'cooldown_after' => env('VERIFICATION_COOLDOWN_AFTER', 3),
        'ban_after'      => env('VERIFICATION_BAN_AFTER', 6),
        'cooldown_days'  => env('VERIFICATION_COOLDOWN_DAYS', 7),
    ],
];
