<?php

return [
    'python' => env('PHOTO_QUALITY_PYTHON', 'python'),
    'timeout' => 12,
    // Starting thresholds require calibration with representative, consented photographs.
    'thresholds' => [
        'blur_min' => 60,
        'brightness_min' => 65,
        'brightness_max' => 235,
        'dark_fraction_max' => 0.35,
        'bright_fraction_max' => 0.45,
        'lighting_difference_max' => 55,
        'white_saturation_max' => 40,
        'white_value_min' => 205,
        'background_white_min' => 0.80,
        'background_std_max' => 25,
        'max_pixels' => 20000000,
    ],
];
