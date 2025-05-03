<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'public_workshop_id',
        'public_key',
        'private_key',
        'online_link',
        'event_name',
        'event_start_dT',
        'event_end_dT',
        'cpd_points_earned',
        'event_type',
        'event_privacy',
        'misc_data',
        'archived'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'uuid',
        'public_workshop_id' => 'uuid',
        'event_start_dT' => 'datetime',
        'event_end_dT' => 'datetime',
        'cpd_points_earned' => 'decimal:2',
        'misc_data' => 'json',
        'archived' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'private_key',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($event) {
            // Auto-generate UUID if not set
            if (empty($event->id)) {
                $event->id = Str::uuid();
            }
            
            if (empty($event->public_workshop_id)) {
                $event->public_workshop_id = Str::uuid();
            }
            
            if (empty($event->public_key)) {
                // Generate a unique 9-character public key
                $event->public_key = strtoupper(Str::random(9));
            }
            
            if (empty($event->private_key)) {
                // Generate a secure private key
                $event->private_key =  Str::random(72);
            }
        });
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'public_workshop_id';
    }

    /**
     * Get event duration in minutes.
     *
     * @return int|null
     */
    public function getDurationInMinutesAttribute()
    {
        if ($this->event_start_dT && $this->event_end_dT) {
            return $this->event_start_dT->diffInMinutes($this->event_end_dT);
        }
        
        return null;
    }

    /**
     * Check if the event is ongoing.
     *
     * @return bool
     */
    public function getIsOngoingAttribute()
    {
        if ($this->event_start_dT && $this->event_end_dT) {
            $now = now();
            return $this->event_start_dT <= $now && $this->event_end_dT >= $now;
        }
        
        return false;
    }
    
    /**
     * Scope a query to only include active events.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }
    
    /**
     * Scope a query to only include events of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeType($query, $type)
    {
        return $query->where('event_type', $type);
    }
    
    /**
     * Scope a query to only include upcoming events.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        return $query->where('event_start_dT', '>', now())
                    ->where('archived', false)
                    ->orderBy('event_start_dT');
    }
    
    /**
     * Scope a query to only include public events.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublic($query)
    {
        return $query->where('event_privacy', 'public');
    }
}
