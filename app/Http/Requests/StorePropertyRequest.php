<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:8000'],
            'status'      => ['required', Rule::in(['for_sale', 'under_offer', 'sold', 'rental'])],
            'type'        => ['required', Rule::in(['house', 'apartment', 'townhouse', 'land', 'acreage'])],

            'price'       => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'price_label' => ['nullable', 'string', 'max:80'],
            'sold_price'  => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'sold_at'     => ['nullable', 'date'],
            'days_on_market' => ['nullable', 'integer', 'min:0', 'max:2000'],

            // The rental side is independent of the sale side, so one property
            // can be for sale, for rent, or both at the same time.
            'is_for_rent'      => ['boolean'],
            'rent_price'       => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'rent_price_label' => ['nullable', 'string', 'max:80'],
            'rent_period'      => ['nullable', Rule::in(['month', 'year'])],
            'rent_status'      => ['nullable', Rule::in(['available', 'let_agreed', 'let'])],

            'address'   => ['required', 'string', 'max:200'],
            'suburb'    => ['required', 'string', 'max:120'],
            'state'     => ['required', 'string', 'max:8'],
            'postcode'  => ['nullable', 'string', 'max:8'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'bedrooms'   => ['required', 'integer', 'min:0', 'max:50'],
            'bathrooms'  => ['required', 'integer', 'min:0', 'max:50'],
            'carspaces'  => ['required', 'integer', 'min:0', 'max:50'],
            'land_size'  => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'floor_size' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'features'          => ['nullable', 'string', 'max:1000'],
            'inspection_times'  => ['nullable', 'string', 'max:180'],
            'meta_description'  => ['nullable', 'string', 'max:180'],

            'is_featured'  => ['boolean'],
            'is_published' => ['boolean'],

            'images'   => ['nullable', 'array', 'max:20'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $isForRent = $this->boolean('is_for_rent');

        $this->merge([
            'is_featured'  => $this->boolean('is_featured'),
            'is_published' => $this->boolean('is_published'),
            'is_for_rent'  => $isForRent,
        ]);

        // When the rental side is off, drop its numbers so a stale rent figure
        // left in the form cannot linger on a property no longer let.
        if (! $isForRent) {
            $this->merge([
                'rent_price'       => null,
                'rent_price_label' => null,
                'rent_period'      => $this->input('rent_period') ?: 'month',
                'rent_status'      => 'available',
            ]);
        } else {
            $this->merge([
                'rent_period' => $this->input('rent_period') ?: 'month',
                'rent_status' => $this->input('rent_status') ?: 'available',
            ]);
        }
    }

    /**
     * Attributes ready for a mass assignment, with the comma-separated
     * feature list normalised into an array.
     */
    public function propertyAttributes(): array
    {
        $data = $this->safe()->except(['images', 'features']);

        $data['features'] = collect(explode(',', (string) $this->input('features')))
            ->map(fn($f) => trim($f))
            ->filter()
            ->values()
            ->all();

        return $data;
    }
}
