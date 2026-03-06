<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
  // GET /addresses
  public function index(Request $request)
  {
    $user = $request->user();

    $addresses = Address::where('user_id', $user->id)
      ->orderByDesc('is_default')
      ->orderByDesc('id')
      ->get();

    return response()->json([
      'status' => true,
      'data' => $addresses,
    ]);
  }

  // POST /addresses
  public function store(Request $request)
  {
    $user = $request->user();

    $data = $this->validateAddress($request);

    // If is_default true => make others false
    if (!empty($data['is_default'])) {
      Address::where('user_id', $user->id)->update(['is_default' => false]);
    }

    $address = Address::create(array_merge($data, [
      'user_id' => $user->id,
    ]));

    // If user has no default yet, make first address default
    if (!Address::where('user_id', $user->id)->where('is_default', true)->exists()) {
      $address->update(['is_default' => true]);
      $address->refresh();
    }

    return response()->json([
      'status' => true,
      'message' => 'Address created successfully.',
      'data' => $address,
    ], 201);
  }

  // GET /addresses/{id}
  public function show(Request $request, $id)
  {
    $user = $request->user();

    $address = Address::where('user_id', $user->id)->findOrFail($id);

    return response()->json([
      'status' => true,
      'data' => $address,
    ]);
  }

  // POST /addresses/{id}  (Update)
  public function update(Request $request, $id)
  {
    $user = $request->user();

    $address = Address::where('user_id', $user->id)->findOrFail($id);

    $data = $this->validateAddress($request, true);

    if (array_key_exists('is_default', $data) && $data['is_default']) {
      Address::where('user_id', $user->id)->update(['is_default' => false]);
    }

    $address->update($data);
    $address->refresh();

    return response()->json([
      'status' => true,
      'message' => 'Address updated successfully.',
      'data' => $address,
    ]);
  }

  // DELETE /addresses/{id}
  public function destroy(Request $request, $id)
  {
    $user = $request->user();

    $address = Address::where('user_id', $user->id)->findOrFail($id);

    $wasDefault = (bool) $address->is_default;

    $address->delete();

    // If deleted was default, set latest address as default (optional logic)
    if ($wasDefault) {
      $next = Address::where('user_id', $user->id)->orderByDesc('id')->first();
      if ($next) $next->update(['is_default' => true]);
    }

    return response()->json([
      'status' => true,
      'message' => 'Address deleted successfully.',
    ]);
  }

  private function validateAddress(Request $request, bool $isUpdate = false): array
  {
    $rules = [
      'label' => ['nullable', 'string', 'max:50'],
      'full_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
      'phone' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:20'],

      'address_line1' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
      'address_line2' => ['nullable', 'string', 'max:255'],
      'landmark' => ['nullable', 'string', 'max:100'],

      'city' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:80'],
      'state' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:80'],
      'country' => ['nullable', 'string', 'max:80'],
      'pincode' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:12'],

      'address_type' => ['nullable', Rule::in(['home', 'office', 'other'])],
      'is_default' => ['nullable', 'boolean'],
    ];

    return $request->validate($rules);
  }
}