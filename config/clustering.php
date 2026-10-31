<?php
/** Configurable, explainable incident-clustering thresholds. */
return [
    'max_distance_meters' => (float)(getenv('CLUSTER_MAX_DISTANCE_METERS') ?: 650),
    'max_time_diff_hours' => (int)(getenv('CLUSTER_MAX_TIME_HOURS') ?: 72),
    'min_similarity_score' => (float)(getenv('CLUSTER_MIN_SCORE') ?: 65),
    // Category compatibility is intentionally explicit. Add category ID pairs only
    // when two categories can describe the same underlying event.
    'compatible_categories' => [],
    'weights' => ['category_match' => 30, 'distance_score' => 25, 'time_score' => 15, 'text_similarity' => 20, 'neighbourhood_match' => 10],
    'stopwords' => ['the','is','at','which','on','a','an','and','or','in','near','to','for','of','with','from','by','it','was','are','this','that','there','please','very','has','have','been','about','some','out','up','down','also','water','issue','problem'],
];
