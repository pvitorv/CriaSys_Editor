<?php

namespace App\Http\Controllers;

use App\Models\UserIntegration;
use App\Services\Tts\TtsVoiceCatalog;
use App\Support\ExternalHttp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    /**
     * Provedores que aceitam configuração por usuário.
     *
     * @var array<string, array{name: string, docs: string, voice_label: string, voice_hint: string}>
     */
    private const PROVIDERS = [
        'elevenlabs' => [
            'name' => 'ElevenLabs',
            'docs' => 'https://elevenlabs.io/app/developers',
            'billing' => 'https://elevenlabs.io/app/subscription',
            'voice_label' => 'Voz padrão (Voice ID)',
            'voice_hint' => 'Clone sua voz no painel da ElevenLabs; ela aparece automaticamente no seletor do editor.',
        ],
        'openai' => [
            'name' => 'OpenAI TTS',
            'docs' => 'https://platform.openai.com/api-keys',
            'billing' => 'https://platform.openai.com/settings/organization/billing/overview',
            'voice_label' => 'Voz padrão',
            'voice_hint' => 'Vozes: alloy, echo, fable, onyx (grave, boa para narração), nova, shimmer. Licença permite uso comercial (YouTube monetizado).',
        ],
    ];

    public function edit(): View
    {
        $user = auth()->user();

        $providers = [];
        foreach (self::PROVIDERS as $slug => $meta) {
            $integration = $user->integrationFor($slug);
            $configured = $integration?->hasApiKey() ?? false;

            $providers[$slug] = [
                'meta' => $meta,
                'configured' => $configured,
                'enabled' => $integration?->enabled ?? true,
                'default_voice' => $integration?->default_voice,
                'credits' => $configured
                    ? $this->creditsFor($slug, $integration->apiKey())
                    : null,
            ];
        }

        return view('integrations.edit', ['providers' => $providers]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function creditsFor(string $provider, ?string $apiKey): ?array
    {
        return match ($provider) {
            'elevenlabs' => $this->elevenLabsCredits($apiKey),
            'openai' => $this->openAiCredits($apiKey),
            default => null,
        };
    }

    /**
     * A OpenAI não expõe saldo para chaves secret (sk-...): o endpoint de saldo
     * só aceita session key do navegador. Fazemos a melhor tentativa e, quando
     * indisponível, retornamos unit=unavailable para o painel orientar o usuário.
     *
     * @return array<string, mixed>|null
     */
    private function openAiCredits(?string $apiKey): ?array
    {
        if (! $apiKey) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember(
            'tts:credits:openai:'.md5($apiKey),
            now()->addMinutes(5),
            function () use ($apiKey) {
                try {
                    $response = ExternalHttp::client(15)
                        ->withToken($apiKey)
                        ->get('https://api.openai.com/dashboard/billing/credit_grants');

                    if ($response->successful() && $response->json('total_granted') !== null) {
                        $granted = (float) $response->json('total_granted', 0);
                        $used = (float) $response->json('total_used', 0);
                        $remaining = $response->json('total_available') !== null
                            ? (float) $response->json('total_available')
                            : max(0, $granted - $used);

                        return [
                            'unit' => 'usd',
                            'granted' => $granted,
                            'used' => $used,
                            'remaining' => $remaining,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignora: cai no aviso de indisponível abaixo.
                }

                return ['unit' => 'unavailable'];
            }
        );
    }

    /**
     * @return array{unit: string, tier: string, used: int, limit: int, remaining: int}|null
     */
    private function elevenLabsCredits(?string $apiKey): ?array
    {
        if (! $apiKey) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember(
            'tts:credits:elevenlabs:'.md5($apiKey),
            now()->addMinutes(5),
            function () use ($apiKey) {
                try {
                    $response = ExternalHttp::client(15)
                        ->withHeaders(['xi-api-key' => $apiKey])
                        ->get('https://api.elevenlabs.io/v1/user/subscription');

                    if (! $response->successful()) {
                        return null;
                    }

                    $used = (int) $response->json('character_count', 0);
                    $limit = (int) $response->json('character_limit', 0);

                    return [
                        'unit' => 'chars',
                        'tier' => (string) $response->json('tier', 'desconhecido'),
                        'used' => $used,
                        'limit' => $limit,
                        'remaining' => max(0, $limit - $used),
                    ];
                } catch (\Throwable $e) {
                    return null;
                }
            }
        );
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'default_voice' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $user = auth()->user();
        $integration = $user->integrationFor($provider) ?? new UserIntegration([
            'user_id' => $user->id,
            'provider' => $provider,
        ]);
        $integration->user_id = $user->id;
        $integration->provider = $provider;

        $credentials = $integration->credentials ?? [];
        // Chave em branco = manter a existente (o campo vem vazio por segurança).
        if (! empty($data['api_key'])) {
            app(TtsVoiceCatalog::class)->forgetElevenLabsCache($credentials['api_key'] ?? null);
            $credentials['api_key'] = trim($data['api_key']);
        }

        $integration->credentials = $credentials;
        $integration->default_voice = $data['default_voice'] ?? null;
        $integration->enabled = (bool) ($data['enabled'] ?? true);
        $integration->save();

        return back()->with('success', self::PROVIDERS[$provider]['name'].' atualizado.');
    }

    public function destroy(string $provider): RedirectResponse
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);

        $user = auth()->user();
        $integration = $user->integrationFor($provider);
        if ($integration) {
            app(TtsVoiceCatalog::class)->forgetElevenLabsCache($integration->apiKey());
            $integration->delete();
        }

        return back()->with('success', self::PROVIDERS[$provider]['name'].' removido.');
    }

    public function test(string $provider): JsonResponse
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);

        $integration = auth()->user()->integrationFor($provider);
        $apiKey = $integration?->apiKey();

        if (! $apiKey) {
            return response()->json(['ok' => false, 'message' => 'Nenhuma chave salva. Salve a chave antes de testar.']);
        }

        try {
            if ($provider === 'elevenlabs') {
                $response = ExternalHttp::client(20)
                    ->withHeaders(['xi-api-key' => $apiKey])
                    ->get('https://api.elevenlabs.io/v1/voices');

                if ($response->successful()) {
                    $count = count($response->json('voices', []));

                    return response()->json(['ok' => true, 'message' => "Conectado. {$count} vozes disponíveis."]);
                }

                if ($response->json('detail.status') === 'missing_permissions') {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Chave válida, mas SEM permissão para listar vozes. Na ElevenLabs, marque "Has access to all" (ou habilite Voices: Read) ao criar a chave.',
                    ]);
                }

                return response()->json(['ok' => false, 'message' => 'Chave rejeitada pela ElevenLabs (HTTP '.$response->status().').']);
            }

            if ($provider === 'openai') {
                $response = ExternalHttp::client(20)
                    ->withToken($apiKey)
                    ->get('https://api.openai.com/v1/models');

                if ($response->successful()) {
                    return response()->json(['ok' => true, 'message' => 'Conectado à OpenAI.']);
                }

                return response()->json(['ok' => false, 'message' => 'Chave rejeitada pela OpenAI (HTTP '.$response->status().').']);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Erro ao conectar: '.$e->getMessage()]);
        }

        return response()->json(['ok' => false, 'message' => 'Provedor não suporta teste.']);
    }
}
