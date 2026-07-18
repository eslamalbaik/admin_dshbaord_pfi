<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'title', 'slug', 'short_description', 'description', 'thumbnail', 
        'category', 'difficulty', 'language', 'meta_title', 'meta_description', 
        'keywords', 'is_free', 'price', 'discount_price', 'include_in_subscription', 
        'status', 'is_published', 'instructor_id'
    ];

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
