<?php

namespace App\Models\Concerns;

use App\Models\Activity;

/**
 * Records create/update/delete of a model into the activity trail.
 *
 * Only actions performed by a signed-in user are logged — seeders, migrations
 * and console commands have nobody to attribute, so they are skipped.
 *
 * A model can override activityLabel() to describe itself, or activityEntry()
 * for full control over the wording (see Leave, which reports approvals and
 * rejections rather than a bare "updated").
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));
        static::updated(fn ($model) => $model->recordActivity('updated'));
        static::deleted(fn ($model) => $model->recordActivity('deleted'));
    }

    protected function recordActivity(string $verb): void
    {
        if (! auth()->hasUser()) {
            return;
        }

        [$action, $description] = $this->activityEntry($verb);

        Activity::log($action, $description, $this);
    }

    /**
     * The action key and sentence written to the trail.
     *
     * @return array{0: string, 1: string}
     */
    protected function activityEntry(string $verb): array
    {
        return $this->defaultActivityEntry($verb);
    }

    /**
     * The generic wording, available to models that override activityEntry().
     *
     * @return array{0: string, 1: string}
     */
    protected function defaultActivityEntry(string $verb): array
    {
        $type = $this->activityType();

        return [
            $type . '.' . $verb,
            auth()->user()->name . ' ' . $verb . ' ' . $type . ' "' . $this->activityLabel() . '"',
        ];
    }

    /**
     * Lower-case model name used in the action key, e.g. "leave".
     */
    protected function activityType(): string
    {
        return strtolower(class_basename($this));
    }

    /**
     * Short human label for this record.
     */
    public function activityLabel(): string
    {
        return (string) ($this->title ?? $this->name ?? ('#' . $this->getKey()));
    }
}
