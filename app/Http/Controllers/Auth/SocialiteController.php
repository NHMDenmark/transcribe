<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use App\Models\User_categories;

class SocialiteController extends Controller
{
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback($provider)
    {
        try {
            // 1. Obter dados do serviço externo
            $socialUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            return redirect('/login')->with('error', 'Erro ao autenticar com ' . $provider);
        }

        // 2. Verificar se esta conta social já está registada
        $account = SocialAccount::where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account) {
            // Já existe? Faz login do dono da conta
            Auth::login($account->user);
            return redirect()->intended('/dashboard');
        }

        // 3. Se a conta social não existe, verificamos o email
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            // Utilizador totalmente novo: Criamos o User primeiro

            // the level a new person starts at
            $defaultCategory = \App\Models\User_categories::starting();

            $user = User::create([
        'name'     => $socialUser->getName() ?? $socialUser->getNickname(),
        'email'    => $socialUser->getEmail(),
        // Geramos uma password aleatória de 24 caracteres e encriptamos
        'password' => bcrypt(\Illuminate\Support\Str::random(24)),
        'category' => $defaultCategory ? $defaultCategory->id : null,
            ]);
        }

        // 4. Criamos a ligação entre o User e esta Rede Social
        $user->socialAccounts()->create([
            'provider_name' => $provider,
            'provider_id'   => $socialUser->getId(),
        ]);

        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}