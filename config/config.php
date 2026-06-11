<?php

declare(strict_types=1);

return [
    'APP_NAME' => 'My Photo Gallery',
    'APP_URL' => 'http://mygallery',
    'APP_ENV' => 'local',
    'APP_DEBUG' => true,
    'UPLOAD_MAX_SIZE' => 30 * 1024 * 1024,
    'PHOTOS_PER_PAGE' => 12,
    'MAX_IMAGE_WIDTH' => 8000,
    'MAX_IMAGE_HEIGHT' => 8000,
    'MAX_IMAGE_PIXELS' => 50 * 1000 * 1000,
    'LARGE_MAX_WIDTH' => 2400,
    'LOGIN_MAX_ATTEMPTS' => 5,
    'LOGIN_LOCK_SECONDS' => 300,
];
