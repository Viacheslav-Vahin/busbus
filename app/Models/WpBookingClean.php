<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WpBookingClean extends Model
{
    protected $table = 'wp_bookings_clean';
    protected $primaryKey = 'id';
    public $incrementing = false; // це VIEW
    public $timestamps = false;   // у VIEW є поля часу, але Eloquent їх не оновлює

    // Масив полів для масового присвоєння не потрібен — ми лише читаємо

    /** Базові скопи для фільтрів у списку */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.mb_strtolower(trim($term), 'UTF-8').'%';
        return $q->where(function($qq) use ($like) {
            $qq->whereRaw('LOWER(passenger_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(passenger_email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(passenger_email_effective) LIKE ?', [$like])
                ->orWhereRaw('LOWER(booked_by_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(booked_by_email) LIKE ?', [$like])
                ->orWhere('passenger_phone', 'like', $like)
                ->orWhere('wp_id', 'like', $like)
                ->orWhere('wp_order_id', 'like', $like);
        });
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('order_status', $status) : $q;
    }

    public function scopeBookerType(Builder $q, ?string $type): Builder
    {
        return $type ? $q->where('booker_type', $type) : $q;
    }

    public function scopeDateBetween(Builder $q, ?string $from, ?string $to, string $column = 'booking_date'): Builder
    {
        if ($from) $q->where($column, '>=', $from.' 00:00:00');
        if ($to)   $q->where($column, '<=', $to.' 23:59:59');
        return $q;
    }
}
