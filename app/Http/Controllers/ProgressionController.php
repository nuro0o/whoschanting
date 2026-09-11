<?php

namespace App\Http\Controllers;

use App\Game\AccountProgression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressionController extends Controller
{
    public function show(Request $request, AccountProgression $progression): Response
    {
        $data = $progression->view($request->user()->id);

        return Inertia::render('Progression', ['progression' => $data, 'characters' => $data['characters']]);
    }

    public function customize(Request $request, AccountProgression $progression): JsonResponse
    {
        $input = $request->validate(['title' => ['required', 'string', 'max:40'], 'frame' => ['required', 'string', 'max:40'],
            'accent' => ['required', 'string', 'max:40'], 'background' => ['sometimes', 'string', 'max:40'],
            'character' => ['nullable', 'string', 'max:40'], 'creator' => ['sometimes', 'nullable', 'array']]);
        $result = $progression->customize($request->user()->id, $input);
        $request->session()->forget('chanting.characters.'.$request->user()->id);

        return response()->json(['progression' => $result]);
    }
}
