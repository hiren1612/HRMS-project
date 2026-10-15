<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * QA L2: announcements appear on every dashboard but had no screen to manage
 * them, so a notice could only be changed in the database. This is the
 * missing CRUD — it also closes L1, where a stale "Payroll date moved" notice
 * could not be removed.
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcements.index', [
            'announcements' => Announcement::latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('announcements.create');
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        Announcement::create($request->validated());

        return redirect()->route('announcements.index')->with('success', 'Announcement posted.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('announcements.edit', compact('announcement'));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->validated());

        return redirect()->route('announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement deleted.');
    }
}
