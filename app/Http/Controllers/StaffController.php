<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStaffRequest;
use App\Http\Requests\UpdateStaffAccessRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staff = $request->user()->tenant->users()->where('role', 'staff')
            ->orderBy('name')->paginate(20, ['id', 'name', 'email', 'role', 'is_active', 'permissions']);

        return response()->json($staff);
    }

    public function store(CreateStaffRequest $request): JsonResponse
    {
        $user = $request->user()->tenant->users()->make($request->safe()->only(['name', 'email', 'password']));
        $user->role = 'staff';
        $user->permissions = $request->validated('permissions');
        $user->save();

        return response()->json(['staff' => $this->details($user)], 201);
    }

    public function update(UpdateStaffAccessRequest $request, string $staff): JsonResponse
    {
        // Scope before looking up the ID; owners and other shops are never targets.
        $user = $request->user()->tenant->users()->where('role', 'staff')->findOrFail($staff);
        $user->is_active = $request->boolean('is_active');
        $user->permissions = $request->validated('permissions');
        $user->save();

        return response()->json(['staff' => $this->details($user)]);
    }

    private function details(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'is_active', 'permissions']);
    }
}
