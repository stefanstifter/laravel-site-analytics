<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Stifter\SiteAnalytics\ClickRecorder;

/**
 * Receives the clicks sent by <x-site-analytics::script />. Answers 204 whether or not the click was recorded,
 * so the browser learns nothing about the tracking rules, and 422 without a body for malformed requests.
 */
final class RecordClickController
{
    public function __invoke(Request $request, ClickRecorder $recorder): Response
    {
        $validator = Validator::make($request->only(['label', 'group', 'href', 'page']), [
            'label' => ['required', 'string', 'max:'.ClickRecorder::LABEL_LENGTH],
            'group' => ['nullable', 'string', 'max:'.ClickRecorder::GROUP_LENGTH],
            'href' => ['nullable', 'string', 'max:2048'],
            'page' => ['required', 'string', 'starts_with:/', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->noContent(422);
        }

        $recorder->record($request, $request->input('label'), $request->input('group'), $request->input('href'), $request->input('page'));

        return response()->noContent();
    }
}
