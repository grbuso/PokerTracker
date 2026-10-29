<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoundRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_chips' => ['required', 'integer', 'min:1'],
            'chips_per_player' => ['required', 'integer', 'min:1'],
            'user_ids' => ['required', 'array', 'min:2'],
            'user_ids.*' => ['exists:users,id'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-game') ?? false;
    }
}
