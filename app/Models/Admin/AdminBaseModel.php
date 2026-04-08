<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class AdminBaseModel extends BaseModel
{
    protected $connection = 'pgsql';

    const CREATED_AT = 'create_ts';
    const UPDATED_AT = 'last_update_ts';

    protected $guarded = [];

    protected $excludeFromUtcConversion = ['create_ts', 'last_update_ts', 'delete_ts'];

    protected $casts = [
        'create_ts' => 'datetime',
        'last_update_ts' => 'datetime',
        'delete_ts' => 'datetime',
    ];

    public function getFullTableName(): string
    {
        return $this->getConnection()->getTablePrefix() . $this->getTable();
    }

    protected function asDateTime($value)
    {
        if ($value === null || $value === '-infinity' || $value === 'infinity') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('UTC');
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone('UTC');
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, 'UTC');
        }

        return Carbon::parse($value, 'UTC');
    }

    public function setAttribute($key, $value)
    {
        if (
            !in_array($key, $this->excludeFromUtcConversion, true)
            && isset($this->casts[$key])
            && in_array($this->casts[$key], ['datetime', 'datetime:Y-m-d H:i:s'], true)
            && $value !== null
        ) {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $now = Carbon::now('Asia/Seoul');
                $value = $value . ' ' . $now->format('H:i:s');
            }

            $value = Carbon::parse($value, 'Asia/Seoul')
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        }

        return parent::setAttribute($key, $value);
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->format('Y-m-d H:i:s');
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        $datetimeFields = array_keys(array_filter(
            $this->getCasts(),
            fn($cast) => in_array($cast, ['datetime', 'datetime:Y-m-d H:i:s'], true)
        ));

        foreach ($datetimeFields as $field) {
            if (!empty($attributes[$field])) {
                $attributes[$field] = Carbon::parse($attributes[$field], 'UTC')
                    ->setTimezone('Asia/Seoul')
                    ->format('Y-m-d H:i:s');
            }
        }

        return $attributes;
    }
}
