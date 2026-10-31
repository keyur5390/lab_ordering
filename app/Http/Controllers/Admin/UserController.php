<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PatientResource;
use App\Models\User;
use App\Services\UserManagementService;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Users (client, 2026-10-31): find users, see a customer's details, change roles, disable
 * accounts and invite staff.
 */
class UserController extends Controller
{
    public function __construct(private UserManagementService $users) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
        $pattern = isset($filters['q']) ? '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%' : null;

        return Inertia::render('admin/users/index', [
            // Wrapped so the page gets {data, links, meta} like other paginated lists.
            'users' => JsonResource::collection(User::query()
                ->when($pattern, fn ($query) => $query->where(fn ($q) => $q
                    ->whereRaw("lower(name) like ? escape '!'", [$pattern])
                    ->orWhereRaw("lower(email) like ? escape '!'", [$pattern])))
                ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
                ->when(($filters['status'] ?? null) === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
                ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->whereNull('disabled_at'))
                ->withCount('orders')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (User $user) => $this->summary($user))),
            'filters' => ['q' => $filters['q'] ?? '', 'role' => $filters['role'] ?? null, 'status' => $filters['status'] ?? null],
            'roles' => $this->roles(),
        ]);
    }

    public function show(Request $request, User $user, Settings $settings): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => [
                ...$this->summary($user->loadCount('orders')),
                'email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at->setTimezone($settings->timezone())->toDateString(),
                'is_self' => $request->user()->is($user),
            ],
            'patients' => PatientResource::collection($user->patients()->get()),
            'addresses' => AddressResource::collection($user->addresses()->get()),
            'orders' => OrderResource::collection($user->orders()->withCount('items')->latest('id')->limit(20)->get()),
            'roles' => $this->roles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/invite', [
            // Staff roles only; customers sign up themselves.
            'roles' => array_values(array_filter($this->roles(), fn (array $role) => $role['value'] !== UserRole::Customer->value)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)->except([UserRole::Customer])],
        ]);

        $user = $this->users->invite($validated['name'], $validated['email'], UserRole::from($validated['role']));

        return to_route('admin.users.show', $user)->with('status', "Invitation sent to {$user->email}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]])['role'];
        $this->users->changeRole($request->user(), $user, UserRole::from($role));

        return to_route('admin.users.show', $user)->with('status', "{$user->name} is now {$user->role->label()}.");
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        $this->users->disable($request->user(), $user);

        return to_route('admin.users.show', $user)->with('status', "{$user->name}'s account is disabled.");
    }

    public function enable(User $user): RedirectResponse
    {
        $this->users->enable($user);

        return to_route('admin.users.show', $user)->with('status', "{$user->name}'s account is enabled again.");
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'disabled' => $user->isDisabled(),
            'orders_count' => $user->orders_count,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roles(): array
    {
        return array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()], UserRole::cases());
    }
}
