<?php

namespace App\Domain\Assistant;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Groq, Cloudflare Workers AI, OpenAI and Google Gemini through their OpenAI-compatible
 * Chat Completions endpoints. Providers are tried in the owner's chosen order; the first one
 * that answers wins. Keys are stored encrypted in settings. Model names change often, so they
 * are editable in Admin › AI assistant (defaults below were current in October 2026).
 */
class AiProviders
{
    public const PROVIDERS = [
        'groq' => ['label' => 'Groq', 'base' => 'https://api.groq.com/openai/v1', 'model' => 'openai/gpt-oss-120b', 'keys' => 'console.groq.com › API Keys'],
        'cloudflare' => ['label' => 'Cloudflare Workers AI', 'base' => 'https://api.cloudflare.com/client/v4/accounts/{account}/ai/v1', 'model' => '@cf/openai/gpt-oss-120b', 'keys' => 'dash.cloudflare.com › AI › Workers AI › Use REST API (token with Workers AI permission) + Account ID'],
        'openai' => ['label' => 'OpenAI', 'base' => 'https://api.openai.com/v1', 'model' => '', 'keys' => 'platform.openai.com › API keys. Enter a current model id from platform.openai.com/docs/models'],
        'gemini' => ['label' => 'Google Gemini', 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'model' => 'gemini-3.8-flash', 'keys' => 'aistudio.google.com › Get API key'],
    ];

    public function __construct(private SettingsRepository $settings) {}

    public function model(string $p): string
    {
        return (string) ($this->settings->get("ai.$p.model") ?: self::PROVIDERS[$p]['model']);
    }

    public function configured(string $p): bool
    {
        return (bool) $this->settings->get("ai.$p.key") && $this->model($p) !== '' && ($p !== 'cloudflare' || $this->settings->get('ai.cloudflare.account_id'));
    }

    /** Enabled providers in fallback order. */
    public function order(): array
    {
        $order = array_filter(explode(',', (string) $this->settings->get('ai.order', 'groq,gemini,cloudflare,openai')));

        return array_values(array_filter(array_unique($order), fn ($p) => isset(self::PROVIDERS[$p]) && $this->settings->get("ai.$p.enabled") && $this->configured($p)));
    }

    public function available(): bool
    {
        return (bool) $this->settings->get('ai.enabled') && $this->order() !== [];
    }

    private function url(string $p): string
    {
        $base = self::PROVIDERS[$p]['base'];
        if ($p === 'cloudflare') {
            $acct = (string) $this->settings->get('ai.cloudflare.account_id');
            if (! preg_match('/^[a-f0-9]{32}$/', $acct)) {
                throw new RuntimeException('Cloudflare account ID looks wrong.');
            }
            $base = str_replace('{account}', $acct, $base);
        }

        return $base.'/chat/completions';
    }

    /**
     * One provider call. Returns [text, tokens_in, tokens_out, latency_ms].
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function call(string $p, array $messages, int $maxTokens = 700): array
    {
        $t0 = hrtime(true);
        $res = Http::withToken((string) $this->settings->get("ai.$p.key"))->acceptJson()->timeout(25)->connectTimeout(5)
            ->post($this->url($p), ['model' => $this->model($p), 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => $maxTokens]);
        if (! $res->successful()) {
            $err = $res->json('error.message') ?? $res->json('errors.0.message') ?? $res->json('0.error.message') ?? ('HTTP '.$res->status());
            throw new RuntimeException(self::PROVIDERS[$p]['label'].': '.mb_substr((string) $err, 0, 200));
        }
        $text = trim((string) $res->json('choices.0.message.content'));
        if ($text === '') {
            throw new RuntimeException(self::PROVIDERS[$p]['label'].': empty reply');
        }

        return [$text, $res->json('usage.prompt_tokens'), $res->json('usage.completion_tokens'), (int) ((hrtime(true) - $t0) / 1e6)];
    }

    /**
     * Tries providers in order. Returns [provider, model, text, tokens_in, tokens_out, latency_ms].
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): array
    {
        $errors = [];
        foreach ($this->order() as $p) {
            try {
                [$text, $in, $out, $ms] = $this->call($p, $messages);

                return [$p, $this->model($p), $text, $in, $out, $ms];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                report($e);
            }
        }
        throw new RuntimeException('No AI provider answered. '.implode(' | ', $errors));
    }
}
