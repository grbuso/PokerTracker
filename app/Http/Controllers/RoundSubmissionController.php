<?php

namespace App\Http\Controllers;

use App\Models\ChipSubmission;
use App\Models\Round;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RoundSubmissionController extends Controller
{
    public function index(Request $request, Round $round): Response
    {
        $round->load([
            'players' => fn ($query) => $query->orderBy('name'),
        ]);

        $dates = ChipSubmission::query()
            ->where('round_id', $round->id)
            ->whereNull('registered_at')
            ->distinct()
            ->orderByDesc('recorded_on')
            ->pluck('recorded_on')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->values();

        $selectedDate = $dates->first() ?? today()->toDateString();

        $requestedDate = $request->query('date');

        if (is_string($requestedDate) && $requestedDate !== '') {
            try {
                $selectedDate = Carbon::parse($requestedDate)->toDateString();
            } catch (\Throwable) {
                // Mantém a data padrão quando o valor da query é inválido.
            }
        }

        $submissions = ChipSubmission::query()
            ->where('round_id', $round->id)
            ->whereDate('recorded_on', $selectedDate)
            ->whereNull('registered_at')
            ->get(['user_id', 'chips', 'chip_counts', 'source', 'submitted_at']);

        return Inertia::render('rounds/Submissions', [
            'round' => $round,
            'dates' => $dates,
            'selectedDate' => $selectedDate,
            'submissions' => $submissions,
        ]);
    }
}
