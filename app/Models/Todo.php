<?php
// app/Models/Todo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'completed',
        'due_date',
        'category',
        'user_id'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'due_date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}