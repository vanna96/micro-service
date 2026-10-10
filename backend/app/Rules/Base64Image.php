<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Base64Image implements Rule
{
    private const MAX_BYTES = 4 * 1024 * 1024;
    private const MAX_PIXELS = 25_000_000;
    private const MAX_DIMENSION = 10_000;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (! is_string($value)
            || ! preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $value)
        ) {
            return false;
        }

        $encoded = substr($value, strpos($value, ',') + 1);
        if (strlen($encoded) > (int) ceil(self::MAX_BYTES * 4 / 3) + 4) {
            return false;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) > self::MAX_BYTES) {
            return false;
        }

        $size = @getimagesizefromstring($binary);
        if (! is_array($size)) {
            return false;
        }

        [$width, $height] = $size;

        return $width > 0
            && $height > 0
            && $width <= self::MAX_DIMENSION
            && $height <= self::MAX_DIMENSION
            && ($width * $height) <= self::MAX_PIXELS;
    }

    public function message()
    {
        return 'The :attribute must be a valid base64 encoded image.';
    }
}
