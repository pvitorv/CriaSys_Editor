<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('username')->get();

        return view('admin.users.index', compact('users'));
    }

    public function pause(User $user): RedirectResponse
    {
        $this->protectSelf(auth()->user(), $user);

        $user->update(['status' => User::STATUS_PAUSED]);

        UserAlert::create([
            'from_user_id' => auth()->id(),
            'to_user_id' => $user->id,
            'subject' => 'Conta pausada',
            'message' => 'Sua conta foi pausada pelo administrador. Entre em contato para mais informações.',
        ]);

        return back()->with('success', "Usuário {$user->username} pausado.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['status' => User::STATUS_ACTIVE]);

        UserAlert::create([
            'from_user_id' => auth()->id(),
            'to_user_id' => $user->id,
            'subject' => 'Conta reativada',
            'message' => 'Sua conta foi reativada. Você já pode acessar o CriaSys Editor normalmente.',
        ]);

        return back()->with('success', "Usuário {$user->username} reativado.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->protectSelf(auth()->user(), $user);

        if ($user->isAdmin()) {
            return back()->withErrors(['admin' => 'Não é possível excluir um administrador.']);
        }

        $username = $user->username;
        $user->delete();

        return back()->with('success', "Usuário {$username} excluído.");
    }

    public function sendAlert(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        UserAlert::create([
            'from_user_id' => auth()->id(),
            'to_user_id' => $user->id,
            'subject' => $data['subject'] ?? 'Alerta do administrador',
            'message' => $data['message'],
        ]);

        return back()->with('success', "Alerta enviado para {$user->username}.");
    }

    private function protectSelf(User $admin, User $target): void
    {
        if ($admin->id === $target->id) {
            abort(403, 'Você não pode executar esta ação em sua própria conta.');
        }
    }
}
