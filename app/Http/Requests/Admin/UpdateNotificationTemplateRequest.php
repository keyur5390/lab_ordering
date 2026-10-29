<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isMail = $this->template()->channel === NotificationChannel::Mail;

        return [
            'subject' => $isMail ? ['required', 'string', 'max:200', $this->knownPlaceholders()] : ['prohibited'],
            // TODO(client): SMS over 160 characters is sent as several messages (and costs more).
            'body' => ['required', 'string', $isMail ? 'max:5000' : 'max:640', $this->knownPlaceholders()],
            'is_active' => ['boolean'],
        ];
    }

    private function knownPlaceholders(): Closure
    {
        $allowed = $this->template()->event->placeholders();

        return function (string $attribute, mixed $value, Closure $fail) use ($allowed) {
            preg_match_all('/\{([a-z_]+)\}/', (string) $value, $matches);
            $unknown = array_diff($matches[1], $allowed);
            if ($unknown !== []) {
                $fail('Unknown placeholder: {'.implode('}, {', $unknown).'}.');
            }
        };
    }

    private function template(): NotificationTemplate
    {
        return $this->route('template');
    }
}
