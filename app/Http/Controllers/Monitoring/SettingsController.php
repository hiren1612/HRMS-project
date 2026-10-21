<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The monitoring settings form.
 *
 * Until this screen existed every one of these values had to be changed by
 * editing the database by hand, which is both error-prone and invisible —
 * nobody could see who had turned screenshots on.
 *
 * The service owns the whitelist, the validation rules and the audit entry.
 * This controller is the form around it, and deliberately cannot write a key
 * the service has not declared editable.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    public function edit(): View
    {
        return view('monitoring.settings', [
            'values' => $this->settings->all(),
            'editable' => TrackingSettings::EDITABLE,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        // Build the rules from the service's own whitelist rather than
        // repeating them here, so the two cannot drift apart. Booleans come
        // off a checkbox, which sends nothing at all when unticked.
        foreach (TrackingSettings::EDITABLE as $key => $rule) {
            $field = $this->fieldName($key);

            $rules[$field] = $rule === 'boolean'
                ? ['nullable', 'boolean']
                : array_merge(['required'], explode('|', $rule));
        }

        $validated = $request->validate($rules);

        $changed = [];

        foreach (TrackingSettings::EDITABLE as $key => $rule) {
            $field = $this->fieldName($key);

            $value = $rule === 'boolean'
                ? $request->boolean($field)
                : $validated[$field];

            if ($rule !== 'boolean' && str_starts_with($rule, 'integer')) {
                $value = (int) $value;
            }

            // Only write what actually moved: an unchanged key should not
            // produce an audit entry saying somebody changed it.
            if ($this->settings->get($key) !== $value) {
                $this->settings->set($key, $value, $request->user()->id);
                $changed[] = $key;
            }
        }

        // Working hours that end before they start would silently discard
        // the whole day's activity, so catch it here rather than letting
        // somebody wonder why tracking stopped.
        if ($this->settings->get('working_hours.start') >= $this->settings->get('working_hours.end')) {
            return back()
                ->withErrors(['working_hours_start' => 'The working day must start before it ends.'])
                ->withInput();
        }

        return redirect()
            ->route('monitoring.settings')
            ->with('success', $changed === []
                ? 'No changes to save.'
                : count($changed) . ' ' . str('setting')->plural(count($changed)) . ' updated.');
    }

    /**
     * Dotted setting key to a form field name.
     *
     * "screenshots.interval_minutes" would be read by Laravel as nested
     * array input, so the form uses underscores and converts back here.
     */
    private function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
