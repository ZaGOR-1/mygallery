<?php

declare(strict_types=1);

// exif_fraction_to_float
assert_equals(1/250, exif_fraction_to_float('1/250'), '1/250 fraction parsing');
assert_equals(2.8, exif_fraction_to_float('28/10'), '28/10 fraction parsing');
assert_equals(35.0, exif_fraction_to_float('35/1'), '35/1 fraction parsing');

assert_equals(null, exif_fraction_to_float('abc'), 'Invalid fraction should return null');
assert_equals(null, exif_fraction_to_float('1/0'), 'Division by zero should return null');
assert_equals(null, exif_fraction_to_float(''), 'Empty string should return null');

// exif_display_value
assert_equals('value', exif_display_value('value'), 'exif_display_value normal string');
assert_equals('Немає даних', exif_display_value(''), 'exif_display_value empty string');
assert_equals('Немає даних', exif_display_value(null), 'exif_display_value null');

// format_aperture
assert_equals('f/2.8', format_aperture('28/10'), 'format_aperture 28/10');
assert_equals('Немає даних', format_aperture(''), 'format_aperture empty');

// format_focal_length
assert_equals('35 мм', format_focal_length('35/1'), 'format_focal_length 35/1');
assert_equals('Немає даних', format_focal_length(''), 'format_focal_length empty');

// format_exposure_time
assert_equals('1/250 с', format_exposure_time('1/250'), 'format_exposure_time 1/250');
assert_equals('2/1 с', format_exposure_time('2/1'), 'format_exposure_time 2/1');
assert_equals('Немає даних', format_exposure_time(''), 'format_exposure_time empty');
