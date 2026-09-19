<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Web;

use Aster\Application\Service\ChatService;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Env;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * JSON endpoint behind the site assistant widget.
 *
 * Rate limited per IP for two reasons at once: a hosted model is metered, and
 * an open text box that reaches a paid API is otherwise a way to spend someone
 * else's credit. CSRF applies as it does to every other POST here - the widget
 * sends the token the layout already puts in a meta tag.
 *
 * The conversation is never stored. History arrives from the client, is used
 * for the one call, and is discarded; nothing about it reaches the database or
 * the log, because a visitor may well have typed a symptom into it.
 */
final class ChatController extends Controller
{
    /** Long enough for a real question, short enough to bound the request. */
    private const int MAX_MESSAGE = 600;

    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly ChatService $chat,
        private readonly RateLimiter $limiter,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function send(Request $request): Response
    {
        if (!$this->chat->isAvailable()) {
            return Response::json(['error' => 'unavailable'], 503);
        }

        $ip = $request->ip($this->config->trustProxy());

        if (!$this->limiter->attempt('chat:' . $ip, Env::int('RATE_CHAT_PER_HOUR', 40), 3600)) {
            return Response::json([
                'error' => 'rate_limited',
                'reply' => $this->view->translator->get('validation.rate_limited'),
            ], 429);
        }

        $message = trim($request->string('message'));

        if ($message === '') {
            return Response::json(['error' => 'empty'], 422);
        }

        $result = $this->chat->ask(
            mb_substr($message, 0, self::MAX_MESSAGE),
            $this->history($request),
            $this->currentLocale(),
        );

        return Response::json($result)->withoutCache();
    }

    /**
     * Prior turns, as posted by the widget.
     *
     * Treated as untrusted input rather than as state: the client could send
     * anything, so roles are normalised in ChatService and the whole thing is
     * length-capped here. Nothing is trusted about it beyond "these are words
     * to give the model as context".
     *
     * @return list<array{role:string, content:string}>
     */
    private function history(Request $request): array
    {
        $raw = json_decode($request->string('history', '[]'), true);

        if (!is_array($raw)) {
            return [];
        }

        $turns = [];

        foreach (array_slice($raw, -8) as $turn) {
            if (!is_array($turn) || !isset($turn['role'], $turn['content']) || !is_string($turn['content'])) {
                continue;
            }

            $content = trim($turn['content']);

            if ($content === '') {
                continue;
            }

            $turns[] = [
                'role'    => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => mb_substr($content, 0, self::MAX_MESSAGE),
            ];
        }

        return $turns;
    }
}
