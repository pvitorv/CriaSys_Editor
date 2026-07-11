<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Senha atual incorreta.']);
        }

        $user->update(['email' => $data['email']]);

        return back()->with('success', 'E-mail atualizado com sucesso.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Senha atual incorreta.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Senha alterada com sucesso.');
    }

    public function updateCreator(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'youtube' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'url', 'max:500'],
            'tiktok' => ['nullable', 'url', 'max:500'],
            'website' => ['nullable', 'url', 'max:500'],
            'subscribe_cta' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = app(\App\Services\Creator\CreatorProfileService::class)->defaults();
        foreach ($data as $key => $value) {
            $profile[$key] = $value === '' ? null : $value;
        }

        $user->update(['creator_profile' => $profile]);

        return back()->with('success', 'Perfil de creator atualizado.');
    }
}
