<?php

namespace App\Models;

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person a customer books tests for: themselves or a family member (PROJECT.md §2).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property PatientRelationship $relationship
 * @property Carbon|null $date_of_birth
 * @property Sex|null $sex
 * @property string|null $phone
 * @property string|null $email
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * `user_id` is set through the user's relationship, never from input.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'relationship', 'date_of_birth', 'sex', 'phone', 'email'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => PatientRelationship::class,
            'date_of_birth' => 'date',
            'sex' => Sex::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSelf(): bool
    {
        return $this->relationship === PatientRelationship::Self;
    }

    /** Has the details a lab needs before it can be booked (date of birth and sex). */
    public function isComplete(): bool
    {
        return $this->date_of_birth !== null && $this->sex !== null;
    }
}
