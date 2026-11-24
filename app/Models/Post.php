<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Like;

class Post extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'image',
        'caption',
    ];

    // A post belongs to one user.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function likes()
    {
       return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(\App\Models\Comment::class);
    }

}
