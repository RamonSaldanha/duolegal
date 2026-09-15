<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvatarUpdateRequest extends FormRequest
{
    /**
     * O avatar é injetado no SVG com `v-html`, então o que chega aqui precisa ser
     * conferido peça por peça: id tem que estar na lista de config/avatar.php e
     * cor tem que ser exatamente `#rrggbb`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['avatar' => ['required', 'array']];

        foreach (config('avatar.shapes') as $key => $allowed) {
            $rules["avatar.{$key}"] = ['required', 'string', Rule::in($allowed)];
        }

        foreach (config('avatar.colors') as $key) {
            $rules["avatar.{$key}"] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.*.in' => 'Essa opção de avatar não existe.',
            'avatar.*.regex' => 'Cor inválida.',
        ];
    }
}
