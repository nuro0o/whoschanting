<?php

namespace App\Http\Controllers;

use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Game\MusicLibrary;
use App\Game\RoomBrowser;
use App\Game\WhisperLibrary;
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
    public function __construct(private MatchEngine $engine, private AccountProgression $progression) {}

    public function home(Request $request): Response
    {
        return Inertia::render('Welcome', ['rules' => $this->engine->rules(), ...$this->characterProps($request)]);
    }

    public function browser(): Response
    {
        return Inertia::render('RoomBrowser');
    }

    public function publicRooms(Request $request, RoomBrowser $browser): JsonResponse
    {
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000']]);

        return response()->json($browser->listing((int) ($data['page'] ?? 1)));
    }

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'rules' => $this->engine->rules(),
            'progression' => $this->progression->view($request->user()->id),
            ...$this->characterProps($request),
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'passkeyCount' => Features::canManagePasskeys() ? $request->user()->passkeys()->count() : 0,
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $room = GameRoom::where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('Game', [
            'code' => strtoupper($code),
            'pinRequired' => isset($room->state['pin_hash']),
            'music' => (new MusicLibrary)->tracks(public_path('assets/Music')),
            'whispers' => (new WhisperLibrary)->tracks(public_path('assets/Sounds/Whispers')),
            ...$this->characterProps($request),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:24'], 'visibility' => ['sometimes', 'string', 'in:private,public'], 'pin' => $this->pinRules(), 'character' => $this->characterRules($request), ...$this->modeRules()]);
        $room = $this->engine->create($this->identity($request), $data['name'], $this->selectedCharacter($request, $data), $data['setup'] ?? [], $request->user()?->id, $data['pin'] ?? null, $data['visibility'] ?? 'private', ipAddress: $request->ip() ?? '');
        $this->engine->presence($room->code, $this->identity($request), 'pending', accountId: $request->user()?->id);
        $this->rememberCharacter($request, $data);

        return response()->json(['code' => $room->code], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6', 'alpha_num:ascii'], 'name' => ['required', 'string', 'min:2', 'max:24'], 'pin' => $this->pinRules(), 'character' => $this->characterRules($request)]);
        $room = $this->engine->join(strtoupper($data['code']), $this->identity($request), $data['name'], $this->selectedCharacter($request, $data), $request->user()?->id, $data['pin'] ?? null, ipAddress: $request->ip() ?? '');
        $this->engine->presence($room->code, $this->identity($request), 'pending', accountId: $request->user()?->id);
        $this->rememberCharacter($request, $data);

        return response()->json(['code' => $room->code]);
    }

    public function state(Request $request, string $code): JsonResponse
    {
        return response()->json($this->engine->access(strtoupper($code), $this->identity($request), accountId: $request->user()?->id));
    }

    public function presence(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ping,disconnect,leave,here,end_room'],
            'client_id' => ['required', 'uuid'],
        ]);

        return response()->json($this->engine->presence(strtoupper($code), $this->identity($request), $data['client_id'], $data['type'], $request->user()?->id));
    }

    public function action(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:ready,start,night,vote,chat,rematch,character,discussion_ready,solve_curse,roster,exorcise,oath,configure_mode,claim,discussion_response,prediction,transfer_host,remove_player,extend_discussion,feedback,accuse,defend,set_pin'],
            'pin' => ['present_if:type,set_pin', ...$this->pinRules()],
            'phase_id' => ['required', 'integer', 'min:1'],
            'client_id' => ['sometimes', 'uuid'],
            'target' => ['nullable', 'string', 'uuid'],
            'use_ability' => ['sometimes', 'boolean'],
            'roster' => ['sometimes', 'string', 'in:classic,illusions'],
            'forged_alignment' => ['sometimes', 'string', 'in:town,cult'],
            'curse_type' => ['nullable', 'string', 'in:puzzle,mist,misdirection'],
            'body' => ['nullable', 'string', 'max:280'],
            'role' => ['required_if:type,claim', 'string', Rule::in(array_keys(config('game.role_alignments')))],
            'cultist_ids' => ['present_if:type,prediction', 'array', 'list', 'max:'.config('game.max_players')],
            'cultist_ids.*' => ['required', 'string', 'uuid', 'distinct'],
            'winner' => ['required_if:type,prediction', 'string', 'in:town,cult'],
            'engagement' => ['required_if:type,feedback', 'string', 'in:engaged,mixed,waiting'],
            'curse_id' => ['required_if:type,solve_curse', 'uuid'],
            'answer' => ['required_if:type,solve_curse', 'array', 'list', 'max:12'],
            'answer.*' => ['required', 'string', 'uuid'],
            'character' => $this->characterRules($request),
            ...$this->modeRules(),
        ]);

        if ($data['type'] === 'character') {
            abort_unless($request->user() !== null, 403, 'Sign in to choose your character.');
        }
        $state = $this->engine->access(strtoupper($code), $this->identity($request), $data, $request->user()?->id);
        if ($data['type'] === 'character') {
            $this->rememberCharacter($request, $data);
        }

        return response()->json($state);
    }

    /** @return list<string> */
    private function pinRules(): array
    {
        return ['nullable', 'string', 'regex:/\A[0-9]{4,8}\z/'];
    }

    /** @return array<string, mixed> */
    private function modeRules(): array
    {
        return [
            'setup' => ['sometimes', 'array:mode,classic_variant,chaos_variant,roles'],
            'setup.mode' => ['sometimes', 'string', 'in:classic,hard,chaos,paranoia,custom'],
            'setup.classic_variant' => ['sometimes', 'string', 'in:classic,illusions'],
            'setup.chaos_variant' => ['sometimes', 'string', 'in:wildcards,maelstrom'],
            'setup.roles' => ['sometimes', 'array', 'max:'.count(config('game.role_alignments'))],
            'setup.roles.*' => ['integer', 'min:0', 'max:'.config('game.max_players')],
        ];
    }

    /** @return array<string, mixed> */
    private function characterProps(Request $request): array
    {
        return ['characters' => $this->progression->characterCatalog($request->user()?->id), 'preferredCharacter' => $this->selectedCharacter($request, [])];
    }

    /** @return array<int, mixed> */
    private function characterRules(Request $request): array
    {
        $available = array_filter($this->progression->characterCatalog($request->user()?->id), fn (array $character): bool => $character['unlocked']);

        return [Rule::prohibitedIf($request->user() === null), 'nullable', 'string', Rule::in(array_column($available, 'id'))];
    }

    /** @param array<string, mixed> $data */
    private function selectedCharacter(Request $request, array $data): ?string
    {
        if ($request->user() === null) {
            return null;
        }

        if (isset($data['character'])) {
            return $data['character'];
        }
        $character = $this->progression->preferredCharacter($request->user()->id)
            ?? $request->session()->get('chanting.characters.'.$request->user()->getAuthIdentifier());

        return is_string($character) && $this->progression->canUseCharacter($request->user()->id, $character) ? $character : null;
    }

    /** @param array<string, mixed> $data */
    private function rememberCharacter(Request $request, array $data): void
    {
        if ($request->user() !== null && isset($data['character'])) {
            $this->progression->rememberCharacter($request->user()->id, $data['character']);
            $request->session()->put('chanting.characters.'.$request->user()->getAuthIdentifier(), $data['character']);
        }
    }

    // Guest seats use Laravel's encrypted session, rather than starter-kit accounts.
    public function broadcastAuth(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['socket_id' => ['required', 'regex:/\A[0-9]+\.[0-9]+\z/'], 'channel_name' => ['required', 'string']]);
        $room = GameRoom::where('code', strtoupper($code))->firstOrFail();
        abort_unless($this->engine->playerId($room->state, $this->identity($request), $request->user()?->id) !== null, 403);
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
