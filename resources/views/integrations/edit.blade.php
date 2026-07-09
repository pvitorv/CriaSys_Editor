@extends('layouts.app')

@section('title', 'Integrações')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="integrationsPage()">
    <div>
        <h1 class="text-2xl font-semibold text-white">Integrações</h1>
        <p class="text-sm text-zinc-400 mt-1">
            Conecte seus próprios provedores de voz. As chaves ficam <strong>criptografadas</strong> e valem só para a sua conta.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-lg border border-zinc-800 bg-zinc-900/60 p-4 text-sm text-zinc-400">
        <p><strong class="text-zinc-200">Edge TTS</strong> (grátis) já vem ativo, sem chave. Para usar <strong class="text-zinc-200">sua própria voz</strong>, conecte a ElevenLabs abaixo, clone a voz no painel deles e ela aparecerá no seletor do editor.</p>
    </div>

    @foreach ($providers as $slug => $p)
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-white">{{ $p['meta']['name'] }}</h2>
                    <a href="{{ $p['meta']['docs'] }}" target="_blank" rel="noopener" class="text-xs text-violet-400 hover:text-violet-300">Obter chave de API →</a>
                </div>
                @if ($p['configured'])
                    <span class="text-xs px-2 py-1 rounded-full bg-emerald-900/50 text-emerald-300 border border-emerald-700">Configurado</span>
                @else
                    <span class="text-xs px-2 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700">Não configurado</span>
                @endif
            </div>

            @if (! empty($p['credits']))
                @php($c = $p['credits'])
                @php($pct = $c['limit'] > 0 ? min(100, round($c['used'] / $c['limit'] * 100)) : 0)
                @php($low = $c['limit'] > 0 && $c['remaining'] <= $c['limit'] * 0.15)
                <div class="rounded-lg bg-zinc-800/60 border border-zinc-700 p-3">
                    <div class="flex justify-between text-xs text-zinc-400 mb-1">
                        <span>Plano: <strong class="text-zinc-200">{{ $c['tier'] }}</strong></span>
                        <span><strong class="{{ $low ? 'text-red-400' : 'text-emerald-400' }}">{{ number_format($c['remaining'], 0, ',', '.') }}</strong> caracteres restantes</span>
                    </div>
                    <div class="h-2 rounded-full bg-zinc-700 overflow-hidden">
                        <div class="h-full {{ $low ? 'bg-red-500' : 'bg-violet-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-1">{{ number_format($c['used'], 0, ',', '.') }} de {{ number_format($c['limit'], 0, ',', '.') }} usados ({{ $pct }}%)</p>
                    @if ($low)
                        <p class="text-[11px] text-red-400 mt-1">Saldo baixo! Compre créditos ou ligue o <strong>Auto Top Up</strong> no painel da ElevenLabs.</p>
                    @endif
                </div>
            @endif

            @if (! empty($p['meta']['billing']))
                <div>
                    <a href="{{ $p['meta']['billing'] }}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-600/90 hover:bg-amber-500 text-sm text-white">
                        Comprar créditos / Gerenciar plano ↗
                    </a>
                    <span class="text-[11px] text-zinc-500 ml-2">A compra é feita no site do provedor (não há API de compra).</span>
                </div>
            @endif

            <form method="POST" action="{{ route('integrations.update', $slug) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-xs text-zinc-400">Chave de API (xi-api-key / API key)</label>
                    <input type="password" name="api_key" autocomplete="off"
                        placeholder="{{ $p['configured'] ? '•••••••• (salva — deixe em branco para manter)' : 'Cole sua chave aqui' }}"
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-xs text-zinc-400">{{ $p['meta']['voice_label'] }}</label>
                    <input type="text" name="default_voice" value="{{ $p['default_voice'] }}"
                        placeholder="opcional"
                        class="w-full mt-1 rounded bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm">
                    <p class="text-xs text-zinc-500 mt-1">{{ $p['meta']['voice_hint'] }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" name="enabled" value="1" @checked($p['enabled'])>
                    Ativa
                </label>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-sm">Salvar</button>

                    <button type="button" @click="test('{{ $slug }}')" :disabled="testing==='{{ $slug }}'"
                        class="px-4 py-2 rounded-lg bg-zinc-700 hover:bg-zinc-600 disabled:opacity-50 text-sm">
                        <span x-text="testing==='{{ $slug }}' ? 'Testando...' : 'Testar conexão'"></span>
                    </button>

                    @if ($p['configured'])
                        <button type="button"
                            @click="if (confirm('Remover a chave de {{ $p['meta']['name'] }}?')) $refs.del_{{ $slug }}.submit()"
                            class="px-4 py-2 rounded-lg text-sm text-red-400 hover:text-red-300">Remover</button>
                    @endif

                    <span x-show="result['{{ $slug }}']" x-cloak
                        :class="result['{{ $slug }}']?.ok ? 'text-emerald-400' : 'text-red-400'"
                        class="text-sm" x-text="result['{{ $slug }}']?.message"></span>
                </div>
            </form>

            @if ($p['configured'])
                <form method="POST" action="{{ route('integrations.destroy', $slug) }}" x-ref="del_{{ $slug }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>
    @endforeach
</div>

<script>
    function integrationsPage() {
        return {
            testing: null,
            result: {},
            async test(provider) {
                this.testing = provider;
                this.result[provider] = null;
                try {
                    const res = await fetch(`/integrations/${provider}/test`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    this.result[provider] = await res.json();
                } catch (e) {
                    this.result[provider] = { ok: false, message: 'Falha na requisição.' };
                } finally {
                    this.testing = null;
                }
            },
        };
    }
</script>
@endsection
