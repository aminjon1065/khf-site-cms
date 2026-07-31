<?php

return [
    'avifenc_binary' => env('AVIFENC_BINARY', 'avifenc'),
    'avif_quality' => (int) env('AVIF_QUALITY', 50),
    'avif_alpha_quality' => (int) env('AVIF_ALPHA_QUALITY', 80),
    'avif_speed' => (int) env('AVIF_SPEED', 6),
];
