<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationship->label(),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->date_of_birth?->age,
            'sex' => $this->sex?->value,
            'sex_label' => $this->sex?->label(),
            'phone' => $this->phone,
            'email' => $this->email,
            'is_self' => $this->isSelf(),
            'is_complete' => $this->isComplete(),
        ];
    }
}
