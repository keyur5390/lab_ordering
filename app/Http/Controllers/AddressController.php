<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Support\Countries;
use App\Support\MapConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's addresses for home collection.
 */
class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('addresses/index', [
            'addresses' => AddressResource::collection($request->user()->addresses()->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('addresses/form', ['address' => null, ...$this->formOptions()]);
    }

    public function store(SaveAddressRequest $request): RedirectResponse
    {
        $request->user()->addresses()->create($request->validated());

        return to_route('addresses.index')->with('status', 'Address added.');
    }

    public function edit(Address $address): Response
    {
        Gate::authorize('update', $address);

        return Inertia::render('addresses/form', ['address' => AddressResource::make($address), ...$this->formOptions()]);
    }

    public function update(SaveAddressRequest $request, Address $address): RedirectResponse
    {
        $address->update($request->validated());

        return to_route('addresses.index')->with('status', 'Address saved.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return to_route('addresses.index')->with('status', 'Address removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'countries' => collect(Countries::all())->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name])->values(),
            'map' => MapConfig::toArray(),
        ];
    }
}
