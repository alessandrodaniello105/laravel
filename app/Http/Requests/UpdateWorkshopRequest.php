<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Workshop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Workshop $workshop */
        $workshop = $this->route('workshop');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('workshops', 'slug')->ignore($workshop->id)],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }
}
