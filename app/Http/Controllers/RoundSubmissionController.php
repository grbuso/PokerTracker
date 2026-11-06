<?php

namespace App\Http\Controllers;

use App\Models\ChipSubmission;
use App\Models\Round;
use Illuminate\Http\Request;
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
            ->values();

        $selectedDate = $request->query('date')
            ?? $dates->first()
            ?? today()->toDateString();

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
