<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }

    public function list()
    {
        return response()->json([
            'data' => User::select(['id', 'name', 'email', 'role', 'created_at'])->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'super_admin',
        ]);

        return response()->json(['message' => 'Administrador creado exitosamente']);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'  => 'required|in:super_admin,cliente',
        ]);

        $user->update($request->only(['name', 'email', 'role']));

        return response()->json(['message' => 'Usuario actualizado exitosamente']);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado exitosamente']);
    }
}
