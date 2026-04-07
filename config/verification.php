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
];
