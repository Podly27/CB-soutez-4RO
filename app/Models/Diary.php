<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diary extends Model
{
    protected $table = 'diary';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'call_sign', 'diary_url', 'qth_name', 'qth_locator', 'qth_locator_lon', 'qth_locator_lat',
        'qso_count', 'created_at', 'updated_at', 'score_points', 'email', 'category_id', 'contest_id', 'user_id', 'options'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'email', 'category_id', 'contest_id' ];

    protected $casts = [
        'options' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function contest()
    {
        return $this->belongsTo(Contest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfCategory($query, $id)
    {
        return $query->whereCategory_id($id);
    }

    public function scopeOfContest($query, $id)
    {
        return $query->whereContest_id($id);
    }

    public function scopeSortByDesc($query, $SortByDesc)
    {
        return $query->orderBy($SortByDesc, 'desc');
    }
}
