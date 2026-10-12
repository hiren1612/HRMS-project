<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The activity log (points #3 and #4).
 *
 * Admin and HR see the whole organisation's trail; everyone else sees only
 * their own actions.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $seesEveryone = $request->user()->hasAnyRole([User::ROLE_ADMIN, User::ROLE_HR]);

        $activities = Activity::with('user')
            ->when(! $seesEveryone, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->string('search') . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)
                    ->orWhere('action', 'like', $term));
            })
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action') . '%'))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latestFirst()
            ->paginate(25)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'seesEveryone' => $seesEveryone,
            // Distinct leading segments, e.g. "leave", "goal", "auth".
            'types' => Activity::query()
                ->when(! $seesEveryone, fn ($q) => $q->where('user_id', $request->user()->id))
                ->pluck('action')
                ->map(fn (string $a) => explode('.', $a)[0])
                ->unique()->sort()->values(),
            'people' => $seesEveryone
                ? User::orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }
}
