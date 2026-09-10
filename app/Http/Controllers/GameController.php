<?php

namespace App\Http\Controllers;

use App\Game\MatchEngine;
use App\Models\GameRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class GameController extends Controller
{
    public function __construct(private MatchEngine $engine) {}

    public function home(Request $request): Response
    {
        return Inertia::render('Welcome', ['rules' => $this->engine->rules(), ...$this->characterProps($request)]);
    }

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'rules' => $this->engine->rules(),
            ...$this->characterProps($request),
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'passkeyCount' => Features::canManagePasskeys() ? $request->user()->passkeys()->count() : 0,
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        GameRoom::where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('Game', ['code' => strtoupper($code), ...$this->characterProps($request)]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:24'], 'character' => $this->characterRules($request)]);
        $room = $this->engine->create($this->identity($request), $data['name'], $this->selectedCharacter($request, $data));
        $this->rememberCharacter($request, $data);

        return response()->json(['code' => $room->code], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6', 'alpha_num:ascii'], 'name' => ['required', 'string', 'min:2', 'max:24'], 'character' => $this->characterRules($request)]);
        $room = $this->engine->join(strtoupper($data['code']), $this->identity($request), $data['name'], $this->selectedCharacter($request, $data));
        $this->rememberCharacter($request, $data);

        return response()->json(['code' => $room->code]);
    }

    public function state(Request $request, string $code): JsonResponse
    {
        return response()->json($this->engine->access(strtoupper($code), $this->identity($request)));
    }

    public function action(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:ready,start,night,vote,chat,rematch,character,discussion_ready'],
            'phase_id' => ['required', 'integer', 'min:1'],
            'target' => ['nullable', 'string', 'uuid'],
            'body' => ['nullable', 'string', 'max:280'],
            'character' => $this->characterRules($request),
        ]);

        if ($data['type'] === 'character') {
            abort_unless($request->user() !== null, 403, 'Sign in to choose your character.');
        }
        $state = $this->engine->access(strtoupper($code), $this->identity($request), $data);
        if ($data['type'] === 'character') {
            $this->rememberCharacter($request, $data);
        }

        return response()->json($state);
    }

    /** @return array<string, mixed> */
    private function characterProps(Request $request): array
    {
        return ['characters' => config('game.characters'), 'preferredCharacter' => $this->selectedCharacter($request, [])];
    }

    /** @return array<int, mixed> */
    private function characterRules(Request $request): array
    {
        return [Rule::prohibitedIf($request->user() === null), 'nullable', 'string', Rule::in(array_column(config('game.characters'), 'id'))];
    }

    /** @param array<string, mixed> $data */
    private function selectedCharacter(Request $request, array $data): ?string
    {
        if ($request->user() === null) {
            return null;
        }

        return $data['character'] ?? $request->session()->get('chanting.characters.'.$request->user()->getAuthIdentifier());
    }

    /** @param array<string, mixed> $data */
    private function rememberCharacter(Request $request, array $data): void
    {
        if ($request->user() !== null && isset($data['character'])) {
            $request->session()->put('chanting.characters.'.$request->user()->getAuthIdentifier(), $data['character']);
        }
    }

    // Guest seats use Laravel's encrypted session, rather than starter-kit accounts.
    public function broadcastAuth(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['socket_id' => ['required', 'regex:/\A[0-9]+\.[0-9]+\z/'], 'channel_name' => ['required', 'string']]);
        $room = GameRoom::where('code', strtoupper($code))->firstOrFail();
        abort_unless($this->engine->playerId($room->state, $this->identity($request)) !== null, 403);
        abort_unless($data['channel_name'] === 'private-room.'.$room->id, 403);

        return response()->json(Broadcast::connection('reverb')->validAuthenticationResponse($request, true));
    }

    private function identity(Request $request): string
    {
        if (! $request->session()->has('chanting.identity')) {
            $request->session()->put('chanting.identity', Str::random(64));
        }

        return $request->session()->get('chanting.identity');
    }
}
