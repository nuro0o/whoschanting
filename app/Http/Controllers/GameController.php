<?php

namespace App\Http\Controllers;

use App\Game\MatchEngine;
use App\Models\GameRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __construct(private MatchEngine $engine) {}

    public function home(): Response
    {
        return Inertia::render('Welcome', ['rules' => $this->engine->rules()]);
    }

    public function show(string $code): Response
    {
        GameRoom::where('code', strtoupper($code))->firstOrFail();

        return Inertia::render('Game', ['code' => strtoupper($code)]);
    }

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:24']]);
        $room = $this->engine->create($this->identity($request), $data['name']);

        return response()->json(['code' => $room->code], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6', 'alpha_num:ascii'], 'name' => ['required', 'string', 'min:2', 'max:24']]);
        $room = $this->engine->join(strtoupper($data['code']), $this->identity($request), $data['name']);

        return response()->json(['code' => $room->code]);
    }

    public function state(Request $request, string $code): JsonResponse
    {
        return response()->json($this->engine->access(strtoupper($code), $this->identity($request)));
    }

    public function action(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:ready,start,night,vote,chat,rematch'],
            'phase_id' => ['required', 'integer', 'min:1'],
            'target' => ['nullable', 'string', 'uuid'],
            'body' => ['nullable', 'string', 'max:280'],
        ]);

        return response()->json($this->engine->access(strtoupper($code), $this->identity($request), $data));
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
