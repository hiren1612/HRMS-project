<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A captured screen image.
 *
 * The row holds the location; the image lives on the private "monitoring"
 * disk. There is deliberately no url() method and no public path — the only
 * way to see one is through the controller that checks permission and
 * records who looked.
 */
class Screenshot extends Model
{
    use SoftDeletes;

    protected $table = 'employee_screenshots';

    /**
     * Mirrors the column defaults — see TrackingSession for why.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'monitoring',
        'status_at_capture' => 'active',
        'bytes' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'device_id',
        'session_id',
        'captured_at',
        'disk',
        'path',
        'thumbnail_path',
        'width',
        'height',
        'bytes',
        'status_at_capture',
        'checksum',
    ];

    /**
     * Hidden from any array/JSON form of the model. A storage path is not
     * secret in itself, but it has no business reaching a browser, and this
     * stops one leaking through a careless ->toJson() in a future endpoint.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk',
        'path',
        'thumbnail_path',
        'checksum',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TrackingDevice::class, 'device_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'session_id');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeCapturedBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('captured_at', [$from, $to]);
    }

    public function scopeOlderThan(Builder $query, \DateTimeInterface $cutoff): Builder
    {
        return $query->where('captured_at', '<', $cutoff);
    }

    public function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->disk);
    }

    public function fileExists(): bool
    {
        return $this->storage()->exists($this->path);
    }

    /**
     * Raw bytes, for streaming through the authorised controller.
     */
    public function contents(): ?string
    {
        return $this->fileExists() ? $this->storage()->get($this->path) : null;
    }

    public function thumbnailContents(): ?string
    {
        if ($this->thumbnail_path && $this->storage()->exists($this->thumbnail_path)) {
            return $this->storage()->get($this->thumbnail_path);
        }

        return $this->contents();
    }

    /**
     * Remove the image and its thumbnail from disk. Called by retention and
     * by force-deletion, so a purge never leaves orphaned files behind.
     */
    public function deleteFiles(): void
    {
        $disk = $this->storage();

        foreach (array_filter([$this->path, $this->thumbnail_path]) as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
    }

    public function sizeLabel(): string
    {
        return $this->bytes >= 1048576
            ? round($this->bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($this->bytes / 1024)) . ' KB';
    }
}
