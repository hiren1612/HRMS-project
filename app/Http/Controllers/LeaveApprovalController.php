<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Notifications\LeaveProcessed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveApprovalController extends Controller
{
    /**
     * List pending leave requests awaiting a decision.
     * Route is already restricted to admin/hr/manager via middleware.
     */
    public function index(): View
    {
        $pendingLeaves = Leave::with('employee.user')
            ->where('status', Leave::STATUS_PENDING)
            ->oldest()        // oldest requests first (FIFO)
            ->paginate(10);

        return view('leaves.manage', compact('pendingLeaves'));
    }

    /**
     * Approve a pending leave request.
     */
    public function approve(Leave $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        $leave->update(['status' => Leave::STATUS_APPROVED]);

        // Notify the employee of the decision.
        $leave->employee->user->notify(new LeaveProcessed($leave));

        return back()->with('success', 'Leave request approved.');
    }

    /**
     * Reject a pending leave request.
     */
    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        $this->authorize('reject', $leave);

        // QA M3: the employee is told why, not just that it was refused.
        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:500'],
        ], [], ['decision_note' => 'reason']);

        $leave->update([
            'status' => Leave::STATUS_REJECTED,
            'decision_note' => $validated['decision_note'],
        ]);

        // Notify the employee of the decision.
        $leave->employee->user->notify(new LeaveProcessed($leave));

        return back()->with('success', 'Leave request rejected.');
    }

    /**
     * QA M4: let the applicant withdraw a request that has not been decided.
     * Only the owner, and only while it is still pending.
     */
    public function cancel(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless($leave->employee_id === $request->user()->employee?->id, 403);
        abort_unless($leave->isPending(), 403, 'Only a pending request can be cancelled.');

        $leave->update(['status' => Leave::STATUS_CANCELLED]);

        return back()->with('success', 'Leave request cancelled.');
    }
}
