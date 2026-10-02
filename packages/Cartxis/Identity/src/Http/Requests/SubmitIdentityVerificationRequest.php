<?php

namespace Cartxis\Identity\Http\Requests;

use Cartxis\Identity\Services\IdentityConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a customer is allowed to send.
 *
 * The rules are about what the file IS, not what it is called: the mime type is
 * checked against the file's own bytes by the image service, and the extension
 * that ends up on disk is derived from that, so a renamed .php never becomes a
 * .php. Size is checked twice as well, once here for a friendly message and
 * once in the service, because the service is reachable from anywhere and must
 * not depend on this class having run.
 */
class SubmitIdentityVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $config = app(IdentityConfig::class);

        $maxKb = $config->maxUploadKb();
        $maxDimension = $config->maxDimension();

        return [
            'national_id' => [
                'required',
                'string',
                'min:4',
                'max:32',
                // Afghan IDs are written with spaces and dashes; the fingerprint
                // folds them away, and this only keeps out obvious rubbish.
                'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{2,30}[A-Za-z0-9]$/',
            ],
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                "max:{$maxKb}",
                Rule::file()
                    ->types($this->allowedExtensions())
                    ->max($maxKb * 1024),
                // Blocks a tiny file that decodes into an enormous image, which
                // is how an upload takes a worker out of memory.
                "dimensions:max_width={$maxDimension},max_height={$maxDimension}",
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'national_id' => 'national ID number',
            'full_name' => 'full name',
            'father_name' => "father's name",
            'date_of_birth' => 'date of birth',
            'image' => 'document photo',
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.required' => __('Enter the number printed on your Tazkira.'),
            'national_id.regex' => __('That does not look like a Tazkira number. Type the digits as printed, without the word Tazkira.'),
            'full_name.required' => __('Enter your full name exactly as it is written on the Tazkira.'),
            'image.required' => __('Add a photo of your Tazkira so our team can check it.'),
            'image.mimes' => __('The document photo must be a JPG, PNG or WebP image.'),
            'image.max' => __('The document photo is too large. Please upload a smaller image.'),
        ];
    }

    /**
     * A clean submission only carries what the service reads. Anything else the
     * form posts is dropped rather than passed along.
     */
    public function submission(): array
    {
        return $this->safe()->only(['national_id', 'full_name', 'father_name', 'date_of_birth']);
    }

    /**
     * @return array<int, string>
     */
    protected function allowedExtensions(): array
    {
        $mimes = app(IdentityConfig::class)->allowedMimes();

        $extensions = [];

        foreach ($mimes as $mime) {
            $extensions[] = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        return array_values(array_unique($extensions));
    }
}